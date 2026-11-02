<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\PackValidator;
use InvalidArgumentException;
use stdClass;

/**
 * Which places of a document of the rules are collections: an object that is a set of named entries (the tracks, the products, the bonuses, the templates of the missions),
 * which the schema says by giving the entries one shape (`additionalProperties`) instead of listing them. A new entry may be added to such a place and to no other: an object
 * whose properties the schema lists (a section of the rules) has a fixed set of them.
 */
final class SchemaCollections
{
    /** @var array<string, array{0: array<string, mixed>, 1: array<string, mixed>}> */
    private static array $schemas = [];

    /**
     * Whether the place is a collection in the document of the rules.
     *
     * @param  list<string|int>  $path
     */
    public static function isCollection(string $document, array $path): bool
    {
        [$schema, $common] = self::schemas($document);
        $node = self::resolve($schema, $schema, $common);

        foreach ($path as $key) {
            $node = self::resolve(self::child($node, (string) $key), $schema, $common);

            if ($node === []) {
                return false;
            }
        }

        return self::collection($node);
    }

    /**
     * The collections that a document has, as the pointers of the objects that hold their entries, in the order of the document.
     *
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
    public static function of(string $document, array $rules): array
    {
        [$schema, $common] = self::schemas($document);
        $found = [];
        self::walk(self::resolve($schema, $schema, $common), $rules, [], $schema, $common, $found);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int|string, mixed>  $value
     * @param  list<string|int>  $path
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $common
     * @param  list<string>  $found
     */
    private static function walk(array $node, array $value, array $path, array $schema, array $common, array &$found): void
    {
        if ($path !== [] && self::collection($node) && ! array_is_list($value)) {
            $found[] = DocumentPointer::join($path);
        }

        foreach ($value as $key => $child) {
            if (! is_array($child)) {
                continue;
            }

            $below = self::resolve(self::child($node, (string) $key), $schema, $common);

            if ($below !== []) {
                self::walk($below, $child, [...$path, $key], $schema, $common, $found);
            }
        }
    }

    /** @param  array<string, mixed>  $node */
    private static function collection(array $node): bool
    {
        return ($node['type'] ?? null) === 'object' && isset($node['additionalProperties']) && is_array($node['additionalProperties']);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function child(array $node, string $key): array
    {
        if (isset($node['properties'][$key]) && is_array($node['properties'][$key])) {
            return $node['properties'][$key];
        }

        if (isset($node['additionalProperties']) && is_array($node['additionalProperties'])) {
            return $node['additionalProperties'];
        }

        if (isset($node['items']) && is_array($node['items'])) {
            return $node['items'];
        }

        return [];
    }

    /**
     * The schema a `$ref` points at, with what the place says of itself on top.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $common
     * @return array<string, mixed>
     */
    private static function resolve(array $node, array $schema, array $common): array
    {
        for ($hops = 0; isset($node['$ref']) && $hops < 8; $hops++) {
            [$file, $pointer] = array_pad(explode('#', (string) $node['$ref'], 2), 2, '');
            $target = $file === '' ? $schema : $common;

            foreach (array_filter(explode('/', $pointer), static fn (string $part): bool => $part !== '') as $part) {
                $target = $target[$part] ?? [];
            }

            $own = $node;
            unset($own['$ref']);
            $node = [...(is_array($target) ? $target : []), ...$own];
        }

        return $node;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private static function schemas(string $document): array
    {
        $file = PackValidator::RULES[$document] ?? throw new InvalidArgumentException("There is no document of the rules called {$document}.");

        if (! isset(self::$schemas[$file])) {
            $directory = rtrim((string) config('ride.content_path'), '/\\').'/schema';

            self::$schemas[$file] = [
                json_decode((string) file_get_contents("{$directory}/{$file}"), true, 512, JSON_THROW_ON_ERROR),
                json_decode((string) file_get_contents("{$directory}/common.schema.json"), true, 512, JSON_THROW_ON_ERROR),
            ];
        }

        return self::$schemas[$file];
    }

    /** Whether a value of a document is an empty object, which stays an object when the document is written. */
    public static function isEmptyObject(mixed $value): bool
    {
        return $value instanceof stdClass && (array) $value === [];
    }
}
