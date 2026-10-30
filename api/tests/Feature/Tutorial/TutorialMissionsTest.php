<?php

declare(strict_types=1);

use App\Content\TutorialSteps;
use App\Models\TutorialProgress;
use App\Models\User;
use App\Models\UserMission;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

// The steps of the tutorial that introduce the missions: the one that shows them when they open (after the guided first minutes), the one that asks for the first
// reward to be taken (optional, at the end) and the one that opens the main missions (the last). They run on the steps that the content has.

afterEach(fn () => Carbon::setTestNow());

function missionsTutorialGet(User $driver, array $headers = []): TestResponse
{
    return test()->getJson('/api/v1/tutorial', [...bearerFor($driver), ...$headers]);
}

/** A driver with a car that stands still, with every step before the given one done. */
function missionsTutorialDriver(string $step): User
{
    [$driver] = driverWithRun();

    foreach (array_column(app(TutorialSteps::class)->all(), 'key') as $earlier) {
        if ($earlier === $step) {
            break;
        }

        TutorialProgress::factory()->create(['user_id' => $driver->id, 'step_key' => $earlier, 'state' => TutorialProgress::STATE_DONE, 'completed_at' => now()]);
    }

    return $driver;
}

describe('the introduction of the missions', function () {
    it('comes right after the guided first minutes, points at the plate of the missions and is done when the driver has read it', function () {
        $driver = missionsTutorialDriver('missions');

        $shown = missionsTutorialGet($driver, ['X-Locale' => 'pl'])->assertOk();

        expect($shown->json('data.current.key'))->toBe('missions')
            ->and($shown->json('data.current.target'))->toBe('missions')
            ->and($shown->json('data.current.number'))->toBe(11)
            ->and($shown->json('data.current.sms'))->toHaveCount(2)
            ->and($shown->json('data.current.sms.0'))->toContain('misje')
            ->and($shown->json('data.goal.title'))->toBe('Zajrzyj do Misji')
            ->and($shown->json('data.goal.waiting'))->toBeNull();

        $done = test()->postJson('/api/v1/tutorial/reply', [], bearerFor($driver))->assertOk();

        // It pays nothing, and the first step of the first day is the next.
        expect($done->json('data.done'))->toBe(11)->and($done->json('data.goal.key'))->toBe('offroad')->and($done->json('data.rewarded'))->toBe([])
            ->and((int) Wallet::query()->find($driver->id)?->cash_cents)->toBe(0);
    });

    it('is told in every language the game speaks', function () {
        $step = app(TutorialSteps::class)->find('missions');

        foreach ([$step['goal'], $step['reply'], ...$step['sms']] as $text) {
            expect(array_keys($text))->toEqualCanonicalizing(['pl', 'en', 'fr', 'de', 'pt-BR']);
        }
    });
});

