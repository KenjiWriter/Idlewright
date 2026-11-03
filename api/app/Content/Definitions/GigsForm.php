<?php

declare(strict_types=1);

namespace App\Content\Definitions;

/**
 * What the page of the gigs holds and what it does to the document: the board (when it opens, how long it stays, how many offers a phase of the day has, how rare the good
 * ones are), the pay (the speed, the anchors of the ladder, the bands of the levels), the kinds and the templates that the offers are drawn from, as the state of the form and
 * back into the document, in the order of the file. A part is entered as a percent (the document keeps it in parts per million), a wait in minutes or seconds (the document keeps
 * it in milliseconds) and what an hour pays in dollars (the document keeps cents). Everything else in the document is left as it is.
 */
final class GigsForm
{
    /** The phases of the day, in the order of the day. */
    public const PHASES = ['dawn', 'day', 'dusk', 'night'];

    /** A part is entered as a percent and kept as parts per million. */
    private const PERCENT = 10_000;

    /** What the night run starts from when a game that has none switches it on: the numbers that the code ships. */
    public const NIGHT_STARTING = [
        'enabled' => false,
        'kind' => 'night',
        'title' => ['en' => 'Cargo without an invoice', 'pl' => 'Towar bez faktury', 'fr' => 'Marchandise sans facture', 'de' => 'Ware ohne Rechnung', 'pt-BR' => 'Carga sem nota fiscal'],
        'amount_min' => 10,
        'amount_max' => 60,
        'pay_percent' => 250,
        'min_level' => 9,
        'daily_limit' => 2,
        'control_percent' => 36,
        'modifiers' => [['classes' => ['hatchback', 'sedan', 'van'], 'delta_points' => -14], ['classes' => ['gt', 'muscle', 'super', 'hyper'], 'delta_points' => 10]],
        'escape_percent' => 58,
        'heat_minutes' => 360,
        'heat_points' => 10,
        'escape_pay_percent' => 50,
        'arrest_minutes' => 25,
        'fine_percent' => 30,
        'bail_percent' => 50,
    ];

    private const MINUTE_MS = 60_000;

    private const SECOND_MS = 1_000;

    /**
     * @param  array<string, mixed>  $gigs  the document of the gigs
     * @return array<string, mixed>
     */
    public static function toState(array $gigs): array
    {
        $slots = (array) ($gigs['slots'] ?? []);
        $pay = (array) ($gigs['pay'] ?? []);

        $rarities = [];

        foreach ((array) ($gigs['rarities'] ?? []) as $rarity) {
            $rarities[] = ['key' => $rarity['key'] ?? null, 'weight' => $rarity['weight'] ?? null, 'pay_percent' => self::percent($rarity['pay_ppm'] ?? null)];
        }

        $anchors = [];

        foreach ((array) ($pay['anchors'] ?? []) as $anchor) {
            $anchors[] = ['from_level' => $anchor['from_level'] ?? null, 'dollars' => self::dollars($anchor['cents_per_hour'] ?? null)];
        }

        $bands = [];

        foreach ((array) ($gigs['bands'] ?? []) as $band) {
            $bands[] = [
                'from_level' => $band['from_level'] ?? null,
                'ratio_percent' => self::percent($band['ratio_ppm'] ?? null),
                'max_ref_minutes' => $band['max_ref_minutes'] ?? null,
                'daily_limit' => $band['daily_limit'] ?? null,
            ];
        }

        $kinds = [];

        foreach ((array) ($gigs['kinds'] ?? []) as $key => $kind) {
            $kinds[] = ['key' => (string) $key, 'name' => DefinitionForm::texts((array) ($kind['name'] ?? []))];
        }

        $templates = [];

        foreach ((array) ($gigs['templates'] ?? []) as $key => $template) {
            $templates[] = self::templateState((string) $key, (array) $template);
        }

        return [
            'settings' => [
                'enabled' => (bool) ($gigs['enabled'] ?? false),
                'unlock_after_step' => $gigs['unlock_after_step'] ?? null,
                'timezone' => $gigs['timezone'] ?? null,
                'reset_hour' => $gigs['reset_hour'] ?? null,
                'wave_minutes' => self::divided($gigs['wave_ms'] ?? null, self::MINUTE_MS),
                'cancel_seconds' => self::divided($gigs['cancel_window_ms'] ?? null, self::SECOND_MS),
                'slots' => array_combine(self::PHASES, array_map(static fn (string $phase): mixed => $slots[$phase] ?? null, self::PHASES)),
            ],
            'rarities' => $rarities,
            'speed' => [
                'ref_speed_kmh' => $pay['ref_speed_kmh'] ?? null,
                'speed_cap_kmh' => $pay['speed_cap_kmh'] ?? null,
                'xp_per_hour' => $pay['xp_per_hour'] ?? null,
                'car_xp_per_hour' => $pay['car_xp_per_hour'] ?? null,
            ],
            'anchors' => $anchors,
            'bands' => $bands,
            'kinds' => $kinds,
            'templates' => $templates,
            'night' => self::nightState($gigs['night'] ?? null),
        ];
    }

