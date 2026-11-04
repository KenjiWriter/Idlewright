<?php

declare(strict_types=1);

use App\Content\Definitions\DefinitionForm;
use App\Content\Definitions\JsonDocument;
use App\Content\Definitions\TutorialForm;
use App\Content\Definitions\VocabularyForm;
use App\Content\Vocabulary\MessageCatalog;
use App\Filament\Pages\TutorialPage;
use App\Models\ContentDefinition;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

// The page of the tutorial says what the mentor writes on, the words of the tutorial and the story before the choice of a wreck, and keeps them in the draft of
// the document as the game reads them. The content of the repository is what is in the database.

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false);
    app()->setLocale('pl');
});

/** @return array<string, mixed> the tutorial of the repository */
function tutorialPagePublished(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/tutorial'));
}

/** @return array<string, mixed> the state of the form for the tutorial of the repository, changed by a function */
function tutorialPageState(Closure $change): array
{
    return $change(TutorialForm::toState(tutorialPagePublished()));
}

/** @return array<string, mixed> the draft of the tutorial */
function tutorialPageDraft(): array
{
    return ContentDefinition::query()->where('kind', 'rules')->where('slug', 'tutorial')->firstOrFail()->draftDocument();
}

/** @return array<string, string|null> a box in every language, with the texts given */
function tutorialPageTexts(array $texts): array
{
    return [...DefinitionForm::texts([]), ...$texts];
}

/** A small PNG whose bytes depend on the seed, so that two pictures are two files. */
function tutorialPagePng(string $seed): string
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

    return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 64, 64, 8, 2, 0, 0, 0)).$chunk('tEXt', "seed\x00{$seed}").$chunk('IDAT', (string) gzcompress(str_repeat("\x00".str_repeat("\x20\x40\x80", 64), 64))).$chunk('IEND', '');
}

