<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One transmutation of the rarity of a part: what it was, what it became and what it cost, for the audit and for the guard of a day. */
class PartTransmutation extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'part_id' => 'integer', 'from_rarity' => 'integer', 'to_rarity' => 'integer', 'coins' => 'integer', 'cash_cents' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
