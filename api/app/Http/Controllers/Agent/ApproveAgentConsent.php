<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent;

use App\Agent\AgentGrants;
use App\Agent\AgentLevel;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * A person of the panel says "Pozwól" on the page of consent of an AI assistant: the consent is written down (the game, the level, the hours they chose, on their behalf),
 * and only then Passport gives the client of the assistant its code. A person who is switched off, has no authenticator app, or may not give the level in that game gives
 * nothing.
 */
final class ApproveAgentConsent
{
    public function __invoke(Request $request, AgentGrants $grants, ApproveAuthorizationController $passport, ResponseInterface $psrResponse): Response
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        $input = $request->validate([
            'auth_token' => ['required', 'string'],
            'game' => ['required', 'string'],
            'level' => ['required', Rule::enum(AgentLevel::class)],
            'hours' => ['required', 'integer', 'min:1', 'max:'.(int) config('ride.agent.max_hours')],
        ]);

        // The request of authorization this page was made for: the one in the session, if the page is the one the session made.
        $expected = $request->session()->get('authToken');
        $serialized = $request->session()->get('authRequest');
        abort_unless(is_string($expected) && hash_equals($expected, (string) $input['auth_token']) && is_string($serialized), 403);

        $authRequest = unserialize($serialized, ['allowed_classes' => [AuthorizationRequest::class, Client::class, Scope::class, User::class]]);
        abort_unless($authRequest instanceof AuthorizationRequest, 403);

        $game = $admin->allowedGames()->firstWhere('slug', $input['game']);
        abort_if($game === null, 403, __('admin.agent.consent.refused'));

        try {
            $grants->open($admin, $game, (string) $authRequest->getClient()->getIdentifier(), (string) $authRequest->getClient()->getName(), AgentLevel::from((int) $input['level']), (int) $input['hours']);
        } catch (InvalidArgumentException) {
            abort(403, __('admin.agent.consent.refused'));
        }

        return $passport->approve($request, $psrResponse);
    }
}
