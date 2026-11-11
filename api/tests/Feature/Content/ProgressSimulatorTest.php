<?php

declare(strict_types=1);

use App\Content\Progress\ProgressPlan;
use App\Content\Progress\ProgressSimulator;
use App\Content\Progress\ProgressTargets;
use App\Content\Publishing\TestCarPreview;
use App\Content\Rules;
use App\Content\TutorialSteps;

// The progress simulator (most AI v2, V2, D13 of the design): one ideal driver from a new account, with the game's own numbers of a lap, the tutorial, the ways of playing
// and of buying, and the targets of the levels and of the cash (D7).

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** @return array<string, mixed> */
function progressOf(array $plan, ?array $player = null): array
{
    return app(ProgressSimulator::class)->simulate(ProgressPlan::fromArray($plan), $player);
}

it('drives laps that pay and teach exactly what the game freezes for the car on the track', function () {
    $result = progressOf(['minutes' => 60, 'tutorial' => false, 'travel' => false, 'track' => 'zlomowo-street']);
    $preview = app(TestCarPreview::class)->preview([['car' => 'rdzak-gt-4']], ['zlomowo-street'])['live']['cars'][0]['tracks']['zlomowo-street'];

    expect($result['summary']['car'])->toBe('rdzak-gt-4')
        ->and($result['summary']['laps'])->toBeGreaterThan(10)
        ->and($result['summary']['xp_laps'])->toBe($result['summary']['laps'] * $preview['xp_per_lap'])
        ->and($result['summary']['xp_tutorial'])->toBe(0)
        ->and($result['summary']['driving_minutes'])->toBe(round($result['summary']['laps'] * $preview['lap_ms']['dry'] / 60_000, 1));
});

it('counts the rewards of the tutorial and reaches each level at the minute the experience allows', function () {
    $result = progressOf(['minutes' => 120]);
    $tutorialXp = array_sum(array_map(static fn (array $step): int => (int) ($step['reward']['xp'] ?? 0), array_filter(app(TutorialSteps::class)->all(), static fn (array $step): bool => $step['phase'] <= 2)));
    $rules = app(Rules::class);

    expect($result['summary']['xp_tutorial'])->toBe($tutorialXp)
        ->and($result['levels'][0])->toMatchArray(['level' => 1, 'minute' => 0.0]);

    $minutes = array_column($result['levels'], 'minute');
    expect($minutes)->toBe(collect($minutes)->sort()->values()->all());

    foreach ($result['levels'] as $row) {
        expect($row['xp'])->toBeGreaterThanOrEqual($rules->playerXpToReach($row['level']));
    }

    expect(array_column($result['levels'], 'level'))->toContain(8)
        ->and(collect($result['moves'])->contains('kind', 'journey'))->toBeTrue();
});

it('leaves the car standing while a driver who comes back now and then is away and the tank is empty', function () {
    $active = progressOf(['minutes' => 600, 'tutorial' => false]);
    $every = progressOf(['minutes' => 600, 'tutorial' => false, 'style' => 'every', 'every_minutes' => 180, 'visit_minutes' => 5]);
    $playerB = progressOf(['minutes' => 2880, 'tutorial' => false, 'style' => 'B'], ['visits_per_day' => 1]);

    expect($every['summary']['driving_minutes'])->toBeLessThan($active['summary']['driving_minutes'])
        ->and($every['summary']['xp'])->toBeLessThan($active['summary']['xp'])
        ->and($playerB['summary']['driving_minutes'])->toBeLessThan(200.0)
        ->and($playerB['assumptions'])->toHaveKey('visits');
});

it('buys from a list in order when the cash and the level allow, keeping the cash of a full tank, and skips what the game does not sell', function () {
    $result = progressOf(['minutes' => 600, 'buy' => 'list', 'list' => ['no-such-part', 'disc-brakes-t2', 'sport-exhaust-t2']]);

    expect(array_column($result['purchases'], 'slug'))->toBe(['disc-brakes-t2', 'sport-exhaust-t2'])
        ->and(array_column($result['notes'], 'code'))->toContain('list_unknown');

    foreach ($result['purchases'] as $purchase) {
        expect($purchase['cash_after_usd'])->toBeGreaterThanOrEqual(90.0);
    }
});

it('buys what adds the most to a visit for a dollar, and that earns more than buying nothing', function () {
    $nothing = progressOf(['style' => 'A', 'minutes' => 10 * 1440], ['visits_per_day' => 3]);
    $best = progressOf(['style' => 'A', 'minutes' => 10 * 1440, 'buy' => 'best'], ['visits_per_day' => 3]);

    $earned = static fn (array $result): float => $result['summary']['cash_usd'] + array_sum(array_column($result['purchases'], 'price_usd'));

    expect($best['purchases'])->not->toBe([])
        ->and($earned($best))->toBeGreaterThan($earned($nothing));
});

it('checks the targets of the levels and of the cash', function () {
    $result = progressOf(['minutes' => 120]);
    $levelEight = collect($result['levels'])->firstWhere('level', 8);

    $met = ProgressTargets::fromProgress(['type' => 'level_at_minute', 'level' => 8, 'minute' => $levelEight['minute']], $result);
    $missed = ProgressTargets::fromProgress(['type' => 'level_at_minute', 'level' => 15, 'minute' => 100], $result);
    $afford = ProgressTargets::fromProgress(['type' => 'can_afford', 'level' => 8, 'items' => ['street-tires-t2', 'disc-brakes-t2'], 'count' => 1], $result, ['street-tires-t2' => 57_000, 'disc-brakes-t2' => 76_000]);

    expect($met['ok'])->toBeTrue()
        ->and($missed)->toMatchArray(['ok' => false, 'measured' => 'not reached in 120 minutes'])
        ->and($afford['ok'])->toBe($levelEight['cash_usd'] >= 570.0);

    expect(fn () => ProgressTargets::validate([['type' => 'level_at_minute', 'level' => 5]]))->toThrow(InvalidArgumentException::class, 'lacks what it needs');
});

it('runs from the command line, and refuses a plan it cannot play', function () {
    $this->artisan('economy:progress', ['--minutes' => 60])->expectsOutputToContain('After 60 minutes')->assertSuccessful();
    $this->artisan('economy:progress', ['--minutes' => 60, '--targets' => '[{"type":"level_at_minute","level":8,"minute":15}]'])->expectsOutputToContain('level 8 at minute 15')->assertSuccessful();
    $this->artisan('economy:progress', ['--style' => 'sometimes'])->expectsOutputToContain('The way of playing is one of')->assertFailed();
    $this->artisan('economy:progress', ['--car' => 'no-such-car'])->expectsOutputToContain('There is no car no-such-car')->assertFailed();
});
