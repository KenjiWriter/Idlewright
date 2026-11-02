<?php

declare(strict_types=1);

use App\Jobs\SendPush;
use App\Models\Game;
use App\Models\PushEvent;
use App\Models\PushPreference;
use App\Models\PushSubscription;
use App\Models\User;
use App\Push\Base64Url;
use App\Push\P256;
use App\Push\PushKeys;
use App\Push\PushOutcome;
use App\Push\PushSender;
use App\Services\AccountErasure;
use App\Services\AccountExport;
use App\Services\Push;
use App\Services\PushSubscriptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// Web push: the keys of a game, the subscription of a device (only addresses of the push services of browsers are taken, and they are kept encrypted), a message that only that
// browser can read, and what the job does with the answer of the push service.

describe('the keys of a game', function () {
    it('are made once, kept encrypted, and replaced only when asked, which deletes the subscriptions they served', function () {
        $game = app(Push::class)->game();
        $game->forceFill(['push_public_key' => null, 'push_private_key' => null, 'push_subject' => null])->save();

        $this->artisan('push:keys')->assertSuccessful();

        $game->refresh();
        $public = $game->push_public_key;

        expect($game->hasPushKeys())->toBeTrue()->and(strlen(Base64Url::decode($public)))->toBe(65)
            ->and(DB::table('games')->where('id', $game->id)->value('push_private_key'))->not->toBe($game->push_private_key)
            ->and($game->toArray())->not->toHaveKey('push_private_key')
            ->and($game->push_subject)->toStartWith('http');

        $driver = User::factory()->guest()->create();
        subscribe($driver, aBrowser())->assertOk();

        $this->artisan('push:keys')->expectsOutputToContain('has its keys already')->assertSuccessful();
        expect($game->refresh()->push_public_key)->toBe($public)->and(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(1);

        $this->artisan('push:keys', ['--force' => true])->expectsOutputToContain('1 subscriptions of the old ones are deleted')->assertSuccessful();
        expect($game->refresh()->push_public_key)->not->toBe($public)->and(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('write to the address of the operator, else to the address of the game', function () {
        config(['ride.legal.operator.email' => 'kontakt@example.test']);
        $game = Game::query()->create(['slug' => 'statki', 'name' => 'Statki', 'domain' => 'statki.example.test', 'locale' => 'pl', 'theme_color' => '#0b3a5b', 'status' => Game::STATUS_LIVE, 'is_default' => false]);

        expect(PushKeys::defaultSubject($game))->toBe('mailto:kontakt@example.test');

        config(['ride.legal.operator.email' => '']);

        expect(PushKeys::defaultSubject($game))->toBe('https://statki.example.test');
    });

    it('are the game’s own: a subscription made with the key of one game is not served with the key of another', function () {
        $driver = pushDriver();
        subscribe($driver, aBrowser())->assertOk();
        $other = Game::query()->create(['slug' => 'statki', 'name' => 'Statki', 'domain' => 'statki.example.test', 'locale' => 'pl', 'theme_color' => '#0b3a5b', 'status' => Game::STATUS_LIVE, 'is_default' => false]);
        PushKeys::create($other);
        Http::fake();

        $result = app(PushSender::class)->send($other->refresh(), subscriptionOf(), aMessage());

        expect($result['outcome'])->toBe(PushOutcome::Rejected);
        Http::assertNothingSent();
    });
});

describe('GET /push/config', function () {
    it('says that the game cannot send, then gives the public key a browser subscribes with', function () {
        app(Push::class)->game()->forceFill(['push_public_key' => null, 'push_private_key' => null])->save();
        $driver = User::factory()->guest()->create();

        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.public_key', null)->assertJsonPath('data.devices', 0);

        PushKeys::create(app(Push::class)->game());
        $key = app(Push::class)->game()->push_public_key;

        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertJsonPath('data.enabled', true)->assertJsonPath('data.public_key', $key);
    });

    it('says that the game cannot send when the rules switch it off, and takes no subscription', function () {
        $driver = pushDriver();
        thePushRulesAre(['enabled' => false]);

        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertJsonPath('data.enabled', false)->assertJsonPath('data.public_key', null);
        subscribe($driver, aBrowser())->assertStatus(422)->assertJsonPath('code', 'push_unavailable');
    });

    it('is for a driver who is signed in', function () {
        $this->getJson('/api/v1/push/config')->assertUnauthorized();
    });
});

describe('PUT /push/subscription', function () {
    it('saves the device encrypted, once for an address however often it is said, and counts the devices', function () {
        $driver = pushDriver();
        $browser = aBrowser();

        subscribe($driver, $browser)->assertOk()->assertJsonPath('data.subscribed', true)->assertJsonPath('data.devices', 1);
        subscribe($driver, $browser)->assertOk()->assertJsonPath('data.devices', 1);

        $saved = subscriptionOf();
        $raw = DB::table('push_subscriptions')->first();

        expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(1)
            ->and($saved->user_id)->toBe($driver->id)
            ->and($saved->endpoint)->toBe(PUSH_ENDPOINT)
            ->and($raw->endpoint)->not->toContain('googleapis')->and($raw->p256dh)->not->toBe($browser['body']['keys']['p256dh'])
            ->and($saved->vapid_key)->toBe(app(Push::class)->game()->push_public_key)
            ->and($saved->timezone)->toBe('Europe/Warsaw')->and($saved->locale)->toBe('fr')
            ->and($saved->toArray())->not->toHaveKeys(['endpoint', 'p256dh', 'auth']);
    });

    it('moves a device to the driver who is signed in on it now', function () {
        $first = pushDriver();
        $second = User::factory()->guest()->create();
        $browser = aBrowser();

        subscribe($first, $browser)->assertOk();
        subscribe($second, $browser)->assertOk()->assertJsonPath('data.devices', 1);

        expect(subscriptionOf()->user_id)->toBe($second->id)
            ->and($this->getJson('/api/v1/push/config', bearerFor($first))->json('data.devices'))->toBe(0);
    });

    it('takes only the addresses of the push services of browsers, because the server calls them', function (string $endpoint) {
        $driver = pushDriver();

        subscribe($driver, aBrowser($endpoint))->assertStatus(422)->assertJsonPath('code', 'push_endpoint');
        expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'plain http' => 'http://fcm.googleapis.com/fcm/send/x',
        'a server of the driver’s own' => 'https://attacker.example.test/push',
        'the address of the machine itself' => 'https://127.0.0.1/fcm/send/x',
        'a host that only ends like a push service' => 'https://evilfcm.googleapis.com.example.test/x',
        'a port that is not the usual one' => 'https://fcm.googleapis.com:8443/fcm/send/x',
        'a user in the address' => 'https://user:secret@fcm.googleapis.com/fcm/send/x',
        'not an address' => 'not an address',
    ]);

    it('takes the services of Firefox, Safari and Windows too', function (string $endpoint) {
        $driver = pushDriver();

        subscribe($driver, aBrowser($endpoint))->assertOk();
    })->with([
        'https://updates.push.services.mozilla.com/wpush/v2/abc',
        'https://web.push.apple.com/QAbc',
        'https://wns2-par02p.notify.windows.com/w/?token=abc',
    ]);

    it('refuses keys that are not a point of the curve, a secret of the wrong size and a body without keys', function () {
        $driver = pushDriver();
        $browser = aBrowser();

        foreach ([
            [...$browser['body'], 'keys' => ['p256dh' => Base64Url::encode("\x04".str_repeat("\x01", 64)), 'auth' => $browser['body']['keys']['auth']]],
            [...$browser['body'], 'keys' => ['p256dh' => $browser['body']['keys']['p256dh'], 'auth' => Base64Url::encode('short')]],
            [...$browser['body'], 'keys' => ['p256dh' => '!!', 'auth' => '!!']],
        ] as $body) {
            subscribe($driver, $browser, $body)->assertStatus(422)->assertJsonPath('code', 'push_keys');
        }

        subscribe($driver, $browser, ['endpoint' => PUSH_ENDPOINT])->assertStatus(422)->assertJsonValidationErrors(['keys']);
        expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('forgets a time zone that does not exist and takes the language of the driver when the device does not say', function () {
        $driver = pushDriver();
        $driver->forceFill(['locale' => 'de'])->save();
        $body = aBrowser()['body'];
        unset($body['locale']);
        $body['timezone'] = 'Mars/Olympus';

        $this->putJson('/api/v1/push/subscription', $body, bearerFor($driver))->assertOk();

        expect(subscriptionOf()->timezone)->toBeNull()->and(subscriptionOf()->locale)->toBe('de');
    });

    it('keeps the ten newest devices of a driver', function () {
        $driver = pushDriver();

        foreach (range(1, PushSubscriptions::MAX_DEVICES + 2) as $number) {
            subscribe($driver, aBrowser("https://fcm.googleapis.com/fcm/send/device-{$number}"))->assertOk();
            $this->travel(1)->minutes();
        }

        expect(PushSubscription::query()->where('user_id', $driver->id)->count())->toBe(PushSubscriptions::MAX_DEVICES);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint_hash' => PushSubscription::hashOf('https://fcm.googleapis.com/fcm/send/device-1')]);
    });
});

describe('DELETE /push/subscription', function () {
    it('takes the device away, and only if it is the driver’s own', function () {
        $driver = pushDriver();
        $stranger = User::factory()->guest()->create();
        subscribe($driver, aBrowser())->assertOk();

        $this->deleteJson('/api/v1/push/subscription', ['endpoint' => PUSH_ENDPOINT], bearerFor($stranger))->assertOk()->assertJsonPath('data.removed', false);
        expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(1);

        $this->deleteJson('/api/v1/push/subscription', ['endpoint' => PUSH_ENDPOINT], bearerFor($driver))->assertOk()->assertJsonPath('data.removed', true)->assertJsonPath('data.devices', 0);
        expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('POST /push/test', function () {
    it('queues a message in the language of the device, to every device that is not switched off', function () {
        Queue::fake();
        $driver = pushDriver();
        subscribe($driver, aBrowser())->assertOk();
        subscribe($driver, aBrowser('https://updates.push.services.mozilla.com/wpush/v2/x'))->assertOk();
        PushSubscription::query()->withoutGlobalScopes()->where('endpoint_hash', PushSubscription::hashOf('https://updates.push.services.mozilla.com/wpush/v2/x'))->update(['disabled_at' => now()]);

        $this->postJson('/api/v1/push/test', [], bearerFor($driver))->assertOk()->assertJsonPath('data.queued', 1);

        Queue::assertPushed(SendPush::class, 1);
        Queue::assertPushed(SendPush::class, function (SendPush $job): bool {
            return $job->message['kind'] === 'test' && $job->message['lang'] === 'fr' && str_contains($job->message['body'], 'notifications sont activées')
                && $job->message['ttl'] === 600 && $job->message['url'] === '/garage' && $job->message['title'] === app(Push::class)->game()->name;
        });
    });

    it('says when there is no device', function () {
        Queue::fake();
        $driver = pushDriver();

        $this->postJson('/api/v1/push/test', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'push_no_device');
        Queue::assertNothingPushed();
    });

    it('says when the game cannot send', function () {
        Queue::fake();
        $driver = pushDriver();
        thePushRulesAre(['enabled' => false]);

        $this->postJson('/api/v1/push/test', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'push_unavailable');
        Queue::assertNothingPushed();
    });

    it('is three an hour', function () {
        Queue::fake();
        $driver = pushDriver();
        subscribe($driver, aBrowser())->assertOk();

        foreach (range(1, 3) as $ignored) {
            $this->postJson('/api/v1/push/test', [], bearerFor($driver))->assertOk();
        }

        $this->postJson('/api/v1/push/test', [], bearerFor($driver))->assertStatus(429);
    });
});

describe('the job that sends', function () {
    /** The job for the device of a browser that subscribed, and the browser. */
    function sendingTo(User $driver): array
    {
        $browser = aBrowser();
        subscribe($driver, $browser)->assertOk();

        return [(new SendPush(subscriptionOf()->id, aMessage()->toArray()))->withFakeQueueInteractions(), $browser];
    }

    it('sends a message that only that browser can read, signed as the game, with the headers of the push service', function () {
        Http::fake([PUSH_ENDPOINT => Http::response('', 201)]);
        [$job, $browser] = sendingTo(pushDriver());

        $job->handle(app(PushSender::class));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($browser): bool {
            $game = app(Push::class)->game();
            $plain = receivePush($request->body(), $browser['private'], $browser['point'], $browser['auth']);

            return $request->url() === PUSH_ENDPOINT
                && $request->method() === 'POST'
                && str_starts_with($request->header('Authorization')[0], 'vapid t=') && str_ends_with($request->header('Authorization')[0], ', k='.$game->push_public_key)
                && $request->header('Content-Encoding')[0] === 'aes128gcm'
                && $request->header('TTL')[0] === '600'
                && $request->header('Urgency')[0] === 'normal'
                && $request->header('Topic')[0] === aMessage()->topic() && strlen($request->header('Topic')[0]) <= 32
                && json_decode((string) $plain, true) === ['kind' => 'test', 'title' => 'Rdzak', 'body' => 'Skończyło się paliwo.', 'url' => '/garage', 'tag' => 'test', 'lang' => 'pl'];
        });
        $job->assertNotReleased();
        expect(subscriptionOf()->last_ok_at)->not->toBeNull()->and(subscriptionOf()->failures)->toBe(0);
    });

    it('deletes a subscription that the push service says is gone', function (int $status) {
        Http::fake([PUSH_ENDPOINT => Http::response('', $status)]);
        [$job] = sendingTo(pushDriver());

        $job->handle(app(PushSender::class));

        expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
    })->with([404, 410]);

    it('tries again later when the push service is busy or not there, as long as it says', function (Closure $answer, int $delay) {
        Http::fake([PUSH_ENDPOINT => $answer]);
        [$job] = sendingTo(pushDriver());

        $job->handle(app(PushSender::class));

        $job->assertReleased($delay);
        expect(subscriptionOf()->failures)->toBe(0)->and(subscriptionOf()->isActive())->toBeTrue();
    })->with([
        'busy, and it says for how long' => [fn () => Http::response('', 429, ['Retry-After' => '900']), 900],
        'busy, and it does not' => [fn () => Http::response('', 429), 60],
        'down' => [fn () => Http::response('', 503), 60],
        'not there at all' => [fn () => throw new ConnectionException('no route'), 60],
    ]);

    it('counts a refusal against the device and switches it off after five', function () {
        Http::fake([PUSH_ENDPOINT => Http::response('', 400)]);
        [$job] = sendingTo(pushDriver());

        foreach (range(1, PushSubscription::MAX_FAILURES - 1) as $ignored) {
            $job->handle(app(PushSender::class));
        }

        expect(subscriptionOf()->failures)->toBe(PushSubscription::MAX_FAILURES - 1)->and(subscriptionOf()->isActive())->toBeTrue();

        $job->handle(app(PushSender::class));

        expect(subscriptionOf()->failures)->toBe(PushSubscription::MAX_FAILURES)->and(subscriptionOf()->isActive())->toBeFalse();

        // A device that is switched off is left alone, and a new subscription from it starts afresh.
        Http::fake();
        $job->handle(app(PushSender::class));
        Http::assertNothingSent();

        subscribe(subscriptionOf()->user, [], ['endpoint' => PUSH_ENDPOINT, 'keys' => ['p256dh' => Base64Url::encode(P256::generate()['public']), 'auth' => Base64Url::encode(random_bytes(16))]])->assertOk();
        expect(subscriptionOf()->isActive())->toBeTrue()->and(subscriptionOf()->failures)->toBe(0);
    });

    it('does not send to a device whose subscription was made with another key of the game', function () {
        Http::fake();
        [$job] = sendingTo(pushDriver());
        subscriptionOf()->forceFill(['vapid_key' => 'an-old-key'])->save();

        $job->handle(app(PushSender::class));

        Http::assertNothingSent();
        expect(subscriptionOf()->failures)->toBe(1);
    });
});

describe('/push/preferences', function () {
    it('gives the kinds with what the game switches on, changes only the kinds that are given, and keeps the choice', function () {
        $driver = pushDriver();

        $this->getJson('/api/v1/push/preferences', bearerFor($driver))->assertOk()->assertJsonPath('data.kinds', ['car_stopped' => true, 'car_arrived' => true, 'job_done' => false]);

        $this->patchJson('/api/v1/push/preferences', ['kinds' => ['car_arrived' => false]], bearerFor($driver))->assertOk()
            ->assertJsonPath('data.kinds', ['car_stopped' => true, 'car_arrived' => false, 'job_done' => false]);
        $this->patchJson('/api/v1/push/preferences', ['kinds' => ['job_done' => true]], bearerFor($driver))->assertOk()
            ->assertJsonPath('data.kinds', ['car_stopped' => true, 'car_arrived' => false, 'job_done' => true]);

        $this->getJson('/api/v1/push/preferences', bearerFor($driver))->assertJsonPath('data.kinds.car_arrived', false);
    });

    it('is the driver’s own, and refuses what is not a kind on or off', function () {
        $driver = pushDriver();
        $other = User::factory()->guest()->create();

        $this->patchJson('/api/v1/push/preferences', ['kinds' => ['car_stopped' => false]], bearerFor($driver))->assertOk();

        $this->getJson('/api/v1/push/preferences', bearerFor($other))->assertJsonPath('data.kinds.car_stopped', true);
        $this->patchJson('/api/v1/push/preferences', ['kinds' => ['car_stopped' => 'maybe']], bearerFor($driver))->assertStatus(422)->assertJsonValidationErrors(['kinds.car_stopped']);
        $this->patchJson('/api/v1/push/preferences', [], bearerFor($driver))->assertStatus(422);
    });
});

describe('the data of a driver', function () {
    it('is in the copy of the account without the address and the keys of the device, and is gone with the account', function () {
        $driver = pushDriver();
        subscribe($driver, aBrowser())->assertOk();
        $this->patchJson('/api/v1/push/preferences', ['kinds' => ['job_done' => true]], bearerFor($driver))->assertOk();
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'car_stopped', 'ref_type' => 'race_run', 'ref_id' => 1, 'due_ms' => 5, 'state' => 'sent', 'sent_at' => now()]);
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'car_stopped', 'ref_type' => 'race_run', 'ref_id' => 2, 'due_ms' => 9, 'state' => 'planned']);

        $game = (new AccountExport)->for($driver)['game'];

        expect($game['push_devices'])->toHaveCount(1)->and($game['push_devices'][0])->toHaveKeys(['browser', 'locale', 'timezone', 'subscribed_at', 'last_delivery_at', 'switched_off_at'])
            ->and($game['push_devices'][0]['locale'])->toBe('fr')->and($game['push_devices'][0]['timezone'])->toBe('Europe/Warsaw')
            ->and(json_encode($game))->not->toContain('googleapis')->and(json_encode($game))->not->toContain('endpoint')
            ->and($game['push_preferences'])->toBe(['job_done' => true])
            ->and($game['push_messages'])->toHaveCount(1)->and($game['push_messages'][0]['kind'])->toBe('car_stopped');

        $removed = app(AccountErasure::class)->erase($driver);

        expect($removed['push devices'])->toBe(1)->and($removed['push messages'])->toBe(2)
            ->and(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0)
            ->and(PushEvent::query()->withoutGlobalScopes()->count())->toBe(0)
            ->and(PushPreference::query()->count())->toBe(0);
    });

    it('is cleaned up: a device that was switched off more than 30 days ago, and the notes of messages older than that', function () {
        $driver = pushDriver();
        subscribe($driver, aBrowser('https://fcm.googleapis.com/fcm/send/old'))->assertOk();
        subscribe($driver, aBrowser('https://fcm.googleapis.com/fcm/send/recent'))->assertOk();
        subscribe($driver, aBrowser('https://fcm.googleapis.com/fcm/send/alive'))->assertOk();
        subscriptionOf('https://fcm.googleapis.com/fcm/send/old')->forceFill(['disabled_at' => now()->subDays(31)])->save();
        subscriptionOf('https://fcm.googleapis.com/fcm/send/recent')->forceFill(['disabled_at' => now()->subDays(29)])->save();

        foreach ([['sent', 31, 1], ['skipped', 29, 2], ['planned', 31, 3]] as [$state, $days, $ref]) {
            $event = PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'car_stopped', 'ref_type' => 'race_run', 'ref_id' => $ref, 'due_ms' => $ref, 'state' => $state]);
            PushEvent::query()->whereKey($event->id)->update(['updated_at' => now()->subDays($days)]);
        }

        $this->artisan('push:prune')->expectsOutputToContain('1 devices and 1 notes deleted')->assertSuccessful();

        expect(PushSubscription::query()->count())->toBe(2)
            ->and(PushEvent::query()->pluck('state')->sort()->values()->all())->toBe(['planned', 'skipped']);
    });
});
