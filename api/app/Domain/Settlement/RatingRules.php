<?php

declare(strict_types=1);

namespace App\Domain\Settlement;

use InvalidArgumentException;

/**
 * The numbers that Respekt (the rating of a driver) is made of, as the rules of a game give them: where a driver starts, the lowest they can fall to, how fast the
 * rating moves for a new driver, an established one and one at the top, where the divisions begin and how much of a rating stays after the reset of a season.
 * `defaults()` is what the first game has, and what `Rating` uses when it is not given others.
 */
final readonly class RatingRules
{
    private const PPM = 1_000_000;

    public function __construct(
        public int $start,
        public int $minimum,
        public int $kNew,
        public int $newMatches,
        public int $kEstablished,
        public int $kTop,
        public int $topFrom,
        public int $clubFrom,
        public int $regionalFrom,
        public int $eliteFrom,
        public int $seasonKeepPpm,
    ) {
        if ($minimum > $start || min($kNew, $kEstablished, $kTop) < 1 || $newMatches < 0) {
            throw new InvalidArgumentException('The minimum must not be above the start, and every K must be positive.');
        }

        if (! ($clubFrom < $regionalFrom && $regionalFrom < $eliteFrom) || $seasonKeepPpm < 0 || $seasonKeepPpm > self::PPM) {
            throw new InvalidArgumentException('The divisions must grow, and the part of a rating that a season keeps is between nothing and all of it.');
        }
    }

    /** The rules of the first game: start 1000, K 40 for the first 20 matches, then 24 and 16 from 1700, divisions at 1100, 1400 and 1700, a season keeps 75%. */
    public static function defaults(): self
    {
        return new self(1_000, 600, 40, 20, 24, 16, 1_700, 1_100, 1_400, 1_700, 750_000);
    }

    /**
     * @param  array{start: int, minimum: int, k_new: int, new_matches: int, k_established: int, k_top: int, top_from: int, club_from: int, regional_from: int, elite_from: int, season_keep_ppm: int}  $rating
     */
    public static function fromArray(array $rating): self
    {
        return new self(
            $rating['start'],
            $rating['minimum'],
            $rating['k_new'],
            $rating['new_matches'],
            $rating['k_established'],
            $rating['k_top'],
            $rating['top_from'],
            $rating['club_from'],
            $rating['regional_from'],
            $rating['elite_from'],
            $rating['season_keep_ppm'],
        );
    }

    /** K factor: ratings move fast for new drivers, slower for established ones and slowest at the top. */
    public function kFactor(int $matchesPlayed, int $rating): int
    {
        return match (true) {
            $matchesPlayed < $this->newMatches => $this->kNew,
            $rating < $this->topFrom => $this->kEstablished,
            default => $this->kTop,
        };
    }

    /** Division name from a rating: street, club, regional or elite. */
    public function division(int $rating): string
    {
        return match (true) {
            $rating >= $this->eliteFrom => 'elite',
            $rating >= $this->regionalFrom => 'regional',
            $rating >= $this->clubFrom => 'club',
            default => 'street',
        };
    }

    /** The rating at the start of a season: the part that stays plus the rest of the start value. Rounds half up. */
    public function seasonReset(int $rating): int
    {
        return $this->clamp(intdiv($this->seasonKeepPpm * $rating + (self::PPM - $this->seasonKeepPpm) * $this->start + intdiv(self::PPM, 2), self::PPM));
    }

    /** A rating never falls below the floor. */
    public function clamp(int $rating): int
    {
        return max($this->minimum, $rating);
    }
}
