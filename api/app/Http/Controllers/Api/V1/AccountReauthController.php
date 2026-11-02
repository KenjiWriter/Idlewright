<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AccountRefusedException;
use App\Http\Controllers\Controller;
use App\Mail\AccountLinkMail;
use App\Models\AccountIdentity;
use App\Models\AccountToken;
use App\Services\AccountLinks;
use App\Services\Reauth;
use App\Services\SocialProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The recent proof of who the driver is, which unlinking a sign-in needs (see Reauth). */
final class AccountReauthController extends Controller
{
    /** Whether this device has the proof now, for how long, and the ways to get it: the sign-ins the account has that work. */
    public function show(Request $request, Reauth $reauth, SocialProviders $providers): JsonResponse
    {
        $user = $request->user();
        $remaining = $reauth->remaining($user);

        return response()->json(['data' => [
            'valid' => $remaining > 0,
            'expires_in' => $remaining,
            'methods' => $user->identities()->orderBy('id')->pluck('provider')
                ->filter(fn (string $provider): bool => $provider === AccountIdentity::EMAIL || $providers->isEnabled($provider))
                ->values()->all(),
        ]]);
    }

    /**
     * Sends a link to the e-mail of the account. Whoever opens it, in any browser, gives the proof to the device that asked.
     *
     * @throws AccountRefusedException `reauth_no_method` (422) when the account has no e-mail to send it to
     */
    public function email(Request $request, AccountLinks $links): JsonResponse
    {
        $user = $request->user();
        $device = Reauth::deviceOf($user) ?? throw AccountRefusedException::forbidden('reauth_unavailable');
        $address = $user->identities()->where('provider', AccountIdentity::EMAIL)->value('subject')
            ?? throw AccountRefusedException::unprocessable('reauth_no_method');

        $links->send(AccountLinkMail::REAUTH, AccountToken::REAUTH, (string) $address, $user, $user->id, deviceTokenId: $device);

        return response()->json([
            'message' => __('account.reauth.sent', ['minutes' => intdiv(AccountLinks::ttlSeconds(), 60)]),
            'expires_in' => AccountLinks::ttlSeconds(),
        ], 202);
    }
}
