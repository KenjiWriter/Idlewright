<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Consent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a driver who has not accepted the terms as they are now (see Consent) from going on: every call is answered 403
 * `consent_required` until they accept, so a client that does not know about this stops instead of going on silently.
 *
 * Left open: reading who the driver is (the app finds out from it that the terms changed), the language, accepting, and the
 * two things a driver who does not agree must be able to do: take a copy of their data and delete the account.
 */
final class EnsureConsent
{
    /** The calls a driver can make before they accept, as method and path. */
    private const OPEN = [
        ['GET', 'api/v1/me'],
        ['PATCH', 'api/v1/me/locale'],
        ['POST', 'api/v1/account/consent'],
        ['GET', 'api/v1/account/export'],
        ['DELETE', 'api/v1/account'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->needsConsent() && ! $this->isOpen($request)) {
            throw app(Consent::class)->refusal($user);
        }

        return $next($request);
    }

    private function isOpen(Request $request): bool
    {
        foreach (self::OPEN as [$method, $path]) {
            if ($request->isMethod($method) && $request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
