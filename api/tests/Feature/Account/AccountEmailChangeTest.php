<?php

declare(strict_types=1);

use App\Mail\AccountLinkMail;
use App\Mail\AccountNoticeMail;
use App\Models\AccountIdentity;
use App\Models\AccountToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Mail::fake());

/** An account that signs in with an e-mail. */
function withEmail(string $email = 'old@example.com'): User
{
    $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    identityOf($user, AccountIdentity::EMAIL, $email, $email);

    return $user;
}

function askToChangeEmail(array $headers, string $email = 'new@example.com'): TestResponse
{
    return test()->postJson('/api/v1/account/email/change', ['email' => $email], $headers);
}

/** The secret in the link of a notice or a mail of the account that was queued last. */
function lastSecret(string $mailable): string
{
    $mail = Mail::queued($mailable)->last();
    parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

    return $query['token'];
}

function confirmRevoke(string $secret): TestResponse
{
    return test()->postJson('/api/v1/account/email/revoke', ['token' => $secret]);
}

/** Asks for the change and opens the link that was sent to the new address. */
function changeEmailTo(User $user, string $email = 'new@example.com', ?array $headers = null): array
{
    $headers ??= bearerFor($user);
    askToChangeEmail($headers, $email)->assertStatus(202);

    $link = Mail::queued(AccountLinkMail::class)->last();
    confirmLink(secretOf($link))->assertOk()->assertJsonPath('purpose', 'email_change');

    return $headers;
}

describe('asking to change the e-mail', function () {
    it('requires a token', function () {
        $this->postJson('/api/v1/account/email/change', ['email' => 'new@example.com'])->assertUnauthorized();
    });

    it('sends a link to the new address and a notice to the old one, and changes nothing', function () {
        $user = withEmail();

        askToChangeEmail(bearerFor($user))
            ->assertStatus(202)
            ->assertJsonPath('expires_in', 1_800)
            ->assertJsonPath('message', 'We sent a link to new@example.com. The change is made only when it is opened (it works once, for 30 minutes). The old address was notified.');

        Mail::assertQueued(AccountLinkMail::class, 1);
        Mail::assertQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('new@example.com') && $mail->kind === 'change' && str_starts_with($mail->url, config('ride.web_url').'/account/confirm?token='));
        Mail::assertQueued(AccountNoticeMail::class, 1);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo('old@example.com') && $mail->kind === 'change_asked' && $mail->detail === 'n***@example.com'
            && str_starts_with((string) $mail->url, config('ride.web_url').'/account/secure?token=') && $mail->days === 7);

        $fresh = $user->fresh();
        expect($fresh->email)->toBe('old@example.com')
            ->and($user->identities()->where('provider', 'email')->value('subject'))->toBe('old@example.com')
            ->and($fresh->email_changed_at)->toBeNull();
    });

    it('keeps the new address out of the notice in full', function () {
        askToChangeEmail(bearerFor(withEmail()), 'somebody.else@example.com')->assertStatus(202);

        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => ! str_contains($mail->detail, 'somebody'));
    });

    it('writes the address in lower case', function () {
        $user = withEmail();
        changeEmailTo($user, '  New@Example.COM ');

        expect($user->fresh()->email)->toBe('new@example.com');
    });

    it('is for an account that signs in with an e-mail, and adds one to a guest or an account with another sign-in', function () {
        $google = User::factory()->create();
        identityOf($google, AccountIdentity::GOOGLE, 'g-1');

        askToChangeEmail(bearerFor(User::factory()->guest()->create()))->assertStatus(409)->assertJsonPath('code', 'no_email');
        askToChangeEmail(bearerFor($google))->assertStatus(409)->assertJsonPath('code', 'no_email');
        Mail::assertNothingQueued();
    });

    it('refuses the address the account has', function () {
        askToChangeEmail(bearerFor(withEmail()), 'OLD@example.com')->assertStatus(422)->assertJsonPath('code', 'email_same');
        Mail::assertNothingQueued();
    });

    it('answers a garage that has the address exactly as it answers a free one, and sends that garage its own link', function () {
        $user = withEmail();
        $owner = withEmail('taken@example.com');

        $free = askToChangeEmail(bearerFor($user), 'free@example.com');
        $taken = askToChangeEmail(bearerFor($user), 'taken@example.com');

        expect($taken->getStatusCode())->toBe($free->getStatusCode())
            ->and(str_replace('taken@example.com', 'x', $taken->json('message')))->toBe(str_replace('free@example.com', 'x', $free->json('message')))
            ->and($taken->json('expires_in'))->toBe($free->json('expires_in'));

        // The owner is told that the address has a garage, with a link to sign in to it; no link changes anything on the account that asked.
        Mail::assertQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('taken@example.com') && $mail->kind === 'existing');
        Mail::assertNotQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('taken@example.com') && $mail->kind === 'change');
        expect($owner->fresh()->email)->toBe('taken@example.com')->and($user->fresh()->email)->toBe('old@example.com');
    });

    it('lets only the latest link work', function () {
        $user = withEmail();
        $headers = bearerFor($user);

        askToChangeEmail($headers, 'first@example.com')->assertStatus(202);
        $first = secretOf(Mail::queued(AccountLinkMail::class)->last());
        askToChangeEmail($headers, 'second@example.com')->assertStatus(202);
        $second = secretOf(Mail::queued(AccountLinkMail::class)->last());

        confirmLink($first)->assertStatus(422);
        confirmLink($second)->assertOk();

        expect($user->fresh()->email)->toBe('second@example.com');
    });

    it('validates the address', function (array $body) {
        askToChangeEmailWith($body)->assertStatus(422)->assertJsonValidationErrors(['email']);
    })->with([
        'no address' => [['email' => '']],
        'not an address' => [['email' => 'turbo']],
        'too long' => [['email' => str_repeat('a', 190).'@example.com']],
    ]);
});

