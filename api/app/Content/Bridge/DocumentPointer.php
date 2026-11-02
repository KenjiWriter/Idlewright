<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use InvalidArgumentException;

/**
 * A place in a document of the content, as a JSON pointer (RFC 6901): read and written without losing anything of the document around it. An empty object of the
 * document (`stdClass`) is never entered, so it stays what it was when the document is written back.
 */
final class DocumentPointer
{
    /**
     * The keys of a pointer (`/stats/power_kw` is `['stats', 'power_kw']`).
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the text is not a pointer, or has an empty name in it
     */
    public static function split(string $pointer): array
    {
        if ($pointer === '' || $pointer[0] !== '/') {
            throw new InvalidArgumentException("A pointer starts with a slash: {$pointer}");
        }

        $keys = array_map(
            static fn (string $key): string => str_replace(['~1', '~0'], ['/', '~'], $key),
            explode('/', substr($pointer, 1)),
        );

        foreach ($keys as $key) {
            if ($key === '') {
                throw new InvalidArgumentException("A pointer has no empty names: {$pointer}");
            }
        }

        return $keys;
    }

    /** @param  list<string|int>  $keys */
    public static function join(array $keys): string
    {
        return '/'.implode('/', array_map(static fn (string|int $key): string => str_replace(['~', '/'], ['~0', '~1'], (string) $key), $keys));
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<string|int>  $keys
     */
    public static function has(array $document, array $keys): bool
    {
        $at = $document;

        foreach ($keys as $key) {
            if (! is_array($at) || ! array_key_exists($key, $at)) {
                return false;
            }

            $at = $at[$key];
        }

        return true;
    }

    /**
     * What is at the keys, or null when there is nothing (use `has()` to tell that from a null in the document).
     *
     * @param  array<string, mixed>  $document
     * @param  list<string|int>  $keys
     */
    public static function get(array $document, array $keys): mixed
    {
        $at = $document;

        foreach ($keys as $key) {
            if (! is_array($at) || ! array_key_exists($key, $at)) {
                return null;
            }

            $at = $at[$key];
        }

        return $at;
    }

    /**
     * The document with a value put at the keys; the place has to be there already (a pointer changes what the document has, it does not grow it).
     *
     * @param  array<string, mixed>  $document
     * @param  list<string|int>  $keys
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the place is not in the document
     */
    public static function set(array $document, array $keys, mixed $value): array
    {
        if (! self::has($document, $keys)) {
            throw new InvalidArgumentException('There is nothing at '.self::join($keys).'.');
        }

        $at = &$document;

        foreach ($keys as $key) {
            $at = &$at[$key];
        }

        $at = $value;
        unset($at);

        return $document;
    }

    /**
     * Whether the keys are the ones the pattern names: a `*` in the pattern stands for exactly one name, anything else has to be the same.
     *
     * @param  list<string>  $pattern
     * @param  list<string|int>  $keys
     */
    public static function matches(array $pattern, array $keys): bool
    {
        if (count($pattern) !== count($keys)) {
            return false;
        }

        foreach ($pattern as $position => $name) {
            if ($name !== '*' && $name !== (string) $keys[$position]) {
                return false;
            }
        }

        return true;
    }
}
