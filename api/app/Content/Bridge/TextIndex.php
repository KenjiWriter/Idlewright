<?php

declare(strict_types=1);

namespace App\Content\Bridge;

/**
 * The texts of a document that have the languages of the game (a name, a description, the words of a product): found by their shape, an object whose names are languages and
 * whose values are texts. A place that is not one of these is not a text the bridge may change.
 */
final class TextIndex
{
    public const LOCALES = ['en', 'pl', 'fr', 'de', 'pt-BR'];

    /** Whether a value in a document is a text in several languages. */
    public static function isText(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach ($value as $locale => $text) {
            if (! in_array($locale, self::LOCALES, true) || ! is_string($text) || $text === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Every text of a document by its pointer.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, array<string, string>>
     */
    public static function of(array $document): array
    {
        $found = [];
        self::walk($document, [], $found);

        return $found;
    }

    /**
     * @param  array<int|string, mixed>  $node
     * @param  list<string|int>  $path
     * @param  array<string, array<string, string>>  $found
     */
    private static function walk(array $node, array $path, array &$found): void
    {
        foreach ($node as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $here = [...$path, $key];

            if (self::isText($value)) {
                $found[DocumentPointer::join($here)] = $value;

                continue;
            }

            self::walk($value, $here, $found);
        }
    }
}
