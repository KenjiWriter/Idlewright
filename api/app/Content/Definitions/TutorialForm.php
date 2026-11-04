<?php

declare(strict_types=1);

namespace App\Content\Definitions;

use App\Content\TutorialSteps;
use App\Content\Vocabulary\MessageCatalog;
use stdClass;

/**
 * What the page of the tutorial holds and what it does to the document: the version, the arrangements of the first run, the device the mentor writes on, the words
 * of the tutorial, the story before the choice of a wreck, and the steps, each with its messages in the languages of the game and its reward as fields (the cash in
 * dollars). A step that gives nothing has the empty object that the file has, not an empty list. What the page does not show of the document is kept as it is.
 */
final class TutorialForm
{
    /** The parts of a device that may be left out, each drawn unless the document says false. */
    public const PARTS = ['crack', 'status_bar', 'input', 'who'];

    /** The devices the mentor can write on. */
    public const DEVICES = ['phone', 'letter', 'tablet', 'plain', 'picture'];

    /** How the camera can move over the picture of the story. */
    public const CAMERAS = ['zoom-in', 'zoom-out', 'pan-left', 'pan-right', 'still'];

    /** The story of the app (the document has none), one of the game's own, or none at all. */
    public const STORY_BUILTIN = 'builtin';

    public const STORY_OWN = 'own';

    public const STORY_NONE = 'none';

    public const STORY_MODES = [self::STORY_BUILTIN, self::STORY_OWN, self::STORY_NONE];

    /**
     * The words of the app that the tutorial can say in its own way, by the name the document gives them (the key of the message without tutorial. or story. in
     * front): every message of the tutorial, and those of the screen of the story but the scenes and the first message, which a story of the game's own has as
     * fields of its own. Empty without the texts of the web app to ask.
     *
     * @return array{messenger: list<string>, story: list<string>}
     */
    public static function labelNames(?MessageCatalog $catalog = null): array
    {
        $catalog ??= MessageCatalog::fromConfig();
        $names = ['messenger' => [], 'story' => []];

        if (! $catalog->available()) {
            return $names;
        }

        foreach (array_keys($catalog->all()) as $key) {
            if (str_starts_with($key, 'tutorial.')) {
                $names['messenger'][] = substr($key, strlen('tutorial.'));
            } elseif (str_starts_with($key, 'story.') && preg_match('/^story\.(scene[0-9]+|sms)$/', $key) !== 1) {
                $names['story'][] = substr($key, strlen('story.'));
            }
        }

        return $names;
    }

    /** The prefix of the messages of the app that the labels of a section of the document stand for. */
    public static function labelPrefix(string $section): string
    {
        return $section === 'story' ? 'story' : 'tutorial';
    }

