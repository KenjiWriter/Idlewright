<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A study that the AI assistant had the simulators count (see `App\Agent\AgentJobs`): queued, running, done or failed, with what came of it.
 *
 * @property array<string, mixed> $options
 * @property array<string, string>|null $replacements
 * @property array<string, mixed>|null $result
 */
class AgentJob extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['options' => 'array', 'replacements' => 'array', 'result' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<AgentGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(AgentGrant::class, 'agent_grant_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::QUEUED, self::RUNNING], true);
    }
}
