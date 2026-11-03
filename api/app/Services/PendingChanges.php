<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Definitions\DocumentDiff;
use App\Content\Definitions\ThreadDiff;
use App\Content\Studio\Parameter;
use App\Content\Studio\ParameterCatalog;
use App\Filament\Support\FieldHelp;
use App\Models\ContentDefinition;
use Illuminate\Support\Str;

/**
 * What the drafts of the game would change in the game: for each definition that has one, how it differs from what was published last. A definition that was never published is
 * new; one whose draft says what the published document says has nothing to show. The numbers of the rules are said the way the studio says them (where they are, in the
 * unit a person thinks in); everything else by its place in the document.
 */
final class PendingChanges
{
    public const NEW = 'new';

    public const CHANGED = 'changed';

    public const SAME = 'same';

    /** The most changes that are listed for one definition: the rest is counted. */
    public const LISTED = 200;

    /**
     * @return list<array{definition: ContentDefinition, state: string, count: int, changes: list<array{type: string, group: string|null, label: string, from: string|null, to: string|null}>}>
     */
    public function all(): array
    {
        $catalog = null;
        $entries = [];

        $definitions = ContentDefinition::query()->whereNotNull('draft')->with('latestVersion')->orderBy('kind')->orderBy('slug')->get();

        foreach ($definitions as $definition) {
            $draft = (array) $definition->draftDocument();
            $published = $definition->publishedDocument();

            if ($published === null) {
                $entries[] = ['definition' => $definition, 'state' => self::NEW, 'count' => 0, 'changes' => []];

                continue;
            }

            $changes = DocumentDiff::between($published, $draft);
            $numbers = $definition->kind === ContentDefinition::KIND_RULES ? $this->numbers($catalog ??= ParameterCatalog::ofTheGame(), $draft, $published) : [];
            $threads = [];

            // The sections of a thread are said as a person would say it (this one moved, that one changed), not place by place.
            if ($definition->kind === ContentDefinition::KIND_RULES) {
                [$changes, $threads] = $this->threads($changes, $published, $draft);
            }

            $entries[] = [
                'definition' => $definition,
                'state' => $changes === [] && $threads === [] ? self::SAME : self::CHANGED,
                'count' => count($changes) + count($threads),
                'changes' => array_slice([...array_map(fn (array $change): array => $this->described($change, $numbers), $changes), ...$threads], 0, self::LISTED),
            ];
        }

        return $entries;
    }

    /** How many definitions have a draft. */
    public function waiting(): int
    {
        return ContentDefinition::query()->whereNotNull('draft')->count();
    }

    /**
     * The changes of the sections of the threads taken out of the list of changes, and said by what was done to the sections instead.
     *
     * @param  list<array{pointer: string, type: string, from: mixed, to: mixed}>  $changes
     * @param  array<string, mixed>  $published
     * @param  array<string, mixed>  $draft
     * @return array{0: list<array{pointer: string, type: string, from: mixed, to: mixed}>, 1: list<array{type: string, group: string|null, label: string, from: string|null, to: string|null}>}
     */
    private function threads(array $changes, array $published, array $draft): array
    {
        $tracks = [];
        $rest = [];

        foreach ($changes as $change) {
            if (preg_match('#^/tracks/([^/]+)/thread/sections(/|$)#', $change['pointer'], $matches) === 1) {
                $tracks[str_replace(['~1', '~0'], ['/', '~'], $matches[1])] = true;
            } else {
                $rest[] = $change;
            }
        }

        $lines = [];

        foreach (array_keys($tracks) as $track) {
            $track = (string) $track;
            $group = __('admin.pending.thread_group', ['track' => $track]);

            foreach (ThreadDiff::between((array) ($published['tracks'][$track]['thread']['sections'] ?? []), (array) ($draft['tracks'][$track]['thread']['sections'] ?? [])) as $operation) {
                foreach ($this->threadLines($operation, $group) as $line) {
                    $lines[] = $line;
                }
            }
        }

        return [$rest, $lines];
    }

