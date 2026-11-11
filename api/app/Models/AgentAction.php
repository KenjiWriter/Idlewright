<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call of a tool by an AI assistant, as the trail keeps it.
 */
class AgentAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ok' => 'boolean', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<AgentGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(AgentGrant::class, 'agent_grant_id');
    }
}
