<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agent\AgentContext;
use App\Agent\AgentGrants;
use App\Games\CurrentGame;
use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a call of the AI assistant through only under a consent in force (after `auth:agent` has checked its key of OAuth): the consent of the person the key belongs to,
 * for the client the key was given to, not ended, not past its time, and not more than the person may still do in the game. Then the game of the consent is put in force
 * for the call. A key without such a consent is revoked, so the client of the assistant asks the person again.
 */
final class AgentConnection
{
    /** How often the time of the last use of a consent is written down. */
    private const TOUCH_EVERY_SECONDS = 60;

    public function __construct(private readonly AgentGrants $grants, private readonly AgentContext $context, private readonly CurrentGame $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('agent');
        $token = $admin instanceof Admin ? $admin->token() : null;

        if (! $admin instanceof Admin || ! $token instanceof AccessToken || ! $token->can(Registrar::OAUTH_SCOPE) || (string) $token->oauth_client_id === '') {
            return $this->refuse('The key has no consent behind it.');
        }

        $clientId = (string) $token->oauth_client_id;
        $grant = $this->grants->activeFor($admin->id, $clientId);
        // The person as the database has them now: switched off or given another role a moment ago counts at once.
        $person = $admin->fresh();

        if ($grant === null || ! $person instanceof Admin || ! $person->is_active || ! $person->canEnter($grant->game_id) || ! $grant->level->allowedFor($person->roleIn($grant->game_id))) {
            if ($grant !== null) {
                $this->grants->revoke($grant);
            }

            $this->grants->revokeKeys($admin->id, $clientId);

            return $this->refuse('The consent has ended. Ask the person to connect the assistant again in the panel.');
        }

        $this->current->set($grant->game);
        $this->context->set($grant);

        if ($grant->last_used_at === null || $grant->last_used_at->diffInSeconds(now()) > self::TOUCH_EVERY_SECONDS) {
            $grant->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function refuse(string $why): Response
    {
        return response()->json(['error' => 'invalid_token', 'error_description' => $why], 401);
    }
}
