<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Studio\Parameter;
use App\Models\ContentDefinition;
use InvalidArgumentException;

/**
 * Turns the operations of a block of changes into the numbers they change. The selectors are expanded to the list of what they pick, the arithmetic and the rounding are
 * done here (the AI says "12% more", not what 12% more is), every number is judged by what its schema allows of it, and the operations are applied in order to a working
 * copy, so that a later one sees what an earlier one did. Nothing is written: the result is a `ChangeCheck`.
 *
 * What is refused is refused, not bent: a number that would go over its limit is an error, not the limit.
 */
final class ChangeResolver
{
    use ResolvesTextsAndCreations;
    use ResolvesTutorial;

    public const MAX_TARGETS = 200;

    public const MAX_CHANGES = 500;

    /** @var array<string, ContentDefinition|null> */
    private array $definitions = [];

    /** @var array<string, ParameterIndex> */
    private array $indexes = [];

    /** @var array<string, array<string, mixed>> */
    private array $documents = [];

    /** @var array<string, array{draft: bool, saved_at: string|null}> */
    private array $touched = [];

    /** @var array<string, int> the operation that last wrote a place, by document and pointer */
    private array $written = [];

    /** @var list<ResolvedChange> */
    private array $changes = [];

    /** @var list<string> the new cars and parts, by `part:slug` */
    private array $created = [];

    /** @var list<BridgeIssue> */
    private array $issues = [];

    /** @var list<string>|null */
    private ?array $scope = null;

    /**
     * @param  array<string, mixed>  $block  a block that passed the schema
     * @param  list<string>|null  $scope  what the instruction contained, as `rules:starter`, `kind:part`, `part:engine-r4-1-6-t2`; null for no limit
     */
    public function resolve(array $block, ?array $scope = null): ChangeCheck
    {
        $this->definitions = $this->indexes = $this->documents = $this->touched = $this->written = $this->changes = $this->issues = $this->created = $this->insertedSteps = [];
        $this->scope = $scope;

        foreach ($block['ops'] as $position => $operation) {
            $number = $position + 1;
            $id = $operation['id'] ?? null;

            match ($operation['op']) {
                'set' => $this->set($number, $id, $operation),
                'scale' => $this->scale($number, $id, $operation),
                'text' => $this->text($number, $id, $operation),
                'create' => $this->create($number, $id, $operation),
                'add' => $this->add($number, $id, $operation),
                'insert' => $this->insert($number, $id, $operation),
            };

            if (count($this->changes) > self::MAX_CHANGES) {
                $this->issues[] = BridgeIssue::error('too_many', 'The block changes more than '.self::MAX_CHANGES.' numbers. Split it into smaller blocks.', $number, $id);

                break;
            }
        }

        return new ChangeCheck($this->changes, $this->issues, $this->documents, $this->touched, $block, $this->created);
    }

