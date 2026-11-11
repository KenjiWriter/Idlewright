<?php

declare(strict_types=1);

use App\Agent\AgentServer;
use App\Agent\Tools\CheckTargetsTool;
use App\Agent\Tools\ScratchSetTool;
use App\Agent\Tools\SimulateProgressTool;
use App\Models\AgentJob;
use Illuminate\Support\Facades\Queue;

// The progress simulator and the targets in numbers as tools of the AI assistant (V2, D7 and D13 of the design): on the live pack and on the scratch, so the assistant
// sees what a change does to the pace of a new driver before anybody saves anything.

require_once __DIR__.'/../../Support/AgentHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** The minute a level is reached in an answer of simulate_progress. */
function minuteOfLevel(array $answer, int $level): ?float
{
    return collect($answer['result']['report']['levels'])->firstWhere('level', $level)['minute'] ?? null;
}

it('plays a new driver on the live pack and on the scratch, and a dearer level comes later', function () {
    agentGrantInForce();
    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Poziom 5 drozszy', 'ops' => [['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/player/early_xp/3', 'value' => 1500, 'unit' => 'plain']]])
        ->assertStructuredContentPath('kept', true);

    $answers = [];

    foreach (['live', 'scratch'] as $variant) {
        AgentServer::tool(SimulateProgressTool::class, ['variant' => $variant, 'minutes' => 240, 'tutorial' => false, 'travel' => false])
            ->assertOk()
            ->assertStructuredContentPath('status', 'done')
            ->assertStructuredContentPath('result', function (array $result) use (&$answers, $variant): bool {
                $answers[$variant] = ['result' => $result];

                return true;
            });
    }

    expect(minuteOfLevel($answers['scratch'], 5))->toBeGreaterThan(minuteOfLevel($answers['live'], 5))
        ->and($answers['live']['result']['report']['assumptions'])->toHaveKey('ideal_player');
});

it('checks targets of the levels inside simulate_progress, and sends the others to check_targets', function () {
    agentGrantInForce();

    AgentServer::tool(SimulateProgressTool::class, ['variant' => 'live', 'minutes' => 120, 'targets' => [['type' => 'level_at_minute', 'level' => 8, 'minute' => 10, 'tolerance_percent' => 50]]])
        ->assertOk()
        ->assertStructuredContentPath('result.report.targets.0.ok', true);

    AgentServer::tool(SimulateProgressTool::class, ['variant' => 'live', 'targets' => [['type' => 'gates']]])->assertHasErrors(['check gates with check_targets']);
    AgentServer::tool(SimulateProgressTool::class, ['variant' => 'live', 'style' => 'sometimes'])->assertHasErrors(['The way of playing is one of']);
});

it('checks targets of the levels, the cash and the days of the cars at once, each against what was measured', function () {
    agentGrantInForce();
    config(['ride.agent.wait_seconds' => 120]);

    AgentServer::tool(CheckTargetsTool::class, ['variant' => 'live', 'targets' => [
        ['type' => 'level_at_minute', 'level' => 8, 'minute' => 10, 'tolerance_percent' => 50, 'plan' => ['minutes' => 60]],
        ['type' => 'can_afford', 'level' => 8, 'items' => ['street-tires-t2', 'no-such-part'], 'plan' => ['minutes' => 60]],
        ['type' => 'days_to_car', 'player' => 'A', 'car' => 'L1', 'day' => 2],
        ['type' => 'level_at_minute', 'level' => 15, 'minute' => 30],
    ]])
        ->assertOk()
        ->assertStructuredContentPath('status', 'done')
        ->assertStructuredContentPath('result.met', false)
        ->assertStructuredContentPath('result.targets', function (array $rows): bool {
            expect(array_column($rows, 'type'))->toBe(['level_at_minute', 'can_afford', 'days_to_car', 'level_at_minute'])
                ->and($rows[0]['ok'])->toBeTrue()
                ->and($rows[1]['measured'])->toContain('unknown: no-such-part')
                ->and($rows[2]['measured'])->toStartWith('day ')
                ->and($rows[3]['ok'])->toBeFalse();

            return true;
        });
});

it('counts a check with the gates on the worker of the queue agent, as a long study', function () {
    agentGrantInForce();
    Queue::fake();
    config(['ride.agent.wait_seconds' => 0]);

    AgentServer::tool(CheckTargetsTool::class, ['variant' => 'drafts', 'targets' => [['type' => 'gates']]])->assertOk()->assertStructuredContentPath('status', 'queued');

    expect(AgentJob::query()->sole()->study)->toBe('check_targets');
    AgentServer::tool(CheckTargetsTool::class, ['variant' => 'drafts', 'targets' => [['type' => 'nonsense']]])->assertHasErrors(['the type is one of']);
});
