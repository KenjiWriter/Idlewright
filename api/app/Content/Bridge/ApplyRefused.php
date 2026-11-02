<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use RuntimeException;

/**
 * A block of changes that could not be kept as drafts, or an application that could not be undone, and why: `not_ok` (the block has errors), `changed_meanwhile` (a draft it
 * would go on top of changed after it was checked), `already_applied` (the same text was applied before, and a scale would add up), `invalid` (a document did not pass when it
 * was kept), `conflict` (a draft is not what the application made of it, so undoing it would lose later work), `not_applied` (there is nothing to undo).
 */
final class ApplyRefused extends RuntimeException
{
    /** @param  list<string>  $details  the documents or the points the reason is about */
    public function __construct(public readonly string $reason, string $message = '', public readonly array $details = [])
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
