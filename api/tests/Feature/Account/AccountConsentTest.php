<?php

declare(strict_types=1);

use App\Mail\AccountLinkMail;
use App\Models\AccountIdentity;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\Consent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/** A driver who accepted the texts in the version they had then. */
function acceptedIn(?string $version, array $attributes = []): User
{
    $user = User::factory()->create([
        'terms_accepted_at' => now()->subDays(10),
        'age_confirmed_at' => now()->subDays(10),
        'terms_version' => $version,
        ...$attributes,
    ]);
    identityOf($user, AccountIdentity::EMAIL, "driver{$user->id}@example.com", "driver{$user->id}@example.com");

    return $user;
}

function acceptAgain(array $headers, array $body = []): TestResponse
{
    return test()->postJson('/api/v1/account/consent', ['accept_terms' => true, 'confirm_age' => true, 'version' => Consent::current(), ...$body], $headers);
}

beforeEach(function () {
    config(['ride.legal.version' => '2026-11-01', 'ride.legal.consent_version' => null]);
});

describe('which version asks for a new consent', function () {
    it('has days for versions, so that they compare as days', function () {
        expect(config('ride.legal.version'))->toMatch('/^\d{4}-\d{2}-\d{2}$/');
        config(['ride.legal.version' => (string) env('LEGAL_VERSION', '2026-10-23')]);
        expect(config('ride.legal.version'))->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    });

    it('asks every change of the version when there is no version of its own for consent', function () {
        expect(Consent::required())->toBe('2026-11-01')
            ->and(Consent::isOutdated('2026-10-23'))->toBeTrue()
            ->and(Consent::isOutdated('2026-11-01'))->toBeFalse()
            ->and(Consent::isOutdated('2026-11-02'))->toBeFalse();
    });

    it('asks only for the change that needs it when there is one', function () {
        config(['ride.legal.consent_version' => '2026-10-23']);

        expect(Consent::required())->toBe('2026-10-23')
            ->and(Consent::current())->toBe('2026-11-01')
            ->and(Consent::isOutdated('2026-10-23'))->toBeFalse()
            ->and(Consent::isOutdated('2026-10-01'))->toBeTrue();
    });

    it('asks a driver who accepted before the texts had a version', function () {
        expect(Consent::isOutdated(null))->toBeTrue()->and(Consent::isOutdated(''))->toBeTrue();
    });

    it('does not ask a guest, who has accepted nothing', function () {
        expect(User::factory()->guest()->create()->needsConsent())->toBeFalse();
    });

    it('asks a driver whose version is older, and not one whose version is the current one', function () {
        expect(acceptedIn('2026-10-23')->needsConsent())->toBeTrue()
            ->and(acceptedIn(null)->needsConsent())->toBeTrue()
            ->and(acceptedIn('2026-11-01')->needsConsent())->toBeFalse();
    });
});

describe('what the profile says', function () {
    it('says that nothing is needed from a driver who accepted the current version', function () {
        $this->getJson('/api/v1/me', bearerFor(acceptedIn('2026-11-01')))->assertOk()->assertJsonPath('data.consent', [
            'required' => false,
            'accepted_version' => '2026-11-01',
            'current_version' => '2026-11-01',
        ]);
    });

    it('says that the texts have changed, and which version the driver has accepted', function () {
        $this->getJson('/api/v1/me', bearerFor(acceptedIn('2026-10-23')))->assertOk()->assertJsonPath('data.consent', [
            'required' => true,
            'accepted_version' => '2026-10-23',
            'current_version' => '2026-11-01',
        ]);
    });

    it('says nothing is needed from a guest', function () {
        $this->getJson('/api/v1/me', bearerFor(User::factory()->guest()->create()))->assertOk()
            ->assertJsonPath('data.consent.required', false)
            ->assertJsonPath('data.consent.accepted_version', null);
    });
});

