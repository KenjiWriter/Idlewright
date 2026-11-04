<?php

declare(strict_types=1);

namespace App\Content\Bridge;

/**
 * What a person asks the instruction for an AI to contain: the cars, the parts, the numbers of the rules (all of them, or the sections of the studio that are picked, and
 * the simple mode's few or every number), the tutorial, and the language the descriptions are in. What it contains is what an answer to it may change.
 */
final readonly class BriefSelection
{
    public const LOCALES = ['pl', 'en', 'fr', 'de', 'pt-BR'];

    /** The document of the rules whose numbers the instruction holds. */
    public const RULES = 'starter';

    /** The document of the tutorial, which the instruction holds whole when it is asked for. */
    public const TUTORIAL = 'tutorial';

    /**
     * @param  list<string>  $sections  the sections of the rules to hold (the top level of the document); none means every one that has numbers
     */
    public function __construct(
        public bool $vehicles = true,
        public bool $parts = true,
        public bool $rules = true,
        public array $sections = [],
        public bool $simple = true,
        public string $locale = 'pl',
        public bool $texts = false,
        public bool $design = false,
        public bool $tutorial = false,
    ) {}

    /** @param  array<string, mixed>  $state  the state of the form of the page */
    public static function fromState(array $state): self
    {
        $include = (array) ($state['include'] ?? ['vehicles', 'parts', 'rules']);
        $locale = (string) ($state['locale'] ?? 'pl');

        return new self(
            vehicles: in_array('vehicles', $include, true),
            parts: in_array('parts', $include, true),
            rules: in_array('rules', $include, true),
            sections: array_values(array_filter((array) ($state['sections'] ?? []), 'is_string')),
            simple: (bool) ($state['simple'] ?? true),
            locale: in_array($locale, self::LOCALES, true) ? $locale : 'pl',
            texts: (bool) ($state['texts'] ?? false),
            design: (bool) ($state['design'] ?? false),
            tutorial: in_array('tutorial', $include, true),
        );
    }

    /**
     * What an answer may change, as the scope of the checks: `kind:part`, `rules:starter`, `rules:starter#fuel`, `rules:tutorial`.
     *
     * @param  list<string>  $held  the sections of the rules that have numbers in the instruction
     * @return list<string>
     */
    public function scope(array $held): array
    {
        $scope = [];

        if ($this->vehicles) {
            $scope[] = 'kind:vehicle';
        }

        if ($this->parts) {
            $scope[] = 'kind:part';
        }

        if ($this->rules) {
            foreach ($held as $section) {
                $scope[] = 'rules:'.self::RULES.'#'.$section;
            }
        }

        if ($this->tutorial) {
            $scope[] = 'rules:'.self::TUTORIAL;
        }

        return $scope;
    }

    /** The same choices are the same key. */
    public function key(): string
    {
        $sections = $this->sections;
        sort($sections);

        // The tutorial is in the key only when it is held, so that the key of every other choice stays what it was.
        $choices = [$this->vehicles, $this->parts, $this->rules, $sections, $this->simple, $this->locale, $this->texts, $this->design, ...($this->tutorial ? ['tutorial'] : [])];

        return substr(hash('sha256', json_encode($choices, JSON_THROW_ON_ERROR)), 0, 32);
    }
}
