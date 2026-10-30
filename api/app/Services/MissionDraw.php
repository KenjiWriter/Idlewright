<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\MissionCatalog;
use App\Content\Rules;
use App\Games\CurrentGame;

/**
 * Draws the set of a period for a driver: one mission for every slot, from the templates that fit the level of the driver, by weight. The seed is the game, the
 * driver, the period and the slot (with the secret of the application, as the draw of a crate has), so the same driver gets the same set whenever it is asked for,
 * and nobody can know what the next day brings. The goals are worked out from a tank of the best car of the driver, and the reward of the set is divided among its
 * missions by their shares, so a set pays the same whichever missions it draws (the numbers of the rules): the draw decides what is asked, not how much is paid.
 */
final class MissionDraw
{
    public function __construct(
        private readonly MissionCatalog $catalog,
        private readonly Rules $rules,
        private readonly CurrentGame $game,
    ) {}

    /**
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}  $tankful
     * @return list<array{slot: int, key: string, objective: string, target: int, reward: array{cash_cents: int, xp: int, car_xp: int, items: list<array<string, string>>}}>
     */
    public function set(int $userId, string $period, string $periodKey, int $level, array $tankful): array
    {
        $chosen = [];

        foreach ($this->catalog->slots($period) as $index => $slot) {
            $fitting = array_filter(
                $slot['templates'],
                static fn (array $template): bool => $level >= ($template['min_player_level'] ?? 1) && $level <= ($template['max_player_level'] ?? PHP_INT_MAX),
            );

            if ($fitting === []) {
                continue;
            }

            $seed = hash_hmac('sha256', "mission|{$this->game->id()}|{$userId}|{$period}|{$periodKey}|{$slot['key']}", (string) config('app.key'));
            $key = $this->pick($seed, array_map(static fn (array $template): int => (int) $template['weight'], $fitting));
            $chosen[] = ['slot' => $index + 1, 'key' => $key, 'template' => $fitting[$key]];
        }

        $set = [];

        foreach ($chosen as $mission) {
            $template = $mission['template'];

            $set[] = [
                'slot' => $mission['slot'],
                'key' => $mission['key'],
                'objective' => $template['objective'],
                'target' => $this->target($template, $tankful),
                'reward' => $this->templateReward($period, $template, $tankful),
            ];
        }

        return $set;
    }

    /**
     * What a mission of a set pays: the part of its worth (times its multiplier) that the rules of the period pay in cash and in XP, the experience of the car and the
     * items of the template.
     *
     * @param  array<string, mixed>  $template
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}  $tankful
     * @return array{cash_cents: int, xp: int, car_xp: int, items: list<array<string, string>>}
     */
    public function templateReward(string $period, array $template, array $tankful): array
    {
        return $this->paid($this->templateWorth($template, $tankful) * (int) $template['reward_share'], $this->rules->missions()['periods'][$period], $tankful, $template);
    }

    /**
     * What a template is worth, in tanks of the best car of the driver: the work it asks (a part of a tank of laps, of earnings or of fuel, or a number of times) times what
     * the missions say a unit of that work is worth (`effort`). A mission pays a part of this, so a goal that asks for much pays much.
     *
     * @param  array<string, mixed>  $template
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}  $tankful
     */
    public function templateWorth(array $template, array $tankful): float
    {
        $objective = (string) $template['objective'];
        $amount = in_array($objective, MissionCatalog::TANK_OBJECTIVES, true) ? ((int) ($template['tanks_ppm'] ?? 0)) / 1_000_000 : (int) ($template['count'] ?? 1);

        return $amount * ($this->catalog->effort()[$objective] ?? 0) / 1_000_000;
    }

