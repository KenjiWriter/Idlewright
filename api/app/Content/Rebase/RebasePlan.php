<?php

declare(strict_types=1);

namespace App\Content\Rebase;

/**
 * What content:rebase would do: the drafts it would write, what happens to each document that moves, the places both sides changed in two ways, and what the
 * validation says of the content the drafts make. Nothing is written until it is applied.
 */
final readonly class RebasePlan
{
    /** Taken from the files: the panel had not changed the document. */
    public const FILES = 'files';

    /** Both changed it, in different places: the merge is the draft. */
    public const MERGED = 'merged';

    /** New in the files: a definition that is only a draft. */
    public const ADDED = 'added';

    /** A table that the files made again (a publication makes the bots, the pace and the units again anyway). */
    public const TABLE = 'table';

    /** The files do not have it any more: it stays in the panel, which archives it if it is to go. */
    public const KEPT = 'kept';

    /** The panel archived it and the files changed or added it: it stays archived. */
    public const ARCHIVED = 'archived';

    /**
     * @param  string  $base  what the merge started from, in words
     * @param  bool  $nothingNew  whether the files are the base: then there is nothing to do
     * @param  array<string, string>  $drafts  the text of the new draft of each document by key
     * @param  list<array{key: string, change: string}>  $rows  what happens to each document that moves
     * @param  list<array{key: string, path: string, base: mixed, panel: mixed, files: mixed}>  $conflicts
     * @param  ?string  $prefer  who wins the conflicts (null: nobody, and then a conflict stops the rebase)
     * @param  list<array{file: string, pointer: string, message: string}>  $errors  of the validation of the content with the drafts
     */
    public function __construct(
        public string $base,
        public bool $nothingNew,
        public array $drafts = [],
        public array $rows = [],
        public array $conflicts = [],
        public ?string $prefer = null,
        public array $errors = [],
    ) {}

    /** Whether it can be written: there is something new, the content passes, and every conflict has a winner. */
    public function canApply(): bool
    {
        return ! $this->nothingNew && $this->errors === [] && ($this->conflicts === [] || $this->prefer !== null);
    }
}
