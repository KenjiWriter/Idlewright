<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The paid extras of the game as the economy simulator sees them: the VIP account (a bonus on the cash and the experience of a lap), the auto-refill (a charge fills
 * the tank of a car that would stop, at once and for nothing) and the pass of the endless refill, and what the coins and the pass cost in money. Pure: `Content\GamePerks`
 * makes it from the rules of the game.
 *
 * A car that is filled when its fuel runs out drives on until it breaks down, and a breakdown stands it until the driver comes back: the mean time between two of them
 * is what limits an hour of a car that is never short of fuel.
 */
final readonly class PerkEconomy
{
    /** The hours in a day: a car that is never short of fuel cannot drive more. */
    public const DAY_HOURS = 24.0;

    public function __construct(
        /** The VIP bonus on what a lap pays and on its experience, in ppm. */
        public int $vipCashPpm,
        public int $vipXpPpm,
        /** What the VIP account costs in coins for the days that it lasts. */
        public int $vipCoins,
        public int $vipDays,
        /** The price of the pass of the endless refill in the smallest unit of the price currency, and the days it lasts. */
        public int $passPriceMinor,
        public int $passDays,
        /** The coins that the cheapest charge of the auto-refill costs (the largest pack of charges, per charge). */
        public float $coinsPerCharge,
        /** The chance of a breakdown in a lap of a car in perfect condition, in ppm. */
        public int $breakChancePerLapPpm,
        /** What a coin costs in the smallest unit of the price currency at the smallest pack of coins (the dearest price a driver pays). */
        public float $minorPerCoin,
        public string $currency = 'PLN',
    ) {}

    /** The mean hours that a car drives between two breakdowns at this rung, when no empty tank stops it. */
    public function hoursBetweenBreakdowns(EconomyRules $rules, int $rung): float
    {
        return $this->breakChancePerLapPpm <= 0 ? self::DAY_HOURS : 1_000_000 / ($this->breakChancePerLapPpm * $rules->lapsPerHour($rung));
    }

    /** What the coins cost in the price currency (not the smallest unit). */
    public function priceOfCoins(float $coins): float
    {
        return $coins * $this->minorPerCoin / 100;
    }
}