function askToChangeEmailWith(array $body): TestResponse
{
    return test()->postJson('/api/v1/account/email/change', $body, bearerFor(withEmail()));
}

describe('using the link of the new address', function () {
    it('changes the address of the account and of its e-mail sign-in, and tells the old address', function () {
        $user = withEmail();
        $before = $user->identities()->value('id');

        changeEmailTo($user);

        $fresh = $user->fresh();
        expect($fresh->email)->toBe('new@example.com')
            ->and($fresh->email_verified_at)->not->toBeNull()
            ->and($fresh->email_changed_at)->not->toBeNull()
            ->and($user->identities()->count())->toBe(1)
            ->and($user->identities()->first()->id)->toBe($before)
            ->and($user->identities()->first()->subject)->toBe('new@example.com')
            ->and($user->identities()->first()->email)->toBe('new@example.com');

        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo('old@example.com') && $mail->kind === 'changed' && $mail->detail === 'n***@example.com' && $mail->url !== null);
    });

    it('answers with the profile, to a browser that has no token of its own', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user))->assertStatus(202);

        confirmLink(secretOf(Mail::queued(AccountLinkMail::class)->last()))
            ->assertOk()
            ->assertJsonPath('data.identities', [['provider' => 'email', 'email' => 'n***@example.com']])
            ->assertJsonMissingPath('token');
    });

    it('signs in with the new address and no longer with the old one', function () {
        $user = withEmail();
        changeEmailTo($user);

        $this->postJson('/api/v1/account/login-link', ['email' => 'old@example.com'])->assertStatus(202);
        Mail::assertNotQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('old@example.com') && $mail->kind === 'login');

        $this->postJson('/api/v1/account/login-link', ['email' => 'new@example.com'])->assertStatus(202);
        Mail::assertQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('new@example.com') && $mail->kind === 'login');
    });

    it('keeps the devices signed in', function () {
        $user = withEmail();
        $user->createToken('guest-device');
        $user->createToken('email-login');

        changeEmailTo($user);

        expect($user->tokens()->count())->toBe(3);
    });

    it('makes the links that were sent to the old address useless', function () {
        $user = withEmail();
        $this->postJson('/api/v1/account/login-link', ['email' => 'old@example.com'])->assertStatus(202);
        $oldLogin = secretOf(Mail::queued(AccountLinkMail::class)->last());

        changeEmailTo($user);

        confirmLink($oldLogin)->assertStatus(422);
    });

    it('works once', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user))->assertStatus(202);
        $secret = secretOf(Mail::queued(AccountLinkMail::class)->last());

        confirmLink($secret)->assertOk();
        confirmLink($secret)->assertStatus(422);
    });

    it('works for thirty minutes', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user))->assertStatus(202);
        $secret = secretOf(Mail::queued(AccountLinkMail::class)->last());

        Carbon::setTestNow(now()->addMinutes(31));
        confirmLink($secret)->assertStatus(422);
        Carbon::setTestNow();

        expect($user->fresh()->email)->toBe('old@example.com');
    });

    it('is not what the link that signs in is: the two are not interchangeable', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user))->assertStatus(202);

        // A secret that was never issued for a change does not change anything, whatever else it is good for.
        confirmLink('not-a-secret')->assertStatus(422);
        expect($user->fresh()->email)->toBe('old@example.com');
    });

    it('is refused when somebody took the address meanwhile, and says so to whoever opened the link', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user))->assertStatus(202);
        $secret = secretOf(Mail::queued(AccountLinkMail::class)->last());

        withEmail('new@example.com');

        confirmLink($secret)->assertStatus(409)->assertJsonPath('code', 'identity_taken');
        expect($user->fresh()->email)->toBe('old@example.com');
    });

    it('is refused when the account stopped signing in with an e-mail meanwhile', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user))->assertStatus(202);
        $secret = secretOf(Mail::queued(AccountLinkMail::class)->last());

        $user->identities()->delete();

        confirmLink($secret)->assertStatus(409)->assertJsonPath('code', 'no_email');
    });
});

