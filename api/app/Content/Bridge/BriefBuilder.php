<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Studio\Parameter;
use App\Content\Studio\SimpleMode;
use App\Filament\Support\FieldHelp;
use App\Games\CurrentGame;
use App\Models\ContentDefinition;
use App\Models\Game;
use App\Services\ContentPacks;
use Illuminate\Support\Str;
use InvalidArgumentException;
use stdClass;

/**
 * Makes the instruction that a person gives an AI before the two talk: the role and the manners of the conversation, the format of the block of changes it ends in, and the
 * game itself (the cars and parts as tables, the numbers of the rules with their limits and what each is for, and the tutorial when it is asked for), as it is being worked on. The data is JSON lines between two
 * markers with a nonce that is new every time, with every character of a name or a description that could look like a marker written as an escape, so that what is in the
 * game cannot end the data and start talking to the AI.
 *
 * What the instruction contains is its scope: an answer to it may change that and nothing else.
 */
final class BriefBuilder
{
    /** The fields that tell one car or part from another and that a selector can ask for (a column without a leading slash is a field). */
    private const FACTS = ['class', 'drivetrain', 'powertrain', 'slot', 'tire_kind', 'engine/layout'];

    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR;

    private const EXPLAIN_LIMIT = 360;

    public function __construct(
        private readonly ContentSnapshot $snapshot,
        private readonly ContentPacks $packs,
        private readonly CurrentGame $current,
        private readonly ChangeResolver $resolver,
    ) {}

    /**
     * The instruction for ONE car or part: the whole record with its numbers and texts, and the ones beside it (the same slot, or the cars) to measure it by. What an answer
     * to it may change is that record and nothing else, so it cannot make a new car or touch another.
     *
     * @throws InvalidArgumentException when there is no such car or part
     */
    public function buildRecord(string $kind, string $slug, string $locale, string $code, ?string $nonce = null): Brief
    {
        $definition = ContentDefinition::query()->where('kind', $kind)->where('slug', $slug)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->first()
            ?? throw new InvalidArgumentException("There is no {$kind} {$slug}.");

        $nonce ??= Str::lower(Str::random(10));
        $game = Game::query()->find($this->current->id());
        $document = $definition->workingDocument();
        $base = ['pack' => (string) ($this->packs->active()?->number ?? ''), 'snapshot' => $this->snapshot->current(), 'brief' => $code];
        $scope = ["{$kind}:{$slug}"];
        $numbers = (new ParameterIndex("{$kind}:{$slug}", $document))->all();

        $lines = [
            'GAME '.$this->json(['slug' => (string) $game?->slug, 'name' => (string) $game?->name, 'descriptions_in' => $locale]),
            'BASE '.$this->json($base),
            'SCOPE '.$this->json($scope),
            'RECORD '.$this->json(['kind' => $kind, 'slug' => $slug, 'name' => $this->nameOf($document, $slug, $locale)]),
            ...array_map(fn (Parameter $parameter): string => $this->json([$parameter->pointer(), BridgeUnits::token($parameter->unit), $parameter->display(), $parameter->minDisplay(), $parameter->maxDisplay()]), $numbers),
        ];

        $texts = TextIndex::of($document);

        if ($texts !== []) {
            $lines[] = 'TEXTS '.$this->json(['kind' => $kind, 'slug' => $slug]);

            foreach ($texts as $pointer => $languages) {
                $lines[] = $this->json([$pointer, $languages]);
            }
        }

        // The ones beside it, to measure it by: the parts of the same slot, or the cars.
        $lines = [...$lines, ...$this->tables($kind, $locale, $kind === ContentDefinition::KIND_PART ? (string) ($document['slot'] ?? '-') : '-')];

        $operations = [];

        if (isset($document['name']['en'])) {
            $operations[] = ['op' => 'text', 'id' => 'rename', 'target' => ['kind' => $kind, 'slug' => $slug], 'path' => '/name', 'locale' => 'en', 'value' => $document['name']['en'].' (renamed)'];
        }

        if (isset($document['price_cents'])) {
            $operations[] = ['op' => 'scale', 'id' => 'price', 'target' => ['kind' => $kind, 'slug' => $slug], 'path' => '/price_cents', 'percent' => 10];
        }

        $operations = $operations === [] ? [['op' => 'scale', 'target' => ['kind' => $kind, 'slug' => $slug], 'path' => '/price_cents', 'percent' => 10]] : $operations;
        $envelope = ['format' => 'ride-changes/1', 'game' => (string) $game?->slug, 'base' => $base, 'summary' => 'One sentence saying what the block does', 'ops' => $operations];

        $text = BriefTexts::role()."\n\n"
            .BriefTexts::format($this->schema(), json_encode($envelope, self::JSON | JSON_PRETTY_PRINT))."\n\n"
            .BriefTexts::dataIntro($nonce)."\n".implode("\n", $lines)."\n"
            .BriefTexts::dataEnd($nonce)."\n";

        return new Brief($text, $scope, $code, $base['pack'], $base['snapshot'], strlen($text), (int) ceil(strlen($text) / 3.2));
    }