    /**
     * @param  array<string, mixed>  $tutorial
     * @return array<string, mixed>
     */
    public static function toState(array $tutorial): array
    {
        $steps = [];

        foreach ($tutorial['steps'] ?? [] as $step) {
            $reward = (array) ($step['reward'] ?? []);

            $steps[] = [
                'key' => $step['key'] ?? null,
                'phase' => $step['phase'] ?? null,
                'trigger' => self::conditionsText($step['trigger'] ?? null),
                'completion' => self::conditionsText($step['completion'] ?? null),
                'target' => $step['target'] ?? null,
                'goal' => DefinitionForm::texts($step['goal'] ?? []),
                'waiting' => DefinitionForm::texts($step['waiting'] ?? []),
                'optional' => (bool) ($step['optional'] ?? false),
                'arranges' => $step['arranges'] ?? [],
                'gift_part' => $step['gift']['part'] ?? null,
                'gift_rarity' => $step['gift']['rarity'] ?? null,
                'gift_count' => $step['gift']['count'] ?? null,
                ...self::senderState('sender', $step['sender'] ?? null),
                'sms' => array_map(fn (array $text): array => DefinitionForm::texts($text), $step['sms'] ?? []),
                'reply' => DefinitionForm::texts($step['reply'] ?? []),
                'decline' => DefinitionForm::texts($step['decline'] ?? []),
                'reward_cash' => isset($reward['cash_cents']) ? $reward['cash_cents'] / 100 : null,
                'reward_car_xp' => $reward['car_xp'] ?? null,
                'reward_xp' => $reward['xp'] ?? null,
                'reward_part' => $reward['part'] ?? null,
                'reward_garage' => $reward['garage'] ?? null,
            ];
        }

        return [
            'version' => $tutorial['version'] ?? null,
            'first_run_fuel_ml' => $tutorial['script']['first_run_fuel_ml'] ?? null,
            'breakdown_lap' => $tutorial['script']['breakdown_lap'] ?? null,
            'first_mount_ms' => $tutorial['script']['first_mount_ms'] ?? null,
            'covers_up_to' => isset($tutorial['script']['covers_up_to_cents']) ? $tutorial['script']['covers_up_to_cents'] / 100 : null,
            'listing_vehicle' => $tutorial['script']['exchange_listing']['vehicle'] ?? null,
            'listing_age_years' => $tutorial['script']['exchange_listing']['age_years'] ?? null,
            'listing_mileage_km' => $tutorial['script']['exchange_listing']['mileage_km'] ?? null,
            'listing_condition' => isset($tutorial['script']['exchange_listing']['condition_ppm']) ? $tutorial['script']['exchange_listing']['condition_ppm'] / 10_000 : null,
            'listing_price' => isset($tutorial['script']['exchange_listing']['price_cents']) ? $tutorial['script']['exchange_listing']['price_cents'] / 100 : null,
            ...self::messengerState(is_array($tutorial['messenger'] ?? null) ? $tutorial['messenger'] : []),
            ...self::storyState($tutorial['story'] ?? null),
            'steps' => $steps,
        ];
    }

    /** A trigger or a completion as a box holds it: the facts, one after another, with a plus between them. */
    private static function conditionsText(mixed $condition): ?string
    {
        return $condition === null ? null : implode(' + ', (array) $condition);
    }

    /** The fact, or the facts that must all hold, that a box holds: one fact is a text, more are a list. */
    private static function conditions(mixed $text): string|array|null
    {
        $facts = array_values(array_filter(array_map('trim', explode('+', (string) DefinitionForm::text($text))), fn (string $fact): bool => $fact !== ''));

        return match (count($facts)) {
            0 => null,
            1 => $facts[0],
            default => $facts,
        };
    }

    /**
     * The device as the form shows it: what it is, which of its parts are drawn, who writes when a step names nobody, its icon, and for a device of the game's own
     * the frame and where the screen is in it; and the words of the tutorial, an empty box in every language for each word the game does not say in its own way.
     *
     * @param  array<string, mixed>  $messenger
     * @return array<string, mixed>
     */
    private static function messengerState(array $messenger): array
    {
        $state = ['device' => $messenger['device'] ?? TutorialSteps::DEFAULT_DEVICE];

        foreach (self::PARTS as $part) {
            $state["part_{$part}"] = (bool) ($messenger['parts'][$part] ?? true);
        }

        return [
            ...$state,
            ...self::senderState('messenger_sender', $messenger['sender'] ?? null),
            'icon' => $messenger['icon'] ?? null,
            'frame' => $messenger['picture']['frame'] ?? null,
            'screen_top' => $messenger['picture']['screen']['top'] ?? null,
            'screen_right' => $messenger['picture']['screen']['right'] ?? null,
            'screen_bottom' => $messenger['picture']['screen']['bottom'] ?? null,
            'screen_left' => $messenger['picture']['screen']['left'] ?? null,
            'labels' => self::labelsState('messenger', (array) ($messenger['labels'] ?? [])),
        ];
    }

