<?php

declare(strict_types=1);

namespace App\Content\Definitions;

use App\Content\ThreadDocument;

/**
 * What the page of the thread holds and what it does to the rules: the thread of one track, as the state of the form (the sections as boxes), and the rules with
 * the thread of that track made out of the state. The sections of a form are lines of boxes; a box that a section does not use (the radius of a straight) is
 * not in the document, and a track with no section has no thread and no lap from it.
 */
final class ThreadForm
{
    /**
     * The state of the form for a track.
     *
     * @param  array<string, mixed>  $rules
     * @return array{track: string|null, lap_from: string, mode: string, return_s: int|float|null, sections: list<array<string, mixed>>}
     */
    public static function toState(array $rules, ?string $slug): array
    {
        $slug = $slug !== null && isset($rules['tracks'][$slug]) ? $slug : array_key_first($rules['tracks'] ?? []);
        $track = $slug === null ? [] : (array) $rules['tracks'][$slug];
        $thread = (array) ($track['thread'] ?? []);

        return [
            'track' => $slug,
            'lap_from' => (string) ($track['lap_from'] ?? 'stats'),
            'mode' => (string) ($thread['mode'] ?? 'circuit'),
            'return_s' => isset($thread['return_ms']) ? $thread['return_ms'] / 1000 : null,
            'sections' => array_map(static fn (mixed $section): array => self::sectionState((array) $section), array_values((array) ($thread['sections'] ?? []))),
        ];
    }

    /**
     * The rules with the thread of the track of the state in them. A track with no section loses its thread (and the lap from it); the length of a track that has a
     * thread is what the sections add up to.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function apply(array $rules, array $state): array
    {
        $slug = DefinitionForm::text($state['track'] ?? null);

        if ($slug === null || ! isset($rules['tracks'][$slug])) {
            return $rules;
        }

        $sections = [];

        foreach (array_values((array) ($state['sections'] ?? [])) as $line) {
            $section = self::sectionDocument((array) $line);

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        $track = (array) $rules['tracks'][$slug];
        unset($track['lap_from'], $track['thread']);
        $thread = null;

        if ($sections !== []) {
            $mode = DefinitionForm::text($state['mode'] ?? null) === 'run' ? 'run' : 'circuit';
            $thread = ['mode' => $mode];
            $return = DefinitionForm::number($state['return_s'] ?? null);

            if ($mode === 'run' && (is_int($return) || is_float($return)) && $return > 0) {
                $thread['return_ms'] = (int) round($return * 1000);
            }

            $thread['sections'] = $sections;
        }

        $rules['tracks'][$slug] = self::withThread($track, $thread, $thread !== null && DefinitionForm::text($state['lap_from'] ?? null) === 'thread');

        return $rules;
    }

    /**
     * A piece of thread that is ready to add.
     *
     * @return list<array<string, mixed>>
     */
    public static function preset(string $name): array
    {
        $corner = static fn (int $radius, int $angle, string $turn = 'right'): array => ['type' => 'corner', 'length_m' => null, 'radius_m' => $radius, 'angle_deg' => $angle, 'turn' => $turn, 'surface' => null, 'limit_kmh' => null, 'rough' => null];

        return match ($name) {
            'hairpin' => [$corner(20, 180)],
            'tight' => [$corner(35, 90)],
            'bend' => [$corner(120, 60)],
            'chicane' => [$corner(80, 40, 'left'), ['type' => 'straight', 'length_m' => 30, 'radius_m' => null, 'angle_deg' => null, 'turn' => null, 'surface' => null, 'limit_kmh' => null, 'rough' => null], $corner(80, 40)],
            default => [],
        };
    }

    /**
     * The sections with every corner turning the other way: the same track in the mirror.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function mirrored(array $sections): array
    {
        return array_map(static fn (array $section): array => ($section['type'] ?? null) === 'corner' ? [...$section, 'turn' => ($section['turn'] ?? 'right') === 'left' ? 'right' : 'left'] : $section, array_values($sections));
    }

    /**
     * The sections driven the other way round: in the opposite order, and the corners turn the other way as the car comes at them from the other side.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function reversed(array $sections): array
    {
        return self::mirrored(array_reverse(array_values($sections)));
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private static function sectionState(array $section): array
    {
        return [
            'type' => $section['type'] ?? 'straight',
            'length_m' => $section['length_m'] ?? null,
            'radius_m' => $section['radius_m'] ?? null,
            'angle_deg' => $section['angle_deg'] ?? null,
            'turn' => $section['turn'] ?? null,
            'surface' => $section['surface'] ?? null,
            'limit_kmh' => $section['limit_kmh'] ?? null,
            'rough' => $section['rough'] ?? null,
        ];
    }

    /**
     * A section of the document out of a line of the form, or null for a line with nothing in it.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    private static function sectionDocument(array $line): ?array
    {
        $type = DefinitionForm::text($line['type'] ?? null);

        if ($type === 'straight') {
            $section = ['type' => 'straight', 'length_m' => DefinitionForm::number($line['length_m'] ?? null)];
        } elseif ($type === 'corner') {
            $section = ['type' => 'corner', 'radius_m' => DefinitionForm::number($line['radius_m'] ?? null), 'angle_deg' => DefinitionForm::number($line['angle_deg'] ?? null)];
            $section['turn'] = DefinitionForm::text($line['turn'] ?? null) === 'left' ? 'left' : 'right';
        } else {
            return null;
        }

        foreach (['surface' => DefinitionForm::text($line['surface'] ?? null), 'limit_kmh' => DefinitionForm::number($line['limit_kmh'] ?? null), 'rough' => DefinitionForm::number($line['rough'] ?? null)] as $key => $value) {
            if ($value !== null && $value !== 0) {
                $section[$key] = $value;
            }
        }

        return array_filter($section, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * The track with the thread (and where its lap comes from) in its place among the keys, which is before `lit`, as the form of the world writes it, and its length the
     * sections add up to.
     *
     * @param  array<string, mixed>  $track
     * @param  array<string, mixed>|null  $thread
     * @return array<string, mixed>
     */
    private static function withThread(array $track, ?array $thread, bool $fromThread): array
    {
        if ($thread === null) {
            return $track;
        }

        $made = [];
        $placed = false;

        foreach ($track as $key => $value) {
            if ($key === 'lit' && ! $placed) {
                $made = [...$made, ...($fromThread ? ['lap_from' => 'thread'] : []), 'thread' => $thread];
                $placed = true;
            }

            $made[$key] = $value;
        }

        if (! $placed) {
            $made = [...$made, ...($fromThread ? ['lap_from' => 'thread'] : []), 'thread' => $thread];
        }

        try {
            $made['length_m'] = ThreadDocument::lengthM($thread, (string) ($track['surface'] ?? 'asphalt'));
        } catch (\InvalidArgumentException) {
            // A thread that cannot be driven keeps the length it had; the validator says what is wrong with it.
        }

        return $made;
    }
}
