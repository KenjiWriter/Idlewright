<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\TutorialSteps;
use App\Content\Vocabulary\MessageCatalog;
use InvalidArgumentException;

/**
 * What a block does to the tutorial that it does to no other document: a step is named by its key (the server turns it into its place in the list), a choice is set to
 * one of the values it allows, a sender is set to one plain text, a word of the app that the game has not said yet starts from the words of the app, and a new step is
 * inserted after the one it is to follow. Part of `ChangeResolver`, kept apart to keep that class readable.
 */
trait ResolvesTutorial
{
    /** @var array<string, int> the steps this block inserted, by key, with the operation that inserted each */
    private array $insertedSteps = [];

    /**
     * The keys of a place in the document as it is now, with a step named by its key turned into its place; null (and an issue) when the place names a step by its
     * number, a step that is not there, or a step this block inserted.
     *
     * @param  list<string>  $path
     * @return list<string|int>|null
     */
    private function stepPath(string $key, array $path, int $number, ?string $id): ?array
    {
        if ($key !== TutorialBridge::KEY) {
            return $path;
        }

        [$keys, $problem, $step] = TutorialBridge::toIndex($this->documents[$key] ?? $this->definition($key)->workingDocument(), $path);
        $where = $key.' '.DocumentPointer::join($path);

        if ($problem === 'step_by_index') {
            $this->issues[] = BridgeIssue::error('step_by_index', 'Address a step by its key, not by its number: the STEPS line lists the keys (/steps/refuel/sms/0).', $number, $id, $where);

            return null;
        }

        if ($problem === 'unknown_step') {
            $this->issues[] = BridgeIssue::error('unknown_step', "The tutorial has no step with the key {$step}. Use a key the STEPS line lists.", $number, $id, $where);

            return null;
        }

        if ($step !== null && isset($this->insertedSteps[$step])) {
            $this->issues[] = BridgeIssue::error('new_step_changed', "The step {$step} is inserted by op {$this->insertedSteps[$step]} of this block, and cannot be changed by another operation of it: put its values in the insert.", $number, $id, $where);

            return null;
        }

        return $keys;
    }

    /**
     * A `set` that gives a place a choice or a plain text instead of a number.
     *
     * @param  list<string>  $keyed  the place as the block wrote it (a step by its key)
     * @param  list<string|int>  $path  the place in the document
     * @param  array<string, mixed>  $operation
     */
    private function setChoiceOrWord(int $number, ?string $id, string $key, array $keyed, array $path, array $operation): void
    {
        $where = $key.' '.DocumentPointer::join($keyed);
        $choice = $key === TutorialBridge::KEY ? TutorialBridge::choiceAt($keyed) : null;
        $word = $key === TutorialBridge::KEY && TutorialBridge::isWord($keyed);
        $value = $operation['value'];

        if ($choice !== null) {
            if ($operation['unit'] !== 'choice') {
                $this->issues[] = BridgeIssue::error('unit_mismatch', 'This place is a choice: give one of the values the CHOICES line allows, with the unit choice.', $number, $id, $where);

                return;
            }

            $allowed = TutorialBridge::choicePatterns()[$choice];

            if (! in_array($value, $allowed, true)) {
                $this->issues[] = BridgeIssue::error('bad_choice', 'This choice takes one of '.json_encode($allowed, JSON_UNESCAPED_SLASHES).', not '.json_encode($value, JSON_UNESCAPED_SLASHES).'.', $number, $id, $where);

                return;
            }

            $this->putValue($number, $id, $key, $keyed, $path, $value, 'choice');

            return;
        }

        if ($word) {
            if ($operation['unit'] !== 'text' || ! is_string($value)) {
                $this->issues[] = BridgeIssue::error('unit_mismatch', 'A sender is set with the unit text and a text as the value (or in one language with the op text).', $number, $id, $where);

                return;
            }

            $text = trim($value);

            if ($text === '' || mb_strlen($text) > TutorialBridge::WORD_MAX || preg_match('/[\r\n]/', $text) === 1) {
                $this->issues[] = BridgeIssue::error('bad_text', 'A sender is one line of 1 to '.TutorialBridge::WORD_MAX.' characters.', $number, $id, $where);

                return;
            }

            if (preg_match('/[<>]|https?:\/\/|www\./i', $text) === 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text) === 1) {
                $this->issues[] = BridgeIssue::error('text_markup', 'A text has no markup, no link and no control characters.', $number, $id, $where);

                return;
            }

            $this->putValue($number, $id, $key, $keyed, $path, $text, 'text');

            return;
        }

