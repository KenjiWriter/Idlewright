<?php

declare(strict_types=1);

use App\Agent\AgentContext;
use App\Agent\AgentLevel;
use App\Agent\AgentPlanExecution;
use App\Agent\AgentPlans;
use App\Agent\AgentServer;
use App\Agent\PlanRefused;
use App\Agent\Tools\PackOrderTool;
use App\Agent\Tools\PlanExecuteTool;
use App\Agent\Tools\PlanStatusTool;
use App\Agent\Tools\PlanUndoTool;
use App\Jobs\BuildContentPack;
use App\Jobs\ReportAgentPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AgentGrant;
use App\Models\AgentPlan;
use App\Models\AiExchange;
use App\Models\ContentDefinition;
use App\Models\ContentPublication;
use App\Services\ContentDrafts;
use Illuminate\Support\Facades\Queue;

// What comes of an approved plan of the AI assistant (V4, D9 and D10 of the design): it is written as drafts through the bridge, exactly as approved and signed by the
// plan; the server counts the drafts again beside what it promised; the writing can be undone while nobody touched the drafts; and at the third level, for a publisher and
// only when every draft is the plan's, a pack can be asked for. Making it live stays with a person.

require_once __DIR__.'/../../Support/AgentHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** A plan of the consent in force, measured (without the minutes of it) and approved by a person. */
function approvedPlan(AgentGrant $grant, array $ops = []): AgentPlan
{
    $plan = agentPlanSubmitted($grant, $ops === [] ? [dearerLevelFive()] : $ops);
    $plan->forceFill(['measure_status' => AgentPlan::READY, 'measured_at' => now(), 'measured' => ['targets' => [], 'met' => true, 'gates' => [], 'gates_passed' => true, 'pace' => []]])->save();
    app(AgentPlans::class)->approve($plan, anAdmin(['email' => 'approver-'.$plan->id.'@example.com', 'role' => Admin::ROLE_EDITOR]));
    app(AgentContext::class)->set($grant->refresh());

    return $plan->refresh();
}

/** The experience of level 5 in the drafts of the rules, or null when the rules have no draft. */
function draftedLevelFive(): ?int
{
    $rules = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();

    return $rules->draft === null ? null : (int) json_decode((string) $rules->draft, true)['player']['early_xp'][3];
}

it('writes an approved plan as drafts through the bridge, signed by the plan, and only once', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $plan = approvedPlan($grant);

    AgentServer::tool(PlanExecuteTool::class, ['plan' => $plan->id])
        ->assertOk()
        ->assertStructuredContentPath('state', 'executed')
        ->assertStructuredContentPath('report_status', 'measuring');

    $plan->refresh();
    $answer = AiExchange::query()->findOrFail($plan->ai_exchange_id);

    expect(draftedLevelFive())->toBe(1500)
        ->and($answer->kind)->toBe(AiExchange::KIND_PASTE)
        ->and($answer->status)->toBe('applied')
        ->and($answer->parent->selection)->toBe('agent-plan:'.$plan->id)
        ->and($answer->parent->scope)->toContain('rules:starter#player')
        ->and($plan->executed_by)->toBe($grant->admin_id)
        ->and(AdminAuditLog::query()->whereIn('action', ['ai_changes_applied', 'agent_plan_executed'])->count())->toBe(2);

    Queue::assertPushedOn('agent', ReportAgentPlan::class);
    AgentServer::tool(PlanExecuteTool::class, ['plan' => $plan->id])->assertHasErrors(['written as drafts already']);

    $this->get('/admin/pending-changes')->assertOk()->assertSee('data-test="pending-agent-plan"', false)->assertSee('plan nr '.$plan->id);
});

it('writes nothing for a plan that is not approved, whose approval expired, or whose drafts changed', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $submitted = agentPlanSubmitted($grant, [dearerLevelFive()]);

    AgentServer::tool(PlanExecuteTool::class, ['plan' => $submitted->id])->assertHasErrors(['The plan is not approved']);

    $plan = approvedPlan($grant, [['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/player/early_xp/3', 'value' => 1200, 'unit' => 'plain']]);
    $this->travel(25)->hours();
    AgentServer::tool(PlanExecuteTool::class, ['plan' => $plan->id])->assertHasErrors(['The approval of the plan expired']);
    $this->travelBack();

    $part = ContentDefinition::query()->where('kind', 'part')->where('slug', 'street-tires-t2')->firstOrFail();
    app(ContentDrafts::class)->save($part, [...$part->workingDocument(), 'price_cents' => 58_000], $grant->admin);
    AgentServer::tool(PlanExecuteTool::class, ['plan' => $plan->id])->assertHasErrors(['The drafts changed since the plan was submitted']);

    expect(draftedLevelFive())->toBeNull();
});

