<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A day of the streak: a day on which the goal was done (it pays), or that a shield saved. */
class StreakDay extends Model
{
    public const KIND_GOAL = 'goal';

    public const KIND_SHIELD = 'shield';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'streak' => 'integer',
            'cycle_day' => 'integer',
            'reward' => 'array',
            'claimed_at' => 'datetime',
            'auto_claimed' => 'boolean',
            'claim_result' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