    /**
     * The document with what the form holds in it.
     *
     * @param  array<string, mixed>  $gigs
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function apply(array $gigs, array $state): array
    {
        $settings = (array) ($state['settings'] ?? []);
        $speed = (array) ($state['speed'] ?? []);

        // A game that never had the document is given one by its first draft.
        $gigs += ['schema' => 0, 'version' => now()->format('Y.n').'.1'];
        $gigs['enabled'] = ! empty($settings['enabled']);
        $gigs['unlock_after_step'] = DefinitionForm::text($settings['unlock_after_step'] ?? null);
        $gigs['timezone'] = DefinitionForm::text($settings['timezone'] ?? null);
        $gigs['reset_hour'] = DefinitionForm::number($settings['reset_hour'] ?? null);
        $gigs['wave_ms'] = self::multiplied($settings['wave_minutes'] ?? null, self::MINUTE_MS);
        $gigs['cancel_window_ms'] = self::multiplied($settings['cancel_seconds'] ?? null, self::SECOND_MS);

        $slots = [];

        foreach (self::PHASES as $phase) {
            $slots[$phase] = DefinitionForm::number($settings['slots'][$phase] ?? null);
        }

        $gigs['slots'] = $slots;

        $rarities = [];

        foreach ((array) ($state['rarities'] ?? []) as $line) {
            $key = DefinitionForm::text($line['key'] ?? null);

            if ($key !== null) {
                $rarities[] = ['key' => $key, 'weight' => DefinitionForm::number($line['weight'] ?? null), 'pay_ppm' => self::ppm($line['pay_percent'] ?? null)];
            }
        }

        $gigs['rarities'] = $rarities;

        $anchors = [];

        foreach ((array) ($state['anchors'] ?? []) as $line) {
            $anchors[] = ['from_level' => DefinitionForm::number($line['from_level'] ?? null), 'cents_per_hour' => DefinitionForm::cents($line['dollars'] ?? null)];
        }

        // What the form does not know about (a key that a later version of the rules adds to the pay) stays where it was.
        $gigs['pay'] = [
            'ref_speed_kmh' => DefinitionForm::number($speed['ref_speed_kmh'] ?? null),
            'speed_cap_kmh' => DefinitionForm::number($speed['speed_cap_kmh'] ?? null),
            'xp_per_hour' => DefinitionForm::number($speed['xp_per_hour'] ?? null),
            'car_xp_per_hour' => DefinitionForm::number($speed['car_xp_per_hour'] ?? null),
            'anchors' => $anchors,
        ] + array_diff_key((array) ($gigs['pay'] ?? []), ['ref_speed_kmh' => 1, 'speed_cap_kmh' => 1, 'xp_per_hour' => 1, 'car_xp_per_hour' => 1, 'anchors' => 1]);

        $bands = [];

        foreach ((array) ($state['bands'] ?? []) as $line) {
            $bands[] = [
                'from_level' => DefinitionForm::number($line['from_level'] ?? null),
                'ratio_ppm' => self::ppm($line['ratio_percent'] ?? null),
                'max_ref_minutes' => DefinitionForm::number($line['max_ref_minutes'] ?? null),
                'daily_limit' => DefinitionForm::number($line['daily_limit'] ?? null),
            ];
        }

        $gigs['bands'] = $bands;

        $kinds = [];

        foreach ((array) ($state['kinds'] ?? []) as $line) {
            $key = DefinitionForm::text($line['key'] ?? null);

            if ($key !== null) {
                $kinds[$key] = ['name' => DefinitionForm::i18n((array) ($line['name'] ?? []))];
            }
        }

        $gigs['kinds'] = $kinds;

        $templates = [];
        $existing = (array) ($gigs['templates'] ?? []);

        foreach ((array) ($state['templates'] ?? []) as $line) {
            $key = DefinitionForm::text($line['key'] ?? null);

            if ($key !== null) {
                $templates[$key] = self::templateDocument((array) $line, (array) ($existing[$key] ?? []));
            }
        }

        $gigs['templates'] = $templates;

        // The boxes of the night run are hidden while it is switched off, and a hidden box is not part of what the form gives back: they keep what they had. A game that has none
        // and is not asked to have one keeps having none.
        $night = (array) ($state['night'] ?? []) + self::nightState($gigs['night'] ?? null);

        if (isset($gigs['night']) || ! empty($night['enabled'])) {
            $gigs['night'] = self::nightDocument($night, (array) ($gigs['night'] ?? []));
        }

        return $gigs;
    }

    /**
     * @param  array<string, mixed>|null  $night  the night run of the document, or null when it has none
     * @return array<string, mixed>
     */
    private static function nightState(?array $night): array
    {
        if ($night === null) {
            return self::NIGHT_STARTING;
        }

        $risk = (array) ($night['risk'] ?? []);
        $arrest = (array) ($night['arrest'] ?? []);
        $modifiers = [];

        foreach ((array) ($risk['modifiers'] ?? []) as $modifier) {
            $modifiers[] = ['classes' => array_values((array) ($modifier['class_in'] ?? [])), 'delta_points' => self::percent($modifier['delta_ppm'] ?? null)];
        }

        return [
            'enabled' => (bool) ($night['enabled'] ?? false),
            'kind' => $night['kind'] ?? null,
            'title' => DefinitionForm::texts((array) ($night['title'] ?? [])),
            'amount_min' => $night['amount']['min'] ?? null,
            'amount_max' => $night['amount']['max'] ?? null,
            'pay_percent' => self::percent($night['pay_ppm'] ?? null),
            'min_level' => $night['min_player_level'] ?? null,
            'daily_limit' => $night['daily_limit'] ?? null,
            'control_percent' => self::percent($risk['control_ppm'] ?? null),
            'modifiers' => $modifiers,
            'escape_percent' => self::percent($risk['escape_ppm'] ?? null),
            'heat_minutes' => self::divided($risk['heat_ms'] ?? null, self::MINUTE_MS),
            'heat_points' => self::percent($risk['heat_ppm'] ?? null),
            'escape_pay_percent' => self::percent($night['escape_pay_ppm'] ?? null),
            'arrest_minutes' => self::divided($arrest['ms'] ?? null, self::MINUTE_MS),
            'fine_percent' => self::percent($arrest['fine_ppm'] ?? null),
            'bail_percent' => self::percent($arrest['bail_ppm'] ?? null),
        ];
    }

