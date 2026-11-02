<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Definitions\JsonDocument;

/**
 * What came of looking at a block of changes: every number it changes (with the values before and after), the documents as they would be, and what is wrong or worth a
 * remark. Nothing has been written anywhere. A check with an error is not to be applied; one with only remarks may be.
 */
final readonly class ChangeCheck
{
    /**
     * @param  list<ResolvedChange>  $changes
     * @param  list<BridgeIssue>  $issues
     * @param  array<string, array<string, mixed>>  $documents  the documents that the block touches as they would be, by `rules:starter`, `part:slug`
     * @param  array<string, array{draft: bool, saved_at: string|null}>  $touched  whether each of them has a draft now, and when it was saved
     * @param  array<string, mixed>|null  $block  the block as it was written, when it could be read
     * @param  list<string>  $created  the documents of $documents that are new cars and parts (they are made, not changed)
     */
    public function __construct(
        public array $changes = [],
        public array $issues = [],
        public array $documents = [],
        public array $touched = [],
        public ?array $block = null,
        public array $created = [],
    ) {}

    /** @param  list<BridgeIssue>  $issues */
    public static function refused(array $issues): self
    {
        return new self(issues: $issues);
    }

    /** @param  list<BridgeIssue>  $more */
    public function withIssues(array $more): self
    {
        return new self($this->changes, [...$this->issues, ...$more], $this->documents, $this->touched, $this->block, $this->created);
    }

    /** @param  array<string, mixed>  $block */
    public function withBlock(array $block): self
    {
        return new self($this->changes, $this->issues, $this->documents, $this->touched, $block, $this->created);
    }

    /** @return list<BridgeIssue> */
    public function errors(): array
    {
        return array_values(array_filter($this->issues, static fn (BridgeIssue $issue): bool => $issue->isError()));
    }

    /** @return list<BridgeIssue> */
    public function warnings(): array
    {
        return array_values(array_filter($this->issues, static fn (BridgeIssue $issue): bool => ! $issue->isError()));
    }

    /** Whether the block may be applied: it was read, nothing in it is refused, and it changes something. */
    public function ok(): bool
    {
        return $this->block !== null && $this->errors() === [] && $this->changes !== [];
    }

    /**
     * The text of every document of the check as the content keeps it (by `rules/starter`, `parts/slug`, `vehicles/slug`), to be put over the content that is being worked on.
     *
     * @return array<string, string>
     */
    public function replacements(): array
    {
        $folders = ['rules' => 'rules', 'part' => 'parts', 'vehicle' => 'vehicles'];
        $replace = [];

        foreach ($this->documents as $key => $document) {
            [$kind, $slug] = explode(':', $key, 2);
            $replace[$folders[$kind].'/'.$slug] = JsonDocument::encode($document);
        }

        return $replace;
    }

    /** The message to give back to the AI: only what is refused, and how to answer. Empty when nothing is. */
    public function repairMessage(): string
    {
        $errors = $this->errors();

        if ($errors === []) {
            return '';
        }

        $lines = ['The block of changes was not accepted. Nothing was changed. Fix only the points below and send the whole block again, as one ride-changes block in the same format.', ''];

        foreach ($errors as $position => $issue) {
            $lines[] = ($position + 1).'. '.$issue->line();
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'changes' => array_map(static fn (ResolvedChange $change): array => $change->toArray(), $this->changes),
            'issues' => array_map(static fn (BridgeIssue $issue): array => $issue->toArray(), $this->issues),
            'touched' => $this->touched,
            'created' => $this->created,
        ];
    }
}
