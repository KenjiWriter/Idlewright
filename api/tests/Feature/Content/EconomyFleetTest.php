<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Domain\Economy\FleetCapStudy;
use App\Domain\Economy\Simulator;

// What the limit of active cars does to the pace of the simulator's players, and what the slots of the VIP account are worth: the free players of the anchors are not touched (they
// never have more than two cars), and the account gives about three times the income at the end of the ladder.

/** @return array<string, mixed> */
function anchorsOfTheGame(): array
{
    return json_decode((string) file_get_contents(config('ride.content_path').'/rules/economy.json'), true, 512, JSON_THROW_ON_ERROR);
}

describe('the study', function () {
    it('leaves the pace of a free player as it is, and gives the VIP account about three times the income', function () {
        $report = (new FleetCapStudy(new Simulator))->run(anchorsOfTheGame(), 2, 3, 200_000);

        expect(array_keys($report))->toBe(['A', 'B', 'C']);

        foreach ($report as $player) {
            expect($player['free_deviation'])->toBe(0.0)
                ->and($player['ratio'])->toEqualWithDelta(3.0, 0.001)
                ->and(collect($player['rows'])->every(fn (array $row): bool => $row['baseline_day'] === $row['free_day']))->toBeTrue()
                // With more cash the cars come sooner (or on the same day, where the level of the driver is what they wait for).
                ->and(collect($player['rows'])->every(fn (array $row): bool => $row['vip_day'] <= $row['free_day']))->toBeTrue();
        }
    });

    it('gives the VIP account less when the cars in its slots earn only a part of the pay of a lap (the variant B of the board: the fourth and the fifth car at 70%)', function () {
        $yield = [1_000_000, 1_000_000, 1_000_000, 700_000, 700_000];
        $report = (new FleetCapStudy(new Simulator))->run(anchorsOfTheGame(), 2, 3, 200_000, [], $yield);

        // The player B never has a fourth car (a fleet of 1.0 is 2.5 cars with the account), so the slots of the lever do not touch it; the others have 4.25 and are paid less for the
        // fourth and the fifth.
        expect($report['B']['ratio'])->toEqualWithDelta(3.0, 0.001)->and($report['A']['ratio'])->toBeBetween(2.5, 2.8)->and($report['C']['ratio'])->toBeBetween(2.5, 2.8);

        foreach ($report as $player) {
            expect($player['free_deviation'])->toBe(0.0);
        }
    });

    it('is not changed by a yield of all of it, or by no yield at all', function () {
        $whole = (new FleetCapStudy(new Simulator))->run(anchorsOfTheGame(), 2, 3, 200_000, ['B'], [1_000_000, 1_000_000, 1_000_000, 1_000_000, 1_000_000]);
        $none = (new FleetCapStudy(new Simulator))->run(anchorsOfTheGame(), 2, 3, 200_000, ['B']);

        expect($whole['B']['ratio'])->toBe($none['B']['ratio']);
    });

    it('slows a free player whose fleet the limit cuts', function () {
        $report = (new FleetCapStudy(new Simulator))->run(anchorsOfTheGame(), 1, 3, 200_000, ['A']);

        expect($report['A']['free_deviation'])->toBeGreaterThan(0.1)->and($report['A']['fleet_free'])->toBe(1.0);
    });

    it('plays the kinds of players that are asked for', function () {
        expect(array_keys((new FleetCapStudy(new Simulator))->run(anchorsOfTheGame(), 2, 3, 200_000, ['B'])))->toBe(['B']);
    });
});

describe('the command', function () {
    it('shows the days for the three kinds of players and what the VIP account gives, for the rules that ship', function () {
        $this->artisan('economy:fleet')
            ->expectsOutputToContain('2 cars on the road for everybody, 3 more with the VIP account (+20% of the cash of a lap)')
            ->expectsOutputToContain('The limit moves the pace of a free player by at most 0.0%')
            ->expectsOutputToContain('3.00 times as much')
            ->assertSuccessful();
    });

    it('fails when the limit would change the pace of the players who do not pay', function () {
        $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
        $data['slots']['base'] = 1;
        app()->singleton(Rules::class, fn () => Rules::fromArray($data));
        // The gate of these anchors is tight, so that what the limit does to the player who has more than one car is more than it allows.
        $anchors = anchorsOfTheGame();
        $anchors['gate']['tolerance'] = 0.05;
        $path = tempnam(sys_get_temp_dir(), 'anchors');
        file_put_contents($path, json_encode($anchors));

        try {
            $this->artisan('economy:fleet', ['--ruleset' => $path])->expectsOutputToContain('TOO MUCH.')->assertFailed();
        } finally {
            unlink($path);
        }
    });

    it('fails under --strict when the account gives more than the band', function () {
        $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
        $data['slots']['vip'] = 8;
        app()->singleton(Rules::class, fn () => Rules::fromArray($data));

        $this->artisan('economy:fleet')->expectsOutputToContain('OUTSIDE THE BAND.')->assertSuccessful();
        $this->artisan('economy:fleet', ['--strict' => true])->assertFailed();
    });

    it('says so in a game that has no limit', function () {
        $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
        $data['slots']['enabled'] = false;
        app()->singleton(Rules::class, fn () => Rules::fromArray($data));

        $this->artisan('economy:fleet')->expectsOutputToContain('no limit of active cars')->assertFailed();
    });
});