describe('the game waits for the consent', function () {
    it('answers 403 consent_required to what a client that does not know about it asks, with both versions', function (string $method, string $path) {
        $headers = bearerFor(acceptedIn('2026-10-23'));

        $this->json($method, $path, [], $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'consent_required')
            ->assertJsonPath('message', 'The terms or the privacy policy have changed. Accept the new version to keep playing.')
            ->assertJsonPath('accepted_version', '2026-10-23')
            ->assertJsonPath('current_version', '2026-11-01');
    })->with([
        'the garage' => ['GET', '/api/v1/garage'],
        'the cars' => ['GET', '/api/v1/cars'],
        'the map' => ['GET', '/api/v1/map'],
        'the missions' => ['GET', '/api/v1/missions'],
        'the shop' => ['GET', '/api/v1/shop'],
        'the current run' => ['GET', '/api/v1/runs/current'],
        'starting a run' => ['POST', '/api/v1/runs'],
        'the recovery code' => ['POST', '/api/v1/account/recovery-code'],
        'a link for the e-mail' => ['POST', '/api/v1/account/email'],
        'changing the e-mail' => ['POST', '/api/v1/account/email/change'],
        'unlinking' => ['DELETE', '/api/v1/account/identities/email'],
        'proving who the driver is' => ['POST', '/api/v1/account/reauth/email'],
        'linking a provider' => ['POST', '/api/v1/account/identities/google/start'],
    ]);

    it('leaves open what a driver who does not agree needs: who they are, the language, a copy of the data, deleting the account', function () {
        $user = acceptedIn('2026-10-23');
        $headers = bearerFor($user);

        $this->getJson('/api/v1/me', $headers)->assertOk();
        $this->patchJson('/api/v1/me/locale', ['locale' => 'de'], $headers)->assertOk();
        $this->getJson('/api/v1/account/export', $headers)->assertOk();
        $this->deleteJson('/api/v1/account', ['confirm_name' => $user->name], $headers)->assertOk();

        expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
    });

    it('lets the driver play again as soon as they have accepted', function () {
        $headers = bearerFor(acceptedIn('2026-10-23'));
        $this->getJson('/api/v1/garage', $headers)->assertForbidden();

        acceptAgain($headers)->assertOk();

        app('auth')->forgetGuards();
        $this->getJson('/api/v1/garage', $headers)->assertOk();
    });

    it('does not stop a driver who accepted the current version, nor a guest', function () {
        $this->getJson('/api/v1/garage', bearerFor(acceptedIn('2026-11-01')))->assertOk();
        $this->getJson('/api/v1/garage', bearerFor(User::factory()->guest()->create()))->assertOk();
    });

    it('does not stop a driver for a change of the texts that does not need a consent', function () {
        $user = acceptedIn('2026-10-23');
        config(['ride.legal.consent_version' => '2026-10-23']);

        $this->getJson('/api/v1/garage', bearerFor($user))->assertOk();
    });

    it('stops the way back from a sign-in with a provider too, for linking and for proving who the driver is', function () {
        $user = acceptedIn('2026-11-01');
        $headers = bearerFor($user);
        identityOf($user, AccountIdentity::GOOGLE, 'g-1');
        withProviders(providerKeys());
        $link = $this->postJson('/api/v1/account/identities/facebook/start', ['accept_terms' => true, 'confirm_age' => true], $headers)->assertOk();
        providerSays('facebook', ['id' => 'fb-1']);
        $ticket = fragmentOf(comesBackFrom('facebook', stateOf($link->json('url'))))['ticket'];

        // The texts change while the driver is at the provider.
        config(['ride.legal.version' => '2026-11-15']);
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/auth/complete', ['ticket' => $ticket, 'verifier' => $link->json('verifier')], $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'consent_required');
        expect($user->identities()->where('provider', 'facebook')->exists())->toBeFalse();
    });

    it('lets a driver sign in on a new device, which then shows the screen that asks', function () {
        Mail::fake();
        $user = acceptedIn('2026-10-23');
        $this->postJson('/api/v1/account/login-link', ['email' => "driver{$user->id}@example.com"])->assertStatus(202);

        $secret = secretOf(Mail::queued(AccountLinkMail::class)->last());

        confirmLink($secret)->assertOk()->assertJsonPath('data.consent.required', true)->assertJsonStructure(['token']);
    });
});

