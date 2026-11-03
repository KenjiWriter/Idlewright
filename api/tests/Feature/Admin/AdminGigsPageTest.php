<?php

declare(strict_types=1);

use App\Content\Definitions\GigsForm;
use App\Content\Definitions\JsonDocument;
use App\Filament\Pages\GigsPage;
use App\Filament\Pages\PendingChangesPage;
use App\Models\Admin;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;
use App\Services\GigReport;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// The page of the gigs: the board (when it opens and how long it stays, how many offers a time of the day has, how rare the good ones are), the pay (the speeds, the anchors, the
// bands), the kinds and the templates of the document of the gigs, kept as a draft of it; and what the pay comes to for every car of the pack, which is what `gigs:report` says.

/** @return array<string, mixed> */
function gigsPageDocument(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/gigs'));
}

function gigsPageDefinition(): ContentDefinition
{
    return ContentDefinition::query()->where('kind', 'rules')->where('slug', 'gigs')->firstOrFail();
}

/** The state of the form as the page shows it, changed by a function. @return array<string, mixed> */
function gigsPageState(?Closure $change = null): array
{
    $state = GigsForm::toState(gigsPageDocument());

    if ($change !== null) {
        $state = $change($state) ?? $state;
    }

    return $state;
}

/** The position of a line of a repeater of the page by what it holds under a key. */
function gigsPageRow(mixed $rows, string $key, string $value): string
{
    foreach ((array) $rows as $position => $row) {
        if (($row[$key] ?? null) === $value) {
            return (string) $position;
        }
    }

    throw new RuntimeException("No line with {$key} {$value}.");
}

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false);
    app()->setLocale('pl');
});