    /**
     * The story as the form shows it: whether the game tells the story of the app, one of its own or none, its picture, its scenes (the time in seconds), who sends
     * the first message, the message, and the words of the screen of the story.
     *
     * @return array<string, mixed>
     */
    private static function storyState(mixed $story): array
    {
        $story = is_array($story) ? $story : null;

        return [
            'story_mode' => match (true) {
                $story === null => self::STORY_BUILTIN,
                (bool) ($story['enabled'] ?? false) => self::STORY_OWN,
                default => self::STORY_NONE,
            },
            'story_picture' => $story['picture'] ?? null,
            'story_scenes' => array_map(static fn (array $scene): array => [
                'text' => DefinitionForm::texts((array) ($scene['text'] ?? [])),
                'seconds' => isset($scene['ms']) ? $scene['ms'] / 1000 : null,
                'camera' => $scene['camera'] ?? null,
                'focus_x' => $scene['focus']['x'] ?? null,
                'focus_y' => $scene['focus']['y'] ?? null,
            ], array_values((array) ($story['scenes'] ?? []))),
            ...self::senderState('story_sender', $story['sender'] ?? null),
            'story_sms' => DefinitionForm::texts((array) ($story['sms'] ?? [])),
            'story_labels' => self::labelsState('story', (array) ($story['labels'] ?? [])),
        ];
    }

    /**
     * A sender as two boxes: the text that is the same in every language (a number), and the texts by language (a name); only one of them is filled.
     *
     * @return array<string, mixed>
     */
    private static function senderState(string $field, mixed $sender): array
    {
        return [
            $field => is_string($sender) ? $sender : null,
            "{$field}_texts" => DefinitionForm::texts(is_array($sender) ? $sender : []),
        ];
    }

    /**
     * A box in every language for each word of the app that the section can say in its own way, and for each word the document says that the app no longer has (so
     * that it can be cleared).
     *
     * @param  array<string, mixed>  $labels
     * @return array<string, array<string, string|null>>
     */
    private static function labelsState(string $section, array $labels): array
    {
        $state = [];

        foreach ([...self::labelNames()[$section], ...array_map('strval', array_keys($labels))] as $name) {
            $state[$name] = DefinitionForm::texts((array) ($labels[$name] ?? []));
        }

        return $state;
    }