    /** @param  array<string, mixed>  $operation */
    private function set(int $number, ?string $id, array $operation): void
    {
        $key = $this->targetKey($operation['target']);
        $path = $this->pointerOf($operation['path'], $number, $id);

        if ($path === null || ! $this->usable($key, $number, $id)) {
            return;
        }

        if (in_array('*', $path, true)) {
            $this->issues[] = BridgeIssue::error('wildcard_in_set', 'A set changes one number: a * is only for scale.', $number, $id, $key.' '.DocumentPointer::join($path));

            return;
        }

        $keyed = $path;
        $path = $this->stepPath($key, $path, $number, $id);

        if ($path === null) {
            return;
        }

        if ((! is_int($operation['value']) && ! is_float($operation['value'])) || in_array($operation['unit'], ['choice', 'text'], true)) {
            $this->setChoiceOrWord($number, $id, $key, $keyed, $path, $operation);

            return;
        }

        $parameter = $this->index($key)->find(DocumentPointer::join($path));

        if ($parameter === null) {
            $this->issues[] = $this->unknownPath($key, $keyed, $number, $id);

            return;
        }

        if (! $this->inScope($key, $parameter)) {
            $this->issues[] = $this->outOfScope($key, $parameter, $number, $id);

            return;
        }

        if ($this->isRealMoney($parameter)) {
            $this->issues[] = $this->realMoney($key, $parameter, $number, $id);

            return;
        }

        $expected = BridgeUnits::token($parameter->unit);

        if ($operation['unit'] !== $expected) {
            $this->issues[] = BridgeIssue::error('unit_mismatch', "This number is counted in {$expected}, not in {$operation['unit']}. Give the value in {$expected}.", $number, $id, $this->where($key, $parameter));

            return;
        }

        $display = $operation['value'];
        $scaled = $display * $parameter->unit->divisor();

        if ($parameter->integer && abs($scaled - round($scaled)) > 1e-6) {
            $this->issues[] = BridgeIssue::error('not_integer', 'This number moves in steps of '.$this->step($parameter).', and '.$display.' is not a multiple of that.', $number, $id, $this->where($key, $parameter));

            return;
        }

        $raw = $parameter->unit->raw((float) $display, $parameter->integer);
        $after = $parameter->integer ? (int) $raw : $raw;

        $this->apply($number, $id, $key, $parameter, $after, []);
    }

    /** @param  array<string, mixed>  $operation */
    private function scale(int $number, ?string $id, array $operation): void
    {
        $percent = $operation['percent'];
        $basis = (int) round($percent * 100);

        if (abs($percent * 100 - $basis) > 1e-6) {
            $this->issues[] = BridgeIssue::error('percent_precision', 'The percent has at most two decimals.', $number, $id);

            return;
        }

        $pattern = $this->pointerOf($operation['path'], $number, $id);

        if ($pattern === null) {
            return;
        }

        $keys = isset($operation['select']) ? $this->select($operation['select']) : [$this->targetKey($operation['target'])];

        if (isset($operation['select']) && $keys === []) {
            $this->issues[] = BridgeIssue::error('no_match', 'No '.$operation['select']['kind'].' has '.json_encode($operation['select']['where'], JSON_UNESCAPED_SLASHES).'. Nothing was selected.', $number, $id);

            return;
        }

        $targets = [];
        $skipped = [];
        $literalEnd = end($pattern) !== '*';

        foreach ($keys as $key) {
            if (! $this->usable($key, $number, $id)) {
                return;
            }

            $mapped = $this->stepPath($key, $pattern, $number, $id);

            if ($mapped === null) {
                return;
            }

            $found = array_values(array_filter(
                $this->index($key)->expand(array_map('strval', $mapped)),
                fn (Parameter $parameter): bool => ! $this->inInsertedStep($key, $parameter->path),
            ));

            if ($found === []) {
                $skipped[] = $key;

                continue;
            }

            foreach ($found as $parameter) {
                if (! $this->inScope($key, $parameter)) {
                    $this->issues[] = $this->outOfScope($key, $parameter, $number, $id);

                    return;
                }

                if ($this->isRealMoney($parameter)) {
                    if ($literalEnd) {
                        $this->issues[] = $this->realMoney($key, $parameter, $number, $id);

                        return;
                    }

                    $this->issues[] = BridgeIssue::warning('skipped_real_money', 'A field in real money was left out: '.$this->where($key, $parameter).'.', $number, $id);

                    continue;
                }

                $targets[] = [$key, $parameter];
            }
        }

        if ($targets === []) {
            $this->issues[] = $this->unknownPath(implode(', ', array_slice($keys, 0, 3)).(count($keys) > 3 ? ', ...' : ''), $pattern, $number, $id);

            return;
        }

        if ($skipped !== []) {
            $this->issues[] = BridgeIssue::warning('skipped', count($skipped).' selected have nothing at '.DocumentPointer::join($pattern).' and were left out: '.implode(', ', array_slice($skipped, 0, 8)).(count($skipped) > 8 ? ', ...' : '').'.', $number, $id);
        }

        if (count($targets) > self::MAX_TARGETS) {
            $this->issues[] = BridgeIssue::error('too_many', 'The operation picks '.count($targets).' numbers, more than '.self::MAX_TARGETS.'. Narrow it.', $number, $id);

            return;
        }

        $decimals = 1;

        foreach ($targets as [$key, $parameter]) {
            $decimals = max($decimals, min(3, $this->decimals($this->valueAt($key, $parameter))));
        }

        $computed = [];
        $failed = false;

        foreach ($targets as [$key, $parameter]) {
            $current = $this->valueAt($key, $parameter);
            [$after, $rounded] = $this->scaled($current, $basis, $parameter->integer, $decimals);

            if ($after == $current) {
                $this->issues[] = BridgeIssue::warning('no_change', 'The number does not change: '.$this->where($key, $parameter).'.', $number, $id);

                continue;
            }

            if (($problem = $this->outsideLimits($parameter, $after, $number, $id, $key)) !== null) {
                $this->issues[] = $problem;
                $failed = true;

                continue;
            }

            $computed[] = [$key, $parameter, $after, $rounded ? ['rounded'] : []];
        }

        if ($failed) {
            return;
        }

        foreach ($computed as [$key, $parameter, $after, $notes]) {
            $this->apply($number, $id, $key, $parameter, $after, $notes);
        }
    }

