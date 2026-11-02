<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Studio\Unit;

/**
 * One number that a block of changes changes, after the selectors were expanded and the arithmetic was done: where it is, what it was in the content being worked on
 * (a draft, if there is one) and in the content that is live, and what it becomes. The values are the whole numbers of the documents; the `display` ones are what a person
 * reads (a percent for a ppm).
 */
final readonly class ResolvedChange
{
    /**
     * @param  string  $document  `rules:starter`, `part:engine-r4-1-6-t2`
     * @param  list<string|int>  $path  the keys from the top of the document
     * @param  int|float|null  $published  the number in the version that is live, null when there is none
     * @param  list<string>  $notes  `rounded`, `draft` (the document has a draft that the change goes on top of)
     * @param  string|null  $pattern  where the number is in the document with a star for every name that is not a property of the schema (what the catalog of help is keyed by)
     */
    public function __construct(
        public int $op,
        public ?string $opId,
        public string $document,
        public array $path,
        public string $label,
        public Unit $unit,
        public int|float|null $published,
        public int|float $before,
        public int|float $after,
        public array $notes = [],
        public ?string $pattern = null,
    ) {}

    public function pointer(): string
    {
        return DocumentPointer::join($this->path);
    }

    public function beforeText(): string
    {
        return self::text($this->unit, $this->before);
    }

    public function afterText(): string
    {
        return self::text($this->unit, $this->after);
    }

    public function publishedText(): string
    {
        return $this->published === null ? '-' : self::text($this->unit, $this->published);
    }

    /** How much greater the number becomes, as a percent of what it was (0 when it was 0). */
    public function percent(): float
    {
        return $this->before == 0 ? 0.0 : round(100 * ($this->after - $this->before) / $this->before, 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'op' => $this->op,
            'id' => $this->opId,
            'document' => $this->document,
            'pointer' => $this->pointer(),
            'label' => $this->label,
            'unit' => $this->unit->value,
            'published' => $this->published,
            'before' => $this->before,
            'after' => $this->after,
            'percent' => $this->percent(),
            'notes' => $this->notes,
            'pattern' => $this->pattern,
        ];
    }

    private static function text(Unit $unit, int|float $raw): string
    {
        $shown = rtrim(rtrim(number_format($unit->display($raw), 4, '.', ''), '0'), '.');

        return $shown.($unit->suffix() === '' ? '' : ' '.$unit->suffix());
    }
}
