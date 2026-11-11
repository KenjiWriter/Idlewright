<?php

declare(strict_types=1);

use App\Agent\AgentContext;
use App\Agent\AgentLevel;
use App\Agent\AgentPlans;
use App\Agent\AgentScratch;
use App\Agent\AgentServer;
use App\Agent\PlanRefused;
use App\Agent\ToolRefused;
use App\Agent\Tools\PlanStatusTool;
use App\Agent\Tools\PlanSubmitTool;
use App\Agent\Tools\ScratchSetTool;
use App\Content\Bridge\ContentSnapshot;
use App\Jobs\MeasureAgentPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AgentPlan;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;
use Illuminate\Support\Facades\Queue;
use Laravel\Mcp\Request;

// The plans of the AI assistant (V3, D8 of the design): the scratch submitted with its goal and targets, bound to its block and the drafts by a hash, measured by the
// server, and decided only by a person: approved for 24 hours, rejected with a reason, or noted; a correction is a new version, and a change of the drafts makes it outdated.

require_once __DIR__.'/../../Support/AgentHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** A plan made ready to decide on without the minutes of measuring it. */
function measuredPlan(AgentPlan $plan): AgentPlan
{
    $plan->forceFill(['measure_status' => AgentPlan::READY, 'measured' => ['targets' => [], 'met' => true, 'gates' => [], 'gates_passed' => true, 'pace' => []], 'measured_at' => now()])->save();

    return $plan;
}

it('gives the plan tools only to a consent of level 2, and says no to a lower one', function () {
    agentGrantInForce(1);

    expect(AgentServer::toolNamesFor(AgentLevel::Read))->not->toContain('plan_submit', 'plan_status')
        ->and(AgentServer::toolNamesFor(AgentLevel::Plan))->toContain('plan_submit', 'plan_status');

    // The server of MCP does not even know the tool at this level, and the tool says no again by itself, for any other way it might be reached.
    AgentServer::tool(PlanStatusTool::class)->assertHasErrors();
    $answer = app()->call([app(PlanStatusTool::class), 'handle'], ['request' => new Request([])]);

    expect($answer->isError())->toBeTrue()->and((string) $answer->content())->toContain('This tool needs a consent of level 2');
});

it('submits the scratch as a plan bound to its block and the drafts, and marks the changes outside its levers', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $fuel = ['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/fuel/price_cents_per_liter', 'percent' => 10];
    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Plan', 'ops' => [dearerLevelFive(), $fuel]])->assertStructuredContentPath('kept', true);

    AgentServer::tool(PlanSubmitTool::class, [
        'title' => 'Poziom 5 wolniej', 'goal' => 'Poziom 5 ma przyjsc pozniej.', 'strategy' => 'Wyzszy prog.', 'levers' => ['rules:starter#player'],
        'targets' => [['type' => 'level_at_minute', 'level' => 5, 'minute' => 75], ['type' => 'gates']],
        'alternatives' => [['option' => 'Mniej XP z torow', 'why' => 'Rusza cala gre']],
    ])
        ->assertOk()
        ->assertStructuredContentPath('version', 1)
        ->assertStructuredContentPath('state', 'submitted')
        ->assertStructuredContentPath('measure_status', 'measuring')
        ->assertStructuredContentPath('changes_count', 2)
        ->assertStructuredContentPath('outside_levers', ['rules:starter /fuel/price_cents_per_liter'])
        ->assertSee('/admin/agent-plans?plan=');

    $plan = AgentPlan::query()->sole();

    expect($plan->admin_id)->toBe($grant->admin_id)
        ->and($plan->drafts_snapshot)->toBe(app(ContentSnapshot::class)->current())
        ->and($plan->content_hash)->toHaveLength(64)
        ->and($plan->block['ops'])->toHaveCount(2)
        ->and($plan->replacements)->toHaveKey('rules/starter')
        ->and(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0)
        ->and(AdminAuditLog::query()->where('action', 'agent_plan_submitted')->count())->toBe(1);

    Queue::assertPushedOn('agent', MeasureAgentPlan::class);
});

it('refuses a plan with no scratch, with targets or levers it cannot read, or with characters a person cannot see', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $plan = ['title' => 'Plan', 'goal' => 'Cel', 'strategy' => 'Jak', 'levers' => ['rules:starter#player'], 'targets' => [['type' => 'gates']]];

    AgentServer::tool(PlanSubmitTool::class, $plan)->assertHasErrors(['The scratch is empty']);

    app(AgentScratch::class)->set($grant, 'Plan', [dearerLevelFive()]);

    AgentServer::tool(PlanSubmitTool::class, [...$plan, 'levers' => ['the xp']])->assertHasErrors(['The lever the xp is not one']);
    AgentServer::tool(PlanSubmitTool::class, [...$plan, 'targets' => [['type' => 'level_at_minute']]])->assertHasErrors(['lacks what it needs']);
    AgentServer::tool(PlanSubmitTool::class, [...$plan, 'goal' => "Cel\u{200B}"])->assertHasErrors(['characters that a person cannot see']);
    AgentServer::tool(PlanSubmitTool::class, [...$plan, 'supersedes' => 999])->assertHasErrors(['There is no plan 999']);

    expect(AgentPlan::query()->count())->toBe(0);
});