    /**
     * Puts a number in the working copy of its document and writes down the change.
     *
     * @param  list<string>  $notes
     */
    private function apply(int $number, ?string $id, string $key, Parameter $parameter, int|float $after, array $notes): void
    {
        $before = $this->valueAt($key, $parameter);

        if ($after == $before) {
            $this->issues[] = BridgeIssue::warning('no_change', 'The number does not change: '.$this->where($key, $parameter).'.', $number, $id);

            return;
        }

        if (($problem = $this->outsideLimits($parameter, $after, $number, $id, $key)) !== null) {
            $this->issues[] = $problem;

            return;
        }

        $definition = $this->definition($key);
        $place = $key.' '.DocumentPointer::join($parameter->path);

        if (isset($this->written[$place])) {
            $this->issues[] = BridgeIssue::warning('overwritten', "Op {$this->written[$place]} had written this number; this one writes it again.", $number, $id, $this->where($key, $parameter));
        }

        $this->written[$place] = $number;
        $this->documents[$key] = DocumentPointer::set($this->documents[$key] ?? $definition->workingDocument(), $parameter->path, $after);
        $this->touched[$key] ??= ['draft' => $definition->hasDraft(), 'saved_at' => $definition->draft_saved_at?->toIso8601String()];

        $published = $definition->publishedDocument();
        $was = $published === null ? null : DocumentPointer::get($published, $parameter->path);

        $this->changes[] = new ResolvedChange(
            op: $number,
            opId: $id,
            document: $key,
            path: $this->keyedPath($key, $parameter->path),
            label: $parameter->group.' › '.$parameter->label,
            unit: $parameter->unit,
            published: is_numeric($was) ? $was : null,
            before: $before,
            after: $after,
            notes: [...$notes, ...($definition->hasDraft() ? ['draft'] : [])],
            pattern: $parameter->pattern,
        );
    }

    /**
     * The new value of a number and whether it was rounded to get it: a whole number is rounded half away from zero on whole numbers, a decimal to the longest decimals the
     * numbers it is changed with have.
     *
     * @return array{0: int|float, 1: bool}
     */
    private function scaled(int|float $current, int $basis, bool $integer, int $decimals): array
    {
        if ($integer && is_int($current)) {
            $product = $current * (10_000 + $basis);

            return [intdiv(abs($product) + 5_000, 10_000) * ($product <=> 0), $product % 10_000 !== 0];
        }

        $exact = $current * (10_000 + $basis) / 10_000;
        $after = round($exact, $decimals);

        return [$after, abs($exact - $after) > 1e-9];
    }