    /**
     * The night run of the document with what the form holds in it, in the order of the file; what the form does not know about stays where it was.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private static function nightDocument(array $state, array $existing): array
    {
        $modifiers = [];

        foreach ((array) ($state['modifiers'] ?? []) as $line) {
            $classes = array_values(array_filter(array_map(DefinitionForm::text(...), (array) ($line['classes'] ?? []))));

            if ($classes !== []) {
                $modifiers[] = ['class_in' => $classes, 'delta_ppm' => self::ppm($line['delta_points'] ?? null)];
            }
        }

        $risk = [
            'control_ppm' => self::ppm($state['control_percent'] ?? null),
            'modifiers' => $modifiers,
            'escape_ppm' => self::ppm($state['escape_percent'] ?? null),
            'heat_ms' => self::multiplied($state['heat_minutes'] ?? null, self::MINUTE_MS),
            'heat_ppm' => self::ppm($state['heat_points'] ?? null),
        ];

        $arrest = [
            'ms' => self::multiplied($state['arrest_minutes'] ?? null, self::MINUTE_MS),
            'fine_ppm' => self::ppm($state['fine_percent'] ?? null),
            'bail_ppm' => self::ppm($state['bail_percent'] ?? null),
        ];

        $document = [
            'enabled' => ! empty($state['enabled']),
            'kind' => DefinitionForm::text($state['kind'] ?? null),
            'title' => DefinitionForm::i18n((array) ($state['title'] ?? [])),
            'amount' => ['min' => DefinitionForm::number($state['amount_min'] ?? null), 'max' => DefinitionForm::number($state['amount_max'] ?? null)],
            'pay_ppm' => self::ppm($state['pay_percent'] ?? null),
            'min_player_level' => DefinitionForm::number($state['min_level'] ?? null),
            'daily_limit' => DefinitionForm::number($state['daily_limit'] ?? null),
            'risk' => $risk + array_diff_key((array) ($existing['risk'] ?? []), $risk),
            'escape_pay_ppm' => self::ppm($state['escape_pay_percent'] ?? null),
            'arrest' => $arrest + array_diff_key((array) ($existing['arrest'] ?? []), $arrest),
        ];

        return $document + array_diff_key($existing, $document);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private static function templateState(string $key, array $template): array
    {
        $phasePay = (array) ($template['phase_pay_ppm'] ?? []);
        $phases = (array) ($template['phases'] ?? []);

        return [
            'key' => $key,
            'kind' => $template['kind'] ?? null,
            'title' => DefinitionForm::texts((array) ($template['title'] ?? [])),
            'capacity' => $template['capacity'] ?? null,
            'amount_min' => $template['amount']['min'] ?? null,
            'amount_max' => $template['amount']['max'] ?? null,
            'class_in' => array_values((array) ($template['class_in'] ?? [])),
            'mode' => $template['mode'] ?? null,
            'local_min' => $template['local_km']['min'] ?? null,
            'local_max' => $template['local_km']['max'] ?? null,
            'pay_percent' => self::percent($template['pay_ppm'] ?? null),
            // A phase that pays plain is shown as the plain 100%, and written down only when it is not.
            'phase_pay' => array_combine(self::PHASES, array_map(static fn (string $phase): mixed => self::percent($phasePay[$phase] ?? 1_000_000), self::PHASES)),
            'phases' => array_combine(self::PHASES, array_map(static fn (string $phase): mixed => $phases[$phase] ?? null, self::PHASES)),
            'window_waves' => $template['window_waves'] ?? null,
            'deadline_percent' => self::percent($template['deadline_ppm'] ?? null),
            'min_level' => $template['min_player_level'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $line  the state of the template
     * @param  array<string, mixed>  $existing  the template as the document has it, for the keys the form does not know
     * @return array<string, mixed>
     */
    private static function templateDocument(array $line, array $existing): array
    {
        $document = [
            'kind' => DefinitionForm::text($line['kind'] ?? null),
            'title' => DefinitionForm::i18n((array) ($line['title'] ?? [])),
            'capacity' => DefinitionForm::text($line['capacity'] ?? null),
            'amount' => ['min' => DefinitionForm::number($line['amount_min'] ?? null), 'max' => DefinitionForm::number($line['amount_max'] ?? null)],
        ];

        $classes = array_values(array_filter(array_map(DefinitionForm::text(...), (array) ($line['class_in'] ?? []))));

        if ($classes !== []) {
            $document['class_in'] = $classes;
        }

        $mode = DefinitionForm::text($line['mode'] ?? null);
        $document['mode'] = $mode;

        // The length of a trip in the city is for the trips in the city; a trip between cities is as long as the road.
        if ($mode === 'local') {
            $document['local_km'] = ['min' => DefinitionForm::number($line['local_min'] ?? null), 'max' => DefinitionForm::number($line['local_max'] ?? null)];
        }

        $document['pay_ppm'] = self::ppm($line['pay_percent'] ?? null);

        $phasePay = [];

        foreach (self::PHASES as $phase) {
            $ppm = self::ppm($line['phase_pay'][$phase] ?? null);

            if ($ppm !== null && $ppm !== 1_000_000) {
                $phasePay[$phase] = $ppm;
            }
        }

        if ($phasePay !== []) {
            $document['phase_pay_ppm'] = $phasePay;
        }

        $phases = [];

        foreach (self::PHASES as $phase) {
            $phases[$phase] = DefinitionForm::number($line['phases'][$phase] ?? null);
        }

        $document['phases'] = $phases;
        $document['window_waves'] = DefinitionForm::number($line['window_waves'] ?? null);

        $deadline = self::ppm($line['deadline_percent'] ?? null);

        if ($deadline !== null) {
            $document['deadline_ppm'] = $deadline;
        }

        $document['min_player_level'] = DefinitionForm::number($line['min_level'] ?? null);

        // What the form does not know about (a key that a later version of the rules adds to a template) stays where it was.
        return $document + array_diff_key($existing, $document, ['class_in' => 1, 'local_km' => 1, 'phase_pay_ppm' => 1, 'deadline_ppm' => 1]);
    }

    /** A part of the document as the percent of the form. */
    private static function percent(mixed $ppm): mixed
    {
        return is_numeric($ppm) ? $ppm / self::PERCENT : $ppm;
    }

    /** The percent of the form as the part of the document (a box that holds no number is left for the schema to say, and a box left empty is nothing). */
    private static function ppm(mixed $percent): mixed
    {
        $number = DefinitionForm::number($percent);

        return is_int($number) || is_float($number) ? (int) round($number * self::PERCENT) : $number;
    }

    /** Cents of the document as the dollars of the form. */
    private static function dollars(mixed $cents): mixed
    {
        return is_numeric($cents) ? $cents / 100 : $cents;
    }

    /** A time of the document, in milliseconds, as the minutes or the seconds of the form. */
    private static function divided(mixed $ms, int $unit): mixed
    {
        return is_numeric($ms) ? $ms / $unit : $ms;
    }

    /** The minutes or the seconds of the form as the milliseconds of the document. */
    private static function multiplied(mixed $amount, int $unit): mixed
    {
        $number = DefinitionForm::number($amount);

        return is_int($number) || is_float($number) ? (int) round($number * $unit) : $number;
    }
}
