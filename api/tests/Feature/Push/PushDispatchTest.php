<?php

declare(strict_types=1);

use App\Jobs\SendPush;
use App\Models\PushEvent;
use App\Models\RaceRun;
use App\Models\Travel;
use App\Models\User;
use App\Models\WorkshopJob;
use App\Push\PushKeys;
use App\Services\Push;
use App\Services\PushDispatcher;
use App\Services\PushSeen;
use App\Services\RunService;
use Database\Factories\RaceRunFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

// Telling the drivers who are away what happened: a car that stopped (forecast, then looked at), a car that arrived, a job that is done. The test clock starts at 20:00 in Warsaw;
// the car of the test has a lap of 75 s and uses 1.5 l a lap, so 6 l are 4 laps, which is the 300 s after which it stops.

afterEach(fn () => Carbon::setTestNow());

/** A driver with a device and a car with `$litres` of fuel, looked at once (so the moment it stops is planned); `$run` changes the car. */
function awayDriver(int $litres = 6, array $run = []): array
{
    [$driver, $car] = driverWithRun(['fuel_ml' => $litres * 1_000, ...$run]);
    $game = app(Push::class)->game();

    if (! $game->hasPushKeys()) {
        PushKeys::create($game);
    }

    $browser = aBrowser();
    subscribe($driver, $browser)->assertOk();
    app(RunService::class)->settle($car->id, RunService::nowMs());

    return [$driver, $car, $browser];
}

/** What the dispatcher does at a moment after the start of the test clock (milliseconds). */
function dispatchAt(int $msAfterStart): array
{
    clockAt($msAfterStart);

    return app(PushDispatcher::class)->run(RunService::nowMs());
}

function pushedMessages(): array
{
    $messages = [];

    Queue::assertPushed(SendPush::class, function (SendPush $job) use (&$messages): bool {
        $messages[] = $job->message;

        return true;
    });

    return $messages;
}

describe('the forecast', function () {
    it('is made for a driver who has a device, at the moment the car runs out of fuel, and not for one who has none', function () {
        [$stranger, $strangerCar] = driverWithRun(['fuel_ml' => 6_000]);
        app(RunService::class)->settle($strangerCar->id, RunService::nowMs());

        expect(PushEvent::query()->withoutGlobalScopes()->count())->toBe(0);

        [$driver, $car] = awayDriver();

        $event = PushEvent::query()->sole();

        expect($event->kind)->toBe('car_stopped')->and($event->ref_id)->toBe($car->id)->and($event->state)->toBe('planned')
            ->and($event->due_ms)->toBe(RUN_T0 + 300_000)->and($event->user_id)->toBe($driver->id);
    });

    it('is the moment the car really stops, for cars of other laps, tanks and breakdowns', function (int $lapMs, int $perLapMl, int $tankMl, ?int $breakAfterLaps, string $reason) {
        [$driver, $car] = awayDriver(0, [
            'fuel_ml' => $tankMl,
            'break_after_laps' => $breakAfterLaps,
            'config' => RaceRunFactory::constantConfig(lapMs: $lapMs, fuelPerLapMl: $perLapMl),
        ]);
        $due = PushEvent::query()->sole()->due_ms;

        // One millisecond before, the car drives; at the moment, it has stopped, for the reason that was forecast.
        app(RunService::class)->settle($car->id, $due - 1);
        expect(RaceRun::query()->find($car->id)->status)->toBe(RaceRun::STATUS_RUNNING);

        app(RunService::class)->settle($car->id, $due + 5_000);
        $run = RaceRun::query()->find($car->id);

        expect($run->status)->toBe(RaceRun::STATUS_STOPPED)->and($run->stop_reason)->toBe($reason)->and($run->cursor_ms)->toBe($due);
    })->with([
        'tank of 4 laps' => [75_000, 1_500, 6_000, null, 'fuel_empty'],
        'a long lap and a thirsty car' => [210_000, 4_200, 25_000, null, 'fuel_empty'],
        'a short lap' => [31_000, 350, 9_000, null, 'fuel_empty'],
        'a breakdown first' => [75_000, 1_500, 45_000, 7, 'breakdown'],
        'a breakdown on the last lap before the tank' => [60_000, 1_000, 12_000, 12, 'breakdown'],
    ]);

    it('is made again when the car is looked at after the driver filled the tank', function () {
        [$driver, $car] = awayDriver();
        expect(PushEvent::query()->sole()->due_ms)->toBe(RUN_T0 + 300_000);

        RaceRun::query()->whereKey($car->id)->update(['fuel_ml' => 45_000]);
        app(RunService::class)->settle($car->id, RunService::nowMs());

        expect(PushEvent::query()->sole()->due_ms)->toBe(RUN_T0 + 30 * 75_000);
    });

    it('is dropped for a car that stands for a reason of its own', function () {
        [$driver, $car] = awayDriver();

        RaceRun::query()->whereKey($car->id)->update(['stop_reason' => RaceRun::STOP_WORKSHOP]);
        app(RunService::class)->settle($car->id, RunService::nowMs());

        expect(PushEvent::query()->count())->toBe(0);
    });
});