    private function outsideLimits(Parameter $parameter, int|float $after, int $number, ?string $id, string $key): ?BridgeIssue
    {
        $where = $this->where($key, $parameter);

        if ($parameter->max !== null && $after > $parameter->max) {
            return BridgeIssue::error('above_max', 'The number would be '.$this->show($parameter, $after).', and the most it may be is '.$this->show($parameter, $parameter->max).'.', $number, $id, $where);
        }

        if ($parameter->min !== null && $after < $parameter->min) {
            return BridgeIssue::error('below_min', 'The number would be '.$this->show($parameter, $after).', and the least it may be is '.$this->show($parameter, $parameter->min).'.', $number, $id, $where);
        }

        return null;
    }

    /**
     * The documents a selector picks: the cars or the parts that have every field it asks for, in the order of their slugs.
     *
     * @param  array{kind: string, where: array<string, mixed>}  $select
     * @return list<string>
     */
    private function select(array $select): array
    {
        $keys = [];

        $found = ContentDefinition::query()
            ->where('kind', $select['kind'])
            ->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)
            ->orderBy('slug')
            ->get();

        foreach ($found as $definition) {
            if ($this->matches($definition->workingDocument(), $select['where'])) {
                $key = "{$definition->kind}:{$definition->slug}";
                $this->definitions[$key] = $definition;
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $where
     */
    private function matches(array $document, array $where): bool
    {
        foreach ($where as $field => $condition) {
            $keys = explode('/', (string) $field);

            if (! DocumentPointer::has($document, $keys) || ! $this->satisfies(DocumentPointer::get($document, $keys), $condition)) {
                return false;
            }
        }

        return true;
    }

    private function satisfies(mixed $value, mixed $condition): bool
    {
        if (! is_array($condition)) {
            return $this->same($value, $condition);
        }

        foreach ($condition as $test => $expected) {
            $holds = match ($test) {
                'in' => array_reduce($expected, fn (bool $any, mixed $one): bool => $any || $this->same($value, $one), false),
                'gte' => is_numeric($value) && $value >= $expected,
                'lte' => is_numeric($value) && $value <= $expected,
                'gt' => is_numeric($value) && $value > $expected,
                'lt' => is_numeric($value) && $value < $expected,
                default => false,
            };

            if (! $holds) {
                return false;
            }
        }

        return true;
    }

    /** Two numbers are the same when they are equal as numbers (3 and 3.0); anything else has to be identical. */
    private function same(mixed $a, mixed $b): bool
    {
        return (is_int($a) || is_float($a)) && (is_int($b) || is_float($b)) ? $a == $b : $a === $b;
    }

    /** @param  array{rules?: string, kind?: string, slug?: string}  $target */
    private function targetKey(array $target): string
    {
        return isset($target['rules']) ? 'rules:'.$target['rules'] : $target['kind'].':'.$target['slug'];
    }

    /**
     * Whether a document may be changed by this block: it exists in this game (and is not archived), and it is within what the instruction contained.
     */
    private function usable(string $key, int $number, ?string $id): bool
    {
        if ($this->sectionsOf($key) === []) {
            $this->issues[] = BridgeIssue::error('out_of_scope', "{$key} was not in the instruction this block answers.", $number, $id, $key);

            return false;
        }

        if ($this->definition($key) === null) {
            $this->issues[] = BridgeIssue::error('unknown_document', "There is no {$key} in this game, or it is archived.", $number, $id, $key);

            return false;
        }

        return true;
    }

    /**
     * The sections of a document that the instruction held when it held only some of them: null when it held the whole document (or there is no limit), an empty list when it
     * held none of it.
     *
     * @return list<string>|null
     */
    private function sectionsOf(string $key): ?array
    {
        if ($this->scope === null || in_array($key, $this->scope, true) || in_array('kind:'.explode(':', $key, 2)[0], $this->scope, true)) {
            return null;
        }

        $sections = [];

        foreach ($this->scope as $entry) {
            if (str_starts_with($entry, $key.'#')) {
                $sections[] = substr($entry, strlen($key) + 1);
            }
        }

        return $sections;
    }

    private function inScope(string $key, Parameter $parameter): bool
    {
        $sections = $this->sectionsOf($key);

        return $sections === null || in_array((string) $parameter->path[0], $sections, true);
    }

    private function outOfScope(string $key, Parameter $parameter, int $number, ?string $id): BridgeIssue
    {
        return BridgeIssue::error('out_of_scope', 'This number is in a part of the rules that the instruction did not contain.', $number, $id, $this->where($key, $parameter));
    }

    /** @return list<string>|null */
    private function pointerOf(string $pointer, int $number, ?string $id): ?array
    {
        try {
            return DocumentPointer::split($pointer);
        } catch (InvalidArgumentException $exception) {
            $this->issues[] = BridgeIssue::error('bad_pointer', $exception->getMessage(), $number, $id);

            return null;
        }
    }

    private function definition(string $key): ?ContentDefinition
    {
        if (! array_key_exists($key, $this->definitions)) {
            [$kind, $slug] = explode(':', $key, 2);

            $this->definitions[$key] = ContentDefinition::query()
                ->where('kind', $kind)
                ->where('slug', $slug)
                ->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)
                ->first();
        }

        return $this->definitions[$key];
    }

    private function index(string $key): ParameterIndex
    {
        return $this->indexes[$key] ??= new ParameterIndex($key, $this->definition($key)->workingDocument());
    }

    /** What the number is now, in the working copy: what an earlier operation made of it counts. */
    private function valueAt(string $key, Parameter $parameter): int|float
    {
        return DocumentPointer::get($this->documents[$key] ?? $this->definition($key)->workingDocument(), $parameter->path);
    }

    private function isRealMoney(Parameter $parameter): bool
    {
        return str_ends_with($parameter->key, '_minor');
    }

    private function realMoney(string $key, Parameter $parameter, int $number, ?string $id): BridgeIssue
    {
        return BridgeIssue::error('real_money_field', 'A price in real money is not changed through this block. Change it in the panel.', $number, $id, $this->where($key, $parameter));
    }

    /** @param  list<string>  $path */
    private function unknownPath(string $key, array $path, int $number, ?string $id): BridgeIssue
    {
        return BridgeIssue::error('unknown_path', DocumentPointer::join($path).' is not a number that can be changed in '.$key.'. Use a path the instruction lists.', $number, $id, $key.' '.DocumentPointer::join($path));
    }

    private function where(string $key, Parameter $parameter): string
    {
        return $key.' '.DocumentPointer::join($this->keyedPath($key, $parameter->path));
    }

    /**
     * A place as the instruction and the table of changes write it: a step of the tutorial by its key.
     *
     * @param  list<string|int>  $path
     * @return list<string|int>
     */
    private function keyedPath(string $key, array $path): array
    {
        return $key === TutorialBridge::KEY ? TutorialBridge::toKeys($this->documents[$key] ?? $this->definition($key)?->workingDocument() ?? [], $path) : $path;
    }

    /**
     * Whether a place is in a step that this block inserted, which no other operation of the block changes.
     *
     * @param  list<string|int>  $path
     */
    private function inInsertedStep(string $key, array $path): bool
    {
        $keyed = $this->keyedPath($key, $path);

        return $key === TutorialBridge::KEY && ($keyed[0] ?? null) === 'steps' && isset($keyed[1]) && isset($this->insertedSteps[(string) $keyed[1]]);
    }

    private function show(Parameter $parameter, int|float $raw): string
    {
        $shown = rtrim(rtrim(number_format($parameter->unit->display($raw), 4, '.', ''), '0'), '.');

        return $shown.($parameter->unit->suffix() === '' ? '' : ' '.$parameter->unit->suffix());
    }

    private function step(Parameter $parameter): string
    {
        return $this->show($parameter, $parameter->unit->raw($parameter->unit->step(true), true) ?: 1);
    }

    /** How many decimals a number is written with (86.0 has none, 86.25 has two). */
    private function decimals(int|float $value): int
    {
        $text = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        $dot = strpos($text, '.');

        return $dot === false ? 0 : strlen($text) - $dot - 1;
    }
}