describe('the first reward', function () {
    it('is optional, and the driver may leave it for later', function () {
        $driver = missionsTutorialDriver('first-mission');

        $shown = missionsTutorialGet($driver, ['X-Locale' => 'pl'])->assertOk();

        expect($shown->json('data.current.key'))->toBe('first-mission')
            ->and($shown->json('data.current.optional'))->toBeTrue()
            ->and($shown->json('data.current.decline'))->toBe('Później')
            ->and($shown->json('data.current.target'))->toBe('missions');

        $left = test()->postJson('/api/v1/tutorial/decline', [], bearerFor($driver))->assertOk();

        expect($left->json('data.goal.key'))->toBe('main-missions')->and((int) Wallet::query()->find($driver->id)?->cash_cents)->toBe(0);
    });

    it('is done when the driver has taken the reward of a mission, and pays $100 once', function () {
        $driver = missionsTutorialDriver('first-mission');
        missionsTutorialGet($driver);

        // The phone is answered, and the step waits for a mission to be taken.
        $waiting = test()->postJson('/api/v1/tutorial/reply', [], bearerFor($driver))->assertOk();
        expect($waiting->json('data.goal.key'))->toBe('first-mission');

        UserMission::query()->create(['user_id' => $driver->id, 'kind' => 'daily', 'period_key' => '2026-10-30', 'slot' => 1, 'mission_key' => 't', 'objective' => 'laps', 'target' => 5, 'progress' => 5, 'reward' => ['cash_cents' => 0], 'claimed_at' => now(), 'completed_at' => now()]);

        $done = missionsTutorialGet($driver)->assertOk();

        expect($done->json('data.rewarded.0.key'))->toBe('first-mission')->and($done->json('data.rewarded.0.cash_cents'))->toBe(10_000)
            ->and($done->json('data.goal.key'))->toBe('main-missions')
            ->and((int) Wallet::query()->find($driver->id)?->cash_cents)->toBe(10_000);

        missionsTutorialGet($driver);
        expect((int) Wallet::query()->find($driver->id)?->cash_cents)->toBe(10_000);
    });

    it('does not count a mission that was done and not taken', function () {
        $driver = missionsTutorialDriver('first-mission');
        UserMission::query()->create(['user_id' => $driver->id, 'kind' => 'daily', 'period_key' => '2026-10-30', 'slot' => 1, 'mission_key' => 't', 'objective' => 'laps', 'target' => 5, 'progress' => 5, 'reward' => ['cash_cents' => 0], 'completed_at' => now()]);

        missionsTutorialGet($driver);
        $waiting = test()->postJson('/api/v1/tutorial/reply', [], bearerFor($driver))->assertOk();

        expect($waiting->json('data.goal.key'))->toBe('first-mission')->and($waiting->json('data.rewarded'))->toBe([]);
    });
});

describe('the end of the tutorial', function () {
    it('opens the main missions: the last step is read, and the tutorial is over', function () {
        $driver = missionsTutorialDriver('main-missions');

        expect(test()->getJson('/api/v1/missions', bearerFor($driver))->json('data.main_locked'))->toBe('tutorial');

        $shown = missionsTutorialGet($driver)->assertOk();
        expect($shown->json('data.current.key'))->toBe('main-missions')->and($shown->json('data.current.target'))->toBe('missions');

        $done = test()->postJson('/api/v1/tutorial/reply', [], bearerFor($driver))->assertOk();

        expect($done->json('data.status'))->toBe('finished')->and($done->json('data.goal'))->toBeNull()
            ->and(test()->getJson('/api/v1/missions', bearerFor($driver))->json('data.main_locked'))->toBeNull();
    });
});

describe('a driver who has been through the tutorial before the missions came', function () {
    it('who finished it is told of the missions, and the main ones wait for them to have read it', function () {
        [$driver] = driverWithRun();

        // The twenty steps that the tutorial had.
        foreach (array_column(app(TutorialSteps::class)->all(), 'key') as $key) {
            if (! in_array($key, ['missions', 'first-mission', 'main-missions'], true)) {
                TutorialProgress::factory()->create(['user_id' => $driver->id, 'step_key' => $key, 'state' => TutorialProgress::STATE_DONE, 'completed_at' => now()]);
            }
        }

        $shown = missionsTutorialGet($driver)->assertOk();

        expect($shown->json('data.status'))->toBe('in_progress')->and($shown->json('data.current.key'))->toBe('missions')
            ->and(test()->getJson('/api/v1/missions', bearerFor($driver))->json('data.main_locked'))->toBe('tutorial');
    });

    it('who left it is not given the steps that were added after they left', function () {
        [$driver] = driverWithRun();

        foreach (array_column(app(TutorialSteps::class)->all(), 'key') as $key) {
            if (! in_array($key, ['missions', 'first-mission', 'main-missions'], true)) {
                TutorialProgress::factory()->create(['user_id' => $driver->id, 'step_key' => $key, 'state' => TutorialProgress::STATE_SKIPPED, 'skipped_at' => now()]);
            }
        }

        $shown = missionsTutorialGet($driver)->assertOk();

        expect($shown->json('data.status'))->toBe('skipped')->and($shown->json('data.current'))->toBeNull()
            ->and(TutorialProgress::query()->where('user_id', $driver->id)->whereIn('step_key', ['missions', 'first-mission', 'main-missions'])->count())->toBe(0)
            // The missions are theirs all the same.
            ->and(test()->getJson('/api/v1/missions', bearerFor($driver))->json('data.main_locked'))->toBeNull();
    });
});
