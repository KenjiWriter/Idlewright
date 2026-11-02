<?php

declare(strict_types=1);

namespace App\Models;

use App\Games\BelongsToGame;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchase with real money: what was bought (coins or the pass), for how much, and the reference of the payment at the provider. It is kept when the account
 * is erased (the shop has to keep its books), with the account cut off, and it keeps the game it was made in, so the books of a game stay whole.
 */
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use BelongsToGame, HasFactory;

    public const STATUS_PAID = 'paid';

    /** Part of the price was given back; the coins are the driver's still (a person decides what to do about it). */
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const STATUS_REFUNDED = 'refunded';

    /** The payer disputed the payment with their bank: what it gave is taken away until the dispute is decided. */
    public const STATUS_DISPUTED = 'disputed';

    /** What counts as money the shop kept (less what was given back). */
    public const KEPT_STATUSES = [self::STATUS_PAID, self::STATUS_PARTIALLY_REFUNDED];

    public const DISPUTE_OPEN = 'open';

    public const DISPUTE_WON = 'won';

    public const DISPUTE_LOST = 'lost';

    public const REVIEW_ACCOUNT_ERASED = 'account_erased';

    public const REVIEW_PARTIAL_REFUND = 'partial_refund';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'grant_days' => 'integer',
            'amount_minor' => 'integer',
            'refunded_amount_minor' => 'integer',
            'livemode' => 'boolean',
            'refunded_at' => 'datetime',
            'consent_at' => 'datetime',
            'waiver_at' => 'datetime',
            'adult_declared_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'attempt_id');
    }
}
