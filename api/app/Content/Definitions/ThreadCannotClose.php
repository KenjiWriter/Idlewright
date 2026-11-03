<?php

declare(strict_types=1);

namespace App\Content\Definitions;

use RuntimeException;

/**
 * Why the sections of a thread cannot be made into a loop: the page says it in the language of the person, from the key (`admin.thread.close_{reason}`) and its words.
 */
final class ThreadCannotClose extends RuntimeException
{
    /**
     * @param  string  $reason  `run` (a run has no loop), `incomplete` (a section lacks a number), `no_corner`, `few_straights`, `turn` or `lengths`
     * @param  array<string, string|int|float>  $replace  the words of the message
     */
    public function __construct(public readonly string $reason, public readonly array $replace = [])
    {
        parent::__construct("The thread cannot be closed: {$reason}.");
    }
}
