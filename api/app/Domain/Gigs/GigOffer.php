<?php

declare(strict_types=1);

namespace App\Domain\Gigs;

/**
 * One offer of a board: a round trip of `$km` in all (from the city `$region` to `$toRegion` and back, or a trip in the city when `$toRegion` is null) with
 * `$amount` people or kilograms. It is the same, to the number, every time the board is made from the same seed, so it is never stored; what the driver took is.
 */
final readonly class GigOffer
{
    /**
     * @param  string  $key  `{city}.{wave}.{slot}.{fingerprint}`: what the driver sends back to take it
     * @param  string  $phase  the phase of the day the wave began in
     * @param  string  $capacity  `seats` or `cargo_kg`
     * @param  list<string>|null  $classIn  the classes that may take it, or null for any
     * @param  int  $untilMs  when the offer leaves the board
     * @param  int  $refMs  the reference time of the trip: what its length takes at the reference speed
     * @param  int|null  $deadlinePpm  the part of the reference time the car must finish within, or null
     * @param  bool  $opportunity  the night run of a night: it is made once for the night, not drawn from a slot of the wave, and the police look at it
     */
    public function __construct(
        public string $key,
        public string $region,
        public ?string $toRegion,
        public int $wave,
        public int $slot,
        public string $phase,
        public string $template,
        public string $kind,
        public string $capacity,
        public int $amount,
        public ?array $classIn,
        public string $rarity,
        public int $km,
        public int $refMs,
        public int $payPpm,
        public ?int $deadlinePpm,
        public int $untilMs,
        public bool $opportunity = false,
    ) {}
}