    /**
     * What was done to one section, as the rows of the table.
     *
     * @param  array{op: string, from: int|null, to: int|null, section: array<string, mixed>, fields: array<string, array{0: mixed, 1: mixed}>}  $operation
     * @return list<array{type: string, group: string|null, label: string, from: string|null, to: string|null}>
     */
    private function threadLines(array $operation, string $group): array
    {
        $summary = $this->sectionSummary($operation['section']);

        return match ($operation['op']) {
            ThreadDiff::MOVED => [['type' => 'changed', 'group' => $group, 'label' => __('admin.pending.thread_place', ['section' => $summary]), 'from' => (string) $operation['from'], 'to' => (string) $operation['to']]],
            ThreadDiff::ADDED => [['type' => 'added', 'group' => $group, 'label' => __('admin.pending.thread_added', ['number' => $operation['to']]), 'from' => null, 'to' => $summary]],
            ThreadDiff::REMOVED => [['type' => 'removed', 'group' => $group, 'label' => __('admin.pending.thread_removed', ['number' => $operation['from']]), 'from' => $summary, 'to' => null]],
            default => array_values(array_map(
                fn (string $field, array $values): array => [
                    'type' => 'changed',
                    'group' => $group,
                    'label' => __('admin.pending.thread_section_field', ['number' => $operation['to'], 'field' => $this->fieldName($field)]),
                    'from' => $this->fieldValue($field, $values[0]),
                    'to' => $this->fieldValue($field, $values[1]),
                ],
                array_keys($operation['fields']),
                $operation['fields'],
            )),
        };
    }

    /** @param  array<string, mixed>  $section */
    private function sectionSummary(array $section): string
    {
        return ($section['type'] ?? null) === 'corner'
            ? __('admin.thread.corner_label', ['radius' => $section['radius_m'] ?? '?', 'angle' => $section['angle_deg'] ?? '?'])
            : __('admin.thread.straight_label', ['length' => $section['length_m'] ?? '?']);
    }

    private function fieldName(string $field): string
    {
        $key = ['length_m' => 'length', 'radius_m' => 'radius', 'angle_deg' => 'angle', 'limit_kmh' => 'limit'][$field] ?? $field;

        return (string) (FieldHelp::entry('thread.'.$key)['label'] ?? $field);
    }

    private function fieldValue(string $field, mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $field === 'turn' || $field === 'surface' => FieldHelp::options('thread.'.$field, [(string) $value])[(string) $value] ?? (string) $value,
            default => (string) $value,
        };
    }

    /**
     * The numbers of a document of the rules by their pointer, with what the published document has for each.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $published
     * @return array<string, Parameter>
     */
    private function numbers(ParameterCatalog $catalog, array $draft, array $published): array
    {
        $numbers = [];

        foreach (array_keys($catalog->sections($draft)) as $section) {
            foreach ($catalog->parameters($draft, $section, $published) as $parameter) {
                $numbers[$parameter->pointer()] = $parameter;
            }
        }

        return $numbers;
    }

    /**
     * @param  array{pointer: string, type: string, from: mixed, to: mixed}  $change
     * @param  array<string, Parameter>  $numbers
     * @return array{type: string, group: string|null, label: string, from: string|null, to: string|null}
     */
    private function described(array $change, array $numbers): array
    {
        $number = $numbers[$change['pointer']] ?? null;

        if ($number !== null && (is_int($change['from']) || is_float($change['from'])) && (is_int($change['to']) || is_float($change['to']))) {
            $suffix = $number->unit->suffix();

            return [
                'type' => $change['type'],
                'group' => $number->group,
                'label' => $number->label,
                'from' => $number->unit->display($change['from']).$suffix,
                'to' => $number->unit->display($change['to']).$suffix,
            ];
        }

        return [
            'type' => $change['type'],
            'group' => null,
            'label' => $change['pointer'],
            'from' => $this->shown($change['from']),
            'to' => $this->shown($change['to']),
        ];
    }

    private function shown(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_object($value) => '{}',
            is_array($value) => $value === [] ? '[]' : Str::limit((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 120),
            default => Str::limit((string) $value, 120),
        };
    }
}