    /**
     * The sections of the rules that have numbers, with how many numbers each has and how many of those the simple mode shows.
     *
     * @return array<string, array{numbers: int, simple: int}>
     */
    public function sections(): array
    {
        $sections = [];
        $simple = new SimpleMode;

        foreach ($this->rulesIndex()?->all() ?? [] as $parameter) {
            $row = $sections[$parameter->section] ?? ['numbers' => 0, 'simple' => 0];
            $sections[$parameter->section] = ['numbers' => $row['numbers'] + 1, 'simple' => $row['simple'] + ($simple->keeps($parameter) ? 1 : 0)];
        }

        return $sections;
    }

    public function build(BriefSelection $selection, string $code, ?string $nonce = null): Brief
    {
        $nonce ??= Str::lower(Str::random(10));
        $game = Game::query()->find($this->current->id());
        $base = ['pack' => (string) ($this->packs->active()?->number ?? ''), 'snapshot' => $this->snapshot->current(), 'brief' => $code];

        $tables = [];

        if ($selection->vehicles) {
            array_push($tables, ...$this->tables(ContentDefinition::KIND_VEHICLE, $selection->locale));
        }

        if ($selection->parts) {
            array_push($tables, ...$this->tables(ContentDefinition::KIND_PART, $selection->locale));
        }

        [$rules, $held, $picked] = $selection->rules ? $this->rules($selection) : [[], [], []];
        $scope = $selection->scope($held);
        // The design of a game is a talk about the words of it, so the texts are in the data whatever was asked.
        $texts = $selection->texts || $selection->design ? $this->texts($selection, $held) : [];
        $collections = $selection->rules ? $this->collections($held) : [];
        $tutorial = $selection->tutorial ? $this->tutorialDocument() : null;
        $tutorialLines = $tutorial === null ? [] : (new TutorialBrief)->lines($tutorial, $selection->locale, $selection->texts || $selection->design);

        $lines = [
            'GAME '.$this->json(['slug' => (string) $game?->slug, 'name' => (string) $game?->name, 'descriptions_in' => $selection->locale]),
            'BASE '.$this->json($base),
            'SCOPE '.$this->json($scope),
            ...$tables,
            ...$rules,
            ...$collections,
            ...$texts,
            ...$tutorialLines,
        ];

        $envelope = ['format' => 'ride-changes/1', 'game' => (string) $game?->slug, 'base' => $base, 'summary' => 'One sentence saying what the block does', 'ops' => $this->examples($scope, $base, (string) $game?->slug, $picked, $tutorial)];

        $text = ($selection->design ? BriefTexts::designRole() : BriefTexts::role())."\n\n"
            .($tutorial === null ? '' : BriefTexts::tutorial()."\n\n")
            .BriefTexts::format($this->schema(), json_encode($envelope, self::JSON | JSON_PRETTY_PRINT))."\n\n"
            .BriefTexts::dataIntro($nonce, $tutorial !== null)."\n".implode("\n", $lines)."\n"
            .BriefTexts::dataEnd($nonce)."\n";

        return new Brief($text, $scope, $code, $base['pack'], $base['snapshot'], strlen($text), (int) ceil(strlen($text) / 3.2));
    }

