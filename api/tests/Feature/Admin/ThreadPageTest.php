<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Content\Definitions\ThreadForm;
use App\Filament\Pages\ThreadPage;
use App\Models\Admin;
use App\Models\ContentDefinition;
use Livewire\Livewire;

// The thread of a track in the panel: the sections of one track, the line they draw and the laps on them, kept as a draft of the rules of the world.

function threadPublishedRules(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));
}

/** @param  Closure(array<string, mixed>): array<string, mixed>|null  $change */
function threadState(?Closure $change = null, string $track = 'zlomowo-street'): array
{
    $state = ThreadForm::toState(threadPublishedRules(), $track);

    return $change === null ? $state : ($change($state) ?? $state);
}

/** @return list<array<string, mixed>> the sections of a small loop, as the form has them */
function threadSections(): array
{
    $line = ['length_m' => null, 'radius_m' => null, 'angle_deg' => null, 'turn' => null, 'surface' => null, 'limit_kmh' => null, 'rough' => null];

    return [
        [...$line, 'type' => 'straight', 'length_m' => 400],
        [...$line, 'type' => 'corner', 'radius_m' => 60, 'angle_deg' => 180, 'turn' => 'right'],
        [...$line, 'type' => 'straight', 'length_m' => 400],
        [...$line, 'type' => 'corner', 'radius_m' => 60, 'angle_deg' => 180, 'turn' => 'right'],
    ];
}

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false);
    app()->setLocale('pl');
});

it('is for any admin to open, and starts from the first track with nothing drawn', function () {
    signedInAdmin(Admin::ROLE_EDITOR);

    $this->get('/admin/thread')->assertOk()->assertSee('zlomowo-street');

    $state = Livewire::test(ThreadPage::class)->instance()->data;

    expect($state['track'])->toBe('zlomowo-street')
        ->and($state['sections'])->toBe([])
        ->and($state['lap_from'])->toBe('stats');
});

it('keeps the thread of a track as a draft, with the length the sections add up to', function () {
    signedInAdmin();

    Livewire::test(ThreadPage::class)
        ->fillForm(threadState(function (array $state): array {
            $state['track'] = 'suchy-pas-short-strip';
            $state['sections'] = threadSections();

            return $state;
        }, 'suchy-pas-short-strip'))
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('admin.definitions.saved'));

    $track = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail()->draftDocument()['tracks']['suchy-pas-short-strip'];

    expect($track['thread']['mode'])->toBe('circuit')
        ->and($track['thread']['sections'])->toHaveCount(4)
        ->and($track['thread']['sections'][1])->toBe(['type' => 'corner', 'radius_m' => 60, 'angle_deg' => 180, 'turn' => 'right'])
        ->and($track['length_m'])->toBe(400 + 188 + 400 + 188)
        ->and(array_key_exists('lap_from', $track))->toBeFalse()
        // The track already says its speed, so the thread does not take it over.
        ->and($track['speed_kmh'])->toBe(threadPublishedRules()['tracks']['suchy-pas-short-strip']['speed_kmh']);
});

it('moves the lap of a track to its thread and keeps the base lap of the track', function () {
    signedInAdmin();

    Livewire::test(ThreadPage::class)
        ->fillForm(threadState(function (array $state): array {
            $state['lap_from'] = 'thread';
            $state['sections'] = threadSections();

            return $state;
        }))
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('admin.definitions.saved'));

    $rules = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail()->draftDocument();

    expect($rules['tracks']['zlomowo-street']['lap_from'])->toBe('thread')
        ->and($rules['tracks']['zlomowo-street']['speed_kmh'])->toBe(124.8)
        ->and(array_keys($rules['tracks']['zlomowo-street']))->toContain('thread');
});

it('refuses a corner that has no radius, with the reason, and keeps nothing', function () {
    signedInAdmin();

    Livewire::test(ThreadPage::class)
        ->fillForm(threadState(function (array $state): array {
            $state['sections'] = threadSections();
            $state['sections'][1]['radius_m'] = null;

            return $state;
        }))
        ->call('save')
        ->assertHasFormErrors();

    expect(ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail()->hasDraft())->toBeFalse();
});

it('shows the thread of another track when it is chosen', function () {
    signedInAdmin();

    $page = Livewire::test(ThreadPage::class)->set('data.track', 'autodrom-circuit');

    expect($page->instance()->data['track'])->toBe('autodrom-circuit')
        ->and($page->instance()->trackSlug)->toBe('autodrom-circuit');
});

it('shows the line, the numbers and the cars once a thread can be driven', function () {
    signedInAdmin();

    Livewire::test(ThreadPage::class)
        ->fillForm(threadState(function (array $state): array {
            $state['sections'] = threadSections();

            return $state;
        }))
        ->assertSee('<svg', escape: false)
        ->assertSee('Rdzak GT-4')
        ->assertSee(__('admin.thread.col_formula'));
});

it('puts ready-made pieces, the mirror and the other way round on the sections', function () {
    $sections = threadSections();
    $withPiece = [...$sections, ...ThreadForm::preset('chicane')];
    $mirrored = ThreadForm::mirrored($sections);
    $reversed = ThreadForm::reversed($sections);

    expect($withPiece)->toHaveCount(7)
        ->and($withPiece[4]['turn'])->toBe('left')
        ->and($withPiece[6]['turn'])->toBe('right')
        ->and($mirrored[1]['turn'])->toBe('left')
        ->and($mirrored[0]['type'])->toBe('straight')
        ->and(array_column($reversed, 'type'))->toBe(['corner', 'straight', 'corner', 'straight'])
        ->and($reversed[0]['turn'])->toBe('left')
        ->and(ThreadForm::preset('nonsense'))->toBe([]);
});