        $this->issues[] = in_array($operation['unit'], ['choice', 'text'], true)
            ? BridgeIssue::error('not_a_choice', DocumentPointer::join($keyed).' is not a choice or a sender. Use a place the CHOICES line lists, a sender, or give a number in its unit.', $number, $id, $where)
            : BridgeIssue::error('not_a_number', DocumentPointer::join($keyed).' takes a number in '.$operation['unit'].', not '.json_encode($value, JSON_UNESCAPED_SLASHES).'.', $number, $id, $where);
    }

    /**
     * Puts a choice or a plain text in the working copy, the objects on the way made when the tutorial had not said them (a messenger with the phone, a part of it).
     *
     * @param  list<string>  $keyed
     * @param  list<string|int>  $path
     */
    private function putValue(int $number, ?string $id, string $key, array $keyed, array $path, string|bool|null $value, string $kind): void
    {
        $definition = $this->definition($key);
        $document = $this->documents[$key] ?? $definition->workingDocument();
        $where = $key.' '.DocumentPointer::join($keyed);
        $had = DocumentPointer::has($document, $path);
        $before = DocumentPointer::get($document, $path);

        if (! $had && $kind === 'choice') {
            // What the app does when the tutorial says nothing is what the place is now.
            $before = collect(TutorialBridge::choices($document))->first(fn (array $line): bool => $line[0] === DocumentPointer::join($keyed))[2] ?? null;
            $had = true;
        }

        if ($had && $before === $value) {
            $this->issues[] = BridgeIssue::warning('no_change', 'The value does not change.', $number, $id, $where);

            return;
        }

        if (($path[0] ?? null) === 'messenger' && ! is_array($document['messenger'] ?? null)) {
            $document['messenger'] = ['device' => TutorialSteps::DEFAULT_DEVICE];
        }

        try {
            $document = DocumentPointer::place($document, $path, $value);
        } catch (InvalidArgumentException $problem) {
            $this->issues[] = BridgeIssue::error('unknown_path', $problem->getMessage(), $number, $id, $where);

            return;
        }

        $place = $key.' '.DocumentPointer::join($path);

        if (isset($this->written[$place])) {
            $this->issues[] = BridgeIssue::warning('overwritten', "Op {$this->written[$place]} had written this place; this one writes it again.", $number, $id, $where);
        }

        $this->written[$place] = $number;
        $this->documents[$key] = $document;
        $this->touched[$key] ??= ['draft' => $definition->hasDraft(), 'saved_at' => $definition->draft_saved_at?->toIso8601String()];

        $published = $definition->publishedDocument();

        $this->changes[] = new ResolvedChange(
            op: $number,
            opId: $id,
            document: $key,
            path: $keyed,
            label: ucfirst(str_replace('_', ' ', implode(' › ', $keyed))),
            unit: null,
            published: $published !== null && DocumentPointer::has($published, $path) ? self::shown(DocumentPointer::get($published, $path)) : null,
            before: $had ? self::shown($before) : null,
            after: self::shown($value),
            notes: $definition->hasDraft() ? ['draft'] : [],
            type: ResolvedChange::TEXT,
        );
    }

    /**
     * The texts in the languages at a place of the tutorial that has none yet in that shape: a sender that is one plain text becomes that text in every language, and a
     * word of the app that the game has not said yet starts as the words of the app in every language. Null (and an issue) when there is nothing to start from.
     *
     * @param  list<string>  $keyed
     * @param  list<string|int>  $path
     * @param  array<string, mixed>  $document  the working copy, with the objects on the way made
     * @return array<string, string>|null
     */
    private function startOfTutorialText(string $key, array $keyed, array $path, mixed $texts, array &$document, int $number, ?string $id): ?array
    {
        if ($key !== TutorialBridge::KEY) {
            return null;
        }

        $where = $key.' '.DocumentPointer::join($keyed);

        if (is_string($texts) && TutorialBridge::isWord($keyed)) {
            return array_fill_keys(TextIndex::LOCALES, $texts);
        }

        $isLabel = count($keyed) === 3 && in_array($keyed[0], ['messenger', 'story'], true) && $keyed[1] === 'labels';

        if (! $isLabel || $texts !== null) {
            return null;
        }

        $message = ($keyed[0] === 'messenger' ? 'tutorial.' : 'story.').$keyed[2];
        $catalog = MessageCatalog::fromConfig();

        if (! $catalog->has($message)) {
            $this->issues[] = BridgeIssue::error('unknown_label', "The app has no word {$keyed[2]} in its {$keyed[0]}: use a word the TEXTS lines list.", $number, $id, $where);

            return [];
        }

        if ($keyed[0] === 'story' && ! is_array($document['story'] ?? null)) {
            $this->issues[] = BridgeIssue::error('no_story', 'The game tells the story of the app: its words are changed once the game has a story of its own, which is made in the panel.', $number, $id, $where);

            return [];
        }

        if ($keyed[0] === 'messenger' && ! is_array($document['messenger'] ?? null)) {
            $document['messenger'] = ['device' => TutorialSteps::DEFAULT_DEVICE];
        }

        $start = [];

        foreach (TextIndex::LOCALES as $locale) {
            $start[$locale] = (string) ($catalog->text($message, $locale) ?? $catalog->all()[$message]);
        }

        return $start;
    }

    /** @param  array<string, mixed>  $operation */
    private function insert(int $number, ?string $id, array $operation): void
    {
        $key = $this->targetKey($operation['target']);
        $path = $this->pointerOf($operation['path'], $number, $id);

        if ($path === null || ! $this->usable($key, $number, $id)) {
            return;
        }

        $where = $key.' '.DocumentPointer::join($path);

        if ($key !== TutorialBridge::KEY || $path !== ['steps']) {
            $this->issues[] = BridgeIssue::error('not_insertable', 'Only a step of the tutorial is inserted, at /steps of the tutorial. A new entry of a collection is added with add.', $number, $id, $where);

            return;
        }

        $definition = $this->definition($key);
        $document = $this->documents[$key] ?? $definition->workingDocument();
        $step = $operation['value'];
        $name = is_array($step) && is_string($step['key'] ?? null) ? $step['key'] : null;

        if ($name === null || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            $this->issues[] = BridgeIssue::error('bad_step', 'The new step needs a key: lower case words joined by dashes.', $number, $id, $where);

            return;
        }

        if (TutorialBridge::stepIndex($document, $name) !== null) {
            $this->issues[] = BridgeIssue::error('key_taken', "The tutorial has a step with the key {$name} already. Choose another.", $number, $id, $where);

            return;
        }

        if ($this->mentionsRealMoney($step)) {
            $this->issues[] = BridgeIssue::error('real_money_field', 'A step has no price in real money.', $number, $id, $where);

            return;
        }

        $after = $operation['after'] ?? null;
        $position = 0;

        if ($after !== null) {
            $index = TutorialBridge::stepIndex($document, (string) $after);

            if ($index === null) {
                $this->issues[] = BridgeIssue::error('unknown_step', "The tutorial has no step with the key {$after} to put the new one after. Use a key the STEPS line lists.", $number, $id, $where);

                return;
            }

            $position = $index + 1;
        }

        $steps = array_values((array) ($document['steps'] ?? []));
        array_splice($steps, $position, 0, [$step]);
        $document['steps'] = $steps;

        $this->documents[$key] = $document;
        // The places of the steps after the new one moved: the numbers are found again in the document as it is now.
        $this->indexes[$key] = new ParameterIndex($key, $document);
        $this->touched[$key] ??= ['draft' => $definition->hasDraft(), 'saved_at' => $definition->draft_saved_at?->toIso8601String()];
        $this->insertedSteps[$name] = $number;

        $texts = TextIndex::of($step);
        $translated = collect($texts)->contains(fn (array $languages): bool => array_diff(array_keys($languages), ['en', 'pl']) !== []);

        $this->changes[] = new ResolvedChange(
            op: $number,
            opId: $id,
            document: $key,
            path: ['steps', $name],
            label: 'Steps › '.$name.' ('.($after === null ? 'at the start' : 'after '.$after).')',
            unit: null,
            published: null,
            before: null,
            after: (string) ($texts['/goal']['en'] ?? $texts['/goal']['pl'] ?? $name),
            notes: ['new', ...($translated ? ['unreviewed'] : []), ...($definition->hasDraft() ? ['draft'] : [])],
            type: ResolvedChange::ADD,
        );
    }

    /** A choice or a plain text as the table of changes shows it. */
    private static function shown(mixed $value): ?string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
        };
    }
}
