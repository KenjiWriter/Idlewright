<?php

declare(strict_types=1);

use App\Games\CurrentGame;
use App\Models\Game;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Purchase;
use App\Models\User;
use App\Payments\FakeProvider;
use App\Payments\Payments;
use App\Payments\PaymentsUnavailable;
use App\Payments\StripeProvider;
use App\Services\AccountErasure;
use App\Services\AccountExport;
use App\Services\Entitlements;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

require_once __DIR__.'/../../Support/PaymentHelpers.php';

// What the payments leave behind and how they are looked after: how long the books are kept, what goes with an erased account and what stays, the commands that say where the
// payments stand and turn them on, and the check that a key of the wrong mode does nothing.

afterEach(fn () => Carbon::setTestNow());

/** The text a command wrote. */
function output(string $command, array $options = []): string
{
    Artisan::call($command, $options);

    return Artisan::output();
}

describe('how long the shop keeps a purchase', function () {
    it('keeps it through the sixth year after the year it was made in, and removes it in the next, with the paid attempt that holds the consents', function () {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $attempt = aStripeAttempt(null, ['state' => 'paid']);
        Purchase::factory()->create(['user_id' => null, 'attempt_id' => $attempt->id]);

        Carbon::setTestNow('2032-12-31 23:59:59');
        expect(output('purchases:prune'))->toContain('Removed 0 purchase(s)')->and(Purchase::query()->count())->toBe(1);

        Carbon::setTestNow('2033-01-01 00:00:00');
        expect(output('purchases:prune'))->toContain('Removed 1 purchase(s) and 1 paid attempt(s)')
            ->and(Purchase::query()->count())->toBe(0)->and(PaymentAttempt::query()->count())->toBe(0);
    });

    it('can be told another number of years, and only reports with --dry-run', function () {
        Carbon::setTestNow('2026-06-15 12:00:00');
        Purchase::factory()->create(['user_id' => null]);
        Carbon::setTestNow('2028-01-01 00:00:00');

        expect(output('purchases:prune', ['--dry-run' => true, '--years' => 1]))->toContain('1 purchase(s)')->and(Purchase::query()->count())->toBe(1)
            ->and(output('purchases:prune', ['--years' => 3]))->toContain('Removed 0 purchase(s)')
            ->and(output('purchases:prune', ['--years' => 1]))->toContain('Removed 1 purchase(s)')->and(Purchase::query()->count())->toBe(0);
    });

    it('refuses a number of years that is not one', function () {
        expect(Artisan::call('purchases:prune', ['--years' => -2]))->toBe(1);
    });

    it('removes the purchases of every game', function () {
        $ships = aSecondPayingGame();
        Carbon::setTestNow('2026-06-15 12:00:00');
        Purchase::factory()->create(['user_id' => null, 'game_id' => $ships->id]);
        Carbon::setTestNow('2040-01-01 00:00:00');

        Artisan::call('purchases:prune');

        expect(Purchase::query()->withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('what the attempts and the events leave', function () {
    it('removes the attempts nothing was paid on after a month, and keeps the paid ones, the open ones, and a mismatch for a person', function () {
        Carbon::setTestNow('2026-11-02 12:00:00');
        $old = now()->subDays(31);
        $failed = aStripeAttempt(null, ['state' => 'failed', 'created_at' => $old]);
        $expired = aStripeAttempt(null, ['state' => 'expired', 'created_at' => $old]);
        $ranOut = aStripeAttempt(null, ['state' => 'created', 'created_at' => $old, 'expires_at' => $old]);
        $recent = aStripeAttempt(null, ['state' => 'expired', 'created_at' => now()->subDays(5)]);
        $paid = aStripeAttempt(null, ['state' => 'paid', 'created_at' => $old]);
        $mismatch = aStripeAttempt(null, ['state' => 'mismatch', 'created_at' => $old]);
        $open = aStripeAttempt(null, ['state' => 'processing', 'created_at' => $old, 'expires_at' => now()->addHour()]);

        expect(output('payments:prune', ['--dry-run' => true]))->toContain('3 attempt(s)')->and(PaymentAttempt::query()->count())->toBe(7);
        output('payments:prune');

        expect(PaymentAttempt::query()->pluck('id')->sort()->values()->all())->toBe(collect([$recent, $paid, $mismatch, $open])->pluck('id')->sort()->values()->all())
            ->and([$failed, $expired, $ranOut])->toHaveCount(3);
    });

    it('removes the events that were worked after thirteen months, and keeps the ones a person has to see', function () {
        Carbon::setTestNow('2026-11-02 12:00:00');
        $old = now()->subMonths(14);
        PaymentEvent::factory()->create(['state' => 'processed', 'received_at' => $old]);
        PaymentEvent::factory()->create(['state' => 'ignored', 'received_at' => $old]);
        PaymentEvent::factory()->create(['state' => 'rejected', 'received_at' => $old]);
        PaymentEvent::factory()->create(['state' => 'failed', 'received_at' => $old]);
        PaymentEvent::factory()->create(['state' => 'processed', 'received_at' => now()->subMonths(2)]);

        output('payments:prune');

        expect(PaymentEvent::query()->orderBy('id')->pluck('state')->all())->toBe(['rejected', 'failed', 'processed']);
    });
});

describe('an account that is erased', function () {
    it('takes the attempts nothing was paid on, and leaves the paid one with its purchase, both cut from the account', function () {
        $driver = User::factory()->create();
        aStripeAttempt($driver, ['state' => 'expired', 'request_id' => 'a']);
        $paid = aStripeAttempt($driver, ['state' => 'paid', 'request_id' => 'b']);
        $purchase = Purchase::factory()->create(['user_id' => $driver->id, 'attempt_id' => $paid->id]);

        expect(app(AccountErasure::class)->inventory($driver))->toMatchArray(['payment attempts' => 1]);
        app(AccountErasure::class)->erase($driver);

        expect(PaymentAttempt::query()->count())->toBe(1)
            ->and($paid->refresh()->user_id)->toBeNull()
            ->and($purchase->refresh()->user_id)->toBeNull()
            ->and($purchase->attempt_id)->toBe($paid->id)
            ->and(User::query()->find($driver->id))->toBeNull();
    });

    it('is in the copy of the data, with the consents and without the address of the page of payment', function () {
        $driver = User::factory()->create();
        aStripeAttempt($driver, ['state' => 'expired', 'checkout_url' => 'https://checkout.stripe.test/secret-page']);

        $game = app(AccountExport::class)->for($driver)['game'];

        expect($game['payment_attempts'])->toHaveCount(1)
            ->and($game['payment_attempts'][0])->toMatchArray(['product' => 'coins-small', 'state' => 'expired', 'terms_version' => '2026-10-23'])
            ->and($game['payment_attempts'][0])->not->toHaveKeys(['id', 'user_id', 'checkout_url']);
    });
});

describe('the mode of the money', function () {
    it('is the mode of the key, and is allowed only where it belongs', function (string $key, string $environment, bool $allowTest, bool $allowed) {
        config(['ride.premium.payments.stripe.secret' => $key, 'ride.premium.payments.allow_test' => $allowTest]);
        app()['env'] = $environment;

        expect(app(StripeProvider::class)->modeAllowed())->toBe($allowed);
    })->with([
        'a test key in development' => ['sk_test_abc', 'local', false, true],
        'a restricted test key in development' => ['rk_test_abc', 'local', false, true],
        'a live key in development' => ['sk_live_abc', 'local', false, false],
        'a live key in production' => ['sk_live_abc', 'production', false, true],
        'a restricted live key in production' => ['rk_live_abc', 'production', false, true],
        'a test key in production' => ['sk_test_abc', 'production', false, false],
        'a test key in a demonstration in production' => ['sk_test_abc', 'production', true, true],
        'no key' => ['', 'local', false, false],
        'something that is not a key of Stripe' => ['whsec_abc', 'local', false, false],
    ]);

    it('says which money it is, and does not guess when the key is not one', function () {
        config(['ride.premium.payments.stripe.secret' => 'sk_live_abc']);
        expect(app(StripeProvider::class)->livemode())->toBeTrue();

        config(['ride.premium.payments.stripe.secret' => 'sk_test_abc']);
        expect(app(StripeProvider::class)->livemode())->toBeFalse();

        config(['ride.premium.payments.stripe.secret' => 'nonsense']);
        app(StripeProvider::class)->livemode();
    })->throws(PaymentsUnavailable::class);

    it('does not send a driver to Stripe before the page of payment is built, and does not sell the pass meanwhile', function () {
        stripeIsSetUp();

        expect(app(StripeProvider::class)->ready())->toBeFalse()->and(app(Payments::class)->available())->toBeFalse()->and(Entitlements::paymentsConnected())->toBeFalse();
    });

    it('lets the fake provider sell outside production only', function () {
        payWithTheFakeProvider();
        expect(app(Payments::class)->available())->toBeTrue()->and(app(FakeProvider::class)->livemode())->toBeFalse();

        app()['env'] = 'production';
        Game::query()->find(1)->update(['payments' => ['enabled' => true]]);

        expect(app(Payments::class)->available())->toBeFalse();
    });

    it('knows no provider that is not one of its own', function () {
        config(['ride.premium.provider' => 'paypal']);

        expect(app(Payments::class)->available())->toBeFalse();
        app(Payments::class)->provider();
    })->throws(PaymentsUnavailable::class);
});

describe('the commands', function () {
    it('says where the payments stand, and shows no key', function () {
        stripeIsSetUp('sk_test_supersecret');
        Game::query()->create(['slug' => 'ships', 'name' => 'Ships', 'domain' => 'ships.test', 'locale' => 'en', 'theme_color' => '#0b3d5c', 'status' => 'live', 'payments' => ['enabled' => false, 'withdrawal' => 'waived']]);

        $text = output('payments:status');

        expect($text)->toContain('Provider: stripe')->toContain('test money')->toContain('Webhook secret: set')->toContain('Ready to send a driver to pay: no')
            ->toContain('raceidle')->toContain('ships')->toContain('waived')->not->toContain('supersecret')->not->toContain(STRIPE_TEST_WEBHOOK_SECRET);
    });

    it('says that nothing is sold when there is no provider', function () {
        expect(output('payments:status'))->toContain('Provider: none (nothing is sold)');
    });

    it('turns the payments of a game on and off, and keeps the rest of what the game says about them', function () {
        $ships = Game::query()->create(['slug' => 'ships', 'name' => 'Ships', 'domain' => 'ships.test', 'locale' => 'en', 'theme_color' => '#0b3d5c', 'status' => 'live', 'payments' => ['withdrawal' => 'waived']]);

        expect(Artisan::call('payments:switch', ['state' => 'on', '--game' => 'ships']))->toBe(0)
            ->and($ships->refresh()->payments)->toBe(['withdrawal' => 'waived', 'enabled' => true])
            ->and(Game::query()->find(1)->payments)->toBeNull();

        app(CurrentGame::class)->set(null);
        Artisan::call('payments:switch', ['state' => 'off', '--game' => 'ships']);

        expect($ships->refresh()->payments)->toBe(['withdrawal' => 'waived', 'enabled' => false]);
    });

    it('turns the payments of the first game on when no game is named, and warns in production', function () {
        app()['env'] = 'production';

        expect(output('payments:switch', ['state' => 'on']))->toContain('lawyer')->and(Game::query()->find(1)->payments)->toBe(['enabled' => true]);
    });

    it('refuses a state that is not on or off, and a game that is not there', function () {
        expect(Artisan::call('payments:switch', ['state' => 'maybe']))->toBe(1)->and(Artisan::call('payments:switch', ['state' => 'on', '--game' => 'nowhere']))->toBe(1);
    });
});
