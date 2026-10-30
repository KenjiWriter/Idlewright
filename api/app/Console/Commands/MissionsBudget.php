<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\MissionCatalog;
use App\Content\Rules;
use App\Models\RaceRun;
use App\Services\MissionDraw;
use App\Services\MissionScale;
use Illuminate\Console\Command;

/**
 * What the missions of the game ask and pay, for a car on a track: for every template the goal it asks, what it is worth (in tanks of the car) and what it pays in cash and
 * XP, what a set pays on average (a set draws one template of every slot, by weight), what a month of doing everything comes to, and the coins of a month next to the
 * prices of the game. The tool for tuning the numbers of the rules, and for seeing what a change does before it is published.
 */
final class MissionsBudget extends Command
{
    use RunsInAGame;

    protected $signature = 'missions:budget
        {--car=rdzak-gt-4 : The slug of the car whose tank is the measure}
        {--track=zlomowo-street : The slug of the track it drives}
        {--level=1 : The level of the driver, which decides what templates fit}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Show what the missions ask and pay, for a car on a track';

    public function handle(MissionCatalog $catalog, Rules $rules, MissionScale $scale, MissionDraw $draw): int
    {
        $settings = $rules->missions();

        if ($settings === null) {
            $this->error('The rules of this game have no missions.');

            return self::FAILURE;
        }

        $run = new RaceRun(['car_slug' => (string) $this->option('car'), 'track_slug' => (string) $this->option('track')]);
        $tank = $scale->tankOf($run);

        if ($tank === null) {
            $this->error('That car cannot drive that track on its tank.');

            return self::FAILURE;
        }

        $level = (int) $this->option('level');
        $dollars = static fn (int|float $cents): string => '$'.number_format($cents / 100, 2);

        $this->info(sprintf('A tank of the %s on %s: %d laps, %s gross, %s after the fuel, %d XP; filling it costs %s. The missions are %s.', $this->option('car'), $this->option('track'), $tank['laps'], $dollars($tank['gross_cents']), $dollars($tank['net_cents']), $tank['xp'], $dollars($tank['fuel_cents']), $settings['enabled'] ? 'on' : 'OFF'));

        $cash = [];
        $xp = [];
        $worth = [];

        foreach (MissionCatalog::PERIODS as $period) {
            $rows = [];
            $cash[$period] = 0.0;
            $xp[$period] = 0.0;
            $worth[$period] = 0.0;

            foreach ($catalog->slots($period) as $slot) {
                $fitting = array_filter($slot['templates'], static fn (array $template): bool => $level >= ($template['min_player_level'] ?? 1) && $level <= ($template['max_player_level'] ?? PHP_INT_MAX));
                $weights = array_sum(array_column($fitting, 'weight')) ?: 1;

                foreach ($slot['templates'] as $key => $template) {
                    $fits = isset($fitting[$key]);
                    $reward = $draw->templateReward($period, $template, $tank);
                    $worthOf = $draw->templateWorth($template, $tank) * (int) $template['reward_share'];
                    $items = implode(', ', array_map(static fn (array $item): string => $item['type'] === 'crate' ? "crate {$item['kind']}" : "part {$item['rarity']}", $template['items'] ?? []));

                    if ($fits) {
                        $chance = $template['weight'] / $weights;
                        $cash[$period] += $chance * $reward['cash_cents'];
                        $xp[$period] += $chance * $reward['xp'];
                        $worth[$period] += $chance * $worthOf;
                    }

                    $rows[] = [$slot['key'], $key, $fits ? $this->goal($template, $draw->target($template, $tank)) : '(does not fit)', $template['weight'], number_format($worthOf, 2).' tanks', $dollars($reward['cash_cents']), $reward['xp'], $items];
                }
            }

            $this->newLine();
            $this->line(sprintf('%s: %d missions, on average worth %.1f tanks of work, and the set pays about %s and %d XP, and %d coins for the whole set.', strtoupper($period), count($catalog->slots($period)), $worth[$period], $dollars($cash[$period]), (int) round($xp[$period]), $settings['periods'][$period]['bonus_coins']));
            $this->table(['slot', 'template', 'goal', 'weight', 'worth', 'cash', 'XP', 'items'], $rows);
        }

        $this->newLine();
        $this->line(sprintf('The main missions pay %d%% of what they are worth in cash and %d%% in XP (see missions:budget --help for the rest; the chains are in rules/missions).', $settings['main']['cash_ppm'] / 10_000, $settings['main']['xp_ppm'] / 10_000));

        // A month of 30 days of doing everything: 30 days, 30 / 7 weeks and one month.
        $weeks = 30 / 7;
        $monthCash = 30 * $cash['daily'] + $weeks * $cash['weekly'] + $cash['monthly'];
        $monthXp = 30 * $xp['daily'] + $weeks * $xp['weekly'] + $xp['monthly'];
        $monthCoins = 30 * $settings['periods']['daily']['bonus_coins'] + $weeks * $settings['periods']['weekly']['bonus_coins'] + $settings['periods']['monthly']['bonus_coins'];
        $day = max(1, $tank['net_cents']);

        $this->newLine();
        $this->line(sprintf('A month of doing everything: %s (%d%% of the %s that 30 tanks earn), %d XP (%d%% of the %d of 30 tanks), %d coins.', $dollars($monthCash), (int) round(100 * $monthCash / (30 * $day)), $dollars(30 * $day), (int) round($monthXp), (int) round(100 * $monthXp / max(1, 30 * $tank['xp'])), 30 * $tank['xp'], (int) round($monthCoins)));

        $vip = $rules->vip();
        $products = $rules->premiumProducts();
        $smallest = $products === [] ? null : $products[array_key_first($products)];

        if ($vip !== null) {
            $this->line(sprintf('The VIP account costs %d coins: a month of doing everything is %d%% of it.', $vip['coins'], (int) round(100 * $monthCoins / max(1, $vip['coins']))));
        }

        if ($smallest !== null) {
            $this->line(sprintf('At the price of the smallest pack (%d coins for %.2f %s) a month of doing everything is worth %.2f %s; speeding up at %d minutes a coin, it is %.1f hours of timers.', $smallest['coins'], $smallest['price_minor'] / 100, $rules->premiumPriceCurrency(), $monthCoins * $smallest['price_minor'] / $smallest['coins'] / 100, $rules->premiumPriceCurrency(), $rules->speedupMinutesPerCoin() ?? 5, $monthCoins * ($rules->speedupMinutesPerCoin() ?? 5) / 60));
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $template */
    private function goal(array $template, int $target): string
    {
        return match ($template['objective']) {
            'cash_earned', 'fuel_spent' => '$'.number_format($target / 100, 0),
            default => $template['objective'].' '.$target,
        };
    }
}
