<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\PackValidator;
use App\Content\Studio\Parameter;
use App\Content\Studio\ParameterCatalog;
use InvalidArgumentException;

/**
 * The numbers of one document of the content by the pointer that names them, with what the schema of the document says of each (the unit it is counted in, the
 * limits, whether it is a whole number). A place that is not in this index is not a number the bridge may change: a text, a switch, a name and a place the schema has
 * no word for are all out of it. The catalog is the one the studio is built from, so the two cannot disagree about what a number is.
 */
final class ParameterIndex
{
    /** @var array<string, ParameterCatalog> */
    private static array $catalogs = [];

    /** @var array<string, Parameter> */
    private array $byPointer = [];

    /**
     * @param  string  $name  which document it is: `rules:starter`, `part:engine-r4-1-6-t2`
     * @param  array<string, mixed>  $document
     */
    public function __construct(string $name, array $document)
    {
        $catalog = self::catalogOf($name);

        foreach (array_keys($document) as $section) {
            foreach ($catalog->parameters($document, (string) $section) as $parameter) {
                $this->byPointer[$parameter->pointer()] = $parameter;
            }
        }
    }

    public function find(string $pointer): ?Parameter
    {
        return $this->byPointer[$pointer] ?? null;
    }

    /**
     * Every number whose place is the pattern, where a `*` stands for exactly one name.
     *
     * @param  list<string>  $pattern
     * @return list<Parameter>
     */
    public function expand(array $pattern): array
    {
        return array_values(array_filter($this->byPointer, static fn (Parameter $parameter): bool => DocumentPointer::matches($pattern, $parameter->path)));
    }

    /**
     * The catalog for a document: `rules:starter`, `part:engine-r4-1-6-t2`. The schema is the one the document is checked against, so its limits are the limits.
     *
     * @throws InvalidArgumentException when there is no such kind of document
     */
    private static function catalogOf(string $document): ParameterCatalog
    {
        [$kind, $slug] = array_pad(explode(':', $document, 2), 2, '');

        $file = match ($kind) {
            'rules' => PackValidator::RULES[$slug] ?? throw new InvalidArgumentException("There is no document of the rules called {$slug}."),
            'part' => 'part.schema.json',
            'vehicle' => 'vehicle.schema.json',
            default => throw new InvalidArgumentException("There are no documents of the kind {$kind}."),
        };

        return self::$catalogs[$file] ??= self::load($file);
    }

    private static function load(string $file): ParameterCatalog
    {
        $directory = rtrim((string) config('ride.content_path'), '/\\').'/schema';

        return new ParameterCatalog(
            json_decode((string) file_get_contents("{$directory}/{$file}"), true, 512, JSON_THROW_ON_ERROR),
            json_decode((string) file_get_contents("{$directory}/common.schema.json"), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
