<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Games\CurrentGame;
use App\Models\Game;
use App\Models\PaymentAttempt;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Wallet;
use App\Payments\FakeProvider;
use App\Services\Ledger;
use App\Services\Purchases;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/../../Support/PaymentHelpers.php';

// Going to pay: a secured driver chooses a pack or the pass, confirms what the game asks, and is sent to the page of the provider. What is written down is what they were
// offered and what they confirmed; the money is learned from the provider (WebhookTest), or by asking it when the driver comes back (the last tests here).

afterEach(fn () => Carbon::setTestNow());

/** @param  array<string, mixed>  $body */
function startPurchase(User $driver, array $body = []): TestResponse
{
    return test()->postJson('/api/v1/premium/checkout', [
        'product' => 'coins-medium',
        'request_id' => 'req-1',
        'accept_terms' => true,
        'adult_or_guardian' => true,
        ...$body,
    ], bearerFor($driver));
}

describe('what the game says about buying', function () {
    it('says nothing can be bought while no provider is set up, and nothing is for sale', function () {
        $response = $this->getJson('/api/v1/premium', bearerFor(User::factory()->create()))->assertOk();

        expect($response->json('data.payments.available'))->toBeFalse()
            ->and(array_unique(array_column($response->json('data.products'), 'purchasable')))->toBe([false])
            ->and($response->json('data.infinity.purchasable'))->toBeFalse();
    });

    it('offers the packs and the pass once a provider is ready, and says what the driver has to confirm', function () {
        payWithTheFakeProvider();

        $response = $this->getJson('/api/v1/premium', bearerFor(User::factory()->create()))->assertOk();

        expect($response->json('data.payments'))->toMatchArray(['available' => true, 'test_mode' => true, 'withdrawal' => 'until_used', 'consents' => ['accept_terms', 'adult_or_guardian']])
            ->and(array_unique(array_column($response->json('data.products'), 'purchasable')))->toBe([true])
            ->and($response->json('data.infinity.purchasable'))->toBeTrue()
            ->and($response->json('data.payments.limits'))->toBe(['per_day_minor' => 50_000, 'per_month_minor' => 150_000, 'max_open_attempts' => 3]);
    });

    it('asks one more confirmation when the game has the driver give up the right of withdrawal', function () {
        payWithTheFakeProvider();
        Game::query()->find(1)->update(['payments' => ['withdrawal' => 'waived']]);

        $response = $this->getJson('/api/v1/premium', bearerFor(User::factory()->create()))->assertOk();

        expect($response->json('data.payments.withdrawal'))->toBe('waived')
            ->and($response->json('data.payments.consents'))->toBe(['accept_terms', 'adult_or_guardian', 'acknowledge_digital_content']);
    });

    it('does not sell in production until the game says so, and then not with a provider that takes no money', function () {
        payWithTheFakeProvider();
        app()['env'] = 'production';

        $this->getJson('/api/v1/premium', bearerFor(User::factory()->create()))->assertJsonPath('data.payments.available', false);

        Game::query()->find(1)->update(['payments' => ['enabled' => true]]);

        $this->getJson('/api/v1/premium', bearerFor(User::factory()->create()))->assertJsonPath('data.payments.available', false);
    });

    it('does not sell in a game that has its payments off, though it is not production', function () {
        payWithTheFakeProvider();
        Game::query()->find(1)->update(['payments' => ['enabled' => false]]);

        $this->getJson('/api/v1/premium', bearerFor(User::factory()->create()))->assertJsonPath('data.payments.available', false);
        startPurchase(User::factory()->create())->assertStatus(422)->assertJsonPath('code', 'payments_unavailable');
    });
});

