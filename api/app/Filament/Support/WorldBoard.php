<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Content\Units\Measures;
use App\Filament\Forms\ContentOptions;

/**
 * The map of the world as the game draws it, made of what the world page holds: the board of the app (390 units wide, and the strip from y 60 to y 360 that the
 * map screen shows), the cities on it with what the game writes under them, the roads between them, and the kilometres that a new road is likely to have.
 *
 * Nothing here is a rule of the game: a car travels the kilometres of the rules, never the length of a line on the board.
 */
final class WorldBoard
{
    public const WIDTH = 390;

    public const TOP = 60;

    public const HEIGHT = 300;

    /** The map screen shows the strip of the board from the 60th to the 360th unit, and the schema lets a city be in it and nowhere else. */
    public const MIN_Y = self::TOP;

    public const MAX_Y = self::TOP + self::HEIGHT;

    /** Half of the circle of a city on the map of a phone (44 pixels on a board 390 wide), with a little room. */
    public const RADIUS = 24;

    /** The kilometres for a unit of the board when no road tells the scale of the map. */
    public const KM_PER_UNIT = 0.2;

    /**
     * @param  array<string, array{key: string, slug: string, name: string, x: int, y: int, level: int|null, surface: string|null}>  $cities  by slug
     * @param  list<array{key: string, from: string, to: string, km: float|null}>  $roads  the kilometres in km, whatever the unit of the game
     */
    private function __construct(
        public readonly array $cities,
        public readonly array $roads,
        public readonly ?string $home,
    ) {}

    /**
     * The board of the state of the world page: the cities that have a slug and a place, the roads between two of them, and the city where a new driver starts (the
     * city of the first track, as the rules have it).
     *
     * @param  array<string, mixed>  $state  the state of the form (`regions`, `roads`, `tracks`, each keyed as the form keys its lines)
     */
    public static function of(array $state, Measures $measures): self
    {
        $levels = [];
        $surfaces = [];
        $first = null;

        foreach ((array) ($state['tracks'] ?? []) as $track) {
            $region = is_string($track['region'] ?? null) ? $track['region'] : null;

            if ($region === null) {
                continue;
            }

            $first ??= $region;
            $surfaces[$region] ??= is_string($track['surface'] ?? null) ? $track['surface'] : null;

            if (is_numeric($track['min_player_level'] ?? null)) {
                $levels[$region] = min($levels[$region] ?? PHP_INT_MAX, (int) $track['min_player_level']);
            }
        }

        $cities = [];

        foreach ((array) ($state['regions'] ?? []) as $key => $region) {
            $slug = is_string($region['slug'] ?? null) ? trim($region['slug']) : '';
            $x = $region['map']['x'] ?? null;
            $y = $region['map']['y'] ?? null;

            if ($slug === '' || isset($cities[$slug]) || ! is_numeric($x) || ! is_numeric($y)) {
                continue;
            }

            $cities[$slug] = [
                'key' => (string) $key,
                'slug' => $slug,
                'name' => ContentOptions::name($region['name'] ?? [], $slug),
                'x' => (int) round((float) $x),
                'y' => (int) round((float) $y),
                'level' => $levels[$slug] ?? null,
                'surface' => $surfaces[$slug] ?? null,
            ];
        }

        $roads = [];

        foreach ((array) ($state['roads'] ?? []) as $key => $road) {
            $from = $road['from'] ?? null;
            $to = $road['to'] ?? null;

            if (! is_string($from) || ! is_string($to) || ! isset($cities[$from], $cities[$to])) {
                continue;
            }

            $roads[] = [
                'key' => (string) $key,
                'from' => $from,
                'to' => $to,
                'km' => is_numeric($road['km'] ?? null) ? $measures->travel->toMetres((float) $road['km']) / 1000 : null,
            ];
        }

        $home = $first !== null && isset($cities[$first]) ? $first : (array_key_first($cities) ?? null);

        return new self($cities, $roads, $home);
    }

    /** A place on the board that the map screen shows: x from 0 to 390, y from 60 to 360, in whole units. @return array{x: int, y: int} */
    public static function clamp(int|float $x, int|float $y): array
    {
        return [
            'x' => (int) round(max(0, min(self::WIDTH, $x))),
            'y' => (int) round(max(self::MIN_Y, min(self::MAX_Y, $y))),
        ];
    }

    /** How long the line between two cities is on the board, in units. */
    public function length(string $from, string $to): float
    {
        $a = $this->cities[$from];
        $b = $this->cities[$to];

        return hypot($b['x'] - $a['x'], $b['y'] - $a['y']);
    }

    /** The road between two cities, either way, or null when there is none. @return array{key: string, from: string, to: string, km: float|null}|null */
    public function road(string $from, string $to): ?array
    {
        foreach ($this->roads as $road) {
            if (($road['from'] === $from && $road['to'] === $to) || ($road['from'] === $to && $road['to'] === $from)) {
                return $road;
            }
        }

        return null;
    }

    /**
     * The kilometres for a unit of the board: the median of what the roads that are drawn have (their kilometres over the length of their line), or 0.2 when no road
     * has both.
     */
    public function kmPerUnit(): float
    {
        $ratios = [];

        foreach ($this->roads as $road) {
            $length = $this->length($road['from'], $road['to']);

            if ($road['km'] !== null && $road['km'] > 0 && $length > 0) {
                $ratios[] = $road['km'] / $length;
            }
        }

        if ($ratios === []) {
            return self::KM_PER_UNIT;
        }

        sort($ratios);
        $middle = intdiv(count($ratios), 2);

        return count($ratios) % 2 === 1 ? $ratios[$middle] : ($ratios[$middle - 1] + $ratios[$middle]) / 2;
    }

    /** The whole kilometres that a new road between two cities is likely to have, by the scale of the map (never less than 1). */
    public function suggestedKm(string $from, string $to): int
    {
        return max(1, (int) round($this->length($from, $to) * $this->kmPerUnit()));
    }

    /** The icon the game gives a city on the map of a new driver: the house where the driver starts, a lock where a level above the first is needed, a flag elsewhere. */
    public function icon(string $slug): string
    {
        return match (true) {
            $slug === $this->home => 'home',
            ($this->cities[$slug]['level'] ?? 1) > 1 => 'lock',
            default => 'flag',
        };
    }

    /** A hash of what the board draws, which changes whenever the drawing does. */
    public function hash(): string
    {
        return md5(serialize([$this->cities, $this->roads, $this->home, app()->getLocale()]));
    }
}
