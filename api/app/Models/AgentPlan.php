<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plan of the AI assistant (see `App\Agent\AgentPlans`): submitted, approved, rejected or replaced by a newer version, with what the server measured of it.
 *
 * @property list<array<string, mixed>> $targets
 * @property list<array{option: string, why: string}>|null $alternatives
 * @property list<string> $levers
 * @property array<string, mixed> $block
 * @property array<string, string> $replacements
 * @property list<array<string, mixed>> $changes
 * @property list<string>|null $outside_levers
 * @property array<string, mixed>|null $measured
 */
class AgentPlan extends Model
{
    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** A newer version of the plan took its place. */
    public const SUPERSEDED = 'superseded';

    public const MEASURING = 'measuring';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'targets' => 'array', 'alternatives' => 'array', 'levers' => 'array', 'block' => 'array', 'replacements' => 'array', 'changes' => 'array',
            'outside_levers' => 'array', 'measured' => 'array', 'measured_at' => 'datetime', 'decided_at' => 'datetime', 'approved_until' => 'datetime', 'noted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Admin, $this> the person on whose behalf the assistant made the plan */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @return BelongsTo<AgentGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(AgentGrant::class, 'agent_grant_id');
    }

    /** @return BelongsTo<Admin, $this> who approved or rejected the plan */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    /** @return BelongsTo<Admin, $this> who left the last note for the assistant */
    public function noter(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'noted_by');
    }

    /** @return BelongsTo<self, $this> the version this one replaced */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_id');
    }

    /** Whether the plan is approved and the approval still holds. */
    public function isApprovedNow(): bool
    {
        return $this->status === self::APPROVED && $this->approved_until?->isFuture() === true;
    }
}
