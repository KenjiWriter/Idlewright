<?php

declare(strict_types=1);

use App\Agent\AgentContext;
use App\Agent\AgentServer;
use App\Agent\Tools\JobResultTool;
use App\Agent\Tools\RunGatesTool;
use App\Agent\Tools\ScratchSetTool;
use App\Agent\Tools\SimulateCarsTool;
use App\Agent\Tools\SimulateEconomyTool;
use App\Agent\Tools\TracksSimulateTool;
use App\Agent\Tools\TuningBudgetTool;
use App\Jobs\RunAgentStudy;
use App\Models\Admin;
use App\Models\AgentAction;
use App\Models\AgentJob;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;
use Illuminate\Support\Facades\Queue;

// The simulators of the game as tools of the AI assistant (V1, D12, D14 and D18 of the design): a study is counted on the live pack, the drafts or the drafts with the
// scratch; the same study on the same content is answered from what was counted; a long one goes to the worker of the queue `agent`, one at a time in a game and a few an
// hour, and is picked up by its number.

require_once __DIR__.'/../../Support/AgentHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** Puts a dearer fuel in the scratch of the consent in force. */
function scratchWithDearerFuel(int $percent = 50): void
{
    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Drozsze paliwo', 'ops' => [['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/fuel/price_cents_per_liter', 'percent' => $percent]]])
        ->assertStructuredContentPath('kept', true);
}

