<?php

declare(strict_types=1);

use App\Content\TutorialSteps;
use App\Models\TutorialProgress;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Testing\TestResponse;

/**
 * Steps that are added to a tutorial that drivers are in the middle of, and a step that comes without the words of its button. They run on steps of their own,
 * so that they say what the engine does and not what the content asks for.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addedStep(string $key, array $overrides = []): array
{
    $text = ['en' => 'Hello. — K.', 'pl' => 'Cześć. — K.', 'fr' => 'Salut. — K.', 'de' => 'Hallo. — K.', 'pt-BR' => 'Oi. — K.'];

    return [
        'key' => $key,
        'phase' => 3,
        'trigger' => 'none',
        'completion' => 'acknowledged',
        'target' => null,
        'goal' => ['en' => 'Do '.$key, 'pl' => 'Zrób '.$key, 'fr' => 'Fais '.$key, 'de' => 'Mach '.$key, 'pt-BR' => 'Faça '.$key],
        'sender' => '+48 555 010 '.str_pad((string) (100 + crc32($key) % 800), 3, '0'),
        'sms' => [$text],
        'reply' => ['en' => 'Got it', 'pl' => 'Jasne', 'fr' => 'Compris', 'de' => 'Verstanden', 'pt-BR' => 'Entendi'],
        'reward' => [],
        ...$overrides,
    ];
}

/** @param  list<array<string, mixed>>  $steps */
function theTutorialHas(array $steps): void
{
    app()->instance(TutorialSteps::class, TutorialSteps::fromArray([
        'steps' => $steps,
        'script' => ['first_run_fuel_ml' => 17_000, 'breakdown_lap' => 6, 'first_mount_ms' => 30_000, 'covers_up_to_cents' => 30_000],
    ]));
}

function addedGet(User $driver): TestResponse
{
    return test()->getJson('/api/v1/tutorial', bearerFor($driver));
}

function addedReply(User $driver): TestResponse
{
    return test()->postJson('/api/v1/tutorial/reply', [], bearerFor($driver));
}

/** The driver has been through the steps before this one and is in the middle of it. */
function addedRow(User $driver, string $key, string $state): TutorialProgress
{
    return TutorialProgress::factory()->create([
        'user_id' => $driver->id,
        'step_key' => $key,
        'state' => $state,
        'started_at' => now(),
        'completed_at' => $state === TutorialProgress::STATE_DONE ? now() : null,
    ]);
}

describe('a step that shows messages and has no words for its button', function () {
    it('is shown with a button that says OK, and does not break the tutorial', function () {
        $step = addedStep('first');
        unset($step['reply']);
        theTutorialHas([$step]);
        [$driver] = driverWithRun();

        addedGet($driver)->assertOk()
            ->assertJsonPath('data.current.key', 'first')
            ->assertJsonPath('data.current.reply', 'OK');
    });
});

describe('a step that was added behind a driver who is further along', function () {
    it('does not hold up the step the driver is at while its trigger does not hold', function () {
        // The driver was at `later` when `added` was put before it; the tank is full, so the added step has nothing to wait for yet.
        theTutorialHas([addedStep('done'), addedStep('added', ['trigger' => 'fuel_low']), addedStep('later')]);
        [$driver] = driverWithRun();
        addedRow($driver, 'done', TutorialProgress::STATE_DONE);
        addedRow($driver, 'later', TutorialProgress::STATE_ACTIVE);

        addedGet($driver)->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.current.key', 'later')
            ->assertJsonPath('data.goal.key', 'later');

        addedReply($driver)->assertOk()
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.done', 2)
            ->assertJsonPath('data.total', 2);
    });

    it('starts when its trigger holds, and the tutorial is not finished until the driver is through it', function () {
        theTutorialHas([addedStep('done'), addedStep('added', ['trigger' => 'cash:50']), addedStep('later', ['sms' => []])]);
        [$driver] = driverWithRun();
        addedRow($driver, 'done', TutorialProgress::STATE_DONE);
        addedRow($driver, 'later', TutorialProgress::STATE_DONE);

        addedGet($driver)->assertOk()->assertJsonPath('data.status', 'finished')->assertJsonPath('data.current', null);

        (new Ledger)->post($driver->id, 6_000, 'gift', 'added-steps-money:'.$driver->id);

        addedGet($driver)->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.current.key', 'added');
    });

    it('is shown first when it starts, and the answer of the driver goes to it and not to the step that was already waiting', function () {
        theTutorialHas([addedStep('done'), addedStep('added'), addedStep('later')]);
        [$driver] = driverWithRun();
        addedRow($driver, 'done', TutorialProgress::STATE_DONE);
        addedRow($driver, 'later', TutorialProgress::STATE_ACTIVE);

        addedGet($driver)->assertOk()->assertJsonPath('data.current.key', 'added')->assertJsonPath('data.total', 3);

        addedReply($driver)->assertOk()->assertJsonPath('data.current.key', 'later')->assertJsonPath('data.current.acknowledged', false);

        expect(TutorialProgress::query()->where('user_id', $driver->id)->where('step_key', 'later')->value('acknowledged_at'))->toBeNull();

        addedReply($driver)->assertOk()->assertJsonPath('data.status', 'finished');
    });

    it('is not given to a driver who left the tutorial', function () {
        theTutorialHas([addedStep('done'), addedStep('later')]);
        [$driver] = driverWithRun();
        addedRow($driver, 'done', TutorialProgress::STATE_DONE);
        addedRow($driver, 'later', TutorialProgress::STATE_SKIPPED);

        theTutorialHas([addedStep('done'), addedStep('added'), addedStep('later')]);

        addedGet($driver)->assertOk()->assertJsonPath('data.status', 'skipped')->assertJsonPath('data.current', null);
        expect(TutorialProgress::query()->where('user_id', $driver->id)->where('step_key', 'added')->exists())->toBeFalse();
    });
});
