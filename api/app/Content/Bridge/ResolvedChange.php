<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Studio\Unit;

/**
 * One thing that a block of changes changes, after the selectors were expanded and the arithmetic was done: a number (where it is, what it was in the content being worked
 * on (a draft, if there is one) and in the content that is live, and what it becomes), a text in one language, or a new car or part. The values of a number are the whole
 * numbers of the documents; `beforeText()` and `afterText()` are what a person reads (a percent for a ppm).
 */
final readonly class ResolvedChange
{
    public const NUMBER = 'number';

    public const TEXT = 'text';

    public const CREATE = 'create';

    public const ADD = 'add';

    /**
     * @param  string  $document  `rules:starter`, `part:engine-r4-1-6-t2`
     * @param  list<string|int>  $path  the keys from the top of the document
     * @param  int|float|string|null  $published  the value in the version that is live, null when there is none
     * @param  int|float|string|null  $before  the value now, null when there is none (a new car, a language that had no text)
     * @param  list<string>  $notes  `rounded`, `draft` (the document has a draft that the change goes on top of), `unreviewed` (a translation that nobody has read), `new`
     * @param  string|null  $pattern  where the number is in the document with a star for every name that is not a property of the schema (what the catalog of help is keyed by)
     */
    public function __construct(
        public int $op,
        public ?string $opId,
        public string $document,
        public array $path,
        public string $label,
        public ?Unit $unit,
        public int|float|string|null $published,
        public int|float|string|null $before,
        public int|float|string|null $after,
        public array $notes = [],
        public ?string $pattern = null,
        public string $type = self::NUMBER,
    ) {}

    public function pointer(): string
    {
        return $this->path === [] ? '' : DocumentPointer::join($this->path);
    }

    public function beforeText(): string
    {
        return $this->text($this->before);
    }

    public function afterText(): string
    {
        return $this->text($this->after);
    }

    public function publishedText(): string
    {
        return $this->text($this->published);
    }

    /** How much greater the number becomes, as a percent of what it was (0 when it was 0, and for what is not a number). */
    public function percent(): float
    {
        return $this->type !== self::NUMBER || ! is_numeric($this->before) || ! is_numeric($this->after) || $this->before == 0 ? 0.0 : round(100 * ($this->after - $this->before) / $this->before, 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'op' => $this->op,
            'id' => $this->opId,
            'type' => $this->type,
            'document' => $this->document,
            'pointer' => $this->pointer(),
            'label' => $this->label,
            'unit' => $this->unit?->value,
            'published' => $this->published,
            'before' => $this->before,
            'after' => $this->after,
            'percent' => $this->percent(),
            'notes' => $this->notes,
            'pattern' => $this->pattern,
        ];
    }

    private function text(int|float|string|null $value): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_string($value) || $this->unit === null) {
            return (string) $value;
        }

        $shown = rtrim(rtrim(number_format($this->unit->display($value), 4, '.', ''), '0'), '.');

        return $shown.($this->unit->suffix() === '' ? '' : ' '.$this->unit->suffix());
    }
}
