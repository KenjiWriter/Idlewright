<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Models\ContentDefinition;

/**
 * The two operations of a block that are not about a number: setting the text of one language, and making a new car or part. They are part of `ChangeResolver` (they work on
 * its working copy of the documents, its issues and its scope) and are kept apart only to keep that class readable.
 *
 * A text is changed in one language and the others stay. It has no markup and no link, and a translation that is not in English or Polish is marked as unreviewed, because
 * nobody who speaks the language has read it. A new car or part is a copy of one that exists (without its picture) with whole fields replaced, or what the fields say; the
 * picture is never the AI's, and the slug is for good, so one that was ever used is refused.
 */
trait ResolvesTextsAndCreations
{
    /** @param  array<string, mixed>  $operation */
    private function text(int $number, ?string $id, array $operation): void
    {
        $key = $this->targetKey($operation['target']);
        $path = $this->pointerOf($operation['path'], $number, $id);

        if ($path === null || ! $this->usable($key, $number, $id)) {
            return;
        }

        $where = $key.' '.DocumentPointer::join($path);
        $sections = $this->sectionsOf($key);

        if ($sections !== null && ! in_array((string) $path[0], $sections, true)) {
            $this->issues[] = BridgeIssue::error('out_of_scope', 'This text is in a part of the rules that the instruction did not contain.', $number, $id, $where);

            return;
        }

        $definition = $this->definition($key);
        $document = $this->documents[$key] ?? $definition->workingDocument();
        $texts = DocumentPointer::get($document, $path);

        if (! TextIndex::isText($texts)) {
            $this->issues[] = BridgeIssue::error('not_text', 'There is no text in several languages at '.DocumentPointer::join($path).'. Give the place of one, such as /name.', $number, $id, $where);

            return;
        }

        $value = trim((string) $operation['value']);

        if (preg_match('/[<>]|https?:\/\/|www\./i', $value) === 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
            $this->issues[] = BridgeIssue::error('text_markup', 'A text has no markup, no link and no control characters.', $number, $id, $where);

            return;
        }

        $locale = (string) $operation['locale'];
        $before = $texts[$locale] ?? null;

        if ($before === $value) {
            $this->issues[] = BridgeIssue::warning('no_change', 'The text does not change.', $number, $id, $where);

            return;
        }

        $texts[$locale] = $value;
        $place = $key.' '.DocumentPointer::join([...$path, $locale]);

        if (isset($this->written[$place])) {
            $this->issues[] = BridgeIssue::warning('overwritten', "Op {$this->written[$place]} had written this text; this one writes it again.", $number, $id, $where);
        }

        $this->written[$place] = $number;
        $this->documents[$key] = DocumentPointer::set($document, $path, $texts);
        $this->touched[$key] ??= ['draft' => $definition->hasDraft(), 'saved_at' => $definition->draft_saved_at?->toIso8601String()];

        $published = $definition->publishedDocument();
        $was = $published === null ? null : DocumentPointer::get($published, [...$path, $locale]);

        $this->changes[] = new ResolvedChange(
            op: $number,
            opId: $id,
            document: $key,
            path: [...$path, $locale],
            label: ucfirst(str_replace('_', ' ', implode(' › ', array_map('strval', $path)))).' ('.$locale.')',
            unit: null,
            published: is_string($was) ? $was : null,
            before: $before,
            after: $value,
            notes: [...(in_array($locale, ['en', 'pl'], true) ? [] : ['unreviewed']), ...($definition->hasDraft() ? ['draft'] : [])],
            type: ResolvedChange::TEXT,
        );
    }

    /** @param  array<string, mixed>  $operation */
    private function create(int $number, ?string $id, array $operation): void
    {
        $kind = (string) $operation['kind'];
        $slug = (string) $operation['slug'];
        $key = "{$kind}:{$slug}";

        if ($this->scope !== null && ! in_array('kind:'.$kind, $this->scope, true)) {
            $this->issues[] = BridgeIssue::error('out_of_scope', "A {$kind} is not something the instruction this block answers held.", $number, $id, $key);

            return;
        }

        if (isset($this->documents[$key]) || ContentDefinition::query()->where('kind', $kind)->where('slug', $slug)->exists()) {
            $this->issues[] = BridgeIssue::error('slug_taken', "The slug {$slug} is taken, or was once: a slug is for good. Choose another.", $number, $id, $key);

            return;
        }

        $document = [];

        if (isset($operation['from'])) {
            $source = ContentDefinition::query()
                ->where('kind', $kind)
                ->where('slug', (string) $operation['from'])
                ->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)
                ->first();

            if ($source === null) {
                $this->issues[] = BridgeIssue::error('unknown_document', "There is no {$kind} {$operation['from']} to start from.", $number, $id, $key);

                return;
            }

            $document = $source->workingDocument();
            unset($document['image']);
        }