    /**
     * The tables of the cars or of the parts: one table for the cars, one for the parts of each slot. A column is a fact (a field that picks) or a number (a pointer that can be
     * changed, with its unit), and a row is an item.
     *
     * @return list<string>
     */
    private function tables(string $kind, string $locale, ?string $only = null): array
    {
        $groups = [];

        $definitions = ContentDefinition::query()->where('kind', $kind)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->orderBy('slug')->get();

        foreach ($definitions as $definition) {
            $document = $definition->workingDocument();
            $groups[$kind === ContentDefinition::KIND_PART ? (string) ($document['slot'] ?? '-') : '-'][] = [$definition->slug, $document];
        }

        ksort($groups);
        $lines = [];

        foreach ($groups as $group => $items) {
            if ($only !== null && (string) $group !== $only) {
                continue;
            }

            $facts = [];
            $units = [];
            $prepared = [];

            foreach ($items as [$slug, $document]) {
                $row = ['facts' => [], 'numbers' => []];

                foreach (self::FACTS as $fact) {
                    $value = DocumentPointer::get($document, explode('/', $fact));

                    if (is_string($value)) {
                        $row['facts'][$fact] = $value;
                        $facts[$fact] = true;
                    }
                }

                foreach ((new ParameterIndex("{$kind}:{$slug}", $document))->all() as $parameter) {
                    $row['numbers'][$parameter->pointer()] = $parameter->unit->display($parameter->value);
                    $units[$parameter->pointer()] = BridgeUnits::token($parameter->unit);
                }

                $prepared[] = [$slug, $this->nameOf($document, $slug, $locale), $row];
            }

            $factColumns = array_keys($facts);
            $numberColumns = array_keys($units);
            $header = ['kind' => $kind, 'columns' => ['slug', 'name', ...$factColumns, ...$numberColumns], 'units' => $units] + ($group === '-' ? [] : ['group' => ['slot' => $group]]);
            $lines[] = 'TABLE '.$this->json($header);

            foreach ($prepared as [$slug, $name, $row]) {
                $lines[] = $this->json([
                    $slug,
                    $name,
                    ...array_map(static fn (string $fact): ?string => $row['facts'][$fact] ?? null, $factColumns),
                    ...array_map(static fn (string $pointer): int|float|null => $row['numbers'][$pointer] ?? null, $numberColumns),
                ]);
            }
        }

        return $lines;
    }

    /**
     * The numbers of the rules, section by section, and what each kind of number is for.
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<Parameter>} the lines, the sections held, and the numbers held
     */
    private function rules(BriefSelection $selection): array
    {
        $index = $this->rulesIndex();

        if ($index === null) {
            return [[], [], []];
        }

        $simple = new SimpleMode;
        $bySection = [];

        foreach ($index->all() as $parameter) {
            if (($selection->simple && ! $simple->keeps($parameter)) || ($selection->sections !== [] && ! in_array($parameter->section, $selection->sections, true))) {
                continue;
            }

            $bySection[$parameter->section][] = $parameter;
        }

        $lines = [];
        $explain = [];
        $held = [];

        foreach ($bySection as $section => $parameters) {
            $held[] = (string) $section;
            $lines[] = 'RULES '.$this->json(['document' => 'starter', 'section' => $section]);

            foreach ($parameters as $parameter) {
                $lines[] = $this->json([
                    $parameter->pointer(),
                    BridgeUnits::token($parameter->unit),
                    $parameter->display(),
                    $parameter->minDisplay(),
                    $parameter->maxDisplay(),
                ]);

                $explain[$parameter->pattern] ??= $this->explanation($parameter, $selection->locale);
            }
        }

        $lines[] = 'EXPLAIN '.$this->json($explain);

        return [$lines, $held, array_merge(...array_values($bySection) ?: [[]])];
    }

