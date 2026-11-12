<?php

declare(strict_types=1);

namespace App\Agent;

use RuntimeException;

/**
 * Something that cannot be done with a plan, with the reason as a code the page says in the language of the panel and the assistant reads in English (`explain`):
 * a decision (`role`, `decided`, `superseded`, `outdated`, `measuring`, `measure_failed`), writing it (`not_approved`, `approval_expired`, `executed`, `changed`,
 * `apply_failed`), undoing it (`not_executed`, `undo_conflict`, `pack_in_progress`) or asking for a pack after it (`not_publisher`, `foreign_drafts`, `pack_ordered`,
 * `pack_busy`, `nothing_to_publish`).
 */
final class PlanRefused extends RuntimeException
{
    private const EXPLAIN = [
        'role' => 'The person you work for may not do this in this game.',
        'decided' => 'The plan has a decision already.',
        'superseded' => 'A newer version replaced this plan.',
        'outdated' => 'The drafts changed since the plan was submitted: count it again and submit a new version.',
        'measuring' => 'The server is still measuring the plan.',
        'measure_failed' => 'The server could not measure the plan.',
        'not_approved' => 'The plan is not approved: only a person approves it, on its page in the panel.',
        'approval_expired' => 'The approval of the plan expired (it holds for 24 hours): ask the operator to approve it again, or submit a new version.',
        'executed' => 'The plan was written as drafts already.',
        'changed' => 'The block no longer makes exactly the documents that were approved, so nothing was written.',
        'apply_failed' => 'The drafts could not be written; nothing was written.',
        'not_executed' => 'The plan was not written as drafts, so there is nothing to undo or to make a pack of.',
        'undo_conflict' => 'Someone worked on these drafts after the plan wrote them, so the writing is not undone.',
        'pack_in_progress' => 'A pack of these drafts is being made, so the writing is not undone now.',
        'not_publisher' => 'Only a consent of level 3 given by a publisher of the game may ask for a pack.',
        'foreign_drafts' => 'The game has drafts that do not come from this plan; the operator has to decide about them before a pack is made.',
        'pack_ordered' => 'A pack was already asked for after this plan.',
        'pack_busy' => 'Another pack is being made now; ask again when it is done.',
        'nothing_to_publish' => 'There is nothing in the drafts to make a pack of.',
    ];

    /**
     * @param  list<string>  $details  what the reason is about (the drafts that are not the plan's, the documents in conflict)
     */
    public function __construct(public readonly string $reason, public readonly array $details = [], string $more = '')
    {
        parent::__construct(trim("The plan cannot go on: {$reason}. {$more}"));
    }

    /** The reason in plain English for the assistant, with what it is about. */
    public function explain(): string
    {
        $line = self::EXPLAIN[$this->reason] ?? "The plan cannot go on ({$this->reason}).";

        return $this->details === [] ? $line : $line.' '.implode(', ', $this->details).'.';
    }
}
