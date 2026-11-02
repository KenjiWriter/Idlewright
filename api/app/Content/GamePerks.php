<?php

declare(strict_types=1);

namespace App\Content;

use App\Domain\Economy\PerkEconomy;

/**
 * The paid extras of the game as the economy simulator sees them: the VIP bonus, the pass, the charges of the auto-refill, the chance of a breakdown and the price of a
 * coin, from the rules of the game (`premium`, `breakdown`).
 */
final class GamePerks
{
    public function __construct(private readonly Rules $rules) {}

    /**
     * Null when the game sells nothing for coins (no `premium` section): there is no payer to compare with.
     */
    public function economy(): ?PerkEconomy
    {
        $products = $this->rules->premiumProducts();

        if ($products === [] || $this->rules->premiumCurrency() === null) {
            return null;
        }

        $smallest = $products[array_key_first($products)];
        $vip = $this->rules->vip();
        $pass = $this->rules->infinity();
        $refill = $this->rules->autoRefill();
        $perCharge = $refill === null || $refill['packs'] === [] ? 0.0 : min(array_map(static fn (array $pack): float => $pack['coins'] / max(1, $pack['charges']), $refill['packs']));

        return new PerkEconomy(
            vipCashPpm: $this->rules->vipOn() ? $vip['cash_bonus_ppm'] : 0,
            vipXpPpm: $this->rules->vipOn() ? $vip['xp_bonus_ppm'] : 0,
            vipCoins: $vip['coins'] ?? 0,
            vipDays: max(1, $vip['days'] ?? 30),
            passPriceMinor: $this->rules->infinityOn() ? $pass['price_minor'] : 0,
            passDays: max(1, $pass['days'] ?? 30),
            coinsPerCharge: $this->rules->autoRefillOn() ? $perCharge : 0.0,
            breakChancePerLapPpm: $this->rules->breakChancePerLapPpm(),
            minorPerCoin: $smallest['price_minor'] / $smallest['coins'],
            currency: $this->rules->premiumPriceCurrency() ?? 'PLN',
        );
    }
}
