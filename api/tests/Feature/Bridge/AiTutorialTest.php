<?php

declare(strict_types=1);

use App\Content\Bridge\Brief;
use App\Content\Bridge\BriefSelection;
use App\Content\Bridge\ChangeApplier;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ChangeReverter;
use App\Models\ContentDefinition;
use App\Models\Game;

// The tutorial through the bridge: the instruction holds its steps by key, its numbers, its choices and its texts, and an answer changes a text of a step by its key, a
// choice, a sender and a word of the app, and inserts a new step, all of it kept as a draft and undone like the rest.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

function tutorialSelection(bool $texts = false): BriefSelection
{
    return new BriefSelection(vehicles: false, parts: false, rules: false, locale: 'en', texts: $texts, tutorial: true);
}

/** @param list<array<string, mixed>> $ops */
function tutorialBlock(array $ops, Brief $brief): string
{
    return bridgeFenced([
        'format' => 'ride-changes/1',
        'game' => (string) Game::query()->where('is_default', true)->value('slug'),
        'base' => ['pack' => $brief->pack, 'snapshot' => $brief->snapshot, 'brief' => $brief->code],
        'summary' => 'A change of the tutorial for the test',
        'ops' => $ops,
    ]);
}

/** @param list<array<string, mixed>> $ops */
function tutorialCheck(array $ops, ?Brief $brief = null): ChangeCheck
{
    return app(ChangeChecker::class)->check(tutorialBlock($ops, $brief ?? bridgeTheBrief(tutorialSelection())));
}

/** @return list<string> */
function tutorialCodes(ChangeCheck $check): array
{
    return array_map(static fn ($issue): string => $issue->code, $check->issues);
}

/** @return array<string, mixed> the step of the tutorial that the check would keep */
function tutorialStepIn(ChangeCheck $check, string $key): ?array
{
    return collect($check->documents['rules:tutorial']['steps'] ?? [])->first(fn (array $step): bool => $step['key'] === $key);
}

function tutorialDefinition(): ContentDefinition
{
    return ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', 'tutorial')->firstOrFail();
}

const TUTORIAL = ['rules' => 'tutorial'];

