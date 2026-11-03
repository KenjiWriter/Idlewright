<?php

declare(strict_types=1);

use App\Jobs\SendPush;
use App\Models\PushDelivery;
use App\Models\PushEvent;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserVisit;
use App\Push\PushKeys;
use App\Services\Metrics;
use App\Services\Push;
use App\Services\PushArm;
use App\Services\PushDispatcher;
use App\Services\PushPlanner;
use App\Services\RunService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

// The experiment: part of the accounts is never told by push (the holdout), so that it can be seen that the notifications do not make the drivers come back less; and the number that
// a message carries back when it is opened.

afterEach(fn () => Carbon::setTestNow());

function cameBack(User $user, int $dayAfter): void
{
    UserVisit::factory()->create(['user_id' => $user->id, 'at' => CarbonImmutable::instance($user->created_at)->startOfDay()->addDays($dayAfter)->addHours(12)]);
}

describe('the groups', function () {
    it('are a hash of the number of the account: the size asked for, the same account in the same group every time, and none or all at the edges', function () {
        thePushRulesAre(['holdout_ppm' => 200_000]);
        $arm = app(PushArm::class);
        $held = collect(range(1, 4_000))->filter(fn (int $id): bool => $arm->isHoldout($id))->count();

        expect($held)->toBeBetween(720, 880)->and($arm->of(17))->toBe($arm->of(17));

        thePushRulesAre(['holdout_ppm' => 0]);
        expect(collect(range(1, 500))->filter(fn (int $id): bool => app(PushArm::class)->isHoldout($id))->count())->toBe(0);
    });

    it('put everybody in the holdout at 100%', function () {
        thePushRulesAre(['holdout_ppm' => 1_000_000]);

        expect(collect(range(1, 300))->every(fn (int $id): bool => app(PushArm::class)->isHoldout($id)))->toBeTrue();
    });

    it('move with the salt, which is how a new experiment starts', function () {
        thePushRulesAre(['holdout_ppm' => 500_000]);
        $before = collect(range(1, 400))->map(fn (int $id): string => app(PushArm::class)->of($id))->all();

        config(['ride.push.arm_salt' => 'another-experiment']);
        $after = collect(range(1, 400))->map(fn (int $id): string => app(PushArm::class)->of($id))->all();

        expect($after)->not->toBe($before);
    });
});

describe('an account in the holdout', function () {
    it('is not offered notifications, cannot subscribe and is sent nothing', function () {
        thePushRulesAre(['holdout_ppm' => 1_000_000]);
        Queue::fake();
        $driver = pushDriver();

        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.public_key', null);
        subscribe($driver, aBrowser())->assertStatus(422)->assertJsonPath('code', 'push_unavailable');
        $this->postJson('/api/v1/push/test', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'push_unavailable');
        expect(app(Push::class)->notify($driver, 'test', '/garage'))->toBe(0);
        Queue::assertNothingPushed();
    });

    it('has no forecast made for its cars, and what was planned for it is thrown away', function () {
        thePushRulesAre(['holdout_ppm' => 1_000_000]);
        Queue::fake();
        [$driver, $car] = driverWithRun(['fuel_ml' => 6_000]);
        PushSubscription::query()->create([
            'user_id' => $driver->id, 'endpoint_hash' => str_repeat('a', 64), 'endpoint' => 'https://fcm.googleapis.com/fcm/send/x', 'p256dh' => 'p', 'auth' => 'a', 'vapid_key' => 'k',
        ]);
        app(RunService::class)->settle($car->id, RunService::nowMs());

        expect(app(PushPlanner::class)->wanted($driver->id))->toBeFalse()->and(PushEvent::query()->count())->toBe(0);

        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'car_stopped', 'ref_type' => 'race_run', 'ref_id' => $car->id, 'due_ms' => RUN_T0, 'state' => 'planned']);
        $game = app(Push::class)->game();
        $game->hasPushKeys() || PushKeys::create($game);
        clockAt(600_000);

        expect(app(PushDispatcher::class)->run(RunService::nowMs()))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 0]);
        expect(PushEvent::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });
});

