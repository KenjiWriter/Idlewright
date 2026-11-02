<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AccountRefusedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Mail\AccountNoticeMail;
use App\Services\AccountLinks;
use App\Services\Accounts;
use App\Services\Reauth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountIdentityController extends Controller
{
    /**
     * Unlinks a way of signing in from the account: the e-mail, Google, Facebook or Apple. It needs a recent proof of who the driver
     * is (see Reauth), is refused for the last way the account has left, and a guest has nothing to unlink. What the provider
     * holds about the driver is not touched. The address of the account is told, in a notice with no link.
     *
     * @throws AccountRefusedException `identity_not_found` (404), `last_method` (422), `reauth_required` (403)
     */
    public function destroy(Request $request, string $provider, Accounts $accounts, Reauth $reauth, AccountLinks $links): JsonResponse
    {
        $user = $request->user();

        $notice = $accounts->unlink($user, $provider, $reauth->isFresh($user), Reauth::deviceOf($user));

        if ($notice !== null) {
            $links->notify(AccountNoticeMail::UNLINKED, $notice, $user, $provider);
        }

        return response()->json(['data' => new MeResource($user)]);
    }
}