describe('the page of the tutorial', function () {
    it('shows the device, the words and the story of the game, and keeps no draft when it is saved as it is', function () {
        signedInAdmin();

        $page = Livewire::test(TutorialPage::class)
            ->assertSee(__('admin.tutorial.device'))
            ->assertSee(__('admin.tutorial.words'))
            ->assertSee(__('admin.tutorial.story'));

        expect($page->get('data.device'))->toBe('phone')
            ->and($page->get('data.story_mode'))->toBe(TutorialForm::STORY_OWN)
            ->and(count($page->get('data.story_scenes')))->toBe(4)
            ->and(array_keys($page->get('data.labels')))->toContain('network', 'skipText', 'reward')
            ->and(array_keys($page->get('data.story_labels')))->toContain('choose')->not->toContain('scene1', 'sms');

        $page->call('save')->assertHasNoFormErrors();

        expect(ContentDefinition::query()->where('kind', 'rules')->where('slug', 'tutorial')->firstOrFail()->hasDraft())->toBeFalse();
    });

    it('keeps a letter with a sender for the steps that name none, a word of the game and a story of one scene', function () {
        signedInAdmin();
        $published = tutorialPagePublished();
        $first = array_key_first(array_filter($published['steps'], fn (array $step): bool => $step['sms'] !== []));
        $scene = ['text' => tutorialPageTexts(['en' => 'Ahoy, {name}.', 'pl' => 'Ahoj, {name}.']), 'seconds' => 4.5, 'camera' => 'pan-left', 'focus_x' => 30, 'focus_y' => 60];

        Livewire::test(TutorialPage::class)
            ->fillForm(tutorialPageState(function (array $state) use ($first, $scene): array {
                $state['device'] = 'letter';
                $state['part_who'] = false;
                $state['messenger_sender'] = 'Kapitanat portu';
                $state['labels']['send'] = tutorialPageTexts(['en' => 'Post the letter', 'pl' => 'Nadaj list']);
                $state['steps'][$first]['sender'] = null;
                $state['story_scenes'] = [$scene];
                $state['story_sender'] = 'Kapitanat portu';
                $state['story_labels']['choose'] = tutorialPageTexts(['en' => 'Pick a boat', 'pl' => 'Wybierz łódź']);

                return $state;
            }))
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.definitions.saved'));

        $draft = tutorialPageDraft();

        expect($draft['messenger'])->toBe([
            'device' => 'letter',
            'parts' => ['who' => false],
            'sender' => 'Kapitanat portu',
            'labels' => ['send' => ['en' => 'Post the letter', 'pl' => 'Nadaj list']],
        ])
            ->and($draft['steps'][$first])->not->toHaveKey('sender')
            ->and($draft['story']['scenes'])->toBe([['text' => ['en' => 'Ahoy, {name}.', 'pl' => 'Ahoj, {name}.'], 'ms' => 4500, 'camera' => 'pan-left', 'focus' => ['x' => 30, 'y' => 60]]])
            ->and($draft['story']['sender'])->toBe('Kapitanat portu')
            ->and($draft['story']['sms'])->toBe($published['story']['sms'])
            ->and($draft['story']['labels'])->toBe(['choose' => ['en' => 'Pick a boat', 'pl' => 'Wybierz łódź']]);
    });

    it('keeps a sender in the languages of the game, and shows it again', function () {
        signedInAdmin();
        $first = array_key_first(array_filter(tutorialPagePublished()['steps'], fn (array $step): bool => $step['sms'] !== []));

        Livewire::test(TutorialPage::class)
            ->fillForm(tutorialPageState(function (array $state) use ($first): array {
                $state['steps'][$first]['sender'] = null;
                $state['steps'][$first]['sender_texts'] = tutorialPageTexts(['en' => 'Harbour master', 'pl' => 'Kapitanat portu']);

                return $state;
            }))
            ->call('save')
            ->assertHasNoFormErrors();

        expect(tutorialPageDraft()['steps'][$first]['sender'])->toBe(['en' => 'Harbour master', 'pl' => 'Kapitanat portu']);

        $page = Livewire::test(TutorialPage::class);
        $step = array_values($page->get('data.steps'))[$first];

        expect($step['sender'])->toBeNull()
            ->and($step['sender_texts']['pl'])->toBe('Kapitanat portu');
    });

    it('keeps the frame of a device of the game, with where its screen is', function () {
        signedInAdmin();
        $frame = tutorialPagePng('frame');

        Livewire::test(TutorialPage::class)
            ->fillForm(tutorialPageState(function (array $state) use ($frame): array {
                $state['device'] = 'picture';
                $state['frame_upload'] = [UploadedFile::fake()->createWithContent('frame.png', $frame)];
                [$state['screen_top'], $state['screen_right'], $state['screen_bottom'], $state['screen_left']] = [12, 8, 20, 8];

                return $state;
            }))
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.definitions.saved'));

        $draft = tutorialPageDraft();

        expect($draft['messenger']['picture'])->toBe(['frame' => hash('sha256', $frame).'.png', 'screen' => ['top' => 12, 'right' => 8, 'bottom' => 20, 'left' => 8]])
            ->and(json_encode($draft))->not->toContain('frame_upload');
    });

    it('asks a device of the game for its frame', function () {
        signedInAdmin();

        Livewire::test(TutorialPage::class)
            ->fillForm(tutorialPageState(function (array $state): array {
                $state['device'] = 'picture';
                [$state['screen_top'], $state['screen_right'], $state['screen_bottom'], $state['screen_left']] = [10, 10, 10, 10];

                return $state;
            }))
            ->call('save')
            ->assertHasFormErrors(['frame_upload' => 'required']);
    });

    it('leaves the story of the app to a game that chooses it, and keeps the scenes of a story that is turned off', function () {
        signedInAdmin();
        $published = tutorialPagePublished();

        Livewire::test(TutorialPage::class)
            ->fillForm(tutorialPageState(fn (array $state): array => [...$state, 'story_mode' => TutorialForm::STORY_NONE]))
            ->call('save')
            ->assertHasNoFormErrors();

        expect(tutorialPageDraft()['story'])->toBe(['enabled' => false, ...array_diff_key($published['story'], ['enabled' => true])]);

        Livewire::test(TutorialPage::class)
            ->fillForm(tutorialPageState(fn (array $state): array => [...$state, 'story_mode' => TutorialForm::STORY_BUILTIN]))
            ->call('save')
            ->assertHasNoFormErrors();

        expect(tutorialPageDraft())->not->toHaveKey('story');
    });
});

describe('the vocabulary', function () {
    it('no longer offers the words of the tutorial and of its story, but shows those it has so that they can be cleared', function () {
        $form = new VocabularyForm(MessageCatalog::fromConfig());

        expect($form->keys(['messages' => []]))->not->toContain('tutorial.network', 'story.choose')
            ->and($form->keys(['messages' => ['tutorial.send' => ['en' => 'Send it', 'pl' => 'Wyślij to']]]))->toContain('tutorial.send')->not->toContain('tutorial.network');
    });
});
