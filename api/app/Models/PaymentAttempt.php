<?php

declare(strict_types=1);

namespace App\Models;

use App\Games\BelongsToGame;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A driver chose a pack or the pass and was sent to a page of a payment provider to pay for it. The row is what the driver saw (the product, the coins or days, the
 * price in the currency) and the consents they gave, and a payment that comes back is checked against it. It belongs to the game it was made in; a provider reports a payment
 * without a game, so the webhook reads it with `withoutGlobalScopes()` and puts the game of the row in force.
 */
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use BelongsToGame, HasFactory;

    public const KIND_COINS = 'coins';

    public const KIND_PASS = 'pass';

    /** The driver was sent to pay and nothing came back yet. */
    public const STATE_CREATED = 'created';

    /** The payer finished the page, and the money has not arrived (a method that confirms later). */
    public const STATE_PROCESSING = 'processing';

    public const STATE_PAID = 'paid';

    public const STATE_FAILED = 'failed';

    public const STATE_EXPIRED = 'expired';

    /** What was paid is not what was offered (another amount or currency): nothing was given, and a person looks at it. */
    public const STATE_MISMATCH = 'mismatch';

    /** The states in which a payment can still come. */
    public const OPEN_STATES = [self::STATE_CREATED, self::STATE_PROCESSING];

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'grant_days' => 'integer',
            'amount_minor' => 'integer',
            'livemode' => 'boolean',
            'consent_at' => 'datetime',
            'waiver_at' => 'datetime',
            'adult_declared_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<Purchase, $this> */
    public function purchase(): HasOne
    {
        return $this->hasOne(Purchase::class, 'attempt_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->state, self::OPEN_STATES, true);
    }
}