describe('the instruction with the tutorial', function () {
    it('holds the steps by key, the numbers, the choices and the texts, and the tutorial in its scope', function () {
        $brief = bridgeTheBrief(tutorialSelection());

        expect($brief->scope)->toBe(['rules:tutorial'])
            ->and($brief->text)->toContain('## The tutorial')
            ->and($brief->text)->toContain('STEPS {"document":"tutorial"')
            ->and($brief->text)->toContain('["refuel",1,')
            ->and($brief->text)->toContain('RULES {"document":"tutorial"}')
            ->and($brief->text)->toContain('["/script/first_run_fuel_ml","liters",17,')
            ->and($brief->text)->toContain('["/steps/refuel/reward/cash_cents","dollars",')
            ->and($brief->text)->toContain('CHOICES {"document":"tutorial"}')
            ->and($brief->text)->toContain('["/messenger/device",["phone","letter","tablet","plain","picture"],"phone"]')
            ->and($brief->text)->toContain('["/messenger/parts/crack",[true,false],true]')
            ->and($brief->text)->toContain('TEXTS {"document":"tutorial"}')
            ->and($brief->text)->toContain('["/steps/start/sender","+48 555 010 120"]')
            ->and($brief->text)->toMatch('/\["\/messenger\/labels\/network",\{"en":"[^"]+"\},"app"\]/')
            ->and($brief->text)->not->toContain('/steps/0/');
    });

    it('gives the texts in the language of the descriptions and English, and in every language when the texts are asked for', function () {
        $short = bridgeTheBrief(tutorialSelection());
        $long = bridgeTheBrief(tutorialSelection(texts: true));

        $goal = fn (Brief $brief): array => json_decode(collect(explode('
', $brief->text))->first(fn (string $line): bool => str_starts_with($line, '["/steps/start/goal"')), true)[1];

        expect(array_keys($goal($short)))->toEqualCanonicalizing(['en'])
            ->and(array_keys($goal($long)))->toEqualCanonicalizing(['en', 'pl', 'fr', 'de', 'pt-BR'])
            ->and($long->bytes)->toBeGreaterThan($short->bytes);
    });

    it('has an example block that the panel accepts', function () {
        $brief = bridgeTheBrief(tutorialSelection());

        preg_match('/A complete block looks like this.*?```json\n(.*?)\n```/s', $brief->text, $found);
        $example = json_decode($found[1], true, 512, JSON_THROW_ON_ERROR);

        expect(array_column($example['ops'], 'op'))->toBe(['text', 'set', 'insert'])
            ->and(app(ChangeChecker::class)->check($found[1])->errors())->toBe([]);
    });

    it('is not in an instruction that was not asked for it', function () {
        $brief = bridgeTheBrief();

        expect($brief->text)->not->toContain('## The tutorial')->and($brief->scope)->not->toContain('rules:tutorial');

        $check = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/messenger/device', 'value' => 'letter', 'unit' => 'choice']], $brief);

        expect(tutorialCodes($check))->toContain('out_of_scope');
    });

    it('goes round the repair loop: a step by its number and a device the game does not have are named, and the corrected answer is taken', function () {
        $brief = bridgeTheBrief(tutorialSelection());

        $wrong = app(ChangeChecker::class)->check(bridgeCanned('tutorial-wrong', $brief));
        $message = $wrong->repairMessage();

        expect($wrong->ok())->toBeFalse()
            ->and($message)->toContain('op 1 (id device) [bad_choice] at rules:tutorial /messenger/device')
            ->and($message)->toContain('op 3 (id goal) [step_by_index] at rules:tutorial /steps/4/goal');

        $fixed = app(ChangeChecker::class)->check(bridgeCanned('tutorial-fixed', $brief));

        expect($fixed->errors())->toBe([])
            ->and($fixed->ok())->toBeTrue()
            ->and($fixed->documents['rules:tutorial']['messenger'])->toMatchArray(['device' => 'letter', 'sender' => 'Harbour master'])
            ->and(tutorialStepIn($fixed, 'refuel')['goal']['en'])->toBe('Fill up the boat');
    });
});

describe('a step by its key', function () {
    it('changes a text of the step that the key names', function () {
        $check = tutorialCheck([['op' => 'text', 'id' => 'goal', 'target' => TUTORIAL, 'path' => '/steps/refuel/goal', 'locale' => 'pl', 'value' => 'Zatankuj do pełna']]);

        expect($check->errors())->toBe([])
            ->and($check->ok())->toBeTrue()
            ->and(tutorialStepIn($check, 'refuel')['goal']['pl'])->toBe('Zatankuj do pełna')
            ->and($check->changes[0]->pointer())->toBe('/steps/refuel/goal/pl');
    });

    it('changes a number of the step in its unit, and says where by key', function () {
        $check = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/steps/refuel/reward/cash_cents', 'value' => 250, 'unit' => 'dollars']]);

        expect($check->errors())->toBe([])
            ->and(tutorialStepIn($check, 'refuel')['reward']['cash_cents'])->toBe(25_000)
            ->and($check->changes[0]->pointer())->toBe('/steps/refuel/reward/cash_cents');
    });

    it('refuses a step named by its number, and a key the tutorial does not have', function () {
        $byNumber = tutorialCheck([['op' => 'text', 'target' => TUTORIAL, 'path' => '/steps/4/goal', 'locale' => 'en', 'value' => 'Fill up']]);
        $unknown = tutorialCheck([['op' => 'text', 'target' => TUTORIAL, 'path' => '/steps/nonsense/goal', 'locale' => 'en', 'value' => 'Fill up']]);

        expect(tutorialCodes($byNumber))->toBe(['step_by_index'])
            ->and($byNumber->repairMessage())->toContain('Address a step by its key')
            ->and(tutorialCodes($unknown))->toBe(['unknown_step']);
    });
});

describe('a choice and a sender', function () {
    it('sets the device to a letter, and refuses a device the game does not have', function () {
        $letter = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/messenger/device', 'value' => 'letter', 'unit' => 'choice']]);
        $wrong = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/messenger/device', 'value' => 'pager', 'unit' => 'choice']]);
        $asNumber = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/messenger/device', 'value' => 'letter', 'unit' => 'plain']]);

        expect($letter->errors())->toBe([])
            ->and($letter->documents['rules:tutorial']['messenger']['device'])->toBe('letter')
            ->and($letter->changes[0])->toMatchObject(['before' => 'phone', 'after' => 'letter'])
            ->and(tutorialCodes($wrong))->toBe(['bad_choice'])
            ->and(tutorialCodes($asNumber))->toBe(['unit_mismatch']);
    });

    it('turns a part of the device off, making the parts the tutorial did not say', function () {
        $check = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/messenger/parts/crack', 'value' => false, 'unit' => 'choice']]);

        expect($check->errors())->toBe([])
            ->and($check->documents['rules:tutorial']['messenger'])->toBe(['device' => 'phone', 'parts' => ['crack' => false]])
            ->and($check->changes[0])->toMatchObject(['before' => 'true', 'after' => 'false']);
    });

    it('sets a sender to one text for every language, and refuses one that is too long or is not a sender', function () {
        $sender = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/steps/refuel/sender', 'value' => 'Harbour master', 'unit' => 'text']]);
        $long = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/steps/refuel/sender', 'value' => str_repeat('a', 41), 'unit' => 'text']]);
        $notSender = tutorialCheck([['op' => 'set', 'target' => TUTORIAL, 'path' => '/steps/refuel/key', 'value' => 'fuel', 'unit' => 'text']]);

        expect($sender->errors())->toBe([])
            ->and(tutorialStepIn($sender, 'refuel')['sender'])->toBe('Harbour master')
            ->and(tutorialCodes($long))->toBe(['bad_text'])
            ->and(tutorialCodes($notSender))->toBe(['not_a_choice']);
    });

    it('gives a sender of one text another text in one language, keeping it in the others', function () {
        $check = tutorialCheck([['op' => 'text', 'target' => TUTORIAL, 'path' => '/steps/refuel/sender', 'locale' => 'pl', 'value' => 'Kapitanat portu']]);
        $sender = tutorialStepIn($check, 'refuel')['sender'];

        expect($check->errors())->toBe([])
            ->and($sender['pl'])->toBe('Kapitanat portu')
            ->and($sender['en'])->toBe(repositoryDocument('rules/tutorial')['steps'][4]['sender'])
            ->and(array_keys($sender))->toBe(['en', 'pl', 'fr', 'de', 'pt-BR']);
    });
});

describe('a word of the app', function () {
    it('starts from the words of the app in every language, and changes the one asked', function () {
        $check = tutorialCheck([['op' => 'text', 'target' => TUTORIAL, 'path' => '/messenger/labels/send', 'locale' => 'pl', 'value' => 'Nadaj list']]);
        $label = $check->documents['rules:tutorial']['messenger']['labels']['send'];
        $app = json_decode((string) file_get_contents(base_path('../web/src/i18n/locales/de.json')), true)['tutorial']['send'];

        expect($check->errors())->toBe([])
            ->and($label['pl'])->toBe('Nadaj list')
            ->and($label['de'])->toBe($app)
            ->and(array_keys($label))->toBe(['en', 'pl', 'fr', 'de', 'pt-BR']);
    });

    it('refuses a word the app does not have', function () {
        $check = tutorialCheck([['op' => 'text', 'target' => TUTORIAL, 'path' => '/messenger/labels/nonsense', 'locale' => 'pl', 'value' => 'x']]);

        expect(tutorialCodes($check))->toBe(['unknown_label']);
    });
});

describe('a new step', function () {
    /** @return array<string, mixed> */
    function newStep(string $key = 'harbour'): array
    {
        $words = fn (string $text): array => ['en' => $text, 'pl' => $text, 'fr' => $text, 'de' => $text, 'pt-BR' => $text];

        return [
            'key' => $key,
            'phase' => 1,
            'trigger' => 'none',
            'completion' => 'acknowledged',
            'target' => null,
            'goal' => $words('Read the harbour notice'),
            'sender' => 'Harbour master',
            'sms' => [$words('The harbour opens at dawn.')],
            'reply' => $words('Aye'),
            'reward' => new stdClass,
        ];
    }

    it('lands after the step it is to follow', function () {
        $check = tutorialCheck([['op' => 'insert', 'id' => 'new', 'target' => TUTORIAL, 'path' => '/steps', 'after' => 'refuel', 'value' => newStep()]]);
        $keys = array_column($check->documents['rules:tutorial']['steps'], 'key');

        expect($check->errors())->toBe([])
            ->and($check->ok())->toBeTrue()
            ->and($keys[array_search('refuel', $keys, true) + 1])->toBe('harbour')
            ->and($keys)->toHaveCount(count(repositoryDocument('rules/tutorial')['steps']) + 1)
            ->and($check->changes[0])->toMatchObject(['type' => 'add', 'after' => 'Read the harbour notice'])
            ->and($check->changes[0]->notes)->toContain('new');
    });

    it('goes to the very start with no step to follow, and moves the places of the others without mixing them up', function () {
        $check = tutorialCheck([
            ['op' => 'insert', 'target' => TUTORIAL, 'path' => '/steps', 'after' => null, 'value' => newStep()],
            ['op' => 'set', 'target' => TUTORIAL, 'path' => '/steps/refuel/reward/cash_cents', 'value' => 250, 'unit' => 'dollars'],
        ]);

        expect($check->errors())->toBe([])
            ->and($check->documents['rules:tutorial']['steps'][0]['key'])->toBe('harbour')
            ->and(tutorialStepIn($check, 'refuel')['reward']['cash_cents'])->toBe(25_000);
    });

    it('needs a key of its own, a step to follow that is there, and is not changed by another operation of the block', function () {
        $taken = tutorialCheck([['op' => 'insert', 'target' => TUTORIAL, 'path' => '/steps', 'after' => 'refuel', 'value' => newStep('repair')]]);
        $nowhere = tutorialCheck([['op' => 'insert', 'target' => TUTORIAL, 'path' => '/steps', 'after' => 'nonsense', 'value' => newStep()]]);
        $changed = tutorialCheck([
            ['op' => 'insert', 'target' => TUTORIAL, 'path' => '/steps', 'after' => 'refuel', 'value' => newStep()],
            ['op' => 'text', 'target' => TUTORIAL, 'path' => '/steps/harbour/goal', 'locale' => 'en', 'value' => 'Other'],
        ]);
        $elsewhere = tutorialCheck([['op' => 'insert', 'target' => TUTORIAL, 'path' => '/script', 'after' => null, 'value' => newStep()]]);

        expect(tutorialCodes($taken))->toBe(['key_taken'])
            ->and(tutorialCodes($nowhere))->toBe(['unknown_step'])
            ->and(tutorialCodes($changed))->toBe(['new_step_changed'])
            ->and(tutorialCodes($elsewhere))->toBe(['not_insertable']);
    });

    it('is judged with the rest of the tutorial: a step that shows messages and has no button is refused', function () {
        $step = newStep();
        unset($step['reply']);

        $check = tutorialCheck([['op' => 'insert', 'target' => TUTORIAL, 'path' => '/steps', 'after' => 'refuel', 'value' => $step]]);

        expect($check->ok())->toBeFalse()->and(tutorialCodes($check))->toContain('invalid_content');
    });

    it('is kept as a draft, and undoing it puts the draft back as it was', function () {
        $brief = bridgeTheBrief(tutorialSelection());
        $text = tutorialBlock([['op' => 'insert', 'target' => TUTORIAL, 'path' => '/steps', 'after' => 'refuel', 'value' => newStep()]], $brief);
        $admin = anAdmin(['email' => 'tutorial-undo@example.com']);
        $before = tutorialDefinition()->draft;

        $exchange = app(ChangeApplier::class)->apply($text, $admin, app(ChangeChecker::class)->check($text)->touched);

        expect(array_column(tutorialDefinition()->draftDocument()['steps'], 'key'))->toContain('harbour');

        app(ChangeReverter::class)->revert($exchange, $admin);

        expect(tutorialDefinition()->draft)->toBe($before);
    });
});
