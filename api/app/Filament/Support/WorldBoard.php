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

    /**
     * What a player would read wrong on the map, each with the cities and the roads it is about:
     * - `edge`: the circle of a city is cut by the edge of the map screen;
     * - `overlap`: the circles of two cities cover each other;
     * - `labels`: the names of two cities cover each other (placed as the game places them);
     * - `through`: a road runs over a city that it does not join, so it looks like a stop;
     * - `crossing`: two roads cross away from a city, so the crossing looks like a city or a junction;
     * - `unreachable` (an error, not only a drawing): no road leads to a city from the city where a new driver starts, which the rules refuse when the draft is saved.
     *
     * @param  array<string, string>  $surfaces  the name of each surface, as the map writes it under a city
     * @return list<array{kind: string, error: bool, cities: list<string>, roads: list<string>, params: array<string, string>}>
     */
    public function warnings(array $surfaces = []): array
    {
        $warnings = [];
        $slugs = array_keys($this->cities);
        $name = fn (string $slug): string => $this->cities[$slug]['name'];

        foreach ($this->cities as $slug => $city) {
            if ($city['x'] < self::RADIUS || $city['x'] > self::WIDTH - self::RADIUS || $city['y'] < self::MIN_Y + self::RADIUS || $city['y'] > self::MAX_Y - self::RADIUS) {
                $warnings[] = self::warning('edge', [$slug], [], ['city' => $name($slug)]);
            }
        }

        $labels = array_map(fn (array $city): array => $this->labelBox($city, $surfaces), $this->cities);

        foreach ($slugs as $index => $a) {
            foreach (array_slice($slugs, $index + 1) as $b) {
                if ($this->length($a, $b) < 2 * self::RADIUS) {
                    $warnings[] = self::warning('overlap', [$a, $b], [], ['a' => $name($a), 'b' => $name($b)]);
                } elseif (self::boxesMeet($labels[$a], $labels[$b])) {
                    $warnings[] = self::warning('labels', [$a, $b], [], ['a' => $name($a), 'b' => $name($b)]);
                }
            }
        }

        foreach ($this->roads as $road) {
            foreach ($this->cities as $slug => $city) {
                if ($slug !== $road['from'] && $slug !== $road['to'] && $this->distanceToRoad($city, $road) < self::RADIUS) {
                    $warnings[] = self::warning('through', [$slug], [$road['key']], ['from' => $name($road['from']), 'to' => $name($road['to']), 'city' => $name($slug)]);
                }
            }
        }

        foreach ($this->roads as $index => $first) {
            foreach (array_slice($this->roads, $index + 1) as $second) {
                if (array_intersect([$first['from'], $first['to']], [$second['from'], $second['to']]) === [] && $this->roadsCross($first, $second)) {
                    $warnings[] = self::warning('crossing', [], [$first['key'], $second['key']], [
                        'a' => $name($first['from']).' – '.$name($first['to']),
                        'b' => $name($second['from']).' – '.$name($second['to']),
                    ]);
                }
            }
        }

        $reached = $this->reachable();

        foreach ($slugs as $slug) {
            if ($this->home !== null && ! in_array($slug, $reached, true)) {
                $warnings[] = self::warning('unreachable', [$slug], [], ['city' => $name($slug), 'home' => $name($this->home)], error: true);
            }
        }

        return $warnings;
    }

    /** The cities that a road leads to from the city where a new driver starts, that one among them. @return list<string> */
    public function reachable(): array
    {
        if ($this->home === null) {
            return [];
        }

        $reached = [$this->home];

        for ($index = 0; $index < count($reached); $index++) {
            foreach ($this->roads as $road) {
                foreach ([[$road['from'], $road['to']], [$road['to'], $road['from']]] as [$near, $far]) {
                    if ($near === $reached[$index] && ! in_array($far, $reached, true)) {
                        $reached[] = $far;
                    }
                }
            }
        }

        return $reached;
    }

    /**
     * Where the name of a city is on the board, in units, as the game places it: under the circle, moved in from the side where it would be cut, and above the circle
     * at the bottom of the map. The width is guessed from the letters (11 pixels for the name, 9 for the line under it, on a board 390 wide).
     *
     * @param  array{name: string, x: int, y: int, level: int|null, surface: string|null}  $city
     * @param  array<string, string>  $surfaces
     * @return array{left: float, top: float, right: float, bottom: float}
     */
    private function labelBox(array $city, array $surfaces): array
    {
        $under = __('admin.world.board_level', ['level' => $city['level'] ?? 1]).($city['surface'] === null ? '' : ' · '.($surfaces[$city['surface']] ?? $city['surface']));
        $width = max(mb_strlen($city['name']) * 6.6, mb_strlen($under) * 5.4);
        $height = 25.0;
        $left = min(max($city['x'] - $width / 2, 2), self::WIDTH - $width - 2);
        $below = $city['y'] + 28;
        $top = $below + $height > self::MAX_Y - 2 ? $city['y'] - 28 - $height : $below;

        return ['left' => $left, 'top' => $top, 'right' => $left + $width, 'bottom' => $top + $height];
    }

    /** @param  array{left: float, top: float, right: float, bottom: float}  $a  @param  array{left: float, top: float, right: float, bottom: float}  $b */
    private static function boxesMeet(array $a, array $b): bool
    {
        return $a['left'] < $b['right'] && $b['left'] < $a['right'] && $a['top'] < $b['bottom'] && $b['top'] < $a['bottom'];
    }

    /**
     * How far the middle of a city is from the line of a road, in units.
     *
     * @param  array{x: int, y: int}  $city
     * @param  array{from: string, to: string}  $road
     */
    private function distanceToRoad(array $city, array $road): float
    {
        $a = $this->cities[$road['from']];
        $b = $this->cities[$road['to']];
        $dx = $b['x'] - $a['x'];
        $dy = $b['y'] - $a['y'];
        $squared = $dx * $dx + $dy * $dy;
        $along = $squared === 0 ? 0.0 : max(0.0, min(1.0, (($city['x'] - $a['x']) * $dx + ($city['y'] - $a['y']) * $dy) / $squared));

        return hypot($city['x'] - ($a['x'] + $along * $dx), $city['y'] - ($a['y'] + $along * $dy));
    }

    /**
     * Whether the lines of two roads that share no city cross each other.
     *
     * @param  array{from: string, to: string}  $first
     * @param  array{from: string, to: string}  $second
     */
    private function roadsCross(array $first, array $second): bool
    {
        [$p, $q, $r, $s] = [$this->cities[$first['from']], $this->cities[$first['to']], $this->cities[$second['from']], $this->cities[$second['to']]];
        $side = static fn (array $a, array $b, array $c): int => ($b['x'] - $a['x']) * ($c['y'] - $a['y']) <=> ($b['y'] - $a['y']) * ($c['x'] - $a['x']);

        return $side($p, $q, $r) * $side($p, $q, $s) < 0 && $side($r, $s, $p) * $side($r, $s, $q) < 0;
    }

    /**
     * @param  list<string>  $cities
     * @param  list<string>  $roads
     * @param  array<string, string>  $params
     * @return array{kind: string, error: bool, cities: list<string>, roads: list<string>, params: array<string, string>}
     */
    private static function warning(string $kind, array $cities, array $roads, array $params, bool $error = false): array
    {
        return ['kind' => $kind, 'error' => $error, 'cities' => $cities, 'roads' => $roads, 'params' => $params];
    }

    /** A hash of what the board draws, which changes whenever the drawing does. */
    public function hash(): string
    {
        return md5(serialize([$this->cities, $this->roads, $this->home, app()->getLocale()]));
    }
}
