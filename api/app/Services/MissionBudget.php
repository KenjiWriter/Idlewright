<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\MissionCatalog;
use App\Content\Rules;
use App\Models\RaceRun;
use InvalidArgumentException;

/**
 * What the missions of the game ask and pay, worked out from the rules and the pools of the content that is in force: the one place that `missions:budget`, the gate of
 * the publication and the page of the missions in the panel take their numbers from, so that they cannot disagree.
 *
 * A mission pays a part of what it is worth (the work it asks, in tanks of the best car of the driver, times the effort of that kind of work), so what a set pays is the
 * average of the missions it can draw for a level, and what a month comes to is that average over 30 days, 30 / 7 weeks and a month. The shares of a month (of the cash and
 * of the XP of 30 tanks) are the same for every car; the amounts are for one car on one track. The limits are `ride.missions.budget`.
 */
final class MissionBudget
{
    /** A month of 30 days of doing everything: 30 days, 30 / 7 weeks and one month. */
    private const WEEKS = 30 / 7;

    public function __construct(private readonly Rules $rules, private readonly MissionCatalog $catalog, private readonly MissionScale $scale, private readonly MissionDraw $draw) {}

    /**
     * The report for a car on a track and a level of the driver (which decides what templates fit). The shares and the limits do not depend on the car, so a car that cannot
     * drive the track (or a game that has none of that name) still has them, with `tank` null and no amounts.
     *
     * @return array<string, mixed>|null null when the rules of the game have no missions
     */
    public function report(string $car, string $track, int $level = 1): ?array
    {
        $settings = $this->rules->missions();

        if ($settings === null) {
            return null;
        }

        try {
            $tank = $this->scale->tankOf(new RaceRun(['car_slug' => $car, 'track_slug' => $track]));
        } catch (InvalidArgumentException) {
            // A game that has no car or no track of that name has no tank to measure by.
            $tank = null;
        }

        $periods = [];

        foreach (MissionCatalog::PERIODS as $period) {
            $periods[$period] = $this->period($period, $settings['periods'][$period], $level, $tank);
        }

        $month = $this->month($periods, $tank);
        $report = [
            'enabled' => $settings['enabled'],
            'car' => $car,
            'track' => $track,
            'level' => $level,
            'tank' => $tank === null ? null : [...$tank, 'car' => $car, 'track' => $track],
            'periods' => $periods,
            'main' => $this->main($settings['main'], $tank),
            'month' => $month,
            'vip' => $this->vip($month),
            'pack' => $this->pack($month),
        ];

        $report['checks'] = $this->checks($report);

        return $report;
    }

    /**
     * One set: how many missions it has, what its missions are worth on average for the level (in tanks of work), what it pays on average, and what every template asks and
     * pays.
     *
     * @param  array{bonus_coins: int, cash_ppm: int, xp_ppm: int}  $budget
     * @param  array<string, mixed>|null  $tank
     * @return array<string, mixed>
     */
    private function period(string $period, array $budget, int $level, ?array $tank): array
    {
        $row = ['missions' => count($this->catalog->slots($period)), 'cash_ppm' => $budget['cash_ppm'], 'xp_ppm' => $budget['xp_ppm'], 'coins' => $budget['bonus_coins'], 'worth' => 0.0, 'cash_cents' => $tank === null ? null : 0.0, 'xp' => $tank === null ? null : 0.0, 'templates' => []];

        foreach ($this->catalog->slots($period) as $slot) {
            $fitting = array_filter($slot['templates'], static fn (array $template): bool => $level >= ($template['min_player_level'] ?? 1) && $level <= ($template['max_player_level'] ?? PHP_INT_MAX));
            $weights = array_sum(array_column($fitting, 'weight')) ?: 1;

            foreach ($slot['templates'] as $key => $template) {
                $fits = isset($fitting[$key]);
                // What a template is worth does not depend on the car: it is a number of tanks of work.
                $worth = $this->draw->templateWorth($template, []) * (int) $template['reward_share'];
                $reward = $tank === null ? null : $this->draw->templateReward($period, $template, $tank);

                if ($fits) {
                    $chance = $template['weight'] / $weights;
                    $row['worth'] += $chance * $worth;

                    if ($reward !== null) {
                        $row['cash_cents'] += $chance * $reward['cash_cents'];
                        $row['xp'] += $chance * $reward['xp'];
                    }
                }

                $row['templates'][] = [
                    'slot' => $slot['key'],
                    'template' => $key,
                    'objective' => $template['objective'],
                    'fits' => $fits,
                    'asks' => $tank === null || ! $fits ? null : $this->draw->target($template, $tank),
                    'money' => in_array($template['objective'], ['cash_earned', 'fuel_spent'], true),
                    'weight' => $template['weight'],
                    'share' => $template['reward_share'],
                    'worth' => $worth,
                    'cash_cents' => $reward['cash_cents'] ?? null,
                    'xp' => $reward['xp'] ?? null,
                    'items' => implode(', ', array_map(static fn (array $item): string => $item['type'] === 'crate' ? "crate {$item['kind']}" : "part {$item['rarity']}", $template['items'] ?? [])),
                ];
            }
        }

        return $row;
    }

