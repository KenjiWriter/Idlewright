<?php

declare(strict_types=1);

namespace App\Services;

use App\Games\CurrentGame;
use App\Mail\AccountLinkMail;
use App\Mail\AccountNoticeMail;
use App\Models\AccountToken;
use App\Models\User;
use App\Support\Locale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the one-time link of an account flow by e-mail: a token for the purpose, and the e-mail with the address of
 * the web app that carries it. The e-mail is in the language of the account the link is about, and is queued, so a
 * slow mail server does not hold up the request.
 */
final class AccountLinks
{
    public function __construct(private readonly AccountTokens $tokens) {}

    public static function ttlSeconds(): int
    {
        return (int) config('ride.account.link_ttl_seconds');
    }

    /**
     * @param  string  $kind  the kind of e-mail, one of the constants of AccountLinkMail
     * @param  string  $purpose  what the link does when it is used, one of the constants of AccountToken
     * @param  User  $about  the account the e-mail speaks of: it sets the language and the name in the text
     * @param  int  $tokenUserId  the account the token is for
     * @param  int|null  $deviceTokenId  the device that asked, for a link that proves who the driver is on that device only
     */
    public function send(string $kind, string $purpose, string $email, User $about, int $tokenUserId, ?CarbonInterface $consentAt = null, ?int $deviceTokenId = null): void
    {
        $secret = $this->tokens->issue($purpose, self::ttlSeconds(), [
            'user_id' => $tokenUserId,
            'email' => $email,
            'consent_at' => $consentAt,
            'device_token_id' => $deviceTokenId,
        ]);

        $url = app(CurrentGame::class)->webUrl().'/account/confirm?token='.$secret;

        Mail::to($email)
            ->locale(Locale::toLaravel(Locale::normalize($about->locale) ?? Locale::default()))
            ->queue(new AccountLinkMail($kind, $url, $about->name, intdiv(self::ttlSeconds(), 60)));
    }

    /**
     * Tells an address of the account that something was done to it. A notice that can be answered with "this was not me" carries
     * a link for that, which works for `ride.account.email_undo_days` days and secures the account again (see EmailChange::revoke).
     *
     * @param  string  $email  the address the notice goes to; it is also what the link restores
     * @param  string  $detail  what the notice names: the new address (masked), or the way of signing in that was removed
     */
    public function notify(string $kind, string $email, User $about, string $detail, bool $withUndo = false): void
    {
        $days = (int) config('ride.account.email_undo_days');
        $url = null;

        if ($withUndo) {
            $secret = $this->tokens->issue(AccountToken::EMAIL_REVOKE, $days * 86_400, ['user_id' => $about->id, 'email' => $email]);
            $url = app(CurrentGame::class)->webUrl().'/account/secure?token='.$secret;
        }

        Mail::to($email)
            ->locale(Locale::toLaravel(Locale::normalize($about->locale) ?? Locale::default()))
            ->queue(new AccountNoticeMail($kind, $about->name, $detail, $url, $days));
    }
}