describe('accepting again', function () {
    it('requires a token', function () {
        $this->postJson('/api/v1/account/consent', ['accept_terms' => true, 'confirm_age' => true, 'version' => '2026-11-01'])->assertUnauthorized();
    });

    it('saves the version and the moment, keeps the first consent, and puts the new one on record', function () {
        Carbon::setTestNow('2026-11-02 12:00:00');
        $user = acceptedIn('2026-10-23', ['terms_accepted_at' => '2026-10-24 08:00:00', 'age_confirmed_at' => '2026-10-24 08:00:00']);
        $user->consents()->create(['terms_version' => '2026-10-23', 'source' => ConsentRecord::LINK, 'accepted_at' => '2026-10-24 08:00:00']);

        acceptAgain(bearerFor($user))
            ->assertOk()
            ->assertJsonPath('data.consent', ['required' => false, 'accepted_version' => '2026-11-01', 'current_version' => '2026-11-01']);

        $fresh = $user->fresh();
        expect($fresh->terms_version)->toBe('2026-11-01')
            ->and($fresh->terms_accepted_at->toDateTimeString())->toBe('2026-10-24 08:00:00')
            ->and($fresh->age_confirmed_at->toDateTimeString())->toBe('2026-10-24 08:00:00')
            ->and($user->consents()->orderBy('id')->get()->map(fn (ConsentRecord $r) => [$r->terms_version, $r->source, $r->accepted_at->toDateTimeString()])->all())
            ->toBe([['2026-10-23', 'link', '2026-10-24 08:00:00'], ['2026-11-01', 'reconsent', '2026-11-02 12:00:00']]);

        Carbon::setTestNow();
    });

    it('changes nothing when nothing needs accepting, so that a double click is harmless', function () {
        $user = acceptedIn('2026-10-23');
        $headers = bearerFor($user);

        acceptAgain($headers)->assertOk();
        app('auth')->forgetGuards();
        acceptAgain($headers)->assertOk();

        expect($user->consents()->where('source', 'reconsent')->count())->toBe(1);
    });

    it('does not make a guest a driver who accepted', function () {
        $guest = User::factory()->guest()->create();

        acceptAgain(bearerFor($guest))->assertOk()->assertJsonPath('data.consent.required', false);

        $fresh = $guest->fresh();
        expect($fresh->terms_accepted_at)->toBeNull()->and($fresh->terms_version)->toBeNull()->and($guest->consents()->count())->toBe(0);
    });

    it('asks for both: the terms and the age', function (array $body, array $errors) {
        $user = acceptedIn('2026-10-23');

        acceptAgain(bearerFor($user), $body)->assertStatus(422)->assertJsonValidationErrors($errors);

        expect($user->fresh()->terms_version)->toBe('2026-10-23')->and($user->consents()->count())->toBe(0);
    })->with([
        'terms not accepted' => [['accept_terms' => false], ['accept_terms']],
        'age not confirmed' => [['confirm_age' => false], ['confirm_age']],
        'neither' => [['accept_terms' => false, 'confirm_age' => false], ['accept_terms', 'confirm_age']],
        'no version' => [['version' => ''], ['version']],
    ]);

    it('refuses a version that is not the current one, and says which is', function () {
        $user = acceptedIn('2026-10-23');

        acceptAgain(bearerFor($user), ['version' => '2026-10-23'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'consent_version_changed')
            ->assertJsonPath('current_version', '2026-11-01')
            ->assertJsonPath('message', 'The texts changed while you were reading. Read the new version.');

        expect($user->fresh()->terms_version)->toBe('2026-10-23');
    });

    it('allows twenty an hour from an account', function () {
        $headers = bearerFor(acceptedIn('2026-11-01'));

        $statuses = collect(range(1, 21))->map(fn () => acceptAgain($headers)->getStatusCode());

        expect($statuses->last())->toBe(429)->and($statuses->first())->toBe(200);
    });
});

describe('the record of consents', function () {
    it('starts with the consent that secures the account', function () {
        Mail::fake();
        $guest = User::factory()->guest()->create();

        confirmLink(claimSecretFor($guest))->assertOk();

        $record = $guest->consents()->sole();
        expect($record->source)->toBe('link')->and($record->terms_version)->toBe('2026-11-01')->and($record->accepted_at)->not->toBeNull();
    });

    it('is not written again when another sign-in is added to an account that consented', function () {
        $user = acceptedIn('2026-11-01');
        $user->consents()->create(['terms_version' => '2026-11-01', 'source' => 'link', 'accepted_at' => now()]);

        finishWith(providerTicket('google', $user, ['id' => 'g-new']), $user)->assertOk();

        expect($user->identities()->where('provider', 'google')->exists())->toBeTrue()->and($user->consents()->count())->toBe(1);
    });

    it('goes into the copy of the data of the account', function () {
        $user = acceptedIn('2026-10-23');
        $user->consents()->create(['terms_version' => '2026-10-23', 'source' => 'link', 'accepted_at' => '2026-10-24 08:00:00']);
        acceptAgain(bearerFor($user))->assertOk();

        $export = exported(['--id' => $user->id]);

        expect(array_column($export['consents'], 'terms_version'))->toBe(['2026-10-23', '2026-11-01'])
            ->and(array_column($export['consents'], 'source'))->toBe(['link', 'reconsent']);
    });

    it('is removed with the account', function () {
        $user = acceptedIn('2026-10-23');
        $user->consents()->create(['terms_version' => '2026-10-23', 'source' => 'link', 'accepted_at' => now()]);

        expect(Artisan::call('accounts:delete', ['--id' => $user->id, '--force' => true]))->toBe(0);

        expect(ConsentRecord::query()->count())->toBe(0);
    });
});