it('measures the plan itself: the targets on the live pack and on the plan, the gates and the pace of the players', function () {
    $grant = agentGrantInForce(2);
    $plan = agentPlanSubmitted($grant, [dearerLevelFive()], more: ['targets' => [
        ['type' => 'level_at_minute', 'level' => 5, 'minute' => 75, 'tolerance_percent' => 20, 'plan' => ['minutes' => 240, 'tutorial' => false, 'travel' => false]],
        ['type' => 'gates'],
    ]]);

    expect($plan->measure_status)->toBe(AgentPlan::READY)
        ->and($plan->measured['targets'])->toHaveCount(2)
        ->and($plan->measured['targets'][0]['live'])->toStartWith('minute ')
        ->and($plan->measured['targets'][0]['plan'])->toStartWith('minute ')
        ->and($plan->measured['targets'][0]['live'])->not->toBe($plan->measured['targets'][0]['plan'])
        ->and($plan->measured['targets'][1]['type'])->toBe('gates')
        ->and($plan->measured['gates'])->toHaveCount(5)
        ->and($plan->measured['pace'])->not->toBe([])
        ->and($plan->measured['pace'][0])->toHaveKeys(['player', 'car', 'target_day', 'live_day', 'plan_day']);

    AgentServer::tool(PlanStatusTool::class)->assertOk()->assertStructuredContentPath('measure_status', 'ready')->assertStructuredContentPath('plan', $plan->id);
})->group('slow');

it('makes a corrected plan a new version, and the old one says what replaced it', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $first = agentPlanSubmitted($grant, [dearerLevelFive()]);
    $second = agentPlanSubmitted($grant, [['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/player/early_xp/3', 'value' => 1200, 'unit' => 'plain']], more: ['supersedes' => $first->id]);

    expect($second->version)->toBe(2)
        ->and($second->previous_id)->toBe($first->id)
        ->and($first->refresh()->status)->toBe(AgentPlan::SUPERSEDED);

    AgentServer::tool(PlanStatusTool::class, ['plan' => $first->id])->assertStructuredContentPath('state', 'superseded')->assertStructuredContentPath('replaced_by', $second->id);
    AgentServer::tool(PlanStatusTool::class)->assertStructuredContentPath('plan', $second->id);

    expect(fn () => agentPlanSubmitted($grant, [dearerLevelFive()], more: ['supersedes' => $first->id]))->toThrow(ToolRefused::class, 'already replaced');
});

it('lets a person approve a measured plan that is still up to date, for 24 hours, and only once', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $plan = agentPlanSubmitted($grant, [dearerLevelFive()]);
    $plans = app(AgentPlans::class);
    $editor = anAdmin(['email' => 'editor@example.com', 'role' => Admin::ROLE_EDITOR]);

    expect(fn () => $plans->approve($plan, $editor))->toThrow(PlanRefused::class, 'measuring');

    $plans->approve(measuredPlan($plan), $editor);

    expect($plan->refresh()->status)->toBe(AgentPlan::APPROVED)
        ->and($plan->decided_by)->toBe($editor->id)
        ->and($plan->approved_until->diffInHours(now(), true))->toBeGreaterThan(23.9)
        ->and(AdminAuditLog::query()->where('action', 'agent_plan_approved')->value('context'))->toMatchArray(['plan' => $plan->id, 'hash' => $plan->content_hash])
        ->and(fn () => $plans->approve($plan, $editor))->toThrow(PlanRefused::class, 'decided');

    AgentServer::tool(PlanStatusTool::class)->assertStructuredContentPath('state', 'approved')->assertStructuredContentPath('decided_by', $editor->email);

    $this->travel(25)->hours();
    app(AgentContext::class)->set($grant->refresh());

    AgentServer::tool(PlanStatusTool::class)->assertStructuredContentPath('state', 'approval_expired');
});

it('calls a plan outdated once the drafts change under it, and does not let it be approved', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $plan = measuredPlan(agentPlanSubmitted($grant, [dearerLevelFive()]));

    $part = ContentDefinition::query()->where('kind', 'part')->where('slug', 'street-tires-t2')->firstOrFail();
    app(ContentDrafts::class)->save($part, [...$part->workingDocument(), 'price_cents' => 58_000], $grant->admin);

    AgentServer::tool(PlanStatusTool::class)->assertStructuredContentPath('outdated', true);

    expect(fn () => app(AgentPlans::class)->approve($plan, $grant->admin))->toThrow(PlanRefused::class, 'outdated');
});

it('rejects with a reason, passes a note to the assistant, and lets nobody decide outside their games', function () {
    Queue::fake();
    $grant = agentGrantInForce(2);
    $plan = measuredPlan(agentPlanSubmitted($grant, [dearerLevelFive()]));
    $plans = app(AgentPlans::class);
    $stranger = anAdmin(['email' => 'stranger@example.com', 'all_games' => false]);

    expect(fn () => $plans->approve($plan, $stranger))->toThrow(PlanRefused::class, 'role');

    $plans->note($plan, $grant->admin, 'Policz jeszcze gracza B.');
    AgentServer::tool(PlanStatusTool::class)->assertStructuredContentPath('note', 'Policz jeszcze gracza B.')->assertStructuredContentPath('state', 'submitted');

    $plans->reject($plan, $grant->admin, 'Za duzy skok.');
    AgentServer::tool(PlanStatusTool::class)->assertStructuredContentPath('state', 'rejected')->assertStructuredContentPath('reject_reason', 'Za duzy skok.');
});