    /**
     * The main missions in all: how many, what they are worth, and what the rules pay of it (with a car: in money and XP, as they open for it).
     *
     * @param  array{cash_ppm: int, xp_ppm: int}  $share
     * @param  array<string, mixed>|null  $tank
     * @return array{cash_ppm: int, xp_ppm: int, missions: int, worth: float|null, cash_cents: int|null, xp: int|null}
     */
    private function main(array $share, ?array $tank): array
    {
        $count = 0;
        $worth = 0.0;
        $cash = 0;
        $xp = 0;

        foreach ($this->catalog->chains() as $chain) {
            foreach ($chain['missions'] as $mission) {
                $count++;

                if ($tank !== null) {
                    $worth += $this->draw->chainWorth($mission, $tank);
                    $reward = $this->draw->chainReward($mission, $tank);
                    $cash += $reward['cash_cents'];
                    $xp += $reward['xp'];
                }
            }
        }

        return ['cash_ppm' => $share['cash_ppm'], 'xp_ppm' => $share['xp_ppm'], 'missions' => $count, 'worth' => $tank === null ? null : $worth, 'cash_cents' => $tank === null ? null : $cash, 'xp' => $tank === null ? null : $xp];
    }

    /**
     * What a month of doing everything comes to: the shares of the cash and of the XP of 30 tanks (they follow from what the missions are worth and what the rules pay of
     * it, for every car), the coins, and the amounts for the car when there is one.
     *
     * @param  array<string, array<string, mixed>>  $periods
     * @param  array<string, mixed>|null  $tank
     * @return array<string, mixed>
     */
    private function month(array $periods, ?array $tank): array
    {
        $sum = static fn (callable $of): float => 30 * $of($periods['daily']) + self::WEEKS * $of($periods['weekly']) + $of($periods['monthly']);

        $month = [
            'cash_share' => $sum(static fn (array $row): float => $row['worth'] * $row['cash_ppm'] / 1_000_000) / 30,
            'xp_share' => $sum(static fn (array $row): float => $row['worth'] * $row['xp_ppm'] / 1_000_000) / 30,
            'coins' => $sum(static fn (array $row): float => $row['coins']),
            'cash_cents' => null,
            'xp' => null,
            'cash_percent' => null,
            'xp_percent' => null,
            'cash_of_tanks_cents' => null,
            'xp_of_tanks' => null,
        ];

        if ($tank !== null) {
            $month['cash_cents'] = $sum(static fn (array $row): float => $row['cash_cents']);
            $month['xp'] = $sum(static fn (array $row): float => $row['xp']);
            $day = max(1, $tank['net_cents']);
            $month['cash_of_tanks_cents'] = 30 * $day;
            $month['cash_percent'] = (int) round(100 * $month['cash_cents'] / (30 * $day));
            $month['xp_of_tanks'] = 30 * $tank['xp'];
            $month['xp_percent'] = (int) round(100 * $month['xp'] / max(1, 30 * $tank['xp']));
        }

        return $month;
    }

    /**
     * Whether every limit holds, and what each one says.
     *
     * @param  array<string, mixed>  $report
     * @return list<array{name: string, value: float, min: float, max: float, ok: bool}>
     */
    private function checks(array $report): array
    {
        $limits = (array) config('ride.missions.budget', []);
        $checks = [];

        foreach (['cash_share', 'xp_share'] as $name) {
            [$min, $max] = $limits[$name] ?? [null, null];

            if ($min !== null) {
                $value = $report['month'][$name];
                $checks[] = ['name' => $name, 'value' => $value, 'min' => (float) $min, 'max' => (float) $max, 'ok' => $value >= $min && $value <= $max];
            }
        }

        return $checks;
    }

    /**
     * The VIP account next to what a month of the coins comes to: how much of its price the free coins of a month are.
     *
     * @param  array<string, mixed>  $month
     * @return array{coins: int, month_percent: int}|null
     */
    private function vip(array $month): ?array
    {
        $vip = $this->rules->vip();

        return $vip === null ? null : ['coins' => $vip['coins'], 'month_percent' => (int) round(100 * $month['coins'] / max(1, $vip['coins']))];
    }

    /**
     * What a month of the coins is worth at the price of a coin of the first pack, and how many hours of timers it speeds up.
     *
     * @param  array<string, mixed>  $month
     * @return array{coins: int, price_minor: int, currency: string|null, worth: float, speedup_hours: float, minutes_per_coin: int}|null
     */
    private function pack(array $month): ?array
    {
        $products = $this->rules->premiumProducts();
        $smallest = $products === [] ? null : $products[array_key_first($products)];

        if ($smallest === null) {
            return null;
        }

        $minutes = $this->rules->speedupMinutesPerCoin() ?? 5;

        return [
            'coins' => $smallest['coins'],
            'price_minor' => $smallest['price_minor'],
            'currency' => $this->rules->premiumPriceCurrency(),
            'worth' => $month['coins'] * $smallest['price_minor'] / $smallest['coins'] / 100,
            'speedup_hours' => $month['coins'] * $minutes / 60,
            'minutes_per_coin' => $minutes,
        ];
    }
}
