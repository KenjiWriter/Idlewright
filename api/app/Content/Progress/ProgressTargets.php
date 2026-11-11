<?php

declare(strict_types=1);

namespace App\Content\Progress;

use InvalidArgumentException;

/**
 * The goals of a plan in numbers (D7 of the design of the AI bridge v2), in a fixed form that the simulators check, so that a person approves numbers and not a
 * description:
 *
 * - `level_at_minute`: level N at minute M, within a tolerance (15% when left out), for a way of playing (a progress plan);
 * - `can_afford`: when level N is reached the driver has the cash for `count` of the things listed (all of them when left out), by their prices in the catalog;
 * - `days_to_car`: player A, B or C of the economy simulator buys a car of the ladder (by its name in the pace gate, such as L3) on day D, within a tolerance;
 * - `gates`: every gate of publication passes.
 */
final class ProgressTargets
{
    public const TYPES = ['level_at_minute', 'can_afford', 'days_to_car', 'gates'];

    /** The tolerance when a target names none, in percent. */
    public const TOLERANCE = 15.0;

    /**
     * The targets checked for what they must have; the first wrong one stops it.
     *
     * @param  list<mixed>  $targets
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    public static function validate(array $targets): array
    {
        if ($targets === [] || count($targets) > 20) {
            throw new InvalidArgumentException('Give from 1 to 20 targets.');
        }

        foreach (array_values($targets) as $index => $target) {
            $where = 'target '.($index + 1);

            if (! is_array($target) || ! in_array($target['type'] ?? null, self::TYPES, true)) {
                throw new InvalidArgumentException("{$where}: the type is one of ".implode(', ', self::TYPES).'.');
            }

            $positive = static fn (mixed $value): bool => is_numeric($value) && $value > 0;

            $missing = match ($target['type']) {
                'level_at_minute' => ! $positive($target['level'] ?? null) || ! $positive($target['minute'] ?? null),
                'can_afford' => ! $positive($target['level'] ?? null) || ! is_array($target['items'] ?? null) || $target['items'] === [],
                'days_to_car' => ! in_array($target['player'] ?? null, ['A', 'B', 'C'], true) || ! is_string($target['car'] ?? null) || ! $positive($target['day'] ?? null),
                'gates' => false,
            };

            if ($missing) {
                throw new InvalidArgumentException("{$where} ({$target['type']}) lacks what it needs: ".self::needs($target['type']));
            }

            if (isset($target['plan']) && ! is_array($target['plan'])) {
                throw new InvalidArgumentException("{$where}: the plan is an object of the options of the progress simulator.");
            }
        }

        return array_values($targets);
    }

    /**
     * A target of the progress simulator against what it played.
     *
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $result  the answer of `ProgressSimulator::simulate`
     * @param  array<string, int>  $prices  the prices of the things the target names, in cents
     * @return array{type: string, target: string, measured: string, ok: bool}
     */
    public static function fromProgress(array $target, array $result, array $prices = []): array
    {
        $level = (int) $target['level'];
        $row = collect($result['levels'])->firstWhere('level', $level);
        $minutes = (int) $result['summary']['minutes'];

        if ($target['type'] === 'level_at_minute') {
            $goal = (float) $target['minute'];
            $tolerance = (float) ($target['tolerance_percent'] ?? self::TOLERANCE);
            $text = "level {$level} at minute {$goal} (±{$tolerance}%)";

            if ($row === null) {
                return ['type' => 'level_at_minute', 'target' => $text, 'measured' => "not reached in {$minutes} minutes", 'ok' => false];
            }

            $measured = (float) $row['minute'];

            return ['type' => 'level_at_minute', 'target' => $text, 'measured' => "minute {$measured}", 'ok' => abs($measured - $goal) <= $goal * $tolerance / 100];
        }

        $items = array_values(array_map('strval', (array) $target['items']));
        $count = min(count($items), max(1, (int) ($target['count'] ?? count($items))));
        $known = array_intersect_key($prices, array_flip($items));
        $text = "at level {$level}: cash for {$count} of ".implode(', ', $items);

        if (count($known) < count($items)) {
            return ['type' => 'can_afford', 'target' => $text, 'measured' => 'unknown: '.implode(', ', array_diff($items, array_keys($known))), 'ok' => false];
        }

        if ($row === null) {
            return ['type' => 'can_afford', 'target' => $text, 'measured' => "level {$level} not reached in {$minutes} minutes", 'ok' => false];
        }

        sort($known);
        $needed = array_sum(array_slice($known, 0, $count)) / 100;
        $cash = (float) $row['cash_usd'];

        return ['type' => 'can_afford', 'target' => $text, 'measured' => "cash {$cash} USD against {$needed} USD", 'ok' => $cash >= $needed];
    }

    /**
     * A target of the economy simulator against its report (`economy:simulate --json`).
     *
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $report
     * @return array{type: string, target: string, measured: string, ok: bool}
     */
    public static function fromEconomy(array $target, array $report): array
    {
        $tolerance = (float) ($target['tolerance_percent'] ?? self::TOLERANCE);
        $goal = (float) $target['day'];
        $text = "player {$target['player']} buys {$target['car']} on day {$goal} (±{$tolerance}%)";
        $row = collect($report['gate']['rows'] ?? [])->first(fn (array $row): bool => $row['player'] === $target['player'] && $row['car'] === $target['car']);

        if ($row === null) {
            return ['type' => 'days_to_car', 'target' => $text, 'measured' => 'the pace gate has no such car for this player', 'ok' => false];
        }

        return ['type' => 'days_to_car', 'target' => $text, 'measured' => 'day '.$row['day'], 'ok' => abs((float) $row['day'] - $goal) <= $goal * $tolerance / 100];
    }

    /**
     * @param  list<array{name: string, passed: bool}>  $gates
     * @return array{type: string, target: string, measured: string, ok: bool}
     */
    public static function fromGates(array $gates): array
    {
        $failed = array_column(array_filter($gates, static fn (array $gate): bool => ! $gate['passed']), 'name');

        return ['type' => 'gates', 'target' => 'every gate of publication passes', 'measured' => $failed === [] ? 'all '.count($gates).' pass' : 'failed: '.implode(', ', $failed), 'ok' => $failed === [] && $gates !== []];
    }

    private static function needs(string $type): string
    {
        return match ($type) {
            'level_at_minute' => 'level and minute (both above 0), tolerance_percent if not 15.',
            'can_afford' => 'level and items (slugs of parts or cars), count if not all of them.',
            'days_to_car' => 'player (A, B or C), car (its name in the pace gate, such as L3) and day.',
            default => 'nothing.',
        };
    }
}
