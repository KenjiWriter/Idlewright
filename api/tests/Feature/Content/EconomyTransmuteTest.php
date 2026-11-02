<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Domain\Economy\MergeEconomy;
use App\Domain\Economy\TransmuteEconomy;
use App\Domain\Run\Rarity;

// What transmutation costs next to merging: the cash of a step is about half of what merging costs for it, the coins grow with the tier, and a transmuted epic part that could be merged
// would make a legendary one for coins, which is why it cannot.

function transmuteStudy(): TransmuteEconomy
{
    $game = app(Rules::class);
    $merge = new MergeEconomy(
        static fn (string $rarity, int $failures): int => $game->mergeChancePpm(Rarity::fromKey($rarity), $failures),
        $game->mergeCostCents(1_000_000),
        $game->mergeScrapCents(1_000_000),
    );

    return new TransmuteEconomy(
        $merge->steps(),
        array_map(static fn (int $ppm): float => $ppm / 1_000_000, $game->transmute()['cash_ppm']),
        static fn (int $step, int $tier): int => $game->transmuteCost($step, $tier, 0)['coins'],
        [0.0769, 0.1],
    );
}

describe('the study', function () {
    it('sets the cash of a step at about half of what merging costs for it, for the numbers that ship', function () {
        $steps = transmuteStudy()->steps()['steps'];

        expect(array_column($steps, 'rarity'))->toBe(['uncommon', 'rare', 'epic'])
            ->and(round($steps[0]['merge_p'], 2))->toBe(1.52)->and(round($steps[1]['merge_p'], 2))->toBe(5.52)->and(round($steps[2]['merge_p'], 2))->toBe(28.11);

        foreach ($steps as $step) {
            expect($step['share'])->toBeGreaterThan(0.45)->toBeLessThan(0.6);
        }
    });

    it('asks for the coins of the board of the design, and more of them for a higher tier', function () {
        $study = transmuteStudy();

        expect($study->coins(1)['coins'])->toBe([3, 8, 20])
            ->and($study->coins(3)['coins'])->toBe([5, 14, 34])
            ->and($study->coins(5)['total'])->toBe(89)
            ->and($study->coins(8)['total'])->toBe(194)
            ->and($study->coins(8)['zloty_max'])->toBeGreaterThan($study->coins(8)['zloty_min']);
    });

    it('shows what a legendary part would cost in coins if transmuted epic parts could be merged', function () {
        $game = app(Rules::class);
        $merge = new MergeEconomy(
            static fn (string $rarity, int $failures): int => $game->mergeChancePpm(Rarity::fromKey($rarity), $failures),
            $game->mergeCostCents(1_000_000),
            $game->mergeScrapCents(1_000_000),
        );
        $legendary = transmuteStudy()->legendaryThroughMerging(5, $merge->attempts('legendary'));

        expect($legendary['epics'])->toBeGreaterThan(6.0)->toBeLessThan(6.8)->and($legendary['coins'])->toBeBetween(540, 600);
    });
});

describe('the command', function () {
    it('shows the three steps next to merging and the coins of some tiers, and passes for the numbers that ship', function () {
        $this->artisan('economy:transmute')
            ->expectsOutputToContain('Transmutation is on, coins are part of the price, the best rarity it makes is epic, a transmuted part cannot be merged.')
            ->expectsOutputToContain('The whole way to epic')
            ->expectsOutputToContain('That is why they cannot.')
            ->assertSuccessful();
    });

    it('fails when the cash of a step is below the share of merging that was asked for, and warns when a transmuted part can be merged', function () {
        $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
        $data['transmute']['cash_ppm'] = [100_000, 100_000, 100_000];
        $data['transmute']['merge_input'] = true;
        app()->singleton(Rules::class, fn () => Rules::fromArray($data));

        $this->artisan('economy:transmute')->expectsOutputToContain('BELOW')->expectsOutputToContain('transmute.merge_input is on')->assertFailed();
    });
});
