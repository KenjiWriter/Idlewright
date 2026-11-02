<?php

declare(strict_types=1);

namespace App\Models;

use App\Games\BelongsToGame;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The subscription of a device to the push messages of a game: where the push service takes a message for it (the address) and the keys that only that browser can read it with.
 * The address and the keys are secrets, so they are kept encrypted and never leave the server.
 */
class PushSubscription extends Model
{
    use BelongsToGame;

    /** After this many failures in a row the subscription is switched off: the device is gone or the browser does not take what is sent. */
    public const MAX_FAILURES = 5;

    protected $guarded = [];

    protected $hidden = ['endpoint', 'p256dh', 'auth'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'p256dh' => 'encrypted',
            'auth' => 'encrypted',
            'last_ok_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->disabled_at === null;
    }

    /** The hash that a subscription is looked up by. */
    public static function hashOf(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
