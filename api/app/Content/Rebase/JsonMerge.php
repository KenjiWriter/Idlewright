<?php

declare(strict_types=1);

namespace App\Content\Rebase;

use stdClass;

/**
 * A three-way merge of one document of the content: what the panel made of it (`panel`) and what the files of a release made of it (`files`), both from the same
 * earlier document (`base`). A place that only one side changed takes that side, a place both changed in the same way takes that, and a place both changed in two
 * ways is a conflict: it takes the side that `prefer` names, and it is listed.
 *
 * Objects are merged key by key, and a list of objects that each carry a name of their own (`key`, `slug` or `id`: the steps of the tutorial) item by item, so
 * that a step changed in the panel and a step added in the files both stay, in the order the side that did not move the items has. Any other list is one value.
 * The values are what `json_decode` gives without arrays (an object is a stdClass), so that `{}` and `[]` stay what they were.
 */
final class JsonMerge
{
    public const PANEL = 'panel';

    public const FILES = 'files';

    /** The fields that name an item of a list of objects, in the order they are tried. */
    private const NAMES = ['key', 'slug', 'id'];

    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /** @var list<array{path: string, base: mixed, panel: mixed, files: mixed}> */
    private array $conflicts = [];

    public function __construct(private readonly string $prefer = self::PANEL) {}

    /** A document as the merge takes it: its text decoded, or Absent when there is none. */
    public static function decode(?string $text): mixed
    {
        return $text === null ? Absent::Value : json_decode($text, false, 512, JSON_THROW_ON_ERROR);
    }

