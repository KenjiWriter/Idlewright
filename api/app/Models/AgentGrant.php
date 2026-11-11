<?php

declare(strict_types=1);

namespace App\Models;

use App\Agent\AgentLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A consent that a person of the panel gave an AI assistant: one client of OAuth (the Claude app, say), one game, one level, until a time. The assistant works on the
 * person's behalf and never with more than the person may do in that game.
 *
 * @property AgentLevel $level
 */
class AgentGrant extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['level' => AgentLevel::class, 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    /** @return BelongsTo<Admin, $this> the person on whose behalf the assistant works */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Admin, $this> who ended the consent, when someone did */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'revoked_by');
    }

    /** @return HasMany<AgentAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(AgentAction::class);
    }

    /**
     * The consents that are in force: not ended by anyone and not past their time.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