    /**
     * The tutorial with the version, the script, the messenger, the story and the steps of a form.
     *
     * @param  array<string, mixed>  $tutorial
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function apply(array $tutorial, array $state): array
    {
        $steps = [];

        foreach ((array) ($state['steps'] ?? []) as $line) {
            $step = [
                'key' => DefinitionForm::text($line['key'] ?? null),
                'phase' => DefinitionForm::number($line['phase'] ?? null),
                'trigger' => self::conditions($line['trigger'] ?? null),
                'completion' => self::conditions($line['completion'] ?? null),
                'target' => DefinitionForm::text($line['target'] ?? null),
                'goal' => DefinitionForm::i18n((array) ($line['goal'] ?? [])),
                'sender' => self::sender($line['sender'] ?? null, $line['sender_texts'] ?? []),
                'sms' => array_values(array_filter(array_map(fn (mixed $text): array => DefinitionForm::i18n((array) $text), (array) ($line['sms'] ?? [])), fn (array $text): bool => $text !== [])),
            ];

            // A step that names nobody comes from the sender of the messenger.
            if ($step['sender'] === null) {
                unset($step['sender']);
            }

            $reply = DefinitionForm::i18n((array) ($line['reply'] ?? []));

            if ($reply !== []) {
                $step['reply'] = $reply;
            }

            $waiting = DefinitionForm::i18n((array) ($line['waiting'] ?? []));

            if ($waiting !== []) {
                $step['waiting'] = $waiting;
            }

            $decline = DefinitionForm::i18n((array) ($line['decline'] ?? []));

            if ($decline !== []) {
                $step['decline'] = $decline;
            }

            if (! empty($line['optional'])) {
                $step['optional'] = true;
            }

            if (! empty($line['arranges'])) {
                $step['arranges'] = array_values((array) $line['arranges']);
            }

            $giftPart = DefinitionForm::text($line['gift_part'] ?? null);

            if ($giftPart !== null) {
                $step['gift'] = array_filter(['part' => $giftPart, 'rarity' => DefinitionForm::text($line['gift_rarity'] ?? null), 'count' => DefinitionForm::number($line['gift_count'] ?? null)], fn (mixed $value): bool => $value !== null);
            }

            $reward = [];

            foreach ([['cash_cents', DefinitionForm::cents($line['reward_cash'] ?? null)], ['car_xp', DefinitionForm::number($line['reward_car_xp'] ?? null)], ['xp', DefinitionForm::number($line['reward_xp'] ?? null)], ['part', DefinitionForm::text($line['reward_part'] ?? null)], ['garage', DefinitionForm::text($line['reward_garage'] ?? null)]] as [$field, $value]) {
                if ($value !== null) {
                    $reward[$field] = $value;
                }
            }

            $step['reward'] = $reward === [] ? new stdClass : $reward;
            $steps[] = $step;
        }

        $tutorial['version'] = DefinitionForm::text($state['version'] ?? null);
        $tutorial['steps'] = $steps;
        $tutorial['script'] = [
            'first_run_fuel_ml' => DefinitionForm::number($state['first_run_fuel_ml'] ?? null),
            'breakdown_lap' => DefinitionForm::number($state['breakdown_lap'] ?? null),
            'first_mount_ms' => DefinitionForm::number($state['first_mount_ms'] ?? null),
            'covers_up_to_cents' => DefinitionForm::cents($state['covers_up_to'] ?? null),
        ];

        $vehicle = DefinitionForm::text($state['listing_vehicle'] ?? null);

        if ($vehicle !== null) {
            $condition = DefinitionForm::number($state['listing_condition'] ?? null);

            $tutorial['script']['exchange_listing'] = [
                'vehicle' => $vehicle,
                'age_years' => DefinitionForm::number($state['listing_age_years'] ?? null),
                'mileage_km' => DefinitionForm::number($state['listing_mileage_km'] ?? null),
                'condition_ppm' => is_int($condition) || is_float($condition) ? (int) round($condition * 10_000) : $condition,
                'price_cents' => DefinitionForm::cents($state['listing_price'] ?? null),
            ];
        }

        if (array_key_exists('device', $state)) {
            $messenger = self::messenger(is_array($tutorial['messenger'] ?? null) ? $tutorial['messenger'] : [], $state);

            // The old phone with all its parts and the words of the app is what a tutorial without a messenger is: a document that had none does not get one.
            if (isset($tutorial['messenger']) || $messenger !== ['device' => TutorialSteps::DEFAULT_DEVICE]) {
                $tutorial['messenger'] = $messenger;
            }
        }

        if (array_key_exists('story_mode', $state)) {
            $story = self::story(is_array($tutorial['story'] ?? null) ? $tutorial['story'] : [], $state);

            if ($story === null) {
                unset($tutorial['story']);
            } else {
                $tutorial['story'] = $story;
            }
        }

        return $tutorial;
    }

    /**
     * The messenger of a form. A part that is drawn is written only when the document said so already, and the frame of a device of the game's own, which the form
     * shows only for that device, is kept when the form does not show it.
     *
     * @param  array<string, mixed>  $before  the messenger of the document
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function messenger(array $before, array $state): array
    {
        $messenger = ['device' => DefinitionForm::text($state['device'] ?? null) ?? TutorialSteps::DEFAULT_DEVICE];
        $parts = [];

        foreach (self::PARTS as $part) {
            $drawn = (bool) ($state["part_{$part}"] ?? true);

            if (! $drawn || isset($before['parts'][$part])) {
                $parts[$part] = $drawn;
            }
        }

        if ($parts !== []) {
            $messenger['parts'] = $parts;
        }

        $sender = self::sender($state['messenger_sender'] ?? null, $state['messenger_sender_texts'] ?? []);

        if ($sender !== null) {
            $messenger['sender'] = $sender;
        }

        $icon = DefinitionForm::text($state['icon'] ?? null);

        if ($icon !== null) {
            $messenger['icon'] = $icon;
        }

        if (array_key_exists('frame', $state)) {
            $frame = DefinitionForm::text($state['frame']);

            if ($frame !== null) {
                $messenger['picture'] = [
                    'frame' => $frame,
                    'screen' => [
                        'top' => DefinitionForm::number($state['screen_top'] ?? null) ?? 0,
                        'right' => DefinitionForm::number($state['screen_right'] ?? null) ?? 0,
                        'bottom' => DefinitionForm::number($state['screen_bottom'] ?? null) ?? 0,
                        'left' => DefinitionForm::number($state['screen_left'] ?? null) ?? 0,
                    ],
                ];
            }
        } elseif (isset($before['picture'])) {
            $messenger['picture'] = $before['picture'];
        }

        $labels = self::labels((array) ($state['labels'] ?? []));

        if ($labels !== []) {
            $messenger['labels'] = $labels;
        } elseif (! array_key_exists('labels', $state) && isset($before['labels'])) {
            $messenger['labels'] = $before['labels'];
        }

        return $messenger;
    }

    /**
     * The story of a form: none in the document for the story of the app; enabled false, with what the document had kept, for no story; and the story of the
     * game's own as the form says it, with what the form does not show taken from the document.
     *
     * @param  array<string, mixed>  $before  the story of the document
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private static function story(array $before, array $state): ?array
    {
        $mode = (string) ($state['story_mode'] ?? self::STORY_BUILTIN);

        if ($mode === self::STORY_BUILTIN) {
            return null;
        }

        if ($mode === self::STORY_NONE) {
            return ['enabled' => false, ...array_diff_key($before, ['enabled' => true])];
        }

        $story = ['enabled' => true];
        $picture = array_key_exists('story_picture', $state) ? DefinitionForm::text($state['story_picture']) : ($before['picture'] ?? null);

        if ($picture !== null) {
            $story['picture'] = $picture;
        }

        $story['scenes'] = array_key_exists('story_scenes', $state) ? array_map(self::scene(...), array_values((array) $state['story_scenes'])) : ($before['scenes'] ?? []);

        $sender = array_key_exists('story_sender', $state) ? self::sender($state['story_sender'], $state['story_sender_texts'] ?? []) : ($before['sender'] ?? null);

        if ($sender !== null) {
            $story['sender'] = $sender;
        }

        $sms = array_key_exists('story_sms', $state) ? DefinitionForm::i18n((array) $state['story_sms']) : (array) ($before['sms'] ?? []);

        if ($sms !== []) {
            $story['sms'] = $sms;
        }

        $labels = array_key_exists('story_labels', $state) ? self::labels((array) $state['story_labels']) : (array) ($before['labels'] ?? []);

        if ($labels !== []) {
            $story['labels'] = $labels;
        }

        return $story;
    }

    /**
     * A scene of the story from its boxes: the time is written in seconds and kept in milliseconds.
     *
     * @return array<string, mixed>
     */
    private static function scene(mixed $scene): array
    {
        $scene = (array) $scene;
        $seconds = DefinitionForm::number($scene['seconds'] ?? null);

        return [
            'text' => DefinitionForm::i18n((array) ($scene['text'] ?? [])),
            'ms' => is_int($seconds) || is_float($seconds) ? (int) round($seconds * 1000) : $seconds,
            'camera' => DefinitionForm::text($scene['camera'] ?? null),
            'focus' => ['x' => DefinitionForm::number($scene['focus_x'] ?? null), 'y' => DefinitionForm::number($scene['focus_y'] ?? null)],
        ];
    }

    /**
     * A sender from its two boxes: the texts by language when any is written (a name), else the one text of every language (a number), else nobody.
     *
     * @return string|array<string, string>|null
     */
    private static function sender(mixed $plain, mixed $texts): string|array|null
    {
        $languages = DefinitionForm::i18n((array) $texts);

        return $languages !== [] ? $languages : DefinitionForm::text($plain);
    }

    /**
     * The words that a form says in the game's own way: only those with a text in some language.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, array<string, string>>
     */
    private static function labels(array $state): array
    {
        $labels = [];

        foreach ($state as $name => $texts) {
            $words = DefinitionForm::i18n((array) $texts);

            if ($words !== []) {
                $labels[(string) $name] = $words;
            }
        }

        return $labels;
    }
}
