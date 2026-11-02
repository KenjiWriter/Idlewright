<?php

declare(strict_types=1);

use App\Mail\AccountLinkMail;
use App\Mail\AccountNoticeMail;
use App\Models\AccountIdentity;
use App\Models\AccountToken;
use App\Models\User;
use App\Services\Reauth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(fn () => Mail::fake());

/** An account that signs in with an e-mail and with Google. */
function emailAndGoogleAccount(string $email = 'turbo@example.com', string $subject = 'google-1098765'): User
{
    $user = User::factory()->create(['email' => $email]);
    identityOf($user, AccountIdentity::EMAIL, $email, $email);
    identityOf($user, AccountIdentity::GOOGLE, $subject, 'turbo@gmail.com');

    return $user;
}

function unlinkSignIn(string $provider, array $headers): TestResponse
{
    return test()->deleteJson("/api/v1/account/identities/{$provider}", [], $headers);
}

/** The driver opens the link of the e-mail that proves who they are, in a browser that has no token of its own. */
function proveByEmail(array $headers): void
{
    test()->postJson('/api/v1/account/reauth/email', [], $headers)->assertStatus(202);

    confirmLink(secretOf(Mail::queued(AccountLinkMail::class)->last()))->assertOk()->assertJsonPath('purpose', 'reauth');
}

/** The driver signs in again with a provider the account has, and the provider says who they are. */
function proveByProvider(string $provider, string $subject, array $headers): TestResponse
{
    withProviders(providerKeys());
    config(['ride.web_url' => 'https://web.example']);

    $start = test()->postJson("/api/v1/account/reauth/{$provider}/start", [], $headers)->assertOk();
    providerSays($provider, ['id' => $subject]);
    $ticket = fragmentOf(comesBackFrom($provider, stateOf($start->json('url'))))['ticket'];

    app('auth')->forgetGuards();

    return test()->postJson('/api/v1/auth/complete', ['ticket' => $ticket, 'verifier' => $start->json('verifier')], $headers);
}

describe('unlinking a sign-in', function () {
    it('requires a token', function () {
        $this->deleteJson('/api/v1/account/identities/google')->assertUnauthorized();
        $this->getJson('/api/v1/account/reauth')->assertUnauthorized();
        $this->postJson('/api/v1/account/reauth/email')->assertUnauthorized();
    });

    it('has nothing to unlink for a guest', function () {
        $headers = bearerFor(User::factory()->guest()->create());

        unlinkSignIn('google', $headers)->assertNotFound()->assertJsonPath('code', 'identity_not_found');
    });

    it('does not know a sign-in the account does not have', function () {
        $user = User::factory()->create();
        identityOf($user, AccountIdentity::EMAIL, 'turbo@example.com', 'turbo@example.com');
        identityOf($user, AccountIdentity::GOOGLE, 'g-1');

        unlinkSignIn('facebook', bearerFor($user))->assertNotFound()->assertJsonPath('code', 'identity_not_found');
    });

    it('does not take a provider that does not exist', function () {
        unlinkSignIn('twitter', bearerFor(emailAndGoogleAccount()))->assertNotFound();
    });

    it('never unlinks the last sign-in, however well the driver proved who they are', function () {
        $user = User::factory()->create();
        identityOf($user, AccountIdentity::GOOGLE, 'g-1');
        $headers = bearerFor($user);

        withProviders(providerKeys());
        proveByProvider('google', 'g-1', $headers)->assertOk();

        unlinkSignIn('google', $headers)
            ->assertStatus(422)
            ->assertJsonPath('code', 'last_method')
            ->assertJsonPath('message', 'This is the last sign-in of this garage, so it cannot be unlinked. Add another one first.');

        expect($user->identities()->count())->toBe(1);
    });

    it('leaves the second to last sign-in, and then refuses the last', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        proveByEmail($headers);

        unlinkSignIn('google', $headers)->assertOk();
        unlinkSignIn('email', $headers)->assertStatus(422)->assertJsonPath('code', 'last_method');

        expect($user->identities()->pluck('provider')->all())->toBe(['email']);
    });

    it('asks to prove who the driver is first, and unlinks nothing before', function () {
        $user = emailAndGoogleAccount();

        unlinkSignIn('google', bearerFor($user))
            ->assertForbidden()
            ->assertJsonPath('code', 'reauth_required')
            ->assertJsonPath('message', 'Confirm that it is you before you unlink a sign-in.');

        expect($user->identities()->count())->toBe(2);
    });

    it('unlinks a provider after the proof, answers with the profile and keeps the data of the provider alone', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        proveByEmail($headers);

        unlinkSignIn('google', $headers)
            ->assertOk()
            ->assertJsonPath('data.identities', [['provider' => 'email', 'email' => 't***@example.com']]);

        expect($user->identities()->where('provider', 'google')->exists())->toBeFalse()
            ->and($user->fresh()->email)->toBe('turbo@example.com');
    });

    it('tells the address of the account, and says nothing of the link', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        proveByEmail($headers);

        unlinkSignIn('google', $headers)->assertOk();

        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo('turbo@example.com') && $mail->kind === 'unlinked' && $mail->detail === 'google' && $mail->url === null);
    });

    it('clears the address of the account when the e-mail is unlinked, and tells that address', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        proveByEmail($headers);

        unlinkSignIn('email', $headers)->assertOk()->assertJsonPath('data.identities.0.provider', 'google');

        $fresh = $user->fresh();
        expect($fresh->email)->toBeNull()->and($fresh->email_verified_at)->toBeNull();
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo('turbo@example.com') && $mail->kind === 'unlinked' && $mail->detail === 'email');
    });

    it('frees the address of an e-mail that was unlinked for any other account', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        proveByEmail($headers);
        unlinkSignIn('email', $headers)->assertOk();

        $newcomer = User::factory()->guest()->create();
        askForEmailLink($newcomer, ['email' => 'turbo@example.com'])->assertStatus(202);

        Mail::assertQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->kind === 'claim' && $mail->hasTo('turbo@example.com'));
    });

    it('makes the links that were sent to the e-mail useless when it is unlinked', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);

        $this->postJson('/api/v1/account/login-link', ['email' => 'turbo@example.com'])->assertStatus(202);
        $loginLink = secretOf(Mail::queued(AccountLinkMail::class)->last());

        proveByEmail($headers);
        unlinkSignIn('email', $headers)->assertOk();

        confirmLink($loginLink)->assertStatus(422);
    });

    it('signs out the devices that came in with the sign-in that goes, and keeps the others and the one that asked', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        $user->createToken('google-login');
        $user->createToken('google-login');
        $user->createToken('email-login');
        $user->createToken('guest-device');
        proveByEmail($headers);

        unlinkSignIn('google', $headers)->assertOk();

        expect($user->tokens()->pluck('name')->sort()->values()->all())->toBe(['email-login', 'guest-device', 'test']);
    });

    it('keeps the device that asked even when it came in with the sign-in that goes', function () {
        $user = emailAndGoogleAccount();
        $headers = [...bearerFor($user)];
        // The device that asks signed in with Google itself.
        $user->tokens()->update(['name' => 'google-login']);
        $user->createToken('google-login');
        proveByEmail($headers);

        unlinkSignIn('google', $headers)->assertOk();

        expect($user->tokens()->count())->toBe(1);
    });
});