it('counts the economy on the live pack and on the scratch, and the scratch shows what it changes', function () {
    agentGrantInForce();
    scratchWithDearerFuel();

    $live = AgentServer::tool(SimulateEconomyTool::class, ['variant' => 'live', 'players' => ['A']]);
    $live->assertOk()->assertStructuredContentPath('status', 'done')->assertStructuredContentPath('cached', false)->assertSee('"gate"');

    $scratch = AgentServer::tool(SimulateEconomyTool::class, ['variant' => 'scratch', 'players' => ['A']]);
    $scratch->assertOk()->assertStructuredContentPath('status', 'done');

    $jobs = AgentJob::query()->orderBy('id')->get();

    expect($jobs)->toHaveCount(2)
        ->and($jobs[0]->key)->not->toBe($jobs[1]->key)
        ->and(json_encode($jobs[0]->result['report']['consistency']))->not->toBe(json_encode($jobs[1]->result['report']['consistency']))
        ->and($jobs[1]->result['report'])->not->toHaveKey('extras')
        ->and(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
});

it('answers the same study on the same content from what was counted, and counts again when the content changes', function () {
    agentGrantInForce();

    AgentServer::tool(TuningBudgetTool::class, ['variant' => 'drafts'])->assertStructuredContentPath('cached', false);
    AgentServer::tool(TuningBudgetTool::class, ['variant' => 'drafts'])->assertStructuredContentPath('cached', true);

    expect(AgentJob::query()->count())->toBe(1);

    $definition = ContentDefinition::query()->where('kind', 'part')->where('slug', 'street-tires-t2')->firstOrFail();
    app(ContentDrafts::class)->save($definition, [...$definition->workingDocument(), 'price_cents' => 58_000], Admin::query()->firstOrFail());

    AgentServer::tool(TuningBudgetTool::class, ['variant' => 'drafts'])->assertStructuredContentPath('cached', false);

    expect(AgentJob::query()->count())->toBe(2)
        ->and(AgentAction::query()->where('tool', 'tuning_budget')->pluck('summary')->all())->toContain('tuning_budget on drafts: done (counted before), job 1');
});

it('refuses the scratch when there is none, and a variant it does not know', function () {
    agentGrantInForce();

    AgentServer::tool(TuningBudgetTool::class, ['variant' => 'scratch'])->assertHasErrors(['The scratch is empty']);
    AgentServer::tool(TuningBudgetTool::class, ['variant' => 'tomorrow'])->assertHasErrors();
});

it('plays test cars on the live pack and on the drafts with the scratch, side by side', function () {
    agentGrantInForce();
    scratchWithDearerFuel(100);

    AgentServer::tool(SimulateCarsTool::class, ['cars' => [['car' => 'rdzak-gt-4']], 'tracks' => ['zlomowo-street'], 'with_scratch' => true])
        ->assertOk()
        ->assertSee('drafts with the scratch')
        ->assertStructuredContentPath('diff', function (array $diff): bool {
            $track = $diff[0]['zlomowo-street'];

            expect($track['profit_cents_per_hour']['delta'])->toBeLessThan(0)
                ->and($track['xp_per_hour']['delta'])->toBe(0);

            return true;
        });

    AgentServer::tool(SimulateCarsTool::class, ['cars' => [['car' => 'no-such-car']]])->assertOk()->assertSee('unknown_car');
});

it('queues a long study, lets one long study run at a time in a game, and picks it up by its number when the worker is done', function () {
    $grant = agentGrantInForce();
    Queue::fake();
    config(['ride.agent.wait_seconds' => 0]);

    AgentServer::tool(TracksSimulateTool::class, ['variant' => 'live'])->assertOk()->assertStructuredContentPath('status', 'queued')->assertSee('job_result');
    $job = AgentJob::query()->sole();
    Queue::assertPushedOn('agent', RunAgentStudy::class);

    AgentServer::tool(RunGatesTool::class, ['variant' => 'drafts'])->assertHasErrors(["Another long study of this game is being counted now (job {$job->id}"]);
    AgentServer::tool(TracksSimulateTool::class, ['variant' => 'live'])->assertStructuredContentPath('job', $job->id);
    AgentServer::tool(JobResultTool::class, ['job' => $job->id])->assertStructuredContentPath('status', 'queued');

    // The worker takes it: here the same job is run as the worker runs it, in this process, so the consent of the calls is put in force again after it.
    app()->call([new RunAgentStudy($job->id), 'handle']);
    app(AgentContext::class)->set($grant);

    AgentServer::tool(JobResultTool::class, ['job' => $job->id])
        ->assertOk()
        ->assertStructuredContentPath('status', 'done')
        ->assertStructuredContentPath('result.exit', 0);

    AgentServer::tool(JobResultTool::class, ['job' => 999])->assertHasErrors(['There is no job 999']);
});

it('stops a consent after the long studies of an hour, and forgets a long study whose worker stopped', function () {
    $grant = agentGrantInForce();
    Queue::fake();
    config(['ride.agent.wait_seconds' => 0, 'ride.agent.heavy_per_hour' => 1]);

    AgentJob::query()->create([
        'agent_grant_id' => $grant->id, 'game_id' => $grant->game_id, 'study' => 'run_gates', 'variant' => 'live', 'options' => [], 'key' => str_repeat('a', 64),
        'status' => AgentJob::RUNNING, 'created_at' => now()->subMinutes(30),
    ]);

    // Started half an hour ago and never ended: it no longer stops another one, but it counts in the hour.
    AgentServer::tool(TracksSimulateTool::class, ['variant' => 'live'])->assertHasErrors(['long studies counted in the last hour, which is the limit']);
});

it('runs the five gates on the drafts', function () {
    agentGrantInForce();

    AgentServer::tool(RunGatesTool::class, ['variant' => 'drafts'])
        ->assertOk()
        ->assertStructuredContentPath('status', 'done')
        ->assertStructuredContentPath('result.passed', true)
        ->assertStructuredContentPath('result.gates', fn (array $gates): bool => count($gates) === 5);
})->group('slow');

it('counts a study from the command line at once, and keeps nothing', function () {
    $this->artisan('agent:tool', ['name' => 'tuning_budget', '--input' => '{"variant":"live"}'])->expectsOutputToContain('"status": "done"')->assertSuccessful();

    expect(AgentJob::query()->count())->toBe(0);
});
