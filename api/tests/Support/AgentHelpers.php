<?php

declare(strict_types=1);

use App\Agent\AgentContext;
use App\Agent\AgentLevel;
use App\Agent\AgentPlans;
use App\Agent\AgentScratch;
use App\Models\Admin;
use App\Models\AgentGrant;
use App\Models\AgentPlan;
use App\Models\Game;
use Illuminate\Support\Str;
use phpseclib4\Crypt\RSA;

// Helpers of the tests of the AI assistant (tests/Feature/Agent): the keys of Passport, a client of OAuth as an app of Claude registers it, and the way through the page
// of consent to a key.

const AGENT_REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

/** A folder with a pair of keys for Passport, made once for every run of the tests (a key of 2048 bits is enough to sign keys that live for a test). */
function agentKeys(): string
{
    $folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ride-agent-test-keys';

    if (! is_file($folder.DIRECTORY_SEPARATOR.'oauth-private.key')) {
        @mkdir($folder, 0700, true);
        $key = RSA::createKey(2048);
        file_put_contents($folder.DIRECTORY_SEPARATOR.'oauth-private.key', (string) $key);
        file_put_contents($folder.DIRECTORY_SEPARATOR.'oauth-public.key', (string) $key->getPublicKey());
    }

    return $folder;
}

/** @return array<string, mixed> a client of OAuth, registered as an app of Claude registers itself */
function agentClient(): array
{
    return test()->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [AGENT_REDIRECT]])->assertCreated()->json();
}

function challengeOf(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

/**
 * @param  array<string, mixed>  $client
 * @param  array<string, string>  $extra  more of the request (`prompt` => `consent` asks the person again while a consent is in force)
 */
function authorizeUrl(array $client, string $challenge, array $extra = []): string
{
    return '/oauth/authorize?'.http_build_query([...$extra,
        'client_id' => $client['client_id'],
        'redirect_uri' => AGENT_REDIRECT,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-of-the-app',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);
}

/**
 * The person who is signed in goes through the page of consent and the app trades the code for a key.
 *
 * @param  array<string, mixed>  $client
 * @return array{0: string, 1: AgentGrant} the key and the consent
 */
function connectAgent(object $test, array $client, int $level = 2, int $hours = 8, string $game = 'raceidle', ?string $verifier = null, array $extra = []): array
{
    $verifier ??= str_repeat('a', 64);

    $test->get(authorizeUrl($client, challengeOf($verifier), $extra))->assertOk()->assertSee(__('admin.agent.consent.title'));

    $location = (string) $test->post('/oauth/authorize', ['auth_token' => session('authToken'), 'game' => $game, 'level' => $level, 'hours' => $hours])
        ->assertRedirect()->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($location)->toStartWith(AGENT_REDIRECT)->and($query['state'] ?? null)->toBe('state-of-the-app');

    $key = $test->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client['client_id'],
        'redirect_uri' => AGENT_REDIRECT,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json('access_token');

    return [(string) $key, AgentGrant::query()->latest('id')->firstOrFail()];
}

/** A consent in force for the default game, put in force as the connection does it. */
function agentGrantInForce(int $level = 1, string $role = Admin::ROLE_EDITOR): AgentGrant
{
    $admin = signedInAdmin($role);
    $grant = AgentGrant::query()->create([
        'admin_id' => $admin->id, 'game_id' => Game::query()->where('is_default', true)->value('id'), 'oauth_client_id' => (string) Str::uuid(),
        'client_name' => 'Claude', 'level' => AgentLevel::from($level), 'expires_at' => now()->addHour(),
    ]);
    app(AgentContext::class)->set($grant);

    return $grant;
}

/**
 * A plan of the consent in force, submitted as the assistant submits one: its scratch set with the operations, then the scratch submitted with these levers.
 *
 * @param  list<array<string, mixed>>  $ops
 * @param  list<string>  $levers
 * @param  array<string, mixed>  $more
 */
function agentPlanSubmitted(AgentGrant $grant, array $ops, array $levers = ['rules:starter#player'], array $more = []): AgentPlan
{
    expect(app(AgentScratch::class)->set($grant, 'Plan na probe', $ops)->ok())->toBeTrue();

    return app(AgentPlans::class)->submit($grant, [
        'title' => 'Poziom 5 wolniej', 'goal' => 'Poziom 5 ma przyjsc pozniej.', 'strategy' => 'Wyzszy prog XP poziomu 5.', 'levers' => $levers,
        'targets' => [['type' => 'level_at_minute', 'level' => 5, 'minute' => 75, 'plan' => ['minutes' => 240, 'tutorial' => false, 'travel' => false]]],
        ...$more,
    ]);
}

/** The operation that makes level 5 dearer: 1500 XP instead of 800. */
function dearerLevelFive(): array
{
    return ['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/player/early_xp/3', 'value' => 1500, 'unit' => 'plain'];
}
