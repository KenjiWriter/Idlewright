<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccountRefusedException;
use App\Exceptions\IdentityTakenException;
use App\Mail\AccountLinkMail;
use App\Mail\AccountNoticeMail;
use App\Models\AccountIdentity;
use App\Models\AccountToken;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Changing the e-mail of an account that signs in with one, so that the driver does not lose the garage when the address is gone,
 * and nobody who holds a stolen device can take the address over without the owner finding out.
 *
 * A link goes to the new address and changes nothing until it is used. The old address is told twice, when the change is asked
 * for and when it is made, and both notices carry "this was not me", which cancels the change, puts the old address back if it
 * was made, signs out every device and drops the recovery code (someone who held a device may have made one). The answer to the
 * driver is the same whether the new address already has a garage or not: in that case its owner gets the mail that says so.
 */
final class EmailChange
{
    public function __construct(
        private readonly AccountLinks $links,
        private readonly AccountTokens $tokens,
        private readonly Accounts $accounts,
    ) {}

    public static function cooldownDays(): int
    {
        return (int) config('ride.account.email_change_cooldown_days');
    }

    /**
     * Asks for the change.
     *
     * @throws AccountRefusedException `no_email` (409), `email_same` (422) or `email_change_cooldown` (422)
     */
    public function request(User $user, string $email): void
    {
        $current = $user->identities()->where('provider', AccountIdentity::EMAIL)->first()
            ?? throw AccountRefusedException::conflict('no_email');

        if ($current->subject === $email) {
            throw AccountRefusedException::unprocessable('email_same');
        }

        $this->guardCooldown($user);

        // Only the latest link works.
        $this->tokens->cancel($user->id, [AccountToken::EMAIL_CHANGE]);

        $owner = $this->accounts->userFor(AccountIdentity::EMAIL, $email);

        if ($owner === null) {
            $this->links->send(AccountLinkMail::CHANGE, AccountToken::EMAIL_CHANGE, $email, $user, $user->id);
        } else {
            $this->links->send(AccountLinkMail::EXISTING, AccountToken::LOGIN, $email, $owner, $owner->id);
        }

        $this->links->notify(AccountNoticeMail::CHANGE_ASKED, $current->subject, $user, Accounts::maskEmail($email) ?? '', withUndo: true);
    }

    /**
     * Makes the change a link of the new address asked for. The address is looked at again, since it may have been taken since.
     *
     * @throws IdentityTakenException when the address belongs to another account now
     * @throws AccountRefusedException `email_change_cooldown` (422), or `no_email` when the account has no e-mail sign-in any more
     */
    public function apply(AccountToken $token): User
    {
        $new = (string) $token->email;
        $old = null;

        try {
            $user = DB::transaction(function () use ($token, $new, &$old): User {
                $user = User::query()->whereKey($token->user_id)->lockForUpdate()->firstOrFail();
                $identity = $user->identities()->where('provider', AccountIdentity::EMAIL)->first()
                    ?? throw AccountRefusedException::conflict('no_email');

                $this->guardCooldown($user);

                $holder = AccountIdentity::query()->where('provider', AccountIdentity::EMAIL)->where('subject', $new)->first();

                if ($holder !== null && (int) $holder->user_id !== (int) $user->id) {
                    throw new IdentityTakenException(AccountIdentity::EMAIL);
                }

                $old = $identity->subject;

                if ($old !== $new) {
                    $identity->forceFill(['subject' => $new, 'email' => $new])->save();
                    $user->forceFill(['email' => $new, 'email_verified_at' => now(), 'email_changed_at' => now()])->save();

                    // The links that were sent to the old address no longer sign anybody in, and no other change that waits is made after this one.
                    $this->tokens->cancel($user->id, [AccountToken::CLAIM, AccountToken::LOGIN, AccountToken::REAUTH], $old);
                    $this->tokens->cancel($user->id, [AccountToken::EMAIL_CHANGE]);
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw new IdentityTakenException(AccountIdentity::EMAIL);
        }

        if ($old !== null && $old !== $new) {
            $this->links->notify(AccountNoticeMail::CHANGED, $old, $user, Accounts::maskEmail($new) ?? '', withUndo: true);
        }

        return $user;
    }

    /**
     * "This was not me": secures the account again. The change that waits is cancelled; one that was made is undone by putting the old
     * address back, when nobody has taken it meanwhile. Every device is signed out and the recovery code is dropped, so that nothing
     * the intruder holds still works. The owner gets back in with a link to the old address.
     *
     * @return bool whether the old address was put back
     */
    public function revoke(AccountToken $token): bool
    {
        $old = (string) $token->email;

        return DB::transaction(function () use ($token, $old): bool {
            $user = User::query()->whereKey($token->user_id)->lockForUpdate()->firstOrFail();

            $this->tokens->cancel($user->id, [AccountToken::EMAIL_CHANGE, AccountToken::CLAIM, AccountToken::LOGIN, AccountToken::REAUTH]);

            $restored = false;
            $identity = $user->identities()->where('provider', AccountIdentity::EMAIL)->first();

            if ($old !== '' && ($identity === null || $identity->subject !== $old)) {
                $holder = AccountIdentity::query()->where('provider', AccountIdentity::EMAIL)->where('subject', $old)->exists();

                if (! $holder) {
                    $identity === null
                        ? $user->identities()->create(['provider' => AccountIdentity::EMAIL, 'subject' => $old, 'email' => $old])
                        : $identity->forceFill(['subject' => $old, 'email' => $old])->save();

                    $user->forceFill(['email' => $old, 'email_verified_at' => now(), 'email_changed_at' => null])->save();
                    $restored = true;
                }
            }

            $user->tokens()->delete();
            $user->forceFill(['recovery_code_hash' => null, 'recovery_code_set_at' => null])->save();

            return $restored;
        });
    }

    private function guardCooldown(User $user): void
    {
        if ($user->email_changed_at === null) {
            return;
        }

        $until = $user->email_changed_at->copy()->addDays(self::cooldownDays());

        if ($until->isFuture()) {
            throw AccountRefusedException::unprocessable('email_change_cooldown', ['available_at' => $until->toIso8601String()], ['date' => $until->toDateString()]);
        }
    }
}