describe('starting a purchase', function () {
    it('writes down what the driver was offered and what they confirmed, and sends them to the page of the provider', function () {
        payWithTheFakeProvider();
        $driver = User::factory()->create(['locale' => 'pl']);

        $response = startPurchase($driver)->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $attempt = PaymentAttempt::query()->sole();

        expect($response->json('repeated'))->toBeFalse()
            ->and($response->json('data'))->toMatchArray(['attempt' => $attempt->public_id, 'state' => 'created', 'product' => 'coins-medium', 'kind' => 'coins', 'coins' => 220, 'amount_minor' => 1999, 'currency' => 'PLN', 'test_mode' => true])
            ->and($response->json('data.url'))->toStartWith('https://pay.fake.invalid/')
            ->and($attempt->only(['user_id', 'game_id', 'provider', 'request_id', 'coins', 'amount_minor', 'currency', 'state', 'livemode', 'locale', 'terms_version']))
            ->toBe(['user_id' => $driver->id, 'game_id' => 1, 'provider' => 'fake', 'request_id' => 'req-1', 'coins' => 220, 'amount_minor' => 1999, 'currency' => 'PLN', 'state' => 'created', 'livemode' => false, 'locale' => 'pl', 'terms_version' => config('ride.legal.version')])
            ->and($attempt->consent_at)->not->toBeNull()->and($attempt->adult_declared_at)->not->toBeNull()->and($attempt->waiver_at)->toBeNull()
            ->and($attempt->provider_session)->toStartWith('fake_cs_')
            ->and($attempt->expires_at->isFuture())->toBeTrue()
            // Nothing was paid: nothing was given.
            ->and(Purchase::query()->count())->toBe(0)->and(coinsOf($driver))->toBe(0);
    });

    it('sells the pass for the days and the price of the rules', function () {
        payWithTheFakeProvider();

        $response = startPurchase(User::factory()->create(), ['product' => 'infinity'])->assertCreated();

        expect($response->json('data'))->toMatchArray(['kind' => 'pass', 'coins' => 0, 'days' => 30, 'amount_minor' => 1999, 'currency' => 'PLN']);
    });

    it('is refused to a guest, whose garage is lost with the device, and says so in the language of the driver', function () {
        payWithTheFakeProvider();

        startPurchase(User::factory()->guest()->create(['locale' => 'pl']))->assertStatus(422)->assertJsonPath('code', 'account_not_secured')
            ->assertJsonPath('message', 'Najpierw zapisz swój garaż: za prawdziwe pieniądze kupuje tylko zabezpieczone konto.');
        expect(PaymentAttempt::query()->count())->toBe(0);
    });

    it('is refused without the confirmations, each of which is needed', function (array $body, string $missing) {
        payWithTheFakeProvider();

        startPurchase(User::factory()->create(), $body)->assertStatus(422)->assertJsonPath('code', 'consent_required')->assertJsonPath('details.missing', $missing);
        expect(PaymentAttempt::query()->count())->toBe(0);
    })->with([
        'the terms' => [['accept_terms' => false], 'accept_terms'],
        'the declaration of age' => [['adult_or_guardian' => false], 'adult_or_guardian'],
        'both' => [['accept_terms' => false, 'adult_or_guardian' => false], 'accept_terms,adult_or_guardian'],
    ]);

    it('records the moment a driver gave up the right of withdrawal, in a game that asks for it, and refuses without it', function () {
        payWithTheFakeProvider();
        Game::query()->find(1)->update(['payments' => ['withdrawal' => 'waived']]);
        $driver = User::factory()->create();

        startPurchase($driver)->assertStatus(422)->assertJsonPath('details.missing', 'acknowledge_digital_content');
        startPurchase($driver, ['acknowledge_digital_content' => true])->assertCreated();

        expect(PaymentAttempt::query()->sole()->waiver_at)->not->toBeNull();
    });

    it('is the same purchase when the request is sent again, and asks the provider once', function () {
        $fake = payWithTheFakeProvider();
        $driver = User::factory()->create();

        $first = startPurchase($driver)->assertCreated();
        $again = startPurchase($driver)->assertOk();

        expect($again->json('repeated'))->toBeTrue()
            ->and($again->json('data.attempt'))->toBe($first->json('data.attempt'))
            ->and($again->json('data.url'))->toBe($first->json('data.url'))
            ->and(PaymentAttempt::query()->count())->toBe(1)
            ->and($fake)->toBeInstanceOf(FakeProvider::class);
    });

    it('refuses a request that was used for another product', function () {
        payWithTheFakeProvider();
        $driver = User::factory()->create();

        startPurchase($driver)->assertCreated();
        startPurchase($driver, ['product' => 'coins-large'])->assertStatus(409)->assertJsonPath('code', 'request_reused');
    });

    it('does not take another driver for the same request', function () {
        payWithTheFakeProvider();

        startPurchase(User::factory()->create())->assertCreated();
        startPurchase(User::factory()->create())->assertCreated();

        expect(PaymentAttempt::query()->count())->toBe(2);
    });

    it('refuses a pack the game does not sell', function () {
        payWithTheFakeProvider();

        startPurchase(User::factory()->create(), ['product' => 'coins-gold'])->assertStatus(422)->assertJsonPath('code', 'product_not_found');
    });

    it('refuses the pass when the game has switched it off', function () {
        payWithTheFakeProvider();
        app()->singleton(Rules::class, function () {
            $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
            $data['premium']['infinity']['enabled'] = false;

            return Rules::fromArray($data);
        });

        startPurchase(User::factory()->create(), ['product' => 'infinity'])->assertStatus(422)->assertJsonPath('code', 'infinity_unavailable');
    });

    it('is refused on an account where buying is blocked, which a dispute does', function () {
        payWithTheFakeProvider();
        $driver = User::factory()->create();
        (new Ledger)->ensureWallet($driver->id);
        Wallet::query()->where('user_id', $driver->id)->update(['purchases_blocked_at' => now()]);

        startPurchase($driver)->assertStatus(422)->assertJsonPath('code', 'purchases_blocked');
    });

    it('ends the attempt and says the payments are not available when the provider cannot give a page', function () {
        payWithTheFakeProvider()->fail();

        startPurchase(User::factory()->create())->assertStatus(422)->assertJsonPath('code', 'payments_unavailable');

        expect(PaymentAttempt::query()->sole()->state)->toBe('failed');
        payWithTheFakeProvider()->fail(false);
    });

    it('asks the provider for a page that names what is sold in the language of the driver and comes back to the game the driver plays', function () {
        $fake = payWithTheFakeProvider();
        $driver = User::factory()->create(['locale' => 'pl']);

        startPurchase($driver)->assertCreated();
        $attempt = PaymentAttempt::query()->sole();

        expect($fake->lastAsked())->toBe([
            'attempt' => $attempt->public_id,
            'name' => 'Średnia paczka',
            'success' => config('ride.web_url').'/premium/return?attempt='.$attempt->public_id,
            'cancel' => config('ride.web_url').'/premium?attempt='.$attempt->public_id.'&canceled=1',
        ]);

        startPurchase($driver, ['product' => 'infinity', 'request_id' => 'pass'])->assertCreated();

        expect($fake->lastAsked()['name'])->toBe('Przepustka nieskończonego tankowania');
    });

    it('sends a driver of another game to the domain of that game, and the attempt belongs to it', function () {
        $fake = payWithTheFakeProvider();
        $ships = aSecondPayingGame();
        $driver = app(CurrentGame::class)->within($ships, fn () => User::factory()->create());
        readFromDatabase();

        $this->postJson('/api/v1/premium/checkout', ['product' => 'coins-small', 'request_id' => 'req-1', 'accept_terms' => true, 'adult_or_guardian' => true], [...bearerFor($driver), 'X-Game' => 'ships'])->assertCreated();
        $attempt = PaymentAttempt::query()->withoutGlobalScopes()->sole();

        expect($attempt->game_id)->toBe($ships->id)
            ->and($fake->lastAsked()['success'])->toBe(parse_url((string) config('ride.web_url'), PHP_URL_SCHEME).'://ships.test/premium/return?attempt='.$attempt->public_id);
    });
});