    /** A merged document as text, written the way the panel writes a draft. */
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | self::FLAGS);
    }

    /** Whether two values are the same JSON: the order of the keys of an object and the way a whole number is written do not count, the order of a list does. */
    public static function same(mixed $one, mixed $other): bool
    {
        if ($one === Absent::Value || $other === Absent::Value) {
            return $one === $other;
        }

        return self::canonical($one) === self::canonical($other);
    }

    /**
     * The places both sides changed in two ways, by their pointer (an item of a named list by its name), with the three values; Absent is a side that has nothing there.
     *
     * @return list<array{path: string, base: mixed, panel: mixed, files: mixed}>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    /** The merged value, or Absent when the merge leaves the place out (one side took it out and the other did not change it). */
    public function merge(mixed $base, mixed $panel, mixed $files, string $path = ''): mixed
    {
        if (self::same($panel, $files)) {
            return $panel;
        }

        if (self::same($panel, $base)) {
            return $files;
        }

        if (self::same($files, $base)) {
            return $panel;
        }

        if ($panel instanceof stdClass && $files instanceof stdClass && ($base instanceof stdClass || $base === Absent::Value)) {
            return $this->objects($base instanceof stdClass ? $base : new stdClass, $panel, $files, $path);
        }

        if (is_array($panel) && is_array($files) && (is_array($base) || $base === Absent::Value)) {
            $lists = [$panel, $files, ...(is_array($base) ? [$base] : [])];
            $name = self::nameOf($lists);

            if ($name !== null) {
                return $this->lists($name, is_array($base) ? $base : [], $panel, $files, $path);
            }
        }

        $this->conflicts[] = ['path' => $path === '' ? '/' : $path, 'base' => $base, 'panel' => $panel, 'files' => $files];

        return $this->prefer === self::FILES ? $files : $panel;
    }

    private function objects(stdClass $base, stdClass $panel, stdClass $files, string $path): stdClass
    {
        $before = get_object_vars($base);
        $mine = get_object_vars($panel);
        $theirs = get_object_vars($files);
        $merged = new stdClass;

        foreach (self::arrange(self::names(array_keys($theirs)), self::names(array_keys($mine)), self::names(array_keys($before))) as $key) {
            $value = $this->merge(self::at($before, $key), self::at($mine, $key), self::at($theirs, $key), "{$path}/{$key}");

            if ($value !== Absent::Value) {
                $merged->{$key} = $value;
            }
        }

        return $merged;
    }

    /**
     * @param  list<mixed>  $base
     * @param  list<mixed>  $panel
     * @param  list<mixed>  $files
     * @return list<mixed>
     */
    private function lists(string $name, array $base, array $panel, array $files, string $path): array
    {
        $before = self::byName($base, $name);
        $mine = self::byName($panel, $name);
        $theirs = self::byName($files, $name);
        $merged = [];

        foreach ([...array_keys($theirs), ...array_keys($mine), ...array_keys($before)] as $id) {
            $merged[(string) $id] ??= $this->merge(self::at($before, (string) $id), self::at($mine, (string) $id), self::at($theirs, (string) $id), "{$path}/{$id}");
        }

        // The order is the one of the side that kept the order of the items it shares with the base; the other side's new items go in after the item they followed.
        $kept = self::names(array_keys($before));
        $filesMoved = array_values(array_intersect(self::names(array_keys($theirs)), $kept)) !== array_values(array_intersect($kept, self::names(array_keys($theirs))));
        $order = $filesMoved
            ? self::arrange(self::names(array_keys($theirs)), self::names(array_keys($mine)), $kept)
            : self::arrange(self::names(array_keys($mine)), self::names(array_keys($theirs)), $kept);

        $items = [];

        foreach ($order as $id) {
            if ($merged[$id] !== Absent::Value) {
                $items[] = $merged[$id];
            }
        }

        return $items;
    }

    /**
     * The names of the first side in their order, then those of the second that the first lacks, each after the name it follows on the second side (at the start
     * when it follows none of them), then those only the base has (which the merge may still keep, when a side took them out and the other changed them).
     *
     * @param  list<string>  $first
     * @param  list<string>  $second
     * @param  list<string>  $base
     * @return list<string>
     */
    private static function arrange(array $first, array $second, array $base): array
    {
        $order = $first;
        $start = 0;

        foreach ($second as $index => $name) {
            if (in_array($name, $order, true)) {
                continue;
            }

            $at = null;

            for ($back = $index - 1; $back >= 0 && $at === null; $back--) {
                $found = array_search($second[$back], $order, true);
                $at = $found === false ? null : $found + 1;
            }

            if ($at === null) {
                $at = $start++;
            }

            array_splice($order, $at, 0, [$name]);
        }

        return [...$order, ...array_values(array_diff($base, $order))];
    }

    /**
     * The field that names every item of these lists (each item an object with it, as a text or a number, once in its list), or null when none does.
     *
     * @param  list<list<mixed>>  $lists
     */
    private static function nameOf(array $lists): ?string
    {
        if (array_sum(array_map('count', $lists)) === 0) {
            return null;
        }

        foreach (self::NAMES as $name) {
            foreach ($lists as $list) {
                $seen = [];

                foreach ($list as $item) {
                    $id = $item instanceof stdClass && property_exists($item, $name) ? $item->{$name} : null;

                    if (! is_string($id) && ! is_int($id) || isset($seen[(string) $id])) {
                        continue 3;
                    }

                    $seen[(string) $id] = true;
                }
            }

            return $name;
        }

        return null;
    }

    /**
     * @param  list<mixed>  $list
     * @return array<string, mixed>
     */
    private static function byName(array $list, string $name): array
    {
        $items = [];

        foreach ($list as $item) {
            $items[(string) $item->{$name}] = $item;
        }

        return $items;
    }

    /** @param  array<int|string, mixed>  $values */
    private static function at(array $values, string $key): mixed
    {
        return array_key_exists($key, $values) ? $values[$key] : Absent::Value;
    }

    /**
     * @param  list<int|string>  $keys
     * @return list<string>
     */
    private static function names(array $keys): array
    {
        return array_map(static fn (int|string $key): string => (string) $key, $keys);
    }

    private static function canonical(mixed $value): string
    {
        if ($value instanceof stdClass) {
            $fields = get_object_vars($value);
            ksort($fields, SORT_STRING);
            $parts = [];

            foreach ($fields as $key => $field) {
                $parts[] = json_encode((string) $key, self::FLAGS).':'.self::canonical($field);
            }

            return '{'.implode(',', $parts).'}';
        }

        if (is_array($value)) {
            return '['.implode(',', array_map(self::canonical(...), $value)).']';
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return json_encode($value, self::FLAGS);
    }
}
