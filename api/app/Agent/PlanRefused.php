<?php

declare(strict_types=1);

namespace App\Agent;

use RuntimeException;

/**
 * A decision on a plan that cannot be made, with the reason as a code the page says in the language of the panel: `role` (the person may not decide in this game),
 * `decided` (it was approved or rejected already), `superseded` (a newer version took its place), `outdated` (the drafts changed since it was made), `measuring` (the
 * server has not finished measuring it) or `measure_failed`.
 */
final class PlanRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The plan cannot be decided: {$reason}.");
    }
}
