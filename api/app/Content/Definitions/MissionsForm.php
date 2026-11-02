<?php

declare(strict_types=1);

namespace App\Content\Definitions;

use App\Content\MissionCatalog;
use stdClass;

/**
 * What the page of the missions holds and what it does to the content: the numbers of the missions in the rules (when the missions are on, the day starts, and what part of
 * the worth of its missions a set pays, entered in percent) and the document of the missions (what a unit of every kind of work is worth, the slots of the sets with the
 * templates they draw from, and the chains of main missions), as the state of the form and back into the two documents, in the order of the files. Everything else in them is
 * left as it is.
 *
 * A goal that is a part of a tank is a percent of the tank, and a goal that is a number of times is a count; a template has the one that its objective asks for. What a
 * unit of work is worth is entered in tanks of the best car (the document keeps it in parts per million).
 */
final class MissionsForm
{
    /** A part of a tank is entered as a percent of it and kept as parts per million. */
    private const PERCENT = 10_000;

    /** What the missions of a game that has none start from when they are switched on. */
    private const STARTING = ['enabled' => false, 'timezone' => 'Europe/Warsaw', 'reset_hour' => 4, 'unlock_after_step' => 'travel', 'periods' => [
        'daily' => ['bonus_coins' => 2, 'cash_percent' => 10, 'xp_percent' => 6],
        'weekly' => ['bonus_coins' => 8, 'cash_percent' => 10, 'xp_percent' => 6],
        'monthly' => ['bonus_coins' => 25, 'cash_percent' => 10, 'xp_percent' => 6],
    ], 'main' => ['cash_percent' => 15, 'xp_percent' => 9]];

    /** What a tank of work is worth in the parts per million that the document keeps. */
    private const TANK = 1_000_000;

    /**
     * @param  array<string, mixed>  $missions  the document of the missions
     * @param  array<string, mixed>  $rules  the rules, which have the numbers of the missions
     * @return array<string, mixed>
     */
    public static function toState(array $missions, array $rules): array
    {
        $section = $rules['missions'] ?? null;
        $periods = [];

        foreach (MissionCatalog::PERIODS as $period) {
            $budget = $section['periods'][$period] ?? null;
            $periods[$period] = $budget === null ? self::STARTING['periods'][$period] : [
                'bonus_coins' => $budget['bonus_coins'] ?? null,
                'cash_percent' => self::percent($budget['cash_ppm'] ?? null),
                'xp_percent' => self::percent($budget['xp_ppm'] ?? null),
            ];
        }

        $sets = [];

        foreach (MissionCatalog::PERIODS as $period) {
            $sets[$period] = array_map(self::slotState(...), array_values((array) ($missions['periodic'][$period] ?? [])));
        }

        return [
            'settings' => $section === null ? self::STARTING : [
                'enabled' => (bool) ($section['enabled'] ?? false),
                'timezone' => $section['timezone'] ?? null,
                'reset_hour' => $section['reset_hour'] ?? null,
                'unlock_after_step' => $section['unlock_after_step'] ?? null,
                'periods' => $periods,
                'main' => ['cash_percent' => self::percent($section['main']['cash_ppm'] ?? null), 'xp_percent' => self::percent($section['main']['xp_ppm'] ?? null)],
            ],
            'effort' => self::effortState((array) ($missions['effort'] ?? [])),
            'sets' => $sets,
            'chains' => array_map(self::chainState(...), array_values((array) ($missions['chains'] ?? []))),
        ];
    }

