<?php

declare(strict_types=1);

use App\Filament\Pages\ProgressPage;
use Livewire\Livewire;

// The page "Symulator postępu" (most AI v2, V2): a person plays the driver the assistant plays, on the live pack or the drafts, and reads the levels, the purchases, the
// journeys and the assumptions in the language of the panel.

beforeEach(function () {
    contentPacks()->import(repositoryContent(), activate: true, checkTables: false);
    app()->setLocale('pl');
});

it('plays a new driver and shows when each level is reached, with what the simulator leaves out', function () {
    signedInAdmin();

    $this->get('/admin/progress')->assertOk()->assertSee(__('admin.progress.title'))->assertSee(__('admin.progress.simulate'));

    Livewire::test(ProgressPage::class)
        ->set('data.minutes', 60)
        ->set('data.buy', 'list')
        ->set('data.list', 'no-such-part, street-tires-t2')
        ->call('simulate')
        ->assertHasNoErrors()
        ->assertSee(__('admin.progress.levels'))
        ->assertSee('zlomowo-street')
        ->assertSee(__('admin.progress.notes.list_unknown', ['slug' => 'no-such-part']))
        ->assertSee(__('admin.progress.assumptions.ideal_player'))
        ->assertSee(__('admin.progress.kinds.journey'));
});

it('says what is wrong with a plan it cannot play', function () {
    signedInAdmin();

    Livewire::test(ProgressPage::class)
        ->set('data.style', 'every')
        ->set('data.every_minutes', 5)
        ->set('data.visit_minutes', 10)
        ->call('simulate')
        ->assertNotified(__('admin.progress.failed'))
        ->assertSet('result', null);
});
