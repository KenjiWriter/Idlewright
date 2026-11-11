<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agent\AgentGrants;
use App\Models\Admin;
use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before the page of consent of an AI assistant: the panel speaks the language of the people who run it, and a client of OAuth that still holds keys of a person whose
 * consent has ended loses them. Passport skips the page of consent for a client that holds a key good for the same scopes; without this the person would not be asked
 * again, and the assistant would get keys that no consent stands behind.
 */
final class EndKeysWithoutConsent
{
    public function __construct(private readonly AgentGrants $grants) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locale::toLaravel(Locale::normalize((string) config('ride.admin_locale')) ?? Locale::default()));

        $admin = Auth::guard('admin')->user();
        $clientId = $request->query('client_id');

        if ($admin instanceof Admin && is_string($clientId) && $clientId !== '' && $this->grants->activeFor($admin->id, $clientId) === null) {
            $this->grants->revokeKeys($admin->id, $clientId);
        }

        return $next($request);
    }
}