it('counts the drafts again after the writing, and what it measures is what the plan promised', function () {
    $grant = agentGrantInForce(2);
    $plan = agentPlanSubmitted($grant, [dearerLevelFive()], more: ['targets' => [
        ['type' => 'level_at_minute', 'level' => 5, 'minute' => 75, 'tolerance_percent' => 30, 'plan' => ['minutes' => 240, 'tutorial' => false, 'travel' => false]],
        ['type' => 'gates'],
    ]]);
    app(AgentPlans::class)->approve($plan->refresh(), anAdmin(['email' => 'approver@example.com']));
    app(AgentContext::class)->set($grant->refresh());

    AgentServer::tool(PlanExecuteTool::class, ['plan' => $plan->id])->assertOk();

    $plan->refresh();

    expect($plan->report_status)->toBe(AgentPlan::READY)
        ->and($plan->report['same'])->toBeTrue()
        ->and($plan->report['drafts_changed'])->toBeFalse()
        ->and($plan->report['targets'][0]['promised'])->toBe($plan->report['targets'][0]['measured'])
        ->and($plan->report['gates'])->toHaveCount(5)
        ->and($plan->report['pace'])->not->toBe([]);

    AgentServer::tool(PlanStatusTool::class, ['plan' => $plan->id])->assertStructuredContentPath('report.same', true);
})->group('slow');

it('undoes the writing while nobody worked on the drafts, and not after', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $first = approvedPlan($grant);

    AgentServer::tool(PlanExecuteTool::class, ['plan' => $first->id])->assertOk();
    AgentServer::tool(PlanUndoTool::class, ['plan' => $first->id])->assertOk()->assertStructuredContentPath('state', 'reverted');

    expect(draftedLevelFive())->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'agent_plan_reverted')->count())->toBe(1);

    AgentServer::tool(PlanExecuteTool::class, ['plan' => $first->id])->assertHasErrors(['written as drafts already']);

    $second = approvedPlan($grant, [['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/player/early_xp/3', 'value' => 1200, 'unit' => 'plain']]);
    AgentServer::tool(PlanExecuteTool::class, ['plan' => $second->id])->assertOk();

    $rules = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();
    $document = $rules->workingDocument();
    $document['player']['early_xp'][3] = 1300;
    app(ContentDrafts::class)->save($rules, $document, $grant->admin);

    AgentServer::tool(PlanUndoTool::class, ['plan' => $second->id])->assertHasErrors(['Someone worked on these drafts', 'rules:starter']);
    expect(draftedLevelFive())->toBe(1300);
});

it('asks for a pack only at the third level, for a publisher, and only when every draft is the plan\'s', function () {
    Queue::fake();

    expect(AgentServer::toolNamesFor(AgentLevel::Plan))->not->toContain('pack_order')
        ->and(AgentServer::toolNamesFor(AgentLevel::Pack))->toContain('pack_order');

    $grant = agentGrantInForce(3, Admin::ROLE_PUBLISHER);
    $plan = approvedPlan($grant);
    AgentServer::tool(PlanExecuteTool::class, ['plan' => $plan->id])->assertOk();

    $part = ContentDefinition::query()->where('kind', 'part')->where('slug', 'street-tires-t2')->firstOrFail();
    app(ContentDrafts::class)->save($part, [...$part->workingDocument(), 'price_cents' => 58_000], $grant->admin);

    AgentServer::tool(PackOrderTool::class, ['plan' => $plan->id])->assertHasErrors(['drafts that do not come from this plan', 'part:street-tires-t2']);

    app(ContentDrafts::class)->discard($part->refresh(), $grant->admin);
    $editor = anAdmin(['email' => 'editor@example.com', 'role' => Admin::ROLE_EDITOR]);

    expect(fn () => app(AgentPlanExecution::class)->orderPack($plan->refresh(), $editor))->toThrow(PlanRefused::class, 'not_publisher');

    AgentServer::tool(PackOrderTool::class, ['plan' => $plan->id])
        ->assertOk()
        ->assertStructuredContentPath('pack.status', 'queued')
        ->assertSee('Uruchom');

    $publication = ContentPublication::query()->sole();

    expect($publication->note)->toContain((string) $plan->id)
        ->and($publication->requested_by)->toBe($grant->admin_id)
        ->and(AdminAuditLog::query()->where('action', 'agent_plan_pack_ordered')->count())->toBe(1);

    Queue::assertPushed(BuildContentPack::class);
    AgentServer::tool(PackOrderTool::class, ['plan' => $plan->id])->assertHasErrors(['A pack was already asked for']);
    AgentServer::tool(PlanUndoTool::class, ['plan' => $plan->id])->assertHasErrors(['A pack of these drafts is being made']);
});
