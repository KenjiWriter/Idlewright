<?php

declare(strict_types=1);

use App\Domain\Settlement\Rating;
use App\Domain\Settlement\RatingRules;

it('has the rules of the first game as its defaults, the numbers that Rating always had', function () {
    $rules = RatingRules::defaults();

    expect($rules->start)->toBe(Rating::START)
        ->and($rules->kFactor(0, 1_000))->toBe(40)
        ->and($rules->kFactor(19, 1_900))->toBe(40)
        ->and($rules->kFactor(20, 1_699))->toBe(24)
        ->and($rules->kFactor(20, 1_700))->toBe(16)
        ->and($rules->division(1_099))->toBe('street')
        ->and($rules->division(1_100))->toBe('club')
        ->and($rules->division(1_400))->toBe('regional')
        ->and($rules->division(1_700))->toBe('elite');
});

it('resets a season to three quarters of the rating and a quarter of the start, whatever the way it is computed', function () {
    foreach ([600, 701, 999, 1_000, 1_001, 1_234, 1_700, 2_345] as $rating) {
        expect(RatingRules::defaults()->seasonReset($rating))->toBe(intdiv(3 * $rating + 1_000 + 2, 4), "rating {$rating}")
            ->and(Rating::seasonReset($rating))->toBe(RatingRules::defaults()->seasonReset($rating));
    }
});

it('takes the numbers of another game and lets Rating use them', function () {
    $rules = RatingRules::fromArray([
        'start' => 1_200, 'floor' => 800, 'k_new' => 50, 'new_matches' => 10, 'k_established' => 30, 'k_top' => 20,
        'top_from' => 2_000, 'club_from' => 1_300, 'regional_from' => 1_600, 'elite_from' => 1_900, 'season_keep_ppm' => 500_000,
    ]);

    expect(Rating::kFactor(9, 1_500, $rules))->toBe(50)
        ->and(Rating::kFactor(10, 1_500, $rules))->toBe(30)
        ->and(Rating::kFactor(10, 2_000, $rules))->toBe(20)
        ->and(Rating::division(1_299, $rules))->toBe('street')
        ->and(Rating::division(1_900, $rules))->toBe('elite')
        // Half of 1 800 and half of the start 1 200.
        ->and(Rating::seasonReset(1_800, $rules))->toBe(1_500)
        ->and($rules->clamp(700))->toBe(800)
        ->and($rules->clamp(900))->toBe(900);
});

it('refuses numbers that cannot be ratings', function () {
    $good = ['start' => 1_000, 'floor' => 600, 'k_new' => 40, 'new_matches' => 20, 'k_established' => 24, 'k_top' => 16, 'top_from' => 1_700, 'club_from' => 1_100, 'regional_from' => 1_400, 'elite_from' => 1_700, 'season_keep_ppm' => 750_000];

    expect(RatingRules::fromArray($good)->minimum)->toBe(600)
        ->and(fn () => RatingRules::fromArray([...$good, 'floor' => 1_100]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RatingRules::fromArray([...$good, 'k_top' => 0]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RatingRules::fromArray([...$good, 'regional_from' => 1_100]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RatingRules::fromArray([...$good, 'season_keep_ppm' => 1_000_001]))->toThrow(InvalidArgumentException::class);
});