    /**
     * The two documents with what the form holds: the missions and the rules.
     *
     * @param  array<string, mixed>  $missions
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $state
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function apply(array $missions, array $rules, array $state): array
    {
        $base = self::toState($missions, $rules);

        // The boxes of a section that is switched off are hidden and a hidden box is not part of what the form gives back: they keep what they had.
        $settings = (array) ($state['settings'] ?? []);
        $settings['periods'] = array_replace_recursive($base['settings']['periods'], (array) ($settings['periods'] ?? []));
        $settings['main'] = array_replace($base['settings']['main'], (array) ($settings['main'] ?? []));
        $settings += $base['settings'];

        if (isset($rules['missions']) || ! empty($settings['enabled'])) {
            $existing = (array) ($rules['missions'] ?? []);
            $periods = [];

            foreach (MissionCatalog::PERIODS as $period) {
                $budget = (array) $settings['periods'][$period];
                $periods[$period] = [
                    'bonus_coins' => DefinitionForm::number($budget['bonus_coins'] ?? null),
                    'cash_ppm' => self::ppm($budget['cash_percent'] ?? null),
                    'xp_ppm' => self::ppm($budget['xp_percent'] ?? null),
                ];
            }

            $section = [
                'enabled' => ! empty($settings['enabled']),
                'timezone' => DefinitionForm::text($settings['timezone'] ?? null),
                'reset_hour' => DefinitionForm::number($settings['reset_hour'] ?? null),
                'unlock_after_step' => DefinitionForm::text($settings['unlock_after_step'] ?? null),
                'periods' => $periods,
                'main' => ['cash_ppm' => self::ppm($settings['main']['cash_percent'] ?? null), 'xp_ppm' => self::ppm($settings['main']['xp_percent'] ?? null)],
            ];

            $rules['missions'] = $section + array_diff_key($existing, $section);
        }

        $periodic = [];

        foreach (MissionCatalog::PERIODS as $period) {
            $slots = [];

            foreach ((array) ($state['sets'][$period] ?? []) as $line) {
                $slot = self::slotDocument((array) $line);

                if ($slot !== null) {
                    $slots[] = $slot;
                }
            }

            $periodic[$period] = $slots;
        }

        $chains = [];

        foreach ((array) ($state['chains'] ?? []) as $line) {
            $chain = self::chainDocument((array) $line);

            if ($chain !== null) {
                $chains[] = $chain;
            }
        }

        // A game that never had the document is given one by its first draft.
        $missions += ['schema' => 0, 'version' => now()->format('Y.n').'.1'];
        $missions['effort'] = self::effortDocument((array) ($state['effort'] ?? []));
        $missions['periodic'] = $periodic;

        if ($chains !== [] || isset($missions['chains'])) {
            $missions['chains'] = $chains;
        }

        return [$missions, $rules];
    }

    /**
     * What a unit of every kind of work is worth, as the lines of a form: the goal (laps, or rarity_owned:epic for one that is told by its parameter) and the tanks.
     *
     * @param  array<string, mixed>  $effort
     * @return list<array<string, mixed>>
     */
    private static function effortState(array $effort): array
    {
        $lines = [];

        foreach ($effort as $goal => $ppm) {
            $lines[] = ['goal' => $goal, 'tanks' => is_numeric($ppm) ? $ppm / self::TANK : $ppm];
        }

        return $lines;
    }