    /**
     * The texts that have several languages: the names and descriptions of the cars and parts, and the texts of the sections of the rules that the instruction holds.
     *
     * @param  list<string>  $held  the sections of the rules held
     * @return list<string>
     */
    private function texts(BriefSelection $selection, array $held): array
    {
        $lines = [];

        foreach (array_filter([ContentDefinition::KIND_VEHICLE => $selection->vehicles, ContentDefinition::KIND_PART => $selection->parts]) as $kind => $_) {
            $lines[] = 'TEXTS '.$this->json(['kind' => $kind]);

            foreach (ContentDefinition::query()->where('kind', $kind)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->orderBy('slug')->get() as $definition) {
                $texts = array_intersect_key(TextIndex::of($definition->workingDocument()), ['/name' => 1, '/description' => 1]);
                $lines[] = $this->json([$definition->slug, $texts]);
            }
        }

        $rules = $selection->rules ? $this->rulesDocument() : null;

        if ($rules !== null) {
            $lines[] = 'TEXTS '.$this->json(['document' => BriefSelection::RULES]);

            foreach (TextIndex::of($rules) as $pointer => $texts) {
                if (in_array(explode('/', $pointer)[1], $held, true)) {
                    $lines[] = $this->json([$pointer, $texts]);
                }
            }
        }

        return $lines;
    }

    /**
     * The collections of the sections of the rules that the instruction holds: the places a new entry can be added to.
     *
     * @param  list<string>  $held
     * @return list<string>
     */
    private function collections(array $held): array
    {
        $rules = $this->rulesDocument();

        if ($rules === null) {
            return [];
        }

        $places = array_values(array_filter(
            SchemaCollections::of(BriefSelection::RULES, $rules),
            static fn (string $pointer): bool => in_array(explode('/', $pointer)[1], $held, true),
        ));

        return $places === [] ? [] : ['COLLECTIONS '.$this->json($places)];
    }

    /** @return array<string, mixed>|null */
    private function tutorialDocument(): ?array
    {
        return ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', BriefSelection::TUTORIAL)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->first()?->workingDocument();
    }

    /** @return array<string, mixed>|null */
    private function rulesDocument(): ?array
    {
        return ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', BriefSelection::RULES)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->first()?->workingDocument();
    }

    /** What a kind of number is for: the entry of the catalog of help in the language, else what the schema says of it. */
    private function explanation(Parameter $parameter, string $locale): string
    {
        $entry = FieldHelp::entry('rules.'.$parameter->pattern, $locale);
        $text = $entry !== null ? trim((string) ($entry['label'] ?? '').': '.(string) ($entry['help'] ?? ''), ': ') : ($parameter->description ?? $parameter->group);

        return Str::limit($text, self::EXPLAIN_LIMIT);
    }

