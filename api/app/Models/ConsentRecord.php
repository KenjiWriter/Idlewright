<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One time a driver accepted the terms and the privacy policy and confirmed their age, and which version of the texts they were shown. */
class ConsentRecord extends Model
{
    /** With the first sign-in linked to the account. */
    public const LINK = 'link';

    /** After the texts changed, on the screen that asks again. */
    public const RECONSENT = 'reconsent';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
