<?php

declare(strict_types=1);

use App\Filament\Pages\PushPage;
use App\Models\PushDelivery;
use App\Models\PushEvent;
use App\Models\PushSubscription;
use App\Models\User;
use App\Push\PushKeys;
use App\Services\Push;
use App\Services\PushStats;
use Carbon\CarbonImmutable;

// The page of the panel that says how the notifications of the game stand: the rules, the keys (only the public one), who has agreed and what was sent in the last seven days.

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false, activate: true);
    config(['ride.admin_locale' => 'en']);
    thePushRulesAre([]);
});

function aDevice(User $user, string $browser = 'chrome', ?string $disabledAt = null): PushSubscription
{
    return PushSubscription::query()->create([
        'user_id' => $user->id, 'endpoint_hash' => hash('sha256', uniqid('', true)), 'endpoint' => 'https://fcm.googleapis.com/fcm/send/x', 'p256dh' => 'p', 'auth' => 'a',
        'vapid_key' => 'k', 'ua_family' => $browser, 'disabled_at' => $disabledAt,
    ]);
}

describe('the numbers', function () {
    it('say that nothing can be sent while the game has no keys, and never show a private key', function () {
        $game = app(Push::class)->game();
        $game->forceFill(['push_public_key' => null, 'push_private_key' => null])->save();

        $report = app(PushStats::class)->report();

        expect($report['enabled'])->toBeTrue()->and($report['has_keys'])->toBeFalse()->and($report['public_key'])->toBeNull()
            ->and($report['devices'])->toBe(['active' => 0, 'disabled' => 0, 'browsers' => []])
            ->and(array_keys($report))->not->toContain('private_key');

        PushKeys::create($game);

        $report = app(PushStats::class)->report();

        expect($report['has_keys'])->toBeTrue()->and($report['public_key'])->toBe($game->refresh()->push_public_key)
            ->and(json_encode($report))->not->toContain((string) $game->push_private_key);
    });

    it('count the devices, the accounts that have one and the browsers, and leave out the group that is never told', function () {
        thePushRulesAre(['holdout_ppm' => 500_000]);
        $told = accountsInArm('push', 3, 1);
        $held = accountsInArm('holdout', 2, 1);
        aDevice($told[0]);
        aDevice($told[0], 'firefox');
        aDevice($told[1], 'chrome');
        aDevice($told[2], 'safari', now()->subDay()->toDateTimeString());

        $report = app(PushStats::class)->report();

        expect($report['devices'])->toBe(['active' => 3, 'disabled' => 1, 'browsers' => ['chrome' => 2, 'firefox' => 1]])
            ->and($report['accounts'])->toBe(['eligible' => 3, 'with_device' => 2, 'share' => 0.6667, 'holdout' => 2])
            ->and($report['holdout_ppm'])->toBe(500_000)
            ->and($report['week']['switched_off'])->toBe(1)
            ->and($held)->toHaveCount(2);
    });

    it('count the messages of each of the last seven days, those that were opened, the things told and what was left unsaid with the reason', function () {
        $driver = User::factory()->guest()->create();
        aDevice($driver);
        $today = CarbonImmutable::now();
        PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'a', 'kind' => 'car_stopped', 'opened_at' => $today]);
        PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'b', 'kind' => 'car_stopped']);
        $old = PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'c', 'kind' => 'car_stopped']);
        PushDelivery::query()->whereKey($old->id)->update(['created_at' => $today->subDays(3)]);
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'car_stopped', 'ref_type' => 'race_run', 'ref_id' => 1, 'due_ms' => 1, 'state' => 'sent', 'sent_at' => $today]);
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'car_stopped', 'ref_type' => 'race_run', 'ref_id' => 2, 'due_ms' => 2, 'state' => 'sent', 'sent_at' => $today]);
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'job_done', 'ref_type' => 'workshop_job', 'ref_id' => 3, 'due_ms' => 3, 'state' => 'skipped', 'reason' => 'off']);
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'job_done', 'ref_type' => 'workshop_job', 'ref_id' => 4, 'due_ms' => 4, 'state' => 'skipped', 'reason' => 'off']);
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'job_done', 'ref_type' => 'workshop_job', 'ref_id' => 5, 'due_ms' => 5, 'state' => 'skipped', 'reason' => 'limit']);

        $report = app(PushStats::class)->report($today);
        $days = collect($report['days'])->keyBy('day');

        expect($report['days'])->toHaveCount(7)
            ->and($days[$today->toDateString()])->toBe(['day' => $today->toDateString(), 'queued' => 2, 'opened' => 1, 'told' => 2])
            ->and($days[$today->subDays(3)->toDateString()]['queued'])->toBe(1)
            ->and($report['week'])->toBe(['queued' => 3, 'opened' => 1, 'per_device_per_day' => 0.43, 'switched_off' => 0])
            ->and($report['skipped'])->toBe(['off' => 2, 'limit' => 1]);
    });
});

describe('the page', function () {
    it('shows the state of the rules and the keys, the public key and what was sent, in the language of the panel', function () {
        signedInAdmin();
        $game = app(Push::class)->game();
        PushKeys::create($game, replace: true);
        $driver = User::factory()->guest()->create();
        aDevice($driver);
        PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'a', 'kind' => 'car_stopped']);

        $page = $this->get(PushPage::getUrl())->assertOk();

        $page->assertSee('Push notifications')->assertSee('Switched on in the rules')->assertSee('Keys made')->assertSee($game->refresh()->push_public_key)
            ->assertDontSee((string) $game->push_private_key)->assertSee('Devices: 1 on, 0 switched off.')->assertSeeHtml('data-test="push-day"');
    });

    it('says what to do when there are no keys', function () {
        signedInAdmin();
        app(Push::class)->game()->forceFill(['push_public_key' => null, 'push_private_key' => null])->save();

        $this->get(PushPage::getUrl())->assertOk()->assertSee('No keys yet')->assertSee('php artisan push:keys');
    });

    it('is in every language of the panel', function () {
        signedInAdmin();

        foreach (['pl', 'fr', 'de', 'pt-BR'] as $locale) {
            config(['ride.admin_locale' => $locale]);

            $this->get(PushPage::getUrl())->assertOk()->assertDontSee('admin.push_page');
        }
    });
});
