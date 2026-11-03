<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Content\Definitions\ThreadCloser;
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

it('is for any admin to open, and starts from the first track with its thread', function () {
    signedInAdmin(Admin::ROLE_EDITOR);

    $this->get('/admin/thread')->assertOk()->assertSee('zlomowo-street');

    $state = Livewire::test(ThreadPage::class)->instance()->data;

    expect($state['track'])->toBe('zlomowo-street')
        ->and($state['sections'])->toHaveCount(14)
        ->and(array_values($state['sections'])[0])->toMatchArray(['type' => 'straight'])
        ->and($state['mode'])->toBe('circuit')
        ->and($state['lap_from'])->toBe('stats');
});

it('keeps the thread of a track as a draft, with the length the sections add up to', function () {
    signedInAdmin();

    Livewire::test(ThreadPage::class)
        ->fillForm(threadState(function (array $state): array {
            $state['track'] = 'suchy-pas-short-strip';
            $state['mode'] = 'circuit';
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

it('closes the loop from the page: the last corner and two straights move, and it says which', function () {
    signedInAdmin();
    $opened = threadState(function (array $state): array {
        $state['sections'][0]['length_m'] += 120;
        $state['sections'][3]['angle_deg'] += 17;

        return $state;
    });
    $expected = ThreadCloser::close(array_values($opened['sections']));

    $page = Livewire::test(ThreadPage::class)->fillForm($opened)->call('closeLoop');
    $sections = array_values($page->instance()->data['sections']);

    $page->assertNotified(__('admin.thread.close_done', ['first' => $expected['moved']['straights'][0], 'second' => $expected['moved']['straights'][1], 'corner' => $expected['moved']['corner'], 'gap' => round($expected['gap_m'], 1)]));

    // The corners turn a full circle again, and the sections are the ones the closer made.
    $turn = array_sum(array_map(static fn (array $section): float => $section['type'] === 'corner' ? ($section['turn'] === 'left' ? 1 : -1) * (float) $section['angle_deg'] : 0.0, $sections));

    expect(abs($turn))->toEqualWithDelta(360.0, 0.001)
        ->and($sections)->toHaveCount(14)
        ->and($sections[0]['length_m'])->toBe($expected['sections'][0]['length_m']);
});

it('says why a run cannot be closed, and a loop without a corner', function () {
    signedInAdmin();

    Livewire::test(ThreadPage::class)
        ->fillForm(threadState(function (array $state): array {
            $state['mode'] = 'run';

            return $state;
        }))
        ->call('closeLoop')
        ->assertNotified(__('admin.thread.close_run'));

    Livewire::test(ThreadPage::class)
        ->fillForm(threadState(function (array $state): array {
            $state['sections'] = [threadSections()[0], threadSections()[2]];

            return $state;
        }))
        ->call('closeLoop')
        ->assertNotified(__('admin.thread.close_no_corner'));
});

it('scales the radii, stretches the longest straight and starts from a template, from the page', function () {
    signedInAdmin();

    $page = Livewire::test(ThreadPage::class)->fillForm(threadState());
    $radii = fn (): array => array_column(array_filter(array_values($page->instance()->data['sections']), static fn (array $section): bool => $section['type'] === 'corner'), 'radius_m');
    $was = $radii();

    $page->call('scaleRadii', 2.0);

    expect($radii())->toBe(array_map(static fn (int|float $radius): int|float => $radius * 2, $was));

    $page->call('stretchLongest', 900.0);
    $lengths = array_column(array_filter(array_values($page->instance()->data['sections']), static fn (array $section): bool => $section['type'] === 'straight'), 'length_m');

    expect(max($lengths))->toBe(900);

    $page->call('useTemplate', 'drag');
    $data = $page->instance()->data;

    expect($data['mode'])->toBe('run')
        ->and($data['return_s'])->toBe(5)
        ->and(array_values($data['sections']))->toHaveCount(1);
});

it('has templates that are loops that close and runs that can be driven, and radii and straights that stay within the limits', function () {
    foreach (['city', 'rally', 'oval'] as $name) {
        $template = ThreadForm::template($name);

        expect($template['mode'])->toBe('circuit');

        $closed = ThreadCloser::close($template['sections']);

        expect($closed['gap_m'])->toBeLessThan(3.0, $name);
    }

    foreach (['drag', 'hillclimb'] as $name) {
        expect(ThreadForm::template($name)['mode'])->toBe('run')->and(ThreadForm::template($name)['return_s'])->toBeGreaterThan(0);
    }

    $radiiOf = static fn (array $sections): array => array_values(array_filter(array_column($sections, 'radius_m'), static fn (mixed $radius): bool => $radius !== null));
    $city = ThreadForm::template('city')['sections'];

    expect(max($radiiOf(ThreadForm::scaledRadii($city, 100.0))))->toBe(1000)
        ->and(min($radiiOf(ThreadForm::scaledRadii($city, 0.01))))->toBe(8)
        ->and(ThreadForm::stretched([['type' => 'corner']], 500.0))->toBe([['type' => 'corner']])
        ->and(ThreadForm::template('nonsense')['sections'])->toBe([]);
});
