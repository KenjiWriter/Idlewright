<?php

declare(strict_types=1);

use App\Content\GameCrates;
use App\Domain\Economy\EconomyRules;
use App\Domain\Economy\RetentionStudy;
use App\Domain\Economy\ShiftGate;
use App\Domain\Economy\Simulator;
use App\Domain\Economy\StreakExtra;
use App\Domain\Economy\TransmuteExtra;

// The streak of days and transmutation in the scenario game of the economy simulator: each is played by the three kinds of players, and the day on which a player buys a car of the ladder
// is set against the day of the same game without it.

function retentionEconomy(): EconomyRules
{
    return EconomyRules::fromArray(json_decode((string) file_get_contents(config('ride.content_path').'/rules/economy.json'), true, 512, JSON_THROW_ON_ERROR));
}

/** A cycle of seven days that pays a tenth of a tank every day and a crate on the last. */
function aCycle(int $cashPpm = 100_000, int $crates = 1): array
{
    return array_map(fn (int $day): array => ['cash_ppm' => $cashPpm, 'xp_ppm' => 0, 'crates' => $day === 7 ? $crates : 0], range(1, 7));
}

function retentionStudy(): RetentionStudy
{
    return new RetentionStudy(new Simulator, new ShiftGate);
}

describe('the streak', function () {
    it('adds to the cash of the first thirty days a share that is bigger for a player who earns less in a day', function () {
        $result = retentionStudy()->streak(retentionEconomy(), app(GameCrates::class)->economy(), aCycle(), []);
        $share = fn (string $key): float => $result['runs'][$key]->notes['streak']['uplift_cash_30d'];

        expect($share('A'))->toBeGreaterThan(0.0)->and($share('B'))->toBeGreaterThan($share('A'))->and($share('B'))->toBeGreaterThan($share('C'))
            ->and($result['runs']['A']->notes['streak'])->toHaveKeys(['completion', 'priced_by', 'uplift_cash_30d', 'uplift_xp_30d', 'added_cash', 'days']);
    });

    it('moves nothing when it pays nothing', function () {
        $result = retentionStudy()->streak(retentionEconomy(), app(GameCrates::class)->economy(), aCycle(0, 0), []);

        expect($result['gate']->passed())->toBeTrue()->and($result['runs']['B']->notes['streak']['added_cash'])->toBe(0.0);
        foreach ($result['runs'] as $key => $run) {
            expect($run->dayOf(2))->toBe($result['baseline'][$key]->dayOf(2));
        }
    });

    it('is held to 5% of the day, and the report says which cars it moved by more', function () {
        $result = retentionStudy()->streak(retentionEconomy(), app(GameCrates::class)->economy(), aCycle(400_000, 3), []);

        expect($result['gate']->tolerance)->toBe(0.05)->and($result['gate']->passed())->toBeFalse()->and($result['gate']->failures())->not->toBeEmpty();
    });

    it('pays the day of the milestone on top of the cycle, once', function () {
        $with = new StreakExtra(aCycle(0, 0), [3 => ['cash_ppm' => 500_000, 'xp_ppm' => 0, 'crates' => 0]], app(GameCrates::class)->economy());
        $economy = retentionEconomy();
        $player = array_values($economy->players())[0];
        $simulated = (new Simulator)->simulateGame($economy, $player, [$with]);

        expect($simulated->notes['streak']['added_cash'])->toBeGreaterThan(0.0);
    });
});

describe('transmutation', function () {
    it('turns the three slots of each tier to epic as soon as the cash is there, and adds what epic parts add to the laps', function () {
        $epic = ['tires' => 0.21619, 'suspension' => 0.05, 'brakes' => 0.05];
        $result = retentionStudy()->transmute(retentionEconomy(), app(GameCrates::class)->economy(), $epic, [0.8, 2.8, 14.0], 6);

        foreach ($result['runs'] as $run) {
            expect($run->notes['transmute']['transmutations'])->toBeGreaterThanOrEqual(3)->and($run->notes['transmute']['uplift'])->toBe(0.3162)
                ->and($run->notes['transmute']['spent'])->toBeGreaterThan(0.0)->and($run->notes['transmute']['first_day'])->toBeInt();
        }

        // The gate is the one of the pace of the game.
        expect($result['gate']->tolerance)->toBe(retentionEconomy()->gateTolerance());
    });

    it('does nothing below the level that opens it', function () {
        $result = retentionStudy()->transmute(retentionEconomy(), app(GameCrates::class)->economy(), ['tires' => 0.2], [0.8, 2.8, 14.0], 1_000);

        foreach ($result['runs'] as $key => $run) {
            expect($run->notes['transmute']['transmutations'])->toBe(0)->and($run->notes['transmute']['first_day'])->toBeNull()->and($run->dayOf(2))->toBe($result['baseline'][$key]->dayOf(2));
        }
    });

    it('can be priced by the extra itself: a part that the anchors do not price is left out', function () {
        $extra = new TransmuteExtra(app(GameCrates::class)->economy(), ['no-such-slot' => 0.5], [0.8], 1);
        $simulated = (new Simulator)->simulateGame(retentionEconomy(), array_values(retentionEconomy()->players())[0], [$extra]);

        expect($simulated->notes['transmute']['transmutations'])->toBe(0);
    });
});

describe('the gate', function () {
    it('takes a tolerance of its own', function () {
        $economy = retentionEconomy();
        $baseline = (new Simulator)->simulateAllGame($economy);
        $faster = (new Simulator)->simulateAllGame($economy, static fn (): array => [new StreakExtra(aCycle(400_000, 3), [], app(GameCrates::class)->economy())]);

        expect((new ShiftGate)->evaluate($economy, $baseline, $faster, 0.9)->passed())->toBeTrue()
            ->and((new ShiftGate)->evaluate($economy, $baseline, $faster, 0.0)->passed())->toBeFalse()
            ->and((new ShiftGate)->evaluate($economy, $baseline, $faster)->tolerance)->toBe($economy->gateTolerance());
    });
});

describe('the commands', function () {
    it('play the streak and transmutation with the rules that ship, and say what they found', function () {
        $this->artisan('streaks:budget', ['--simulate' => true])->expectsOutputToContain('The streak in the economy simulator')->run();
        $this->artisan('economy:transmute', ['--simulate' => true])->expectsOutputToContain('A player with coins in the economy simulator')->expectsOutputToContain('Transmutation moves no car by more than 25%')->assertSuccessful();
    });
});
