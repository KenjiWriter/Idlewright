<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A one-time secret of an account flow. Only its hash is stored (see AccountTokens). */
class AccountToken extends Model
{
    public const CLAIM = 'claim';

    public const LOGIN = 'login';

    /** The state of a sign-in with a provider, from the start until the provider sends the person back. */
    public const OAUTH = 'oauth';

    /** What the provider said about the person, waiting for the browser that started the sign-in to finish it. */
    public const RESULT = 'oauth_result';

    /** Confirms the new address of the account: the link goes to the new address and changes nothing until it is used. */
    public const EMAIL_CHANGE = 'email_change';

    /** The link "this was not me" of the notice to the old address: it secures the account again. */
    public const EMAIL_REVOKE = 'email_revoke';

    /** Proves who the driver is, on the device that asked for it, before something that cannot be taken back. */
    public const REAUTH = 'reauth';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