describe('the limits', function () {
    it('keeps the number of pages waiting for a payment', function () {
        payWithTheFakeProvider();
        Game::query()->find(1)->update(['payments' => ['limits' => ['max_open_attempts' => 2]]]);
        $driver = User::factory()->create();

        startPurchase($driver, ['request_id' => 'a'])->assertCreated();
        startPurchase($driver, ['request_id' => 'b'])->assertCreated();
        startPurchase($driver, ['request_id' => 'c'])->assertStatus(422)->assertJsonPath('code', 'too_many_open_attempts');

        // A page that ran out is no longer waiting.
        PaymentAttempt::query()->update(['expires_at' => now()->subMinute()]);
        startPurchase($driver, ['request_id' => 'd'])->assertCreated();
    });

    it('keeps what a driver can spend in a day, counting what is waiting and what the shop kept', function () {
        payWithTheFakeProvider();
        Game::query()->find(1)->update(['payments' => ['limits' => ['per_day_minor' => 5_000, 'per_month_minor' => 100_000]]]);
        $driver = User::factory()->create();

        startPurchase($driver, ['product' => 'coins-large', 'request_id' => 'a'])->assertCreated();
        // 4999 is waiting: the small pack (499) would make 5498.
        startPurchase($driver, ['product' => 'coins-small', 'request_id' => 'b'])->assertStatus(422)->assertJsonPath('code', 'purchase_limit_reached')->assertJsonPath('details.available', '0.01 PLN');

        PaymentAttempt::query()->update(['state' => 'expired']);
        app(Purchases::class)->fulfil($driver->id, 'coins-medium', 'test', 'pay_1');

        // 1999 was paid: 3001 is left of the day.
        startPurchase($driver, ['product' => 'coins-large', 'request_id' => 'c'])->assertStatus(422)->assertJsonPath('details.available', '30.01 PLN');
        startPurchase($driver, ['product' => 'coins-medium', 'request_id' => 'd'])->assertCreated();
    });

    it('keeps what a driver can spend in a month, and gives back what was taken back', function () {
        payWithTheFakeProvider();
        Game::query()->find(1)->update(['payments' => ['limits' => ['per_day_minor' => 100_000, 'per_month_minor' => 3_000]]]);
        $driver = User::factory()->create();
        $paid = app(Purchases::class)->fulfil($driver->id, 'coins-medium', 'test', 'pay_1');
        Purchase::query()->update(['created_at' => now()->subDays(10)]);

        startPurchase($driver, ['product' => 'coins-medium'])->assertStatus(422)->assertJsonPath('code', 'purchase_limit_reached');

        app(Purchases::class)->refund($paid);

        startPurchase($driver, ['product' => 'coins-medium', 'request_id' => 'later'])->assertCreated();
    });
});

