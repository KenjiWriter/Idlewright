<?php

declare(strict_types=1);

namespace App\Domain\Settlement;

/**
 * Respekt, the rating of a driver: an Elo style number that rises with a win and falls with a loss.
 * Integers only; the expected score comes from the EloTable. The numbers it is made of (K, the divisions, the reset of a season) are
 * the `RatingRules` of the game; without others, the rules of the first game.
 */
final class Rating
{
    public const START = 1_000;

    /** K factor: ratings move fast for new drivers, slower for established ones and slowest at the top. */
    public static function kFactor(int $matchesPlayed, int $rating, ?RatingRules $rules = null): int
    {
        return ($rules ?? RatingRules::defaults())->kFactor($matchesPlayed, $rating);
    }

    /**
     * The change of the player's rating after one rated match, rounded half away from zero.
     * The rival's change is computed the same way with the roles swapped.
     */
    public static function delta(EloTable $table, int $k, bool $won, int $playerRating, int $rivalRating): int
    {
        $expected = $table->expectedPpm($rivalRating - $playerRating);
        $score = $won ? Ppm::ONE : 0;

        return Ppm::mul($k, $score - $expected);
    }

    /** Division name from a rating: street, club, regional or elite. */
    public static function division(int $rating, ?RatingRules $rules = null): string
    {
        return ($rules ?? RatingRules::defaults())->division($rating);
    }

    /** Soft reset at the start of a season: 75% of the rating stays, 25% goes back to the start value. Rounds half up. */
    public static function seasonReset(int $rating, ?RatingRules $rules = null): int
    {
        return ($rules ?? RatingRules::defaults())->seasonReset($rating);
    }
}
