<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountConsentController;
use App\Http\Controllers\Api\V1\AccountDataController;
use App\Http\Controllers\Api\V1\AccountEmailController;
use App\Http\Controllers\Api\V1\AccountIdentityController;
use App\Http\Controllers\Api\V1\AccountReauthController;
use App\Http\Controllers\Api\V1\AccountRecoveryController;
use App\Http\Controllers\Api\V1\AuthProviderController;
use App\Http\Controllers\Api\V1\AwayController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\FleetController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\GarageController;
use App\Http\Controllers\Api\V1\GigController;
use App\Http\Controllers\Api\V1\LegalController;
use App\Http\Controllers\Api\V1\LocaleController;
use App\Http\Controllers\Api\V1\MarketController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MissionController;
use App\Http\Controllers\Api\V1\ModelController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PlayerController;
use App\Http\Controllers\Api\V1\PremiumController;
use App\Http\Controllers\Api\V1\PushController;
use App\Http\Controllers\Api\V1\RunController;
use App\Http\Controllers\Api\V1\RunProfileController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\StarterCarController;
use App\Http\Controllers\Api\V1\TransmuteController;
use App\Http\Controllers\Api\V1\TutorialController;
use App\Http\Controllers\Api\V1\VocabularyController;
use App\Http\Controllers\Api\V1\WorldController;
use App\Http\Middleware\ApplyVocabulary;
use App\Http\Middleware\EnsureConsent;
use App\Http\Middleware\ResolveGame;
use App\Http\Middleware\TouchLastSeen;
use App\Models\AccountIdentity;
use App\Services\ContentImages;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/meta/locales', [LocaleController::class, 'index']);
    Route::get('/meta/legal', [LegalController::class, 'show']);
    Route::get('/meta/vocabulary', [VocabularyController::class, 'show']);
    Route::get('/meta/model', [ModelController::class, 'show']);
    Route::get('/meta/game', [GameController::class, 'show']);
    Route::get('/meta/manifest', [GameController::class, 'manifest']);
    Route::get('/meta/theme.css', [BrandingController::class, 'theme'])->name('theme');
    Route::get('/meta/landing', [BrandingController::class, 'landing']);
    Route::get('/meta/share', [BrandingController::class, 'share']);

    // The pictures of the cars and the parts, by the checksum of their bytes.
    Route::get('/media/{name}', [MediaController::class, 'show'])->where('name', ContentImages::ROUTE)->name('media');

    // The sign-ins with another service that are on, and the way in and out of each of them. The providers are
    // Google, Facebook and Apple; the callback is where the provider sends the person back (Apple with a form post).
    Route::get('/auth/providers', [AuthProviderController::class, 'index']);
    Route::post('/auth/complete', [SocialAuthController::class, 'complete'])->middleware('throttle:account-confirm');
    Route::post('/auth/{provider}/start', [SocialAuthController::class, 'startLogin'])->whereIn('provider', AccountIdentity::SOCIAL)->middleware('throttle:account-oauth-start');
    Route::match(['get', 'post'], '/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->whereIn('provider', AccountIdentity::SOCIAL);

    // What a payment provider tells the game. It comes from the provider's servers, not from a game's domain: no game is claimed by the host (the game of a payment is the one
    // of its attempt), and nothing but the signature lets it in.
    Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->withoutMiddleware(ResolveGame::class);

    // A new guest driver. Limited per address to make mass account creation expensive.
    Route::post('/players', [PlayerController::class, 'store'])->middleware('throttle:guest-create');
    // A notification was opened (the service worker has no token of the driver, so this is open; it only takes a secret number a message carried).
    Route::post('/push/seen', [PushController::class, 'seen'])->middleware('throttle:push-seen');

    // Sign in on a new device with a recovery code.
    Route::post('/account/recover', [AccountRecoveryController::class, 'recover'])->middleware('throttle:recovery-attempts');

    // Ask for a link that signs in on a new device. The answer does not tell whether the address has a garage.
    Route::post('/account/login-link', [AccountEmailController::class, 'requestLoginLink'])->middleware('throttle:account-login-link');

    // Use the link of an e-mail: it secures an account or signs a driver in. The secret in it cannot be guessed.
    Route::post('/account/email/confirm', [AccountEmailController::class, 'confirm'])->middleware('throttle:account-confirm');

    // "This was not me", from the notice sent to the old address when the e-mail of an account is changed: it works with the link alone.
    Route::post('/account/email/revoke', [AccountEmailController::class, 'revoke'])->middleware('throttle:account-confirm');

    // A driver who has to accept the terms again can do only what EnsureConsent leaves open.
    Route::middleware(['auth:sanctum', TouchLastSeen::class, ApplyVocabulary::class, EnsureConsent::class])->group(function (): void {
        Route::get('/me', [MeController::class, 'show']);
        Route::patch('/me/locale', [MeController::class, 'updateLocale']);
        // Web push: what the app needs to subscribe, the subscription of a device, and a message to the driver's own devices to see that it works.
        Route::get('/push/config', [PushController::class, 'config']);
        Route::put('/push/subscription', [PushController::class, 'save'])->middleware('throttle:push-subscription');
        Route::delete('/push/subscription', [PushController::class, 'destroy'])->middleware('throttle:push-subscription');
        Route::get('/push/preferences', [PushController::class, 'preferences']);
        Route::patch('/push/preferences', [PushController::class, 'updatePreferences']);
        Route::post('/push/prompt', [PushController::class, 'putOff'])->middleware('throttle:push-subscription');
        Route::post('/push/test', [PushController::class, 'test'])->middleware('throttle:push-test');
        // A copy of the data of the account, and, in the same place, having the account deleted.
        Route::get('/account/export', [AccountDataController::class, 'export'])->middleware('throttle:account-data');
        Route::delete('/account', [AccountDataController::class, 'destroy'])->middleware('throttle:account-erase');
        Route::post('/account/recovery-code', [AccountRecoveryController::class, 'issue'])->middleware('throttle:recovery-issue');
        Route::post('/account/email', [AccountEmailController::class, 'store'])->middleware('throttle:account-email');
        Route::post('/account/email/change', [AccountEmailController::class, 'change'])->middleware('throttle:account-email-change');
        Route::post('/account/identities/{provider}/start', [SocialAuthController::class, 'startLink'])->whereIn('provider', AccountIdentity::SOCIAL)->middleware('throttle:account-oauth-start');
        Route::delete('/account/identities/{provider}', [AccountIdentityController::class, 'destroy'])->whereIn('provider', AccountIdentity::ALL)->middleware('throttle:account-unlink');

        // A recent proof of who the driver is, which unlinking a sign-in needs: by a link to the e-mail of the account, or by signing in again with a provider.
        Route::get('/account/reauth', [AccountReauthController::class, 'show']);
        Route::post('/account/reauth/email', [AccountReauthController::class, 'email'])->middleware('throttle:account-reauth');
        Route::post('/account/reauth/{provider}/start', [SocialAuthController::class, 'startReauth'])->whereIn('provider', AccountIdentity::SOCIAL)->middleware('throttle:account-oauth-start');

        // Accepting the terms again after they changed.
        Route::post('/account/consent', [AccountConsentController::class, 'store'])->middleware('throttle:account-consent');

        Route::get('/starter-cars', StarterCarController::class);

        Route::get('/cars', [FleetController::class, 'index']);
        Route::post('/cars', [FleetController::class, 'buyCar']);
        Route::post('/cars/{car}/select', [FleetController::class, 'select'])->whereNumber('car');
        Route::post('/cars/{car}/sell', [FleetController::class, 'sell'])->whereNumber('car');
        Route::post('/cars/{car}/store', [FleetController::class, 'store'])->whereNumber('car');
        Route::post('/cars/{car}/activate', [FleetController::class, 'activate'])->whereNumber('car');
        Route::post('/cars/{car}/swap', [FleetController::class, 'swap'])->whereNumber('car');
        Route::post('/garages', [FleetController::class, 'buyGarage']);

        Route::get('/market', [MarketController::class, 'show']);
        Route::post('/market/exchange/{key}/inspect', [MarketController::class, 'inspect'])->where('key', '[a-z0-9.-]+');
        Route::post('/market/exchange/{key}/buy', [MarketController::class, 'buyListing'])->where('key', '[a-z0-9.-]+');
        Route::post('/market/dealer', [MarketController::class, 'buyNew']);
        Route::post('/market/storage/{unit}/open', [MarketController::class, 'openStorage'])->where('unit', '[a-z0-9-]+');

        Route::get('/map', [WorldController::class, 'show']);
        Route::post('/travel', [WorldController::class, 'travel']);
        Route::post('/travel/finish', [WorldController::class, 'finishTravel']);
        Route::post('/travel/police', [WorldController::class, 'police']);

        // The side jobs of a city: the board for a car, taking an offer, and taking it back in the first seconds.
        Route::get('/gigs', [GigController::class, 'show'])->middleware('throttle:gigs');
        Route::post('/gigs', [GigController::class, 'take']);
        Route::post('/gigs/bail', [GigController::class, 'bail']);
        Route::delete('/gigs/{gig}', [GigController::class, 'cancel'])->whereNumber('gig');

        Route::get('/garage', [GarageController::class, 'show']);
        Route::post('/garage/jobs', [GarageController::class, 'queueJob']);
        Route::delete('/garage/jobs/{job}', [GarageController::class, 'cancelJob'])->whereNumber('job');
        Route::post('/garage/jobs/{job}/finish', [GarageController::class, 'finishJob'])->whereNumber('job');
        Route::post('/garage/equipment', [GarageController::class, 'buyEquipment']);
        Route::get('/parts/{part}/transmute', [TransmuteController::class, 'show'])->whereNumber('part');
        Route::post('/parts/{part}/transmute', [TransmuteController::class, 'store'])->whereNumber('part');

        Route::get('/tutorial', [TutorialController::class, 'show']);
        Route::get('/tutorial/story', [TutorialController::class, 'story']);
        Route::post('/tutorial/reply', [TutorialController::class, 'reply']);
        Route::post('/tutorial/decline', [TutorialController::class, 'decline']);
        Route::post('/tutorial/skip', [TutorialController::class, 'skip']);

        Route::get('/premium', [PremiumController::class, 'show']);
        Route::post('/premium/autorefill', [PremiumController::class, 'buyAutoRefill']);
        Route::patch('/premium/autorefill', [PremiumController::class, 'setAutoRefill']);
        Route::post('/premium/vip', [PremiumController::class, 'buyVip']);
        // Paying with real money: the driver is sent to the page of the provider, and comes back to ask how it went.
        Route::post('/premium/checkout', [PaymentController::class, 'checkout'])->middleware('throttle:payments-checkout');
        Route::get('/premium/checkout/{attempt}', [PaymentController::class, 'status'])->where('attempt', '[0-9A-Za-z]{26}')->middleware('throttle:payments-status');

        // The sets of missions of a day, a week and a month, and taking what they pay.
        Route::get('/missions', [MissionController::class, 'show'])->middleware('throttle:missions');
        Route::post('/missions/claim', [MissionController::class, 'claimAll']);
        Route::post('/missions/streak/claim', [MissionController::class, 'claimStreak']);
        Route::post('/missions/{mission}/claim', [MissionController::class, 'claim'])->whereNumber('mission');
        Route::post('/missions/{kind}/bonus', [MissionController::class, 'bonus'])->whereIn('kind', ['daily', 'weekly', 'monthly']);

        Route::get('/away', [AwayController::class, 'show']);
        Route::post('/away/seen', [AwayController::class, 'seen']);

        Route::get('/shop', [ShopController::class, 'index']);
        Route::post('/shop/purchases', [ShopController::class, 'purchase']);
        Route::post('/shop/crates/{kind}/open', [ShopController::class, 'openCrate'])->where('kind', '[a-z0-9-]+');

        Route::post('/runs', [RunController::class, 'store']);
        Route::get('/runs/current', [RunController::class, 'current']);
        Route::get('/runs/current/profile', [RunProfileController::class, 'show']);
        Route::get('/runs/{run}', [RunController::class, 'show'])->whereNumber('run');
        Route::patch('/runs/{run}', [RunController::class, 'update'])->whereNumber('run');
        Route::post('/runs/{run}/start', [RunController::class, 'start'])->whereNumber('run');
        Route::post('/runs/{run}/refuel', [RunController::class, 'refuel'])->whereNumber('run');
        Route::post('/runs/{run}/repair', [RunController::class, 'repair'])->whereNumber('run');
        Route::post('/runs/{run}/tow', [RunController::class, 'tow'])->whereNumber('run');
    });
});