describe('the form of the gigs', function () {
    it('shows the waits as minutes and seconds, the parts as percents, the anchors in dollars, and a time of the day that pays plain as 100', function () {
        $state = gigsPageState();
        $taxi = collect($state['templates'])->firstWhere('key', 'taxi-city');

        expect($state['settings'])->toMatchArray(['enabled' => true, 'unlock_after_step' => 'away', 'timezone' => 'Europe/Warsaw', 'reset_hour' => 4, 'wave_minutes' => 22.5, 'cancel_seconds' => 60])
            ->and($state['settings']['slots'])->toBe(['dawn' => 4, 'day' => 4, 'dusk' => 5, 'night' => 3])
            ->and($state['rarities'][1])->toBe(['key' => 'good', 'weight' => 250, 'pay_percent' => 115])
            ->and($state['speed'])->toBe(['ref_speed_kmh' => 70, 'speed_cap_kmh' => 80, 'xp_per_hour' => 225, 'car_xp_per_hour' => 120])
            ->and($state['anchors'][0])->toBe(['from_level' => 1, 'dollars' => 600])
            ->and($state['anchors'][2])->toBe(['from_level' => 6, 'dollars' => 793.5])
            ->and($state['bands'][2])->toBe(['from_level' => 6, 'ratio_percent' => 70, 'max_ref_minutes' => 48, 'daily_limit' => 8])
            ->and(array_column($state['kinds'], 'key'))->toBe(['taxi', 'freight', 'delivery', 'express', 'bulk', 'night'])
            ->and($taxi)->toMatchArray(['kind' => 'taxi', 'capacity' => 'seats', 'amount_min' => 1, 'amount_max' => 4, 'class_in' => [], 'mode' => 'local', 'local_min' => 8, 'local_max' => 14, 'pay_percent' => 90, 'deadline_percent' => null, 'min_level' => 1])
            ->and($taxi['phase_pay'])->toBe(['dawn' => 100, 'day' => 100, 'dusk' => 140, 'night' => 125])
            ->and($taxi['phases'])->toBe(['dawn' => 10, 'day' => 10, 'dusk' => 14, 'night' => 8])
            ->and(collect($state['templates'])->firstWhere('key', 'delivery-city')['class_in'])->toBe(['van', 'pickup'])
            ->and(collect($state['templates'])->firstWhere('key', 'express-trip')['deadline_percent'])->toBe(100);
    });

    it('is the document again, to the letter, when nothing was changed', function () {
        $document = gigsPageDocument();

        expect(JsonDocument::encode(GigsForm::apply($document, GigsForm::toState($document))))->toBe(JsonDocument::encode($document));
    });

    it('turns the minutes, the percents and the dollars back into milliseconds, parts per million and cents', function () {
        $document = GigsForm::apply(gigsPageDocument(), gigsPageState(function (array $state): array {
            $state['settings']['wave_minutes'] = 30;
            $state['settings']['cancel_seconds'] = 45.5;
            $state['rarities'][2]['pay_percent'] = 142.5;
            $state['anchors'][1]['dollars'] = 700.25;
            $state['bands'][0]['ratio_percent'] = 65;

            return $state;
        }));

        expect($document['wave_ms'])->toBe(1_800_000)
            ->and($document['cancel_window_ms'])->toBe(45_500)
            ->and($document['rarities'][2]['pay_ppm'])->toBe(1_425_000)
            ->and($document['pay']['anchors'][1]['cents_per_hour'])->toBe(70_025)
            ->and($document['bands'][0]['ratio_ppm'])->toBe(650_000);
    });

    it('writes the length of a trip in the city only for a trip in the city, and the pay of a time of the day only when it is not the plain one', function () {
        $document = GigsForm::apply(gigsPageDocument(), gigsPageState(function (array $state): array {
            $position = array_search('taxi-city', array_column($state['templates'], 'key'), true);
            $state['templates'][$position]['mode'] = 'intercity';
            $state['templates'][$position]['phase_pay']['night'] = 100;
            $state['templates'][$position]['phase_pay']['day'] = 110;

            return $state;
        }));

        expect($document['templates']['taxi-city'])->not->toHaveKey('local_km')
            ->and($document['templates']['taxi-city']['phase_pay_ppm'])->toBe(['day' => 1_100_000, 'dusk' => 1_400_000])
            ->and($document['templates']['parcels-city']['local_km'])->toBe(['min' => 6, 'max' => 14])
            ->and($document['templates']['parcels-city'])->not->toHaveKey('phase_pay_ppm');
    });

    it('leaves out the classes and the deadline that are not set, and keeps what a later version of the document adds', function () {
        $document = gigsPageDocument();
        $document['templates']['taxi-city']['next_year'] = ['x' => 1];
        $document['pay']['bonus'] = 7;
        $document['templates']['express-trip']['class_in'] = ['van'];

        $state = GigsForm::toState($document);
        $state['templates'][array_search('express-trip', array_column($state['templates'], 'key'), true)]['class_in'] = [];
        $state['templates'][array_search('express-trip', array_column($state['templates'], 'key'), true)]['deadline_percent'] = null;

        $result = GigsForm::apply($document, $state);

        expect($result['templates']['taxi-city']['next_year'])->toBe(['x' => 1])
            ->and($result['pay']['bonus'])->toBe(7)
            ->and($result['templates']['express-trip'])->not->toHaveKey('class_in')->and($result['templates']['express-trip'])->not->toHaveKey('deadline_ppm');
    });

    it('shows the night run in percents, in points and in minutes, with the changes by the body class as lines', function () {
        $night = gigsPageState()['night'];

        expect($night)->toMatchArray([
            'enabled' => true, 'kind' => 'night', 'amount_min' => 10, 'amount_max' => 60, 'pay_percent' => 250, 'min_level' => 9, 'daily_limit' => 2,
            'control_percent' => 36, 'escape_percent' => 58, 'heat_minutes' => 360, 'heat_points' => 10,
            'escape_pay_percent' => 50, 'arrest_minutes' => 25, 'fine_percent' => 30, 'bail_percent' => 50,
        ])
            ->and($night['title']['pl'])->toBe('Towar bez faktury')
            ->and($night['modifiers'][0])->toBe(['classes' => ['hatchback', 'sedan', 'van'], 'delta_points' => -14])
            ->and($night['modifiers'][1])->toBe(['classes' => ['gt', 'muscle', 'super', 'hyper'], 'delta_points' => 10]);
    });

    it('turns the night run back into parts per million and milliseconds, and leaves out a change that names no class', function () {
        $document = GigsForm::apply(gigsPageDocument(), gigsPageState(function (array $state): array {
            $state['night']['control_percent'] = 42.5;
            $state['night']['heat_minutes'] = 30;
            $state['night']['arrest_minutes'] = 10;
            $state['night']['modifiers'][] = ['classes' => ['coupe'], 'delta_points' => -5];
            $state['night']['modifiers'][] = ['classes' => [], 'delta_points' => 99];

            return $state;
        }))['night'];

        expect($document['risk']['control_ppm'])->toBe(425_000)
            ->and($document['risk']['heat_ms'])->toBe(1_800_000)
            ->and($document['arrest']['ms'])->toBe(600_000)
            ->and($document['risk']['modifiers'])->toHaveCount(3)
            ->and($document['risk']['modifiers'][2])->toBe(['class_in' => ['coupe'], 'delta_ppm' => -50_000]);
    });

    it('leaves a game that has no night run without one while it is off, and starts it from the numbers that the code ships when it is switched on', function () {
        $without = gigsPageDocument();
        unset($without['night']);
        $state = GigsForm::toState($without);

        expect($state['night']['enabled'])->toBeFalse()
            ->and(GigsForm::apply($without, $state))->not->toHaveKey('night');

        $state['night']['enabled'] = true;
        $on = GigsForm::apply($without, $state)['night'];

        expect($on)->toEqual(gigsPageDocument()['night']);
    });

    it('gives a game that never had the document one by its first draft', function () {
        $document = GigsForm::apply([], GigsForm::toState(gigsPageDocument()));

        expect($document)->toHaveKeys(['schema', 'version', 'enabled', 'templates'])
            ->and($document['schema'])->toBe(0)
            ->and(array_keys($document['templates']))->toBe(array_keys(gigsPageDocument()['templates']));
    });
});