describe('the proof of who the driver is', function () {
    it('says that there is none, and with what it can be had', function () {
        $user = emailAndGoogleAccount();
        withProviders(providerKeys());

        $this->getJson('/api/v1/account/reauth', bearerFor($user))->assertOk()->assertExactJson(['data' => [
            'valid' => false,
            'expires_in' => 0,
            'methods' => ['email', 'google'],
        ]]);
    });

    it('does not offer a provider the server has no keys for', function () {
        $user = emailAndGoogleAccount();
        withProviders([]);

        $this->getJson('/api/v1/account/reauth', bearerFor($user))->assertOk()->assertJsonPath('data.methods', ['email']);
    });

    it('is given by the link to the e-mail of the account, to the device that asked and no other', function () {
        $user = emailAndGoogleAccount();
        $asking = bearerFor($user);
        $other = bearerFor($user);

        proveByEmail($asking);

        $this->getJson('/api/v1/account/reauth', $asking)->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.expires_in', 600);
        // The guard of the test keeps the driver of the earlier request; another device is a request with no memory of it.
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/account/reauth', $other)->assertOk()->assertJsonPath('data.valid', false);

        app('auth')->forgetGuards();
        unlinkSignIn('google', $other)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        expect($user->identities()->count())->toBe(2);
    });

    it('sends the link to the address of the account only, and makes a token that is good for the proof and nothing else', function () {
        $user = emailAndGoogleAccount();

        $this->postJson('/api/v1/account/reauth/email', [], bearerFor($user))
            ->assertStatus(202)
            ->assertJsonPath('expires_in', 1_800);

        Mail::assertQueued(AccountLinkMail::class, 1);
        Mail::assertQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('turbo@example.com') && $mail->kind === 'reauth' && str_starts_with($mail->url, config('ride.web_url').'/account/confirm?token='));

        $token = AccountToken::query()->where('purpose', AccountToken::REAUTH)->sole();
        expect($token->user_id)->toBe($user->id)->and($token->device_token_id)->not->toBeNull();
    });

    it('cannot be had by e-mail when the account has none', function () {
        $user = User::factory()->create();
        identityOf($user, AccountIdentity::GOOGLE, 'g-1');

        $this->postJson('/api/v1/account/reauth/email', [], bearerFor($user))->assertStatus(422)->assertJsonPath('code', 'reauth_no_method');
        Mail::assertNothingQueued();
    });

    it('lasts ten minutes', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        proveByEmail($headers);

        Carbon::setTestNow(now()->addMinutes(9));
        $this->getJson('/api/v1/account/reauth', $headers)->assertJsonPath('data.valid', true);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->getJson('/api/v1/account/reauth', $headers)->assertJsonPath('data.valid', false);
        unlinkSignIn('google', $headers)->assertForbidden()->assertJsonPath('code', 'reauth_required');

        Carbon::setTestNow();
    });

    it('is not given by a link that was used, expired or never existed', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        $this->postJson('/api/v1/account/reauth/email', [], $headers)->assertStatus(202);
        $secret = secretOf(Mail::queued(AccountLinkMail::class)->last());

        confirmLink($secret)->assertOk();
        Carbon::setTestNow(now()->addMinutes(11));
        confirmLink($secret)->assertStatus(422);
        confirmLink('never-issued')->assertStatus(422);

        $this->postJson('/api/v1/account/reauth/email', [], $headers)->assertStatus(202);
        $late = secretOf(Mail::queued(AccountLinkMail::class)->last());
        Carbon::setTestNow(now()->addMinutes(31));
        confirmLink($late)->assertStatus(422);
        $this->getJson('/api/v1/account/reauth', $headers)->assertJsonPath('data.valid', false);

        Carbon::setTestNow();
    });

    it('is not given by the link of a device that was signed out', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        $this->postJson('/api/v1/account/reauth/email', [], $headers)->assertStatus(202);
        $secret = secretOf(Mail::queued(AccountLinkMail::class)->last());

        $user->tokens()->delete();
        confirmLink($secret)->assertOk();

        expect(app(Reauth::class)->remaining($user))->toBe(0);
    });

    it('is given by signing in again with a provider the account has', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);

        proveByProvider('google', 'google-1098765', $headers)->assertOk()->assertJsonPath('purpose', 'reauth');

        unlinkSignIn('email', $headers)->assertOk();
    });

    it('is not given by another account of the same provider', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);

        proveByProvider('google', 'somebody-elses-google', $headers)->assertForbidden()->assertJsonPath('code', 'reauth_mismatch');

        unlinkSignIn('email', $headers)->assertForbidden()->assertJsonPath('code', 'reauth_required');
    });

    it('is not given by a provider flow started for another driver', function () {
        $user = emailAndGoogleAccount();
        $thief = emailAndGoogleAccount('thief@example.com', 'google-thief');
        identityOf($thief, AccountIdentity::FACEBOOK, 'fb-1');

        withProviders(providerKeys());
        $start = $this->postJson('/api/v1/account/reauth/google/start', [], bearerFor($user))->assertOk();
        providerSays('google', ['id' => 'google-1098765']);
        $ticket = fragmentOf(comesBackFrom('google', stateOf($start->json('url'))))['ticket'];
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/auth/complete', ['ticket' => $ticket, 'verifier' => $start->json('verifier')], bearerFor($thief))
            ->assertForbidden()
            ->assertJsonPath('code', 'wrong_account');
    });

    it('is not started with a provider the account does not have, or one the server does not offer', function () {
        $user = emailAndGoogleAccount();
        withProviders(providerKeys());

        $this->postJson('/api/v1/account/reauth/facebook/start', [], bearerFor($user))->assertStatus(422)->assertJsonPath('code', 'reauth_no_method');

        withProviders([]);
        $this->postJson('/api/v1/account/reauth/google/start', [], bearerFor($user))->assertNotFound()->assertJsonPath('code', 'provider_unavailable');
    });

    it('cannot be finished by a browser that did not start it', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);
        withProviders(providerKeys());
        $start = $this->postJson('/api/v1/account/reauth/google/start', [], $headers)->assertOk();
        providerSays('google', ['id' => 'google-1098765']);
        $ticket = fragmentOf(comesBackFrom('google', stateOf($start->json('url'))))['ticket'];

        $this->postJson('/api/v1/auth/complete', ['ticket' => $ticket, 'verifier' => 'not-the-verifier'], $headers)->assertStatus(422);
        $this->getJson('/api/v1/account/reauth', $headers)->assertJsonPath('data.valid', false);
    });
});

describe('limits', function () {
    it('allows ten unlinkings an hour from an account and no more', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);

        $statuses = collect(range(1, 11))->map(fn () => unlinkSignIn('facebook', $headers)->getStatusCode());

        expect($statuses->all())->toBe([...array_fill(0, 10, 404), 429]);
    });

    it('allows five links that prove who the driver is an hour from an account', function () {
        $user = emailAndGoogleAccount();
        $headers = bearerFor($user);

        $statuses = collect(range(1, 6))->map(fn () => $this->postJson('/api/v1/account/reauth/email', [], $headers)->getStatusCode());

        expect($statuses->all())->toBe([202, 202, 202, 202, 202, 429]);
    });
});

it('leaves the devices of other accounts alone when it signs some out', function () {
    $user = emailAndGoogleAccount();
    $other = emailAndGoogleAccount('other@example.com', 'google-other');
    $other->createToken('google-login');
    $headers = bearerFor($user);
    proveByEmail($headers);

    unlinkSignIn('google', $headers)->assertOk();

    expect(PersonalAccessToken::query()->where('tokenable_id', $other->id)->count())->toBe(1);
});
