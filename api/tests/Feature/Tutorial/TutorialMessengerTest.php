<?php

declare(strict_types=1);

use App\Content\PackValidationResult;
use App\Content\PackValidator;
use App\Content\TutorialSteps;
use App\Content\Vocabulary\Vocabulary;
use App\Models\User;

/**
 * What a game says of the device its mentor writes on, of who writes, of the words of the tutorial and of the story before the choice of a wreck: the tutorial
 * of each game is its own, and a tutorial that says nothing of them is the old phone of RideIdle.
 */

/** A step of a tutorial of its own, with the sender left to the test. @return array<string, mixed> */
function messengerStep(string $key, array $overrides = []): array
{
    return [
        'key' => $key,
        'phase' => 1,
        'trigger' => 'none',
        'completion' => 'acknowledged',
        'target' => null,
        'goal' => ['en' => 'Read it', 'pl' => 'Przeczytaj'],
        'sms' => [['en' => 'Ahoy.', 'pl' => 'Ahoj.']],
        'reply' => ['en' => 'Aye', 'pl' => 'Tak jest'],
        'reward' => [],
        ...$overrides,
    ];
}

/** @param  array<string, mixed>  $extra  messenger and story */
function messengerTutorial(array $steps, array $extra = []): void
{
    app()->instance(TutorialSteps::class, TutorialSteps::fromArray([
        'steps' => $steps,
        'script' => ['first_run_fuel_ml' => 17_000, 'breakdown_lap' => 6, 'first_mount_ms' => 30_000, 'covers_up_to_cents' => 30_000],
        ...$extra,
    ]));
}

function messengerValidated(Closure $change): PackValidationResult
{
    return PackValidator::fromConfig()->validateSource(contentWith(['rules/tutorial' => $change]));
}

/** @return list<string> the pointers of the issues about the tutorial */
function messengerIssues(array $issues): array
{
    return array_values(array_map(static fn (array $issue): string => $issue['pointer'], array_filter($issues, static fn (array $issue): bool => $issue['file'] === 'rules/tutorial.json')));
}

describe('the messenger in the answer', function () {
    it('sends the device of the game, its parts and the sender of the messenger for a step that names none, in the language of the driver', function () {
        messengerTutorial([messengerStep('ahoy')], ['messenger' => [
            'device' => 'letter',
            'parts' => ['who' => false],
            'sender' => ['en' => 'Harbour master', 'pl' => 'Kapitanat portu'],
        ]]);
        [$driver] = driverWithRun();
        $driver->forceFill(['locale' => 'pl'])->save();

        test()->getJson('/api/v1/tutorial', bearerFor($driver))->assertOk()
            ->assertJsonPath('data.current.sender', 'Kapitanat portu')
            ->assertJsonPath('data.messenger.device', 'letter')
            ->assertJsonPath('data.messenger.parts', ['crack' => true, 'status_bar' => true, 'input' => true, 'who' => false])
            ->assertJsonPath('data.messenger.frame_url', null);
    });

    it('lets a step name a sender of its own, and gives the frame and the screen of a device of the game', function () {
        $frame = str_repeat('a', 64).'.png';
        messengerTutorial([messengerStep('ahoy', ['sender' => 'Channel 7'])], ['messenger' => [
            'device' => 'picture',
            'sender' => 'Fleet command',
            'picture' => ['frame' => $frame, 'screen' => ['top' => 12, 'right' => 8, 'bottom' => 20, 'left' => 8]],
        ]]);
        [$driver] = driverWithRun();

        test()->getJson('/api/v1/tutorial', bearerFor($driver))->assertOk()
            ->assertJsonPath('data.current.sender', 'Channel 7')
            ->assertJsonPath('data.messenger.device', 'picture')
            ->assertJsonPath('data.messenger.frame_url', "/api/v1/media/{$frame}")
            ->assertJsonPath('data.messenger.screen', ['top' => 12, 'right' => 8, 'bottom' => 20, 'left' => 8]);
    });
});

describe('the story', function () {
    it('is the story of the game, in the language of the driver', function () {
        $driver = User::factory()->guest()->create(['locale' => 'pl']);

        $response = test()->getJson('/api/v1/tutorial/story', bearerFor($driver))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        expect($response->json('data.enabled'))->toBeTrue()
            ->and($response->json('data.builtin'))->toBeFalse()
            ->and($response->json('data.scenes'))->toHaveCount(4)
            ->and($response->json('data.scenes.0'))->toBe(['text' => '„Słuchaj, muszę zniknąć na jakiś czas.”', 'ms' => 6000, 'camera' => 'zoom-in', 'focus' => ['x' => 50, 'y' => 55]])
            ->and($response->json('data.scenes.3.text'))->toContain('{name}')
            ->and($response->json('data.sender'))->toBe('+48 555 010 112')
            ->and($response->json('data.messenger.device'))->toBe('phone');
    });

    it('is the one of the app for a tutorial that tells none, and none at all when the game turns it off', function () {
        $driver = User::factory()->guest()->create();

        messengerTutorial([messengerStep('ahoy', ['sender' => 'K.'])]);
        test()->getJson('/api/v1/tutorial/story', bearerFor($driver))->assertOk()->assertJsonPath('data.builtin', true)->assertJsonPath('data.enabled', true);

        messengerTutorial([messengerStep('ahoy', ['sender' => 'K.'])], ['story' => ['enabled' => false]]);
        test()->getJson('/api/v1/tutorial/story', bearerFor($driver))->assertOk()->assertJsonPath('data.builtin', false)->assertJsonPath('data.enabled', false);
    });

    it('requires a token', function () {
        test()->getJson('/api/v1/tutorial/story')->assertUnauthorized();
    });
});