describe('the page', function () {
    it('is for any admin to open, starts from what is published, and saving it as it is keeps no draft', function () {
        signedInAdmin(Admin::ROLE_EDITOR);

        $this->get('/admin/gigs')->assertOk()->assertSee(__('admin.gigs.title'));

        $page = Livewire::test(GigsPage::class);

        expect($page->get('data.settings.wave_minutes'))->toEqual(22.5)
            ->and($page->get('data.settings.slots.dusk'))->toBe(5)
            ->and(count($page->get('data.templates')))->toBe(9)
            ->and(count($page->get('data.rarities')))->toBe(3);

        $page->call('save')->assertHasNoFormErrors();

        expect(gigsPageDefinition()->hasDraft())->toBeFalse();
    });

    it('keeps the draft of the document with what was changed, as the document keeps it', function () {
        $admin = signedInAdmin();

        $page = Livewire::test(GigsPage::class);
        $parcels = gigsPageRow($page->get('data.templates'), 'key', 'parcels-city');

        $page
            ->set('data.settings.slots.night', 5)
            ->set('data.settings.wave_minutes', 30)
            ->set("data.templates.{$parcels}.pay_percent", 105)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.definitions.saved'));

        $document = gigsPageDocument();
        $document['slots']['night'] = 5;
        $document['wave_ms'] = 1_800_000;
        $document['templates']['parcels-city']['pay_ppm'] = 1_050_000;

        expect(gigsPageDefinition()->draftDocument())->toEqual($document)
            ->and(gigsPageDefinition()->draft_admin_id)->toBe($admin->id);
    });

    it('keeps the draft of the night run with what was changed on its tab', function () {
        signedInAdmin();

        Livewire::test(GigsPage::class)
            ->set('data.night.control_percent', 40)
            ->set('data.night.arrest_minutes', 30)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.definitions.saved'));

        $night = gigsPageDefinition()->draftDocument()['night'];

        expect($night['risk']['control_ppm'])->toBe(400_000)
            ->and($night['arrest']['ms'])->toBe(1_800_000)
            ->and($night['pay_ppm'])->toBe(2_500_000);
    });

    it('does not keep a night run that no car of the pack can carry', function () {
        signedInAdmin();

        Livewire::test(GigsPage::class)
            ->set('data.night.amount_min', 9000)
            ->set('data.night.amount_max', 9000)
            ->call('save')
            ->assertNotified(__('admin.definitions.invalid'));

        expect(gigsPageDefinition()->hasDraft())->toBeFalse();
    });

    it('does not keep a number that is out of what the form allows', function () {
        signedInAdmin();

        Livewire::test(GigsPage::class)
            ->set('data.settings.wave_minutes', 0.5)
            ->call('save')
            ->assertHasFormErrors(['settings.wave_minutes']);

        expect(gigsPageDefinition()->hasDraft())->toBeFalse();
    });

    it('does not keep a template whose smallest offer is more than its biggest, and says what is wrong', function () {
        signedInAdmin();

        $page = Livewire::test(GigsPage::class);
        $parcels = gigsPageRow($page->get('data.templates'), 'key', 'parcels-city');

        $page
            ->set("data.templates.{$parcels}.amount_min", 500)
            ->call('save')
            ->assertNotified(__('admin.definitions.invalid'));

        expect(gigsPageDefinition()->hasDraft())->toBeFalse();
    });

    it('does not keep a board that opens after a step that the tutorial does not have, and says which', function () {
        signedInAdmin();

        $page = Livewire::test(GigsPage::class);

        // The select offers the steps that there are, so a step that the tutorial has lost since is refused by the form, and by the check of the draft for a document that comes from elsewhere.
        $tutorial = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'tutorial')->firstOrFail();
        $document = $tutorial->workingDocument();
        $document['steps'] = array_values(array_filter($document['steps'], fn (array $step): bool => $step['key'] !== 'away'));
        $tutorial->forceFill(['draft' => JsonDocument::encode($document), 'draft_saved_at' => now()])->save();

        $page->call('save')->assertHasFormErrors(['settings.unlock_after_step']);

        $errors = app(ContentDrafts::class)->validate('rules', gigsPageDocument(), 'gigs')->errors();

        expect(gigsPageDefinition()->hasDraft())->toBeFalse()
            ->and(array_column($errors, 'pointer'))->toContain('/unlock_after_step');
    });

    it('offers the steps of the tutorial for the one that opens the board', function () {
        signedInAdmin();

        $steps = Livewire::test(GigsPage::class)->instance()->form->getFlatFields(withHidden: true)['settings.unlock_after_step']->getOptions();

        expect(array_keys($steps))->toContain('away', 'travel')->and(count($steps))->toBeGreaterThanOrEqual(10);
    });

    it('is linked from the page of the pending changes once it has a draft', function () {
        signedInAdmin();

        Livewire::test(GigsPage::class)->set('data.settings.slots.night', 5)->call('save')->assertHasNoFormErrors();

        $this->get(PendingChangesPage::getUrl())->assertOk()->assertSee('data-slug="gigs"', false)->assertSee(GigsPage::getUrl(), false);
    });

    it('starts a game that has no gigs from the numbers that the code ships, switched off, and the first draft makes the document', function () {
        signedInAdmin();
        $definition = gigsPageDefinition();
        // The pack that was imported points at the versions of the document; a game made before the gigs has neither.
        DB::table('content_pack_items')->whereIn('content_version_id', $definition->versions()->pluck('id'))->delete();
        $definition->versions()->delete();
        $definition->delete();

        $page = Livewire::test(GigsPage::class);

        expect($page->get('data.settings.enabled'))->toBeFalse()
            ->and(count($page->get('data.templates')))->toBe(9);

        $page->call('save')->assertHasNoFormErrors()->assertNotified(__('admin.definitions.saved'));

        expect(gigsPageDefinition()->draftDocument())->toMatchArray(['schema' => 0, 'enabled' => false])
            ->and(array_keys(gigsPageDefinition()->draftDocument()['templates']))->toBe(array_keys(gigsPageDocument()['templates']));
    });
});

