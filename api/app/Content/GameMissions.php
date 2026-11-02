<?php

declare(strict_types=1);

namespace App\Content;

use App\Domain\Economy\MissionEconomy;

/**
 * The missions of the game as the economy simulator sees them: the slots of the sets of a day, a week and a month with the templates and their worth (times the
 * multiplier of the template), and what the rules pay of the worth in cash, experience and coins (`rules/missions` and the `missions` of the rules).
 */
final class GameMissions
{
    public function __construct(private readonly Rules $rules, private readonly MissionCatalog $catalog) {}

    /** Null when the game has no missions, or switches them off. */
    public function economy(): ?MissionEconomy
    {
        $settings = $this->rules->missions();

        if ($settings === null || ! $settings['enabled'] || $this->catalog->isEmpty()) {
            return null;
        }

        $effort = $this->catalog->effort();
        $periods = [];

        foreach (MissionCatalog::PERIODS as $period) {
            $slots = [];

            foreach ($this->catalog->slots($period) as $slot) {
                $templates = [];

                foreach ($slot['templates'] as $template) {
                    $objective = (string) $template['objective'];
                    $amount = in_array($objective, MissionCatalog::TANK_OBJECTIVES, true) ? ((int) ($template['tanks_ppm'] ?? 0)) / 1_000_000 : (int) ($template['count'] ?? 1);

                    $templates[] = [
                        'weight' => (int) $template['weight'],
                        'worth' => $amount * ($effort[$objective] ?? 0) / 1_000_000 * (int) $template['reward_share'],
                        'min_level' => (int) ($template['min_player_level'] ?? 1),
                        'max_level' => (int) ($template['max_player_level'] ?? PHP_INT_MAX),
                    ];
                }

                $slots[] = $templates;
            }

            $periods[$period] = [
                'cash_ppm' => $settings['periods'][$period]['cash_ppm'],
                'xp_ppm' => $settings['periods'][$period]['xp_ppm'],
                'bonus_coins' => $settings['periods'][$period]['bonus_coins'],
                'slots' => $slots,
            ];
        }

        return new MissionEconomy($periods);
    }
}
