<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\TutorialSteps;

/**
 * What the bridge knows of the tutorial that it does not know of the other documents: a step is named by its key and not by its place in the list (the places move when a
 * step is added), some of its fields are a choice among a few values (the device, a part of it that is drawn or not, the move of a camera, what a step points at), and the
 * sender of a message is a plain text that may be the same in every language. The places are written with the key of the step (`/steps/refuel/target`), the way the
 * instruction gives them.
 */
final class TutorialBridge
{
    /** The document, as the scope and the checks name it. */
    public const KEY = 'rules:tutorial';

    /** The places that take one plain text, the same in every language: who the messages come from. */
    public const WORDS = ['/steps/*/sender', '/messenger/sender', '/story/sender'];

    /** The longest plain text, as the schema of the tutorial says of a sender. */
    public const WORD_MAX = 40;

    /** The parts of a device that may be left out. */
    public const PARTS = ['crack', 'status_bar', 'input', 'who'];

    /** @var array<string, mixed>|null */
    private static ?array $schema = null;

    /**
     * The places that are a choice, with the values each may take (from the schema of the tutorial).
     *
     * @return array<string, list<string|bool|null>>
     */
    public static function choicePatterns(): array
    {
        $schema = self::schema();
        $step = $schema['properties']['steps']['items']['properties'] ?? [];
        $choices = ['/messenger/device' => array_values($schema['properties']['messenger']['properties']['device']['enum'] ?? [])];

        foreach (self::PARTS as $part) {
            $choices["/messenger/parts/{$part}"] = [true, false];
        }

        $choices['/story/enabled'] = [true, false];
        $choices['/story/scenes/*/camera'] = array_values($schema['properties']['story']['properties']['scenes']['items']['properties']['camera']['enum'] ?? []);
        $choices['/steps/*/target'] = array_values($step['target']['enum'] ?? []);
        $choices['/steps/*/optional'] = [true, false];

        return $choices;
    }

    /**
     * Every choice of a tutorial, as the instruction lists it: the place (a step by its key), the values it may take, and what it is now (what the app does when the
     * tutorial says nothing: the phone, every part drawn, the story of the app told).
     *
     * @param  array<string, mixed>  $document
     * @return list<array{0: string, 1: list<string|bool|null>, 2: string|bool|null}>
     */
    public static function choices(array $document): array
    {
        $patterns = self::choicePatterns();
        $messenger = is_array($document['messenger'] ?? null) ? $document['messenger'] : [];
        $lines = [['/messenger/device', $patterns['/messenger/device'], (string) ($messenger['device'] ?? TutorialSteps::DEFAULT_DEVICE)]];

        foreach (self::PARTS as $part) {
            $lines[] = ["/messenger/parts/{$part}", [true, false], (bool) ($messenger['parts'][$part] ?? true)];
        }

        $story = is_array($document['story'] ?? null) ? $document['story'] : null;
        $lines[] = ['/story/enabled', [true, false], (bool) ($story['enabled'] ?? true)];

        foreach (array_values((array) ($story['scenes'] ?? [])) as $index => $scene) {
            $lines[] = ["/story/scenes/{$index}/camera", $patterns['/story/scenes/*/camera'], (string) ($scene['camera'] ?? 'still')];
        }

        foreach ($document['steps'] ?? [] as $step) {
            $key = (string) ($step['key'] ?? '');
            $lines[] = ["/steps/{$key}/target", $patterns['/steps/*/target'], $step['target'] ?? null];
            $lines[] = ["/steps/{$key}/optional", [true, false], (bool) ($step['optional'] ?? false)];
        }

        return $lines;
    }

    /**
     * The pattern of choices that a place (a step by its key) is, or null when it is not a choice.
     *
     * @param  list<string>  $keyed
     */
    public static function choiceAt(array $keyed): ?string
    {
        foreach (array_keys(self::choicePatterns()) as $pattern) {
            if (DocumentPointer::matches(DocumentPointer::split($pattern), $keyed)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Whether a place (a step by its key) takes a plain text.
     *
     * @param  list<string>  $keyed
     */
    public static function isWord(array $keyed): bool
    {
        foreach (self::WORDS as $pattern) {
            if (DocumentPointer::matches(DocumentPointer::split($pattern), $keyed)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $document */
    public static function stepIndex(array $document, string $key): ?int
    {
        foreach (array_values((array) ($document['steps'] ?? [])) as $index => $step) {
            if (is_array($step) && ($step['key'] ?? null) === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The keys of a place with the step named by its key turned into its place in the list, which is what the document has. A place that is not in a step, or that has
     * a star for the step, stays as it is.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $keys
     * @return array{0: list<string|int>|null, 1: string|null, 2: string|null} the keys (null when the step is not there), the code of the problem, and the key of the step
     */
    public static function toIndex(array $document, array $keys): array
    {
        if (($keys[0] ?? null) !== 'steps' || ! isset($keys[1]) || $keys[1] === '*') {
            return [$keys, null, null];
        }

        $name = $keys[1];
        $index = self::stepIndex($document, $name);

        if ($index === null) {
            return [null, ctype_digit($name) ? 'step_by_index' : 'unknown_step', $name];
        }

        return [['steps', $index, ...array_slice($keys, 2)], null, $name];
    }

    /**
     * The keys of a place with the place of a step in the list turned into its key: how the instruction and the table of changes write it.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string|int>  $keys
     * @return list<string|int>
     */
    public static function toKeys(array $document, array $keys): array
    {
        if (($keys[0] ?? null) !== 'steps' || ! isset($keys[1]) || ! is_numeric($keys[1])) {
            return $keys;
        }

        $key = $document['steps'][(int) $keys[1]]['key'] ?? null;

        return is_string($key) ? ['steps', $key, ...array_slice($keys, 2)] : $keys;
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return self::$schema ??= json_decode((string) file_get_contents(rtrim((string) config('ride.content_path'), '/\\').'/schema/tutorial.schema.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