describe('the pay', function () {
    it('is worked out for the form as it is, with the numbers of gigs:report, and says that the gate lets them through', function () {
        signedInAdmin();

        $page = Livewire::test(GigsPage::class)->call('measurePay');
        $report = $page->get('report');

        expect($report['rows'])->not->toBe([])
            ->and($report['offences'])->toBe([])
            ->and(array_unique(array_column($report['rows'], 'template')))->toContain('taxi-city', 'bulk-trip');

        $page->assertSeeHtml('data-test="report-verdict"')->assertSee(__('admin.gigs.report.gate_passes'))->assertSeeHtml('data-template="taxi-city"');
    });

    it('says that the gate would stop a pay that is more than the rung of the ladder allows, and keeps nothing', function () {
        signedInAdmin();

        $page = Livewire::test(GigsPage::class);
        $first = array_key_first($page->get('data.bands'));

        $page->set("data.bands.{$first}.ratio_percent", 200)->call('measurePay');

        expect($page->get('report')['offences'])->not->toBe([])
            ->and(array_unique(array_column($page->get('report')['offences'], 'why')))->toContain('r1')
            ->and(gigsPageDefinition()->hasDraft())->toBeFalse();

        $page->assertSee(__('admin.gigs.report.gate_stops'))->assertSeeHtml('data-test="report-offences"');
    });

    it('is not worked out for a document that the schema refuses: the page says what is wrong with it', function () {
        signedInAdmin();

        $page = Livewire::test(GigsPage::class);
        $parcels = gigsPageRow($page->get('data.templates'), 'key', 'parcels-city');

        $page->set("data.templates.{$parcels}.amount_min", 500)->call('measurePay')->assertNotified(__('admin.definitions.invalid'));

        expect($page->get('report'))->toBeNull();
    });

    it('is summed up for every template: how many cars can take it and the range of the pay against the ladder and against the laps', function () {
        $summary = GigReport::byTemplate([
            ['template' => 'b', 'r1' => 0.6, 'r2' => 0.4],
            ['template' => 'a', 'r1' => 0.7, 'r2' => 0.9],
            ['template' => 'a', 'r1' => 0.5, 'r2' => 1.1],
        ]);

        expect(array_keys($summary))->toBe(['a', 'b'])
            ->and($summary['a'])->toBe(['cars' => 2, 'r1_min' => 0.5, 'r1_max' => 0.7, 'r2_min' => 0.9, 'r2_max' => 1.1])
            ->and($summary['b']['cars'])->toBe(1);
    });
});