    /**
     * Operations for the example block, each made from the content of this game and kept only if the panel would accept it as it stands (so the examples never promise what
     * the game does not have).
     *
     * @param  list<string>  $scope
     * @param  array{pack: string, snapshot: string, brief: string}  $base
     * @param  list<Parameter>  $numbers  the numbers of the rules the instruction holds
     * @param  array<string, mixed>|null  $tutorial  the tutorial, when the instruction holds it
     * @return list<array<string, mixed>>
     */
    private function examples(array $scope, array $base, string $game, array $numbers, ?array $tutorial = null): array
    {
        $candidates = $tutorial === null ? [] : $this->tutorialExamples($tutorial);

        $part = ContentDefinition::query()->where('kind', ContentDefinition::KIND_PART)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->orderBy('slug')->get()
            ->map(fn (ContentDefinition $definition): array => $definition->workingDocument())
            ->first(fn (array $document): bool => isset($document['slot'], $document['tier'], $document['price_cents']));

        if ($part !== null && isset($part['slug'], $part['name']['en'])) {
            $candidates[] = ['op' => 'text', 'id' => 'rename', 'target' => ['kind' => 'part', 'slug' => $part['slug']], 'path' => '/name', 'locale' => 'en', 'value' => $part['name']['en'].' (renamed)'];
        }

        $car = ContentDefinition::query()->where('kind', ContentDefinition::KIND_VEHICLE)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->orderBy('slug')->first()?->workingDocument();

        if ($car !== null && isset($car['slug'], $car['price_cents'], $car['name']['en'])) {
            $candidates[] = ['op' => 'create', 'id' => 'new-car', 'kind' => 'vehicle', 'from' => $car['slug'], 'slug' => $car['slug'].'-2', 'fields' => ['name' => array_map(static fn (string $name): string => $name.' II', $car['name']), 'price_cents' => $car['price_cents'] + 100_000]];
        }

        if ($part !== null) {
            $candidates[] = ['op' => 'scale', 'id' => 'prices', 'select' => ['kind' => 'part', 'where' => ['slot' => $part['slot'], 'tier' => $part['tier']]], 'path' => '/price_cents', 'percent' => 10];
        }

        $plain = collect($numbers)->first(fn (Parameter $parameter): bool => $parameter->unit->value === 'plain' && $parameter->integer && ! str_contains($parameter->pattern, '*') && ($parameter->max === null || $parameter->value < $parameter->max) && $parameter->value > ($parameter->min ?? 0));

        if ($plain !== null) {
            $candidates[] = ['op' => 'set', 'id' => 'one-number', 'target' => ['rules' => 'starter'], 'path' => $plain->pointer(), 'value' => $plain->value + 1, 'unit' => 'plain'];
        }

        $starred = collect($numbers)->first(fn (Parameter $parameter): bool => str_contains($parameter->pattern, '*'));

        if ($starred !== null) {
            $candidates[] = ['op' => 'scale', 'id' => 'every-name', 'target' => ['rules' => 'starter'], 'path' => $starred->pattern, 'percent' => -5];
        }

        $kept = array_values(array_filter($candidates, function (array $operation) use ($scope, $base, $game): bool {
            $check = $this->resolver->resolve(['format' => 'ride-changes/1', 'game' => $game, 'base' => $base, 'summary' => 'example', 'ops' => [$operation]], $scope);

            return $check->errors() === [] && $check->changes !== [];
        }));

        return $kept !== [] ? $kept : [['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'a slot of the tables']], 'path' => '/price_cents', 'percent' => 10]];
    }

    /**
     * Operations on the tutorial for the example block: a text of a step by its key, a choice, and a new step after the first one.
     *
     * @param  array<string, mixed>  $tutorial
     * @return list<array<string, mixed>>
     */
    private function tutorialExamples(array $tutorial): array
    {
        $target = ['rules' => BriefSelection::TUTORIAL];
        $candidates = [];
        $first = collect($tutorial['steps'] ?? [])->first(fn (mixed $step): bool => is_array($step) && isset($step['key'], $step['goal']['en']));

        if ($first !== null) {
            $candidates[] = ['op' => 'text', 'id' => 'goal', 'target' => $target, 'path' => "/steps/{$first['key']}/goal", 'locale' => 'en', 'value' => $first['goal']['en'].' (renamed)'];
        }

        $device = (string) ($tutorial['messenger']['device'] ?? 'phone');
        $candidates[] = ['op' => 'set', 'id' => 'device', 'target' => $target, 'path' => '/messenger/device', 'value' => $device === 'letter' ? 'plain' : 'letter', 'unit' => 'choice'];

        if ($first !== null) {
            $words = fn (string $text): array => ['en' => $text, 'pl' => $text, 'fr' => $text, 'de' => $text, 'pt-BR' => $text];
            $candidates[] = ['op' => 'insert', 'id' => 'new-step', 'target' => $target, 'path' => '/steps', 'after' => $first['key'], 'value' => [
                'key' => 'a-new-step',
                'phase' => (int) ($first['phase'] ?? 1),
                'trigger' => 'none',
                'completion' => 'acknowledged',
                'target' => null,
                'goal' => $words('The goal in a few words'),
                'sender' => 'The sender',
                'sms' => [$words('The message of the mentor')],
                'reply' => $words('The words of the button'),
                'reward' => new stdClass,
            ]];
        }

        return $candidates;
    }

    /** The schema of the block as the panel holds it, on one line. */
    private function schema(): string
    {
        $text = (string) file_get_contents(rtrim((string) config('ride.content_path'), '/\\').'/schema/changes.schema.json');

        return json_encode(json_decode($text, false, 512, JSON_THROW_ON_ERROR), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function rulesIndex(): ?ParameterIndex
    {
        $definition = ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', BriefSelection::RULES)->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->first();

        return $definition === null ? null : new ParameterIndex('rules:'.BriefSelection::RULES, $definition->workingDocument());
    }

    /** @param  array<string, mixed>  $document */
    private function nameOf(array $document, string $slug, string $locale): string
    {
        return (string) ($document['name'][$locale] ?? $document['name']['en'] ?? $slug);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, self::JSON);
    }
}
