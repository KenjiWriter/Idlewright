<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The VIP account, played in the scenario game of the simulator: every day of the account the cars are paid the bonus of the rules on what a lap pays (before the
 * fuel, so on the gross of the car and not on its profit) and the driving gives that much more experience. It holds for every car of the player, the wrecks of the
 * garages too. The account is bought again every time it runs out, so a player who has it has it every day; the report says what that costs in a month.
 */
final class VipExtra extends PerkExtra
{
    public function __construct(private readonly PerkEconomy $perks) {}

    public function name(): string
    {
        return 'vip';
    }

    public function day(DayState $state): DayEffect
    {
        $rules = $state->rules;
        $bonus = $this->perks->vipCashPpm / 1_000_000;

        // The gross of a car is what a lap pays: the profit and the fuel. The wrecks earn what a car of the first rung earns.
        $cash = $bonus * ($state->income * $this->grossShare($rules, $state->rung) + $state->otherIncome * $this->grossShare($rules, 0));

        return $this->record($state, new DayEffect(cash: $cash, xpFactor: 1.0 + $this->perks->vipXpPpm / 1_000_000));
    }

    /** How much more the laps of a car pay than its profit: 1.3 is a third more, because the fuel takes a quarter of it. */
    private function grossShare(EconomyRules $rules, int $rung): float
    {
        return $rules->grossPerHour($rung) / $rules->netPerHour($rung);
    }

    public function report(): array
    {
        $coins = $this->perks->vipCoins * 30 / $this->perks->vipDays;

        return [
            'cash_bonus' => $this->perks->vipCashPpm / 1_000_000,
            'xp_bonus' => $this->perks->vipXpPpm / 1_000_000,
            'coins_per_30d' => round($coins, 2),
            'price_per_30d' => round($this->perks->priceOfCoins($coins), 2),
            'currency' => $this->perks->currency,
            ...$this->uplift(),
        ];
    }
}
