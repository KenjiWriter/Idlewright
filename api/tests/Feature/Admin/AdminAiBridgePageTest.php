<?php

declare(strict_types=1);

use App\Filament\Pages\AiBridgePage;
use App\Filament\Pages\StudioPage;
use App\Models\AdminAuditLog;
use App\Models\AiExchange;
use Livewire\Livewire;

// The page of the bridge to an AI: the choices of what the instruction is to hold, how big it comes to, and the instruction itself, which is made when it is asked for, kept
// in the record and not in the state of the page.

beforeEach(function () {
    contentPacks()->import(repositoryContent(), activate: true, checkTables: false);
    app()->setLocale('pl');
});

it('is a page of a game, in the menu of the content, for anyone who works in the panel', function () {
    signedInAdmin();

    $this->get('/admin/ai-bridge')->assertOk()->assertSee(__('admin.ai.title'))->assertSee(__('admin.ai.copy'))->assertSee(StudioPage::sectionName('premium'));
});

it('shows how big the instruction comes to with the choices it has, and counts again when a choice is changed', function () {
    signedInAdmin();

    $page = Livewire::test(AiBridgePage::class);
    $full = $page->get('size');

    $page->set('data.include', ['rules'])->set('data.simple', true);
    $small = $page->get('size');

    expect($full['tokens'])->toBeGreaterThan(5_000)
        ->and($small['tokens'])->toBeLessThan($full['tokens'])
        ->and($small['scope'])->toBeGreaterThan(0)
        ->and($full['bytes'])->toBeGreaterThan($small['bytes']);

    $page->assertSee(__('admin.ai.size'));
});

it('gives the text of the instruction when it is asked for, records it, and does not keep it in its state', function () {
    $admin = signedInAdmin();

    $page = Livewire::test(AiBridgePage::class)->set('data.include', ['parts'])->set('data.locale', 'en');
    $text = $page->instance()->brief();

    $row = AiExchange::query()->firstOrFail();

    expect($text)->toContain('Assistant for changing the parameters of one game')
        ->and($text)->toContain('"kind":"part"')
        ->and($text)->not->toContain('"kind":"vehicle"')
        ->and($row->admin_id)->toBe($admin->id)
        ->and($row->scope)->toBe(['kind:part'])
        ->and($row->locale)->toBe('en')
        ->and($text)->toContain('"brief":"'.$row->code.'"')
        ->and(AdminAuditLog::query()->where('action', 'ai_brief_prepared')->count())->toBe(1)
        ->and(json_encode($page->instance()->data))->not->toContain('Assistant for changing');
});

it('counts the sections of the rules and lets the choice be narrowed to some of them', function () {
    signedInAdmin();

    $page = Livewire::test(AiBridgePage::class)->set('data.sections', ['premium'])->set('data.simple', false);
    $text = $page->instance()->brief();

    expect(AiExchange::query()->firstOrFail()->scope)->toBe(['kind:vehicle', 'kind:part', 'rules:starter#premium'])
        ->and($text)->toContain('RULES {"document":"starter","section":"premium"}')
        ->and($text)->not->toContain('"section":"fuel"');
});