describe('the pause between two changes', function () {
    it('lets the next change be asked for only after seven days', function () {
        $user = withEmail();
        $headers = changeEmailTo($user);

        // The guard of the test keeps the driver of the earlier request; the next request of a real client reads them anew.
        app('auth')->forgetGuards();
        $refused = askToChangeEmail($headers, 'third@example.com');
        $refused->assertStatus(422)->assertJsonPath('code', 'email_change_cooldown');
        expect($refused->json('available_at'))->toBe(now()->addDays(7)->toIso8601String())
            ->and($refused->json('message'))->toBe('The e-mail was changed recently. The next change is possible from '.now()->addDays(7)->toDateString().'.');

        Carbon::setTestNow(now()->addDays(7)->addMinute());
        app('auth')->forgetGuards();
        askToChangeEmail($headers, 'third@example.com')->assertStatus(202);
        Carbon::setTestNow();
    });

    it('is kept by the link too: two links asked for before the change cannot make two changes at once', function () {
        $user = withEmail();
        $headers = bearerFor($user);
        askToChangeEmail($headers, 'first@example.com')->assertStatus(202);
        $first = secretOf(Mail::queued(AccountLinkMail::class)->last());

        // Another request slips a link in between, as if it had been asked for before the first change was made.
        $stray = tokenWith(['purpose' => AccountToken::EMAIL_CHANGE, 'user_id' => $user->id, 'email' => 'stray@example.com']);
        $strayHash = $stray->token_hash;

        confirmLink($first)->assertOk();
        expect($user->fresh()->email)->toBe('first@example.com')
            ->and(AccountToken::query()->where('token_hash', $strayHash)->value('used_at'))->not->toBeNull();
    });

    it('is a setting', function () {
        config(['ride.account.email_change_cooldown_days' => 1]);
        $user = withEmail();
        $headers = changeEmailTo($user);

        app('auth')->forgetGuards();
        askToChangeEmail($headers, 'third@example.com')->assertStatus(422)->assertJsonPath('code', 'email_change_cooldown');
        Carbon::setTestNow(now()->addDay()->addMinute());
        app('auth')->forgetGuards();
        askToChangeEmail($headers, 'third@example.com')->assertStatus(202);
        Carbon::setTestNow();
    });
});

