<?php

declare(strict_types=1);

namespace App\Models;

use App\Games\BelongsToGame;
use Illuminate\Database\Eloquent\Model;

/** A push message that was queued for a device, and whether the notification was opened (see the migration for how that is told). */
class PushDelivery extends Model
{
    use BelongsToGame;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }
}
