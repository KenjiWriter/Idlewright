<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccountRefusedException;
use App\Exceptions\IdentityTakenException;
use App\Exceptions\ProviderAlreadyLinkedException;
use App\Models\AccountIdentity;
use App\Models\AccountToken;
use App\Models\ConsentRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Links the ways of signing in to an account. Linking the first one is what secures a guest account: from then on
 * the account does not expire, and it can be reached on another device without a recovery code.
 */
final class Accounts
{
    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * What linking a sign-in asks of the request: the terms accepted and the age confirmed, unless the account has
     * done that already. Once is enough; a driver who adds a second sign-in is not asked the same again.
     *
     * @return array<string, list<string>>
     */
    public static function consentRules(User $user): array
    {
        return $user->hasConsented() ? [] : ['accept_terms' => ['accepted'], 'confirm_age' => ['accepted']];
    }

    /** The moment to record as the consent of a link that is being asked for: none when it is on record already. */
    public static function consentAt(User $user): ?CarbonInterface
    {
        return $user->hasConsented() ? null : now();
    }

    /** The account an identity belongs to, if any. */
    public function userFor(string $provider, string $subject): ?User
    {
        return User::query()
            ->whereIn('id', AccountIdentity::query()->where('provider', $provider)->where('subject', $subject)->select('user_id'))
            ->first();
    }

    /**
     * Links an identity to an account, which secures a guest account, and saves when the driver accepted the terms
     * (and which version of them) and confirmed their age if that is given. Linking the identity the account already has changes nothing.
     * Only an e-mail identity is also the e-mail of the account, because only an e-mail was proven to be theirs by
     * a link; what a provider says about an e-mail is kept for display and never used to find an account.
     *
     * @throws IdentityTakenException when the identity belongs to another account
     * @throws ProviderAlreadyLinkedException when the account already has another identity of this provider
     */
    public function link(User $user, string $provider, string $subject, ?string $email = null, ?CarbonInterface $consentAt = null): AccountIdentity
    {
        try {
            return DB::transaction(function () use ($user, $provider, $subject, $email, $consentAt): AccountIdentity {
                // Linking two identities for one account at once must not race past the checks below.
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $existing = AccountIdentity::query()->where('provider', $provider)->where('subject', $subject)->first();

                if ($existing !== null) {
                    return (int) $existing->user_id === (int) $locked->id ? $existing : throw new IdentityTakenException($provider);
                }

                if ($locked->identities()->where('provider', $provider)->exists()) {
                    throw new ProviderAlreadyLinkedException($provider);
                }

                $identity = $locked->identities()->create(['provider' => $provider, 'subject' => $subject, 'email' => $email]);

                // The first consent is the one the texts in force were accepted with; it is on record next to the ones that may follow.
                if ($consentAt !== null && $locked->terms_accepted_at === null) {
                    $locked->consents()->create(['terms_version' => config('ride.legal.version'), 'source' => ConsentRecord::LINK, 'accepted_at' => $consentAt]);
                }

                $locked->forceFill(array_filter([
                    'claimed_at' => $locked->claimed_at === null ? now() : null,
                    'terms_accepted_at' => $consentAt !== null && $locked->terms_accepted_at === null ? $consentAt : null,
                    'age_confirmed_at' => $consentAt !== null && $locked->age_confirmed_at === null ? $consentAt : null,
                    // The texts the driver accepted are the ones in force now, which is also what they were shown.
                    'terms_version' => $consentAt !== null && $locked->terms_accepted_at === null ? config('ride.legal.version') : null,
                    'email' => $provider === AccountIdentity::EMAIL ? $subject : null,
                    'email_verified_at' => $provider === AccountIdentity::EMAIL ? now() : null,
                ]))->save();

                $user->setRawAttributes($locked->getAttributes(), true);

                return $identity;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request linked the same identity, or took the e-mail, between the check and the insert.
            throw new IdentityTakenException($provider);
        }
    }

    /**
     * Unlinks a way of signing in. The one that is left is never unlinked, and a driver who has not proved who they are a
     * moment ago (see Reauth) cannot unlink any. The checks are made with the account locked, so that two requests at once cannot
     * between them leave an account with no way in.
     *
     * Unlinking the e-mail clears the address of the account and makes the links that were sent to it useless, since the address
     * is no longer the account's. The devices that signed in with the way that goes are signed out, except the one that asked.
     * What the provider holds about the driver is theirs and the provider's: nothing is deleted there.
     *
     * @param  bool  $proven  whether the driver proved who they are a moment ago, on this device
     * @param  int|null  $keepDevice  the device the request comes from: it stays signed in
     * @return string|null the address a notice of the change goes to: the e-mail of the account, or the one just unlinked
     *
     * @throws AccountRefusedException `identity_not_found` (404), `last_method` (422) or `reauth_required` (403)
     */
    public function unlink(User $user, string $provider, bool $proven, ?int $keepDevice): ?string
    {
        $notice = DB::transaction(function () use ($user, $provider, $proven, $keepDevice): ?string {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $identity = $locked->identities()->where('provider', $provider)->first()
                ?? throw AccountRefusedException::notFound('identity_not_found');

            if ($locked->identities()->count() <= 1) {
                throw AccountRefusedException::unprocessable('last_method');
            }

            if (! $proven) {
                throw AccountRefusedException::forbidden('reauth_required');
            }

            $address = $provider === AccountIdentity::EMAIL ? $identity->subject : $locked->identities()->where('provider', AccountIdentity::EMAIL)->value('subject');

            $identity->delete();

            if ($provider === AccountIdentity::EMAIL) {
                $locked->forceFill(['email' => null, 'email_verified_at' => null])->save();

                (new AccountTokens)->cancel($locked->id, [AccountToken::CLAIM, AccountToken::LOGIN, AccountToken::EMAIL_CHANGE, AccountToken::REAUTH], $address);
            }

            $locked->tokens()->where('name', "{$provider}-login")->when($keepDevice !== null, fn ($query) => $query->where('id', '!=', $keepDevice))->delete();

            return is_string($address) && $address !== '' ? $address : null;
        });

        $user->refresh();

        return $notice;
    }

    /** turbo@example.com becomes t***@example.com. */
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