        foreach ((array) $operation['fields'] as $field => $value) {
            $document[$field] = $value;
        }

        $document['slug'] = $slug;

        $name = is_array($document['name'] ?? null) ? (string) ($document['name']['en'] ?? $document['name']['pl'] ?? $slug) : $slug;

        $this->documents[$key] = $document;
        $this->touched[$key] = ['draft' => false, 'saved_at' => null];
        $this->created[] = $key;

        $this->changes[] = new ResolvedChange(
            op: $number,
            opId: $id,
            document: $key,
            path: [],
            label: ucfirst($kind).' '.$slug.': '.$name,
            unit: null,
            published: null,
            before: null,
            after: $name,
            notes: ['new'],
            type: ResolvedChange::CREATE,
        );
    }

    /** @param  array<string, mixed>  $operation */
    private function add(int $number, ?string $id, array $operation): void
    {
        $key = $this->targetKey($operation['target']);
        $path = $this->pointerOf($operation['path'], $number, $id);

        if ($path === null || ! $this->usable($key, $number, $id)) {
            return;
        }

        $where = $key.' '.DocumentPointer::join($path);
        $sections = $this->sectionsOf($key);

        if ($sections !== null && ! in_array((string) $path[0], $sections, true)) {
            $this->issues[] = BridgeIssue::error('out_of_scope', 'This collection is in a part of the rules that the instruction did not contain.', $number, $id, $where);

            return;
        }

        $definition = $this->definition($key);
        $document = $this->documents[$key] ?? $definition->workingDocument();
        $collection = DocumentPointer::get($document, $path);
        $entries = SchemaCollections::isEmptyObject($collection) ? [] : $collection;

        if (! SchemaCollections::isCollection(explode(':', $key, 2)[1], $path) || ! is_array($entries)) {
            $this->issues[] = BridgeIssue::error('not_a_collection', DocumentPointer::join($path).' is not a collection of named entries that an entry can be added to. Use a place the COLLECTIONS line lists.', $number, $id, $where);

            return;
        }

        $name = (string) $operation['key'];

        if (array_key_exists($name, $entries)) {
            $this->issues[] = BridgeIssue::error('key_taken', "There is an entry called {$name} in this collection already.", $number, $id, $where);

            return;
        }

        if ($this->mentionsRealMoney($operation['value'])) {
            $this->issues[] = BridgeIssue::error('real_money_field', 'An entry has no price in real money here. Change those in the panel.', $number, $id, $where);

            return;
        }

        $entries[$name] = $operation['value'];
        $this->documents[$key] = DocumentPointer::set($document, $path, $entries);
        $this->touched[$key] ??= ['draft' => $definition->hasDraft(), 'saved_at' => $definition->draft_saved_at?->toIso8601String()];

        $translated = collect(TextIndex::of($operation['value']))->contains(fn (array $texts): bool => array_diff(array_keys($texts), ['en', 'pl']) !== []);
        $texts = TextIndex::of($operation['value']);
        $shown = (string) ($texts['/name']['en'] ?? $texts['/name']['pl'] ?? $name);

        $this->changes[] = new ResolvedChange(
            op: $number,
            opId: $id,
            document: $key,
            path: [...$path, $name],
            label: ucfirst(str_replace('_', ' ', implode(' › ', array_map('strval', [...$path, $name])))),
            unit: null,
            published: null,
            before: null,
            after: $shown,
            notes: ['new', ...($translated ? ['unreviewed'] : [])],
            type: ResolvedChange::ADD,
        );
    }

    /** Whether a value has, anywhere in it, a field that is a price in real money. */
    private function mentionsRealMoney(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $field => $inner) {
            if ((is_string($field) && str_ends_with($field, '_minor')) || $this->mentionsRealMoney($inner)) {
                return true;
            }
        }

        return false;
    }
}
