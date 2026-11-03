<?php

declare(strict_types=1);

namespace App\Content\Definitions;

/**
 * What differs between the sections of two threads, said as a person would say it: a section was moved, changed in a number, added or taken away. The plain difference of
 * two documents (`DocumentDiff`) compares a list place by place, so one section moved up the list shows every section between as changed.
 *
 * The sections that stay in their order are found first (the longest run of equal sections); of the rest, a section that is equal to one on the other side was moved, one of
 * the same kind that is not equal was changed (the first of them to the first of them), and what is left was taken away or added.
 */
final class ThreadDiff
{
    public const MOVED = 'moved';

    public const CHANGED = 'changed';

    public const ADDED = 'added';

    public const REMOVED = 'removed';

    /**
     * @param  list<array<string, mixed>>  $old
     * @param  list<array<string, mixed>>  $new
     * @return list<array{op: string, from: int|null, to: int|null, section: array<string, mixed>, fields: array<string, array{0: mixed, 1: mixed}>}> the places are counted from 1
     */
    public static function between(array $old, array $new): array
    {
        $old = array_values($old);
        $new = array_values($new);
        $before = array_map(self::signature(...), $old);
        $after = array_map(self::signature(...), $new);
        $kept = self::kept($before, $after);
        $freeOld = array_values(array_diff(array_keys($old), array_keys($kept)));
        $freeNew = array_values(array_diff(array_keys($new), array_values($kept)));
        $operations = [];

        // A section that is the same, in another place, was moved.
        foreach ($freeOld as $at => $i) {
            foreach ($freeNew as $to => $j) {
                if ($before[$i] === $after[$j]) {
                    $operations[] = ['op' => self::MOVED, 'from' => $i + 1, 'to' => $j + 1, 'section' => $new[$j], 'fields' => []];
                    unset($freeOld[$at], $freeNew[$to]);

                    break;
                }
            }
        }

        // One of the same kind, not the same, was changed.
        foreach ($freeOld as $at => $i) {
            foreach ($freeNew as $to => $j) {
                if (($old[$i]['type'] ?? null) === ($new[$j]['type'] ?? null)) {
                    $operations[] = ['op' => self::CHANGED, 'from' => $i + 1, 'to' => $j + 1, 'section' => $new[$j], 'fields' => self::fields($old[$i], $new[$j])];
                    unset($freeOld[$at], $freeNew[$to]);

                    break;
                }
            }
        }

        foreach ($freeOld as $i) {
            $operations[] = ['op' => self::REMOVED, 'from' => $i + 1, 'to' => null, 'section' => $old[$i], 'fields' => []];
        }

        foreach ($freeNew as $j) {
            $operations[] = ['op' => self::ADDED, 'from' => null, 'to' => $j + 1, 'section' => $new[$j], 'fields' => []];
        }

        usort($operations, static fn (array $a, array $b): int => [$a['to'] ?? $a['from'], $a['from'] ?? 0] <=> [$b['to'] ?? $b['from'], $b['from'] ?? 0]);

        return $operations;
    }

    /**
     * The sections that are in the same order on both sides, as the index in the old list to the index in the new: the longest run of equal sections.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return array<int, int>
     */
    private static function kept(array $before, array $after): array
    {
        $rows = count($before);
        $columns = count($after);
        $length = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $length[$i][$j] = $before[$i] === $after[$j] ? $length[$i + 1][$j + 1] + 1 : max($length[$i + 1][$j], $length[$i][$j + 1]);
            }
        }

        $kept = [];
        [$i, $j] = [0, 0];

        while ($i < $rows && $j < $columns) {
            if ($before[$i] === $after[$j]) {
                $kept[$i] = $j;
                $i++;
                $j++;
            } elseif ($length[$i + 1][$j] >= $length[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $kept;
    }

    /**
     * What the boxes of a section say, as one string, with a number the same whether it is kept as 10 or as 10.0.
     *
     * @param  array<string, mixed>  $section
     */
    private static function signature(array $section): string
    {
        ksort($section);

        return (string) json_encode(array_map(static fn (mixed $value): mixed => is_float($value) && $value === floor($value) ? (int) $value : $value, $section));
    }

    /**
     * The boxes that differ between two sections of one kind, by name: what it was and what it is (null for a box that is not there).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private static function fields(array $old, array $new): array
    {
        $fields = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $name) {
            if ($name !== 'type' && ($old[$name] ?? null) != ($new[$name] ?? null)) {
                $fields[(string) $name] = [$old[$name] ?? null, $new[$name] ?? null];
            }
        }

        return $fields;
    }
}
