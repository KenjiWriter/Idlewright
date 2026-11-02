<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** What a payment provider told the game, once whatever its id is (see the migration). It is about the whole network, so it has no game to be read in. */
class PaymentEvent extends Model
{
    /** @use HasFactory<PaymentEventFactory> */
    use HasFactory;

    public const STATE_RECEIVED = 'received';

    public const STATE_PROCESSED = 'processed';

    /** Nothing to do (an event about a payment the game never asked for). */
    public const STATE_IGNORED = 'ignored';

    /** It speaks of something that is not there yet (a refund before the payment): it is worked again by `payments:reprocess`. */
    public const STATE_DEFERRED = 'deferred';

    /** Refused on purpose: a payment that is not what was offered, or was made in the other mode. A person looks at it; the provider is told 200, because trying again would not change it. */
    public const STATE_REJECTED = 'rejected';

    /** An error on our side: the provider is told 500 and tries again. */
    public const STATE_FAILED = 'failed';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'tries' => 'integer',
            'data' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