describe('telling the driver', function () {
    it('tells of a car that stopped, once, in the language of the device, with the name of the car', function () {
        Queue::fake();
        [$driver] = awayDriver();

        $report = dispatchAt(600_000);

        expect($report)->toBe(['sent' => 1, 'skipped' => 0, 'deferred' => 0]);
        Queue::assertPushed(SendPush::class, 1);
        [$message] = pushedMessages();

        expect($message['kind'])->toBe('car_stopped')->and($message['lang'])->toBe('fr')->and($message['title'])->toContain('est en panne sèche')->and($message['title'])->toContain('Rdzak')
            ->and($message['url'])->toBe('/garage')->and($message['ttl'])->toBe(14_400);
        expect(PushEvent::query()->sole()->state)->toBe('sent');

        expect(dispatchAt(660_000))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 0]);
        Queue::assertPushed(SendPush::class, 1);
    });

    it('tells of a breakdown in other words', function () {
        Queue::fake();
        awayDriver(45, ['break_after_laps' => 2]);

        dispatchAt(600_000);

        expect(pushedMessages()[0]['title'])->toContain('est en panne')->and(pushedMessages()[0]['title'])->not->toContain('sèche');
    });

    it('does not tell when the driver has filled the tank in the meantime: the forecast was only a moment to look', function () {
        Queue::fake();
        [$driver, $car] = awayDriver();

        RaceRun::query()->whereKey($car->id)->update(['fuel_ml' => 45_000]);

        expect(dispatchAt(600_000))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 0]);
        Queue::assertNothingPushed();
        // The look made the forecast again: the tank is full from the moment the car was last settled, so it stops after 30 laps.
        expect(PushEvent::query()->sole())->state->toBe('planned')->due_ms->toBe(RUN_T0 + 30 * 75_000);
    });

    it('does not tell of what the driver has seen themselves', function () {
        Queue::fake();
        [$driver] = awayDriver();

        // The driver looks at the game after the car stopped (at 5 min) and leaves.
        clockAt(420_000);
        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertOk();

        expect(dispatchAt(900_000))->toBe(['sent' => 0, 'skipped' => 1, 'deferred' => 0]);
        Queue::assertNothingPushed();
        expect(PushEvent::query()->sole())->state->toBe('skipped')->reason->toBe('known');
    });

    it('waits while the driver is still in the game, and tells when they have gone', function () {
        Queue::fake();
        [$driver] = awayDriver();

        // The driver was in the game at 4 min 30 s, before the car stopped, and the dispatcher looks at 5 min 30 s.
        clockAt(270_000);
        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertOk();

        expect(dispatchAt(330_000))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 1]);
        Queue::assertNothingPushed();

        // 3 minutes after the last look and a minute more.
        expect(dispatchAt(270_000 + 180_000 + 60_000 - 1))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 0]);
        expect(dispatchAt(270_000 + 180_000 + 60_000))->toBe(['sent' => 1, 'skipped' => 0, 'deferred' => 0]);
    });

    it('keeps the quiet hours of the time zone of the device and tells in the morning, in one message when there are several things', function () {
        Queue::fake();
        [$driver, $car] = awayDriver();
        $second = RaceRun::factory()->create(['user_id' => $driver->id, 'fuel_ml' => 3_000, 'cursor_ms' => RUN_T0]);
        app(RunService::class)->settle($second->id, RunService::nowMs());

        // 22:30 in Warsaw: the quiet hours are on, and they end at 08:00 the next day.
        $report = dispatchAt(150 * 60_000);
        $morning = RUN_T0 + (12 * 3_600_000);

        expect($report)->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 2]);
        expect(PushEvent::query()->pluck('due_ms')->unique()->all())->toBe([$morning]);
        Queue::assertNothingPushed();

        expect(dispatchAt(12 * 3_600_000 - 60_000))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 0]);

        $report = dispatchAt(12 * 3_600_000);

        expect($report)->toBe(['sent' => 2, 'skipped' => 0, 'deferred' => 0]);
        Queue::assertPushed(SendPush::class, 1);
        $message = pushedMessages()[0];

        expect($message['title'])->toBe(app(Push::class)->game()->name)->and($message['body'])->toBe('2 choses vous attendent.')->and($message['tag'])->toBe('digest');
    });

    it('keeps a gap after a message, and says nothing more than the limit of a day', function () {
        Queue::fake();
        [$driver, $car] = awayDriver();

        // A message was sent 10 minutes before the time of the dispatcher: the next one waits for the end of the gap (30 minutes after it).
        PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'job_done', 'ref_type' => 'workshop_job', 'ref_id' => 999, 'due_ms' => RUN_T0, 'state' => 'sent', 'sent_at' => Carbon::createFromTimestampMs(RUN_T0 + 590_000)]);

        expect(dispatchAt(600_000))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 1]);
        expect(PushEvent::query()->where('kind', 'car_stopped')->sole()->due_ms)->toBe(RUN_T0 + 590_000 + 1_800_000);

        // Four messages in the last day: no more.
        foreach ([2, 3, 4, 5] as $hours) {
            PushEvent::query()->create(['user_id' => $driver->id, 'kind' => 'job_done', 'ref_type' => 'workshop_job', 'ref_id' => 1000 + $hours, 'due_ms' => RUN_T0, 'state' => 'sent', 'sent_at' => Carbon::createFromTimestampMs(RUN_T0 - $hours * 3_600_000)]);
        }

        expect(dispatchAt(590_000 + 1_800_000))->toBe(['sent' => 0, 'skipped' => 1, 'deferred' => 0]);
        expect(PushEvent::query()->where('kind', 'car_stopped')->sole())->state->toBe('skipped')->reason->toBe('limit');
        Queue::assertNothingPushed();
    });

    it('tells only of what the driver wants to be told of: a car stopped and a car arrived are on, a job is done is off until they switch it on', function () {
        Queue::fake();
        [$driver, $car, $browser] = awayDriver();
        $job = WorkshopJob::factory()->create(['user_id' => $driver->id, 'car_id' => $car->car_id, 'status' => WorkshopJob::STATUS_WORKING, 'start_ms' => RUN_T0, 'finish_ms' => RUN_T0 + 200_000]);

        $this->getJson('/api/v1/push/preferences', bearerFor($driver))->assertOk()->assertJsonPath('data.kinds', ['car_stopped' => true, 'car_arrived' => true, 'job_done' => false]);

        $report = dispatchAt(600_000);

        expect($report)->toBe(['sent' => 1, 'skipped' => 1, 'deferred' => 0]);
        expect(PushEvent::query()->where('kind', 'job_done')->sole())->state->toBe('skipped')->reason->toBe('off');
        expect(pushedMessages()[0]['kind'])->toBe('car_stopped');

        clockAt(0);
        $this->patchJson('/api/v1/push/preferences', ['kinds' => ['job_done' => true, 'car_stopped' => false]], bearerFor($driver))->assertOk()
            ->assertJsonPath('data.kinds', ['car_stopped' => false, 'car_arrived' => true, 'job_done' => true]);
        $job2 = WorkshopJob::factory()->create(['user_id' => $driver->id, 'car_id' => $car->car_id, 'status' => WorkshopJob::STATUS_WORKING, 'start_ms' => RUN_T0, 'finish_ms' => RUN_T0 + 700_000]);

        Queue::fake();
        $report = dispatchAt(1_800_000 + 600_000 + 60_000);

        expect($report['sent'])->toBe(1);
        expect(pushedMessages()[0])->toMatchArray(['kind' => 'job_done', 'url' => '/workshop']);
        expect($job2->id)->not->toBe($job->id);
    });

    it('tells of a car that arrived, once, and not of a journey that ended long ago', function () {
        Queue::fake();
        [$driver, $car] = awayDriver(45);
        $arrived = Travel::factory()->create(['user_id' => $driver->id, 'race_run_id' => $car->id, 'start_ms' => RUN_T0, 'arrive_ms' => RUN_T0 + 1_800_000]);
        $old = Travel::factory()->create(['user_id' => $driver->id, 'race_run_id' => $car->id, 'start_ms' => RUN_T0 - 90_000_000, 'arrive_ms' => RUN_T0 - 80_000_000, 'status' => Travel::STATUS_ARRIVED]);

        $report = dispatchAt(2_000_000);

        expect($report)->toBe(['sent' => 1, 'skipped' => 0, 'deferred' => 0]);
        expect(pushedMessages()[0])->toMatchArray(['kind' => 'car_arrived'])->and(pushedMessages()[0]['title'])->toContain('est arrivée');
        expect(PushEvent::query()->where('ref_type', 'travel')->pluck('ref_id')->all())->toBe([$arrived->id]);
        expect($old->id)->not->toBe($arrived->id);

        Queue::fake();
        expect(dispatchAt(2_100_000))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 0]);
    });

    it('forgets the events of a driver who has no device any more, and sends nothing for a game that cannot', function () {
        Queue::fake();
        [$driver, $car, $browser] = awayDriver();
        $this->deleteJson('/api/v1/push/subscription', ['endpoint' => PUSH_ENDPOINT], bearerFor($driver))->assertOk();

        expect(dispatchAt(600_000))->toBe(['sent' => 0, 'skipped' => 0, 'deferred' => 0]);
        expect(PushEvent::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });
});

describe('push:send-due', function () {
    it('goes through the games that have keys and tells what is due', function () {
        Queue::fake();
        awayDriver();
        clockAt(600_000);

        $this->artisan('push:send-due')->expectsOutputToContain('1 sent')->assertSuccessful();

        Queue::assertPushed(SendPush::class, 1);
    });
});

describe('the last look at the game', function () {
    it('is written to the millisecond at every request, not once in ten minutes', function () {
        $driver = User::factory()->guest()->create();

        clockAt(1_000);
        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertOk();
        clockAt(4_321);
        $this->getJson('/api/v1/push/config', bearerFor($driver))->assertOk();

        expect(PushSeen::at($driver->refresh()))->toBe(RUN_T0 + 4_321);
    });
});