describe('coming back from the page of the provider', function () {
    it('learns that the driver paid by asking the provider when the webhook is late, and gives the coins once', function () {
        $fake = payWithTheFakeProvider();
        $driver = User::factory()->create();
        $attempt = PaymentAttempt::query()->find(startPurchase($driver)->assertCreated() && PaymentAttempt::query()->value('id'));
        $fake->pay($attempt->provider_session);
        $status = fn () => $this->getJson('/api/v1/premium/checkout/'.$attempt->public_id, bearerFor($driver))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        // A moment after the page: the provider is not asked yet, the webhook may be on its way.
        expect($status()->json('data.state'))->toBe('created')->and(coinsOf($driver))->toBe(0);

        Carbon::setTestNow(now()->addSeconds(20));
        $paid = $status();

        expect($paid->json('data'))->toMatchArray(['state' => 'paid', 'url' => null, 'purchase' => ['coins' => 220, 'days' => null, 'status' => 'paid']])
            ->and(coinsOf($driver))->toBe(220)
            ->and(Purchase::query()->sole()->only(['attempt_id', 'provider', 'amount_minor', 'livemode', 'game_id']))->toBe(['attempt_id' => $attempt->id, 'provider' => 'fake', 'amount_minor' => 1999, 'livemode' => false, 'game_id' => 1]);

        $status();
        expect(coinsOf($driver))->toBe(220)->and(Purchase::query()->count())->toBe(1);
    });

    it('sells the pass the same way, and adds its days', function () {
        $fake = payWithTheFakeProvider();
        $driver = User::factory()->create();
        startPurchase($driver, ['product' => 'infinity'])->assertCreated();
        $attempt = PaymentAttempt::query()->sole();
        $fake->pay($attempt->provider_session);

        Carbon::setTestNow(now()->addSeconds(20));
        $this->getJson('/api/v1/premium/checkout/'.$attempt->public_id, bearerFor($driver))->assertOk()->assertJsonPath('data.state', 'paid');

        $until = Wallet::query()->find($driver->id)->infinity_until;
        expect($until->isFuture())->toBeTrue()->and((int) round(now()->diffInDays($until, true)))->toBe(30)
            ->and(Purchase::query()->sole()->only(['product', 'coins', 'grant_days']))->toBe(['product' => 'infinity', 'coins' => 0, 'grant_days' => 30]);
    });

    it('ends the attempt when the page ran out', function () {
        $fake = payWithTheFakeProvider();
        $driver = User::factory()->create();
        startPurchase($driver)->assertCreated();
        $attempt = PaymentAttempt::query()->sole();
        $fake->expire($attempt->provider_session);

        Carbon::setTestNow(now()->addSeconds(20));
        $this->getJson('/api/v1/premium/checkout/'.$attempt->public_id, bearerFor($driver))->assertOk()->assertJsonPath('data.state', 'expired')->assertJsonPath('data.url', null);
    });

    it('keeps waiting when the provider cannot be asked', function () {
        $fake = payWithTheFakeProvider();
        $driver = User::factory()->create();
        startPurchase($driver)->assertCreated();
        $attempt = PaymentAttempt::query()->sole();
        $attempt->forceFill(['provider_session' => 'fake_cs_nobody_knows'])->save();

        Carbon::setTestNow(now()->addSeconds(20));
        $this->getJson('/api/v1/premium/checkout/'.$attempt->public_id, bearerFor($driver))->assertOk()->assertJsonPath('data.state', 'created');
        expect($fake)->toBeInstanceOf(FakeProvider::class);
    });

    it('shows the purchase of a driver to that driver only', function () {
        payWithTheFakeProvider();
        $driver = User::factory()->create();
        startPurchase($driver)->assertCreated();
        $attempt = PaymentAttempt::query()->sole();

        $this->getJson('/api/v1/premium/checkout/'.$attempt->public_id, bearerFor(User::factory()->create()))->assertNotFound();
        $this->getJson('/api/v1/premium/checkout/01ARZ3NDEKTSV4RRFFQ69G5FAV', bearerFor($driver))->assertNotFound();
        $this->getJson('/api/v1/premium/checkout/not-a-reference', bearerFor($driver))->assertNotFound();
        $this->getJson('/api/v1/premium/checkout/'.$attempt->public_id)->assertUnauthorized();
    });
});