describe('"this was not me"', function () {
    it('cancels a change that waits, signs out every device and drops the recovery code', function () {
        $user = withEmail();
        $user->forceFill(['recovery_code_hash' => 'hash', 'recovery_code_set_at' => now()])->save();
        $user->createToken('guest-device');
        askToChangeEmail(bearerFor($user), 'intruder@example.com')->assertStatus(202);
        $link = secretOf(Mail::queued(AccountLinkMail::class)->last());
        $undo = lastSecret(AccountNoticeMail::class);

        confirmRevoke($undo)->assertOk()->assertJsonPath('purpose', 'revoke')->assertJsonPath('restored', false);

        confirmLink($link)->assertStatus(422);
        $fresh = $user->fresh();
        expect($fresh->email)->toBe('old@example.com')
            ->and($fresh->recovery_code_hash)->toBeNull()
            ->and($fresh->recovery_code_set_at)->toBeNull()
            ->and($user->tokens()->count())->toBe(0);
    });

    it('puts the old address back after a change was made, and lets the owner sign in with it', function () {
        $user = withEmail();
        $user->forceFill(['recovery_code_hash' => 'hash'])->save();
        changeEmailTo($user, 'intruder@example.com');
        $undo = lastSecret(AccountNoticeMail::class);

        confirmRevoke($undo)->assertOk()->assertJsonPath('restored', true);

        $fresh = $user->fresh();
        expect($fresh->email)->toBe('old@example.com')
            ->and($fresh->email_changed_at)->toBeNull()
            ->and($user->identities()->count())->toBe(1)
            ->and($user->identities()->first()->subject)->toBe('old@example.com')
            ->and($fresh->recovery_code_hash)->toBeNull()
            ->and($user->tokens()->count())->toBe(0);

        $this->postJson('/api/v1/account/login-link', ['email' => 'old@example.com'])->assertStatus(202);
        Mail::assertQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('old@example.com') && $mail->kind === 'login');
        $this->postJson('/api/v1/account/login-link', ['email' => 'intruder@example.com'])->assertStatus(202);
        Mail::assertNotQueued(AccountLinkMail::class, fn (AccountLinkMail $mail) => $mail->hasTo('intruder@example.com') && $mail->kind === 'login');
    });

    it('puts back the address the notice was sent to even when the e-mail was changed again since', function () {
        config(['ride.account.email_change_cooldown_days' => 1]);
        $user = withEmail();
        $headers = changeEmailTo($user, 'first@example.com');
        $firstUndo = lastSecret(AccountNoticeMail::class);

        Carbon::setTestNow(now()->addDays(2));
        app('auth')->forgetGuards();
        changeEmailTo($user, 'second@example.com', $headers);

        confirmRevoke($firstUndo)->assertOk()->assertJsonPath('restored', true);
        expect($user->fresh()->email)->toBe('old@example.com');
        Carbon::setTestNow();
    });

    it('does not put the old address back when another account has it, but still secures the account', function () {
        $user = withEmail();
        changeEmailTo($user, 'intruder@example.com');
        $undo = lastSecret(AccountNoticeMail::class);
        withEmail('old@example.com');

        confirmRevoke($undo)->assertOk()->assertJsonPath('restored', false);

        expect($user->fresh()->email)->toBe('intruder@example.com')->and($user->tokens()->count())->toBe(0);
    });

    it('does not touch another account', function () {
        $user = withEmail();
        $other = withEmail('other@example.com');
        $other->createToken('guest-device');
        askToChangeEmail(bearerFor($user), 'intruder@example.com')->assertStatus(202);

        confirmRevoke(lastSecret(AccountNoticeMail::class))->assertOk();

        expect($other->tokens()->count())->toBe(1);
    });

    it('works once', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user))->assertStatus(202);
        $undo = lastSecret(AccountNoticeMail::class);

        confirmRevoke($undo)->assertOk();
        confirmRevoke($undo)->assertStatus(422);
    });

    it('works for seven days after the notice', function () {
        $user = withEmail();
        changeEmailTo($user, 'intruder@example.com');
        $undo = lastSecret(AccountNoticeMail::class);

        Carbon::setTestNow(now()->addDays(6));
        confirmRevoke($undo)->assertOk()->assertJsonPath('restored', true);
        Carbon::setTestNow();
    });

    it('does not work after seven days', function () {
        $user = withEmail();
        changeEmailTo($user, 'intruder@example.com');
        $undo = lastSecret(AccountNoticeMail::class);

        Carbon::setTestNow(now()->addDays(8));
        confirmRevoke($undo)->assertStatus(422);
        expect($user->fresh()->email)->toBe('intruder@example.com');
        Carbon::setTestNow();
    });

    it('is not what a link of another kind does, and a link of this kind is good for nothing else', function () {
        $user = withEmail();
        askToChangeEmail(bearerFor($user), 'next@example.com')->assertStatus(202);
        $undo = lastSecret(AccountNoticeMail::class);
        $change = secretOf(Mail::queued(AccountLinkMail::class)->last());

        confirmRevoke($change)->assertStatus(422);
        confirmLink($undo)->assertStatus(422);
        confirmRevoke('never-issued')->assertStatus(422);
        confirmRevoke('')->assertStatus(422);

        expect($user->fresh()->email)->toBe('old@example.com');
    });
});

describe('limits', function () {
    it('allows five changes an hour from an account, and no more', function () {
        $headers = bearerFor(withEmail());

        $statuses = collect(range(1, 6))->map(fn (int $n) => askToChangeEmail($headers, "address{$n}@example.com")->getStatusCode());

        expect($statuses->all())->toBe([202, 202, 202, 202, 202, 429]);
    });

    it('allows three asks for one address an hour, whoever asks', function () {
        $statuses = collect(range(1, 4))->map(fn () => askToChangeEmail(bearerFor(withEmail('o'.fake()->unique()->numerify('#####').'@example.com')), 'target@example.com')->getStatusCode());

        expect($statuses->all())->toBe([202, 202, 202, 429]);
    });

    it('counts a differently written address as the same one', function () {
        $statuses = collect(['target@example.com', 'TARGET@example.com', ' Target@Example.com', 'target@example.com'])
            ->map(fn (string $email) => askToChangeEmail(bearerFor(withEmail('o'.fake()->unique()->numerify('#####').'@example.com')), $email)->getStatusCode());

        expect($statuses->all())->toBe([202, 202, 202, 429]);
    });

    it('allows thirty uses of "this was not me" an hour from one address', function () {
        $statuses = collect(range(1, 31))->map(fn () => confirmRevoke('nothing')->getStatusCode());

        expect($statuses->last())->toBe(429)->and($statuses->first())->toBe(422);
    });
});