    /**
     * The lines of a form as what the document keeps: the worth in parts per million, a line with no goal left out.
     *
     * @param  array<int|string, mixed>  $lines
     * @return array<string, mixed>
     */
    private static function effortDocument(array $lines): array
    {
        $effort = [];

        foreach ($lines as $line) {
            $goal = DefinitionForm::text($line['goal'] ?? null);

            if ($goal !== null) {
                $tanks = DefinitionForm::number($line['tanks'] ?? null);
                $effort[$goal] = is_int($tanks) || is_float($tanks) ? (int) round($tanks * self::TANK) : $tanks;
            }
        }

        return $effort;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private static function slotState(array $slot): array
    {
        $templates = [];

        foreach ((array) ($slot['templates'] ?? []) as $key => $template) {
            $templates[] = [
                'key' => $key,
                'objective' => $template['objective'] ?? null,
                'tanks_percent' => self::percent($template['tanks_ppm'] ?? null),
                'count' => $template['count'] ?? null,
                'min_level' => $template['min_player_level'] ?? null,
                'max_level' => $template['max_player_level'] ?? null,
                'car_xp_laps' => $template['car_xp_laps'] ?? null,
                'items' => self::itemsState((array) ($template['items'] ?? [])),
                'weight' => $template['weight'] ?? null,
                'share' => $template['reward_share'] ?? null,
            ];
        }

        return ['key' => $slot['key'] ?? null, 'templates' => $templates];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    private static function slotDocument(array $line): ?array
    {
        $key = DefinitionForm::text($line['key'] ?? null);

        if ($key === null) {
            return null;
        }

        $templates = [];

        foreach ((array) ($line['templates'] ?? []) as $template) {
            $name = DefinitionForm::text($template['key'] ?? null);

            if ($name === null) {
                continue;
            }

            $objective = DefinitionForm::text($template['objective'] ?? null);
            $document = ['objective' => $objective];

            // What the goal is made of depends on what it counts: a part of a tank, or a number of times.
            if (in_array($objective, MissionCatalog::TANK_OBJECTIVES, true)) {
                $document['tanks_ppm'] = self::ppm($template['tanks_percent'] ?? null);
            } else {
                $document['count'] = DefinitionForm::number($template['count'] ?? null);
            }

            foreach (['min_player_level' => 'min_level', 'max_player_level' => 'max_level', 'car_xp_laps' => 'car_xp_laps'] as $field => $box) {
                $number = DefinitionForm::number($template[$box] ?? null);

                if ($number !== null) {
                    $document[$field] = $number;
                }
            }

            $items = self::itemsDocument((array) ($template['items'] ?? []));

            if ($items !== []) {
                $document['items'] = $items;
            }

            $document['weight'] = DefinitionForm::number($template['weight'] ?? null);
            $document['reward_share'] = DefinitionForm::number($template['share'] ?? null);
            $templates[$name] = $document;
        }

        return ['key' => $key, 'templates' => $templates];
    }

    /**
     * @param  array<string, mixed>  $chain
     * @return array<string, mixed>
     */
    private static function chainState(array $chain): array
    {
        $missions = [];

        foreach ((array) ($chain['missions'] ?? []) as $mission) {
            $reward = (array) ($mission['reward'] ?? []);
            $missions[] = [
                'key' => $mission['key'] ?? null,
                'title' => DefinitionForm::texts((array) ($mission['title'] ?? [])),
                'text' => DefinitionForm::texts((array) ($mission['text'] ?? [])),
                'objective' => $mission['objective'] ?? null,
                'count' => $mission['count'] ?? null,
                'param' => $mission['param'] ?? null,
                'min_level' => $mission['min_player_level'] ?? null,
                'car_xp_laps' => $reward['car_xp_laps'] ?? null,
                'items' => self::itemsState((array) ($reward['items'] ?? [])),
            ];
        }

        return ['key' => $chain['key'] ?? null, 'title' => DefinitionForm::texts((array) ($chain['title'] ?? [])), 'missions' => $missions];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    private static function chainDocument(array $line): ?array
    {
        $key = DefinitionForm::text($line['key'] ?? null);

        if ($key === null) {
            return null;
        }

        $missions = [];

        foreach ((array) ($line['missions'] ?? []) as $mission) {
            $name = DefinitionForm::text($mission['key'] ?? null);

            if ($name === null) {
                continue;
            }

            $document = [
                'key' => $name,
                'title' => DefinitionForm::i18n((array) ($mission['title'] ?? [])),
                'text' => DefinitionForm::i18n((array) ($mission['text'] ?? [])),
                'objective' => DefinitionForm::text($mission['objective'] ?? null),
                'count' => DefinitionForm::number($mission['count'] ?? null),
            ];

            // A parameter is only for the goals that take one (the rarity, the kind of tires); the check of the pack says what it may be.
            $param = DefinitionForm::text($mission['param'] ?? null);

            if ($param !== null && in_array($document['objective'], ['rarity_owned', 'tires_mounted'], true)) {
                $document['param'] = $param;
            }

            $level = DefinitionForm::number($mission['min_level'] ?? null);

            if ($level !== null) {
                $document['min_player_level'] = $level;
            }

            // The cash and the XP of a main mission are a part of its worth, which the rules say; what it gives besides is the experience of the car and the items.
            $reward = [];
            $carXp = DefinitionForm::number($mission['car_xp_laps'] ?? null);

            if ($carXp !== null) {
                $reward['car_xp_laps'] = $carXp;
            }

            $items = self::itemsDocument((array) ($mission['items'] ?? []));

            if ($items !== []) {
                $reward['items'] = $items;
            }

            // A mission that gives nothing besides its part of the worth has an empty object, which the schema asks for and which an empty array is not.
            $document['reward'] = $reward === [] ? new stdClass : $reward;
            $missions[] = $document;
        }

        return ['key' => $key, 'title' => DefinitionForm::i18n((array) ($line['title'] ?? [])), 'missions' => $missions];
    }

    /**
     * The items a mission gives as the lines of a form.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function itemsState(array $items): array
    {
        return array_map(static fn (array $item): array => ['type' => $item['type'] ?? null, 'kind' => $item['kind'] ?? null, 'rarity' => $item['rarity'] ?? null], array_values($items));
    }

    /**
     * The lines of a form as the items of a document: a crate has its kind and a part its rarity, and a line that says nothing is left out.
     *
     * @param  array<int|string, mixed>  $lines
     * @return list<array<string, mixed>>
     */
    private static function itemsDocument(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $type = DefinitionForm::text($line['type'] ?? null);

            if ($type === 'crate') {
                $items[] = ['type' => 'crate', 'kind' => DefinitionForm::text($line['kind'] ?? null)];
            } elseif ($type === 'part') {
                $items[] = ['type' => 'part', 'rarity' => DefinitionForm::text($line['rarity'] ?? null)];
            }
        }

        return $items;
    }

    /** A part of a tank of the document as the percent of the form. */
    private static function percent(mixed $ppm): mixed
    {
        return is_numeric($ppm) ? $ppm / self::PERCENT : $ppm;
    }

    /** The percent of the form as the part of a tank of the document (a box that holds no number is left for the schema to say). */
    private static function ppm(mixed $percent): mixed
    {
        $number = DefinitionForm::number($percent);

        return is_int($number) || is_float($number) ? (int) round($number * self::PERCENT) : $number;
    }
}
