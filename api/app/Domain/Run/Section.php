<?php

declare(strict_types=1);

namespace App\Domain\Run;

use InvalidArgumentException;

/**
 * One piece of the thread of a track: a straight with a length, or a corner with a radius and an angle. Both can sit on another surface than the
 * track (a stretch of gravel in a rally stage), have a speed limit (a street, a pit lane) and be rough, which asks the car for clearance.
 *
 * The domain only refuses what is nonsense (a length of nothing, an angle of more than a turn). What an operator may draw (the smallest radius, the
 * longest straight) is for the content layer to say, in words.
 */
final readonly class Section
{
    public const STRAIGHT = 'straight';

    public const CORNER = 'corner';

    /** The surfaces a section can be made of; the weather turns them into the five of `Surface`. */
    public const SURFACES = ['asphalt', 'gravel'];

    public const LEFT = 'left';

    public const RIGHT = 'right';

    private function __construct(
        public string $type,
        public float $lengthM,
        public float $radiusM,
        public float $angleDeg,
        public string $turn,
        public ?string $surface,
        public ?float $limitKmh,
        public float $rough,
    ) {
        if ($lengthM <= 0) {
            throw new InvalidArgumentException('A section needs a length.');
        }

        if ($surface !== null && ! in_array($surface, self::SURFACES, true)) {
            throw new InvalidArgumentException("There is no surface {$surface}; a section is asphalt or gravel.");
        }

        if ($limitKmh !== null && $limitKmh <= 0) {
            throw new InvalidArgumentException('A speed limit must be positive.');
        }

        if ($rough < 0) {
            throw new InvalidArgumentException('The roughness of a section cannot be negative.');
        }
    }

    public static function straight(float $lengthM, ?string $surface = null, ?float $limitKmh = null, float $rough = 0.0): self
    {
        return new self(self::STRAIGHT, $lengthM, 0.0, 0.0, '', $surface, $limitKmh, $rough);
    }

    public static function corner(float $radiusM, float $angleDeg, string $turn = self::RIGHT, ?string $surface = null, ?float $limitKmh = null, float $rough = 0.0): self
    {
        if ($radiusM <= 0) {
            throw new InvalidArgumentException('A corner needs a radius.');
        }

        if ($angleDeg <= 0 || $angleDeg > 360) {
            throw new InvalidArgumentException('The angle of a corner is more than nothing and at most a full turn.');
        }

        if (! in_array($turn, [self::LEFT, self::RIGHT], true)) {
            throw new InvalidArgumentException("A corner turns left or right, not {$turn}.");
        }

        return new self(self::CORNER, $radiusM * deg2rad($angleDeg), $radiusM, $angleDeg, $turn, $surface, $limitKmh, $rough);
    }

    public function isCorner(): bool
    {
        return $this->type === self::CORNER;
    }
}