describe('a message that is opened', function () {
    it('carries a number that is written down, and the service worker sends it back with no sign-in', function () {
        Queue::fake();
        $driver = pushDriver();
        subscribe($driver, aBrowser())->assertOk();

        $this->postJson('/api/v1/push/test', [], bearerFor($driver))->assertOk();

        $delivery = PushDelivery::query()->sole();
        Queue::assertPushed(SendPush::class, fn (SendPush $job): bool => $job->message['nid'] === $delivery->nid);
        expect($delivery->nid)->toHaveLength(24)->and($delivery->opened_at)->toBeNull()->and($delivery->kind)->toBe('test');

        app('auth')->forgetGuards();
        $this->postJson('/api/v1/push/seen', ['nid' => $delivery->nid])->assertNoContent();

        $opened = $delivery->refresh()->opened_at;
        expect($opened)->not->toBeNull();

        $this->travel(5)->minutes();
        $this->postJson('/api/v1/push/seen', ['nid' => $delivery->nid])->assertNoContent();

        expect($delivery->refresh()->opened_at->equalTo($opened))->toBeTrue();
    });

    it('says nothing of whether the number was right, and asks for it', function () {
        $this->postJson('/api/v1/push/seen', ['nid' => 'nobody-sent-this'])->assertNoContent();
        $this->postJson('/api/v1/push/seen', [])->assertStatus(422);
        expect(PushDelivery::query()->count())->toBe(0);
    });

    it('is forgotten after 30 days', function () {
        $driver = pushDriver();
        $old = PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'old', 'kind' => 'test']);
        $recent = PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'recent', 'kind' => 'test']);
        PushDelivery::query()->whereKey($old->id)->update(['created_at' => now()->subDays(31)]);
        PushDelivery::query()->whereKey($recent->id)->update(['created_at' => now()->subDays(29)]);

        $this->artisan('push:prune')->assertSuccessful();

        expect(PushDelivery::query()->pluck('nid')->all())->toBe(['recent']);
    });
});

describe('the comparison of the groups', function () {
    it('counts the accounts of each group, the retention of each, and the difference with an interval', function () {
        thePushRulesAre(['holdout_ppm' => 500_000]);
        $told = accountsInArm('push', 4);
        $held = accountsInArm('holdout', 4);

        foreach ($told as $user) {
            cameBack($user, 1);
        }

        cameBack($held[0], 1);

        $report = app(Metrics::class)->byArm(CarbonImmutable::now()->subDays(30), CarbonImmutable::now()->addSecond(), CarbonImmutable::now());

        expect($report['arms']['push']['accounts'])->toBe(4)->and($report['arms']['holdout']['accounts'])->toBe(4)
            ->and($report['arms']['push']['retention']['d1'])->toBe(['eligible' => 4, 'returned' => 4, 'rate' => 1.0])
            ->and($report['arms']['holdout']['retention']['d1'])->toBe(['eligible' => 4, 'returned' => 1, 'rate' => 0.25])
            ->and($report['difference']['d1']['diff'])->toBe(0.75)
            // Four accounts a group: the interval is wide, and the report shows it.
            ->and($report['difference']['d1']['low'])->toBeLessThan(0.75)->and($report['difference']['d1']['high'])->toBeGreaterThanOrEqual(0.75)
            ->and($report['arms']['push']['sessions']['drivers'])->toBe(4);
    });

    it('has no difference when a group is empty, and counts the drivers who have a device', function () {
        thePushRulesAre(['holdout_ppm' => 0]);
        $told = accountsInArm('push', 2);
        PushSubscription::query()->create(['user_id' => $told[0]->id, 'endpoint_hash' => str_repeat('b', 64), 'endpoint' => 'e', 'p256dh' => 'p', 'auth' => 'a', 'vapid_key' => 'k']);

        $report = app(Metrics::class)->byArm(CarbonImmutable::now()->subDays(30), CarbonImmutable::now()->addSecond(), CarbonImmutable::now());

        expect($report['difference']['d1'])->toBeNull()->and($report['arms']['holdout']['accounts'])->toBe(0)->and($report['subscribed']['accounts'])->toBe(1);
    });

    it('counts the messages, those that were opened and those that were followed by a visit within an hour', function () {
        $driver = pushDriver();
        $opened = PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'a', 'kind' => 'car_stopped', 'opened_at' => now()]);
        PushDelivery::query()->create(['user_id' => $driver->id, 'nid' => 'b', 'kind' => 'car_stopped']);
        PushDelivery::query()->whereKey($opened->id)->update(['created_at' => now()->subHours(5)]);
        PushDelivery::query()->where('nid', 'b')->update(['created_at' => now()->subHours(3)]);
        UserVisit::factory()->create(['user_id' => $driver->id, 'at' => now()->subHours(5)->addMinutes(20)]);

        $messages = app(Metrics::class)->byArm(CarbonImmutable::now()->subDays(1), CarbonImmutable::now()->addSecond(), CarbonImmutable::now())['messages'];

        expect($messages)->toBe(['sent' => 2, 'opened' => 1, 'opened_rate' => 0.5, 'returned_within_hour' => 1, 'returned_rate' => 0.5]);
    });

    it('is printed by metrics:report --by=arm, and nothing else is taken for --by', function () {
        thePushRulesAre(['holdout_ppm' => 500_000]);

        $this->artisan('metrics:report', ['--by' => 'arm', '--days' => 30])->expectsOutputToContain('Never told (holdout)')->expectsOutputToContain('The holdout has 0 accounts')->assertSuccessful();
        $this->artisan('metrics:report', ['--by' => 'nothing'])->assertFailed();
    });
});