describe('the words of the tutorial', function () {
    it('are words of the game that win over the vocabulary', function () {
        $source = contentWith([
            'rules/vocabulary' => fn (array $document): array => [...$document, 'messages' => ['tutorial.send' => ['en' => 'Send it', 'pl' => 'Wyślij to'], 'tutorial.who' => ['en' => 'Who?', 'pl' => 'Kto?']]],
            'rules/tutorial' => function (array $tutorial): array {
                $tutorial['messenger']['labels'] = ['send' => ['en' => 'Post the letter', 'pl' => 'Nadaj list'], 'network' => ['en' => 'NO SIGNAL', 'pl' => 'BRAK ZASIĘGU']];
                $tutorial['story']['labels'] = ['choose' => ['en' => 'Pick a boat', 'pl' => 'Wybierz łódź']];

                return $tutorial;
            },
        ]);

        $words = Vocabulary::fromSource($source)->web();

        expect($words['pl']['tutorial.send'])->toBe('Nadaj list')
            ->and($words['pl']['tutorial.who'])->toBe('Kto?')
            ->and($words['de']['tutorial.network'])->toBe('NO SIGNAL')
            ->and($words['pl']['story.choose'])->toBe('Wybierz łódź');
    });
});

describe('what the validator says', function () {
    it('accepts any sender, a number or a name in the languages of the game', function () {
        $result = messengerValidated(function (array $tutorial): array {
            $tutorial['steps'][0]['sender'] = ['en' => 'Harbour master', 'pl' => 'Kapitanat portu', 'fr' => 'Capitainerie', 'de' => 'Hafenmeister', 'pt-BR' => 'Capitania'];
            $tutorial['steps'][1]['sender'] = 'Channel 7';

            return $tutorial;
        });

        expect(messengerIssues($result->errors()))->toBe([]);
    });

    it('refuses a step that shows messages when nobody sends them', function () {
        $result = messengerValidated(function (array $tutorial): array {
            unset($tutorial['steps'][1]['sender']);

            return $tutorial;
        });

        expect(messengerIssues($result->errors()))->toBe(['/steps/1/sender']);

        $withMessenger = messengerValidated(function (array $tutorial): array {
            unset($tutorial['steps'][1]['sender']);
            $tutorial['messenger']['sender'] = 'K.';

            return $tutorial;
        });

        expect(messengerIssues($withMessenger->errors()))->toBe([]);
    });

    it('gives a letter room for 400 characters and every other device 200', function () {
        $long = fn (string $device): PackValidationResult => messengerValidated(function (array $tutorial) use ($device): array {
            $tutorial['messenger']['device'] = $device;
            $tutorial['steps'][1]['sms'][0]['pl'] = str_repeat('a', 300);

            return $tutorial;
        });

        expect(messengerIssues($long('phone')->errors()))->toBe(['/steps/1/sms/0/pl'])
            ->and(messengerIssues($long('letter')->errors()))->toBe([]);
    });

    it('refuses a word that the app does not have, and one that fills in other names', function () {
        $result = messengerValidated(function (array $tutorial): array {
            $tutorial['messenger']['labels'] = [
                'nonsense' => ['en' => 'x', 'pl' => 'x'],
                'reward' => ['en' => 'The captain sent you {money}.', 'pl' => 'Kapitan przysłał Ci {cash}.'],
            ];

            return $tutorial;
        });

        expect(messengerIssues($result->errors()))->toBe(['/messenger/labels/nonsense', '/messenger/labels/reward/en']);
    });

    it('lets a scene fill in the name of the driver and nothing else', function () {
        $result = messengerValidated(function (array $tutorial): array {
            $tutorial['story']['scenes'][0]['text']['en'] = 'Hello {name}, here is {city}.';

            return $tutorial;
        });

        expect(messengerIssues($result->errors()))->toBe(['/story/scenes/0/text/en']);
    });

    it('asks a device of the game for its frame, and says when a picture is not in the store', function () {
        $without = messengerValidated(function (array $tutorial): array {
            $tutorial['messenger'] = ['device' => 'picture'];

            return $tutorial;
        });

        expect($without->isValid())->toBeFalse();

        $missing = messengerValidated(function (array $tutorial): array {
            $tutorial['messenger'] = ['device' => 'picture', 'picture' => ['frame' => str_repeat('b', 64).'.png', 'screen' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]]];

            return $tutorial;
        });

        expect(messengerIssues($missing->errors()))->toBe([])
            ->and(implode(' ', array_column($missing->warnings(), 'message')))->toContain(str_repeat('b', 64).'.png');
    });

    it('tells the vocabulary that the words of the tutorial are set in the tutorial now', function () {
        $result = PackValidator::fromConfig()->validateSource(contentWith([
            'rules/vocabulary' => fn (array $document): array => [...$document, 'messages' => ['tutorial.send' => ['en' => 'Send it', 'pl' => 'Wyślij to']]],
        ]));

        expect(array_column(array_filter($result->warnings(), fn (array $issue): bool => $issue['file'] === 'rules/vocabulary.json'), 'pointer'))->toContain('/messages/tutorial.send');
    });
});