    /**
     * What a main mission is worth in tanks of the best car of the driver as it opens: the laps, the cash and the fuel that it asks over all the time are turned into
     * tanks of that car (so a better car makes a goal of laps worth the same work), and the rest is a number of times, a level or a number of things the driver has.
     *
     * @param  array<string, mixed>  $mission
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}  $tankful
     */
    public function chainWorth(array $mission, array $tankful): float
    {
        $objective = (string) $mission['objective'];
        $count = (int) $mission['count'];
        $effort = $this->catalog->effort();
        $key = isset($mission['param']) ? "{$objective}:{$mission['param']}" : $objective;

        $amount = match ($objective) {
            'laps', 'wet_laps' => $count / max(1, $tankful['laps']),
            'cash_earned' => $count / max(1, $tankful['gross_cents']),
            'fuel_spent' => $count / max(1, $tankful['fuel_cents']),
            default => $count,
        };

        return $amount * ($effort[$key] ?? $effort[$objective] ?? 0) / 1_000_000;
    }

    /**
     * What a template asks of a driver whose tank is this: laps and the cents of cash or fuel as a part of a tank, the rest a number of times.
     *
     * @param  array<string, mixed>  $template
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}  $tankful
     */
    public function target(array $template, array $tankful): int
    {
        $ppm = (int) ($template['tanks_ppm'] ?? 0);

        return match ($template['objective']) {
            'laps', 'wet_laps' => max(1, (int) round($tankful['laps'] * $ppm / 1_000_000)),
            'cash_earned' => $this->dollars((int) round($tankful['gross_cents'] * $ppm / 1_000_000)),
            'fuel_spent' => $this->dollars((int) round($tankful['fuel_cents'] * $ppm / 1_000_000)),
            default => (int) ($template['count'] ?? 1),
        };
    }

    /**
     * What a main mission pays, worked out when it opens from what it is worth in tanks of the best car of the driver (`chainWorth`): a part of it in cash and a part
     * in XP (the rules of the main missions), the experience of the car as that many laps of it, and the items as the mission gives them.
     *
     * @param  array<string, mixed>  $mission
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}  $tankful
     * @return array{cash_cents: int, xp: int, car_xp: int, items: list<array<string, string>>}
     */
    public function chainReward(array $mission, array $tankful): array
    {
        return $this->paid($this->chainWorth($mission, $tankful), $this->rules->missions()['main'], $tankful, $mission['reward'] ?? []);
    }

    /**
     * What a worth of tanks pays: the cash is a part of the net earnings of that many tanks, and the XP a part of their XP (whole dollars, and at least a dollar and a
     * point where the rules pay anything at all); the experience of the car and the items are the ones the template or the mission gives.
     *
     * @param  array{cash_ppm: int, xp_ppm: int}  $share
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}  $tankful
     * @param  array<string, mixed>  $gives
     * @return array{cash_cents: int, xp: int, car_xp: int, items: list<array<string, string>>}
     */
    private function paid(float $worth, array $share, array $tankful, array $gives): array
    {
        return [
            'cash_cents' => $share['cash_ppm'] > 0 ? $this->dollars((int) round($worth * max(0, $tankful['net_cents']) * $share['cash_ppm'] / 1_000_000)) : 0,
            'xp' => $share['xp_ppm'] > 0 ? max(1, (int) round($worth * $tankful['xp'] * $share['xp_ppm'] / 1_000_000)) : 0,
            'car_xp' => isset($gives['car_xp_laps']) ? (int) $gives['car_xp_laps'] * $this->rules->carXpPerLap() : 0,
            'items' => $gives['items'] ?? [],
        ];
    }

    /** Whole dollars, and at least one. */
    private function dollars(int $cents): int
    {
        return max(100, intdiv($cents + 50, 100) * 100);
    }

    /**
     * The key of a weighted choice: the same seed gives the same key.
     *
     * @param  array<string, int>  $weights
     */
    private function pick(string $seed, array $weights): string
    {
        $roll = hexdec(substr($seed, 0, 12)) % max(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            if ($roll < $weight) {
                return (string) $key;
            }

            $roll -= $weight;
        }

        return (string) array_key_last($weights);
    }
}
