<?php

declare(strict_types=1);

use App\Filament\Pages\AiBridgePage;
use App\Filament\Pages\StudioPage;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AiExchange;
use App\Models\ContentDefinition;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

// The page of the bridge to an AI: the choices of what the instruction is to hold, how big it comes to, and the instruction itself, which is made when it is asked for, kept
// in the record and not in the state of the page.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

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

describe('pasting the answer', function () {
    it('checks the answer and shows every number it would change, what is live, what it is now and what it becomes', function () {
        signedInAdmin();
        $text = bridgeCanned('engines-t3', bridgeTheBrief());

        $page = Livewire::test(AiBridgePage::class)->set('answer', $text)->call('check');
        $result = $page->get('result');

        expect($result['ok'])->toBeTrue()
            ->and($result['rows'])->toHaveCount(2)
            ->and($result['rows'][0]['published'])->not->toBe('-')
            ->and($result['rows'][0]['percent'])->toBe('+12.00%')
            ->and($result['repair'])->toBe('');

        $page->assertSee(__('admin.ai.verdict_ok', ['changes' => 2, 'documents' => 2]))
            ->assertSeeHtml('data-ai-table')
            ->assertSee(__('admin.ai.apply'));

        expect(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
    });

    it('says what is wrong in the words of the panel, and offers the message to give back to the AI', function () {
        signedInAdmin();
        $text = bridgeCanned('vip-wrong-unit', bridgeTheBrief());

        $page = Livewire::test(AiBridgePage::class)->set('answer', $text)->call('check');

        expect($page->get('result')['ok'])->toBeFalse()
            ->and($page->get('result')['repair'])->toContain('[unit_mismatch]');

        $page->assertSee(__('admin.ai.issues.unit_mismatch'))
            ->assertSee(__('admin.ai.issues.above_max'))
            ->assertSee(__('admin.ai.copy_errors'))
            ->assertDontSeeHtml('data-ai-apply');
    });

    it('asks to be told that the remarks were seen before it keeps an answer that has some', function () {
        signedInAdmin();
        $brief = bridgeTheBrief();
        $text = str_replace($brief->pack, '2020.1.1', bridgeCanned('vip-price', $brief));

        $page = Livewire::test(AiBridgePage::class)->set('answer', $text)->call('check');

        expect($page->get('result')['ok'])->toBeTrue()->and($page->get('result')['warnings'])->toBeGreaterThan(0);

        $page->assertSeeHtml('wire:model.live="acknowledged"')->assertSee(__('admin.ai.issues.base_pack'));
    });

    it('keeps the checked answer as drafts, says where to look, and forgets the text', function () {
        $admin = signedInAdmin();
        $text = bridgeCanned('vip-price', bridgeTheBrief());

        $page = Livewire::test(AiBridgePage::class)->set('answer', $text)->call('check')->call('apply');

        $rules = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();

        $page->assertNotified(__('admin.ai.applied', ['changes' => 1]));

        expect($rules->draftDocument()['premium']['vip']['coins'])->toBe(120)
            ->and($page->get('answer'))->toBe('')
            ->and($page->get('result'))->toBeNull()
            ->and(AiExchange::query()->where('kind', 'paste')->first()->admin_id)->toBe($admin->id);

        $page->assertSee(__('admin.ai.status_applied'));
    });

    it('is for an editor as well as for a publisher: keeping drafts is what an editor does, publishing is not touched', function () {
        signedInAdmin(Admin::ROLE_EDITOR);
        $text = bridgeCanned('vip-price', bridgeTheBrief());

        Livewire::test(AiBridgePage::class)->set('answer', $text)->call('check')->call('apply');

        expect(AiExchange::query()->where('kind', 'paste')->first()->status)->toBe('applied')
            ->and(ContentDefinition::query()->where('slug', 'starter')->firstOrFail()->hasDraft())->toBeTrue();
    });

    it('undoes an answer from the list of the last ones, and refuses when the draft was worked on since', function () {
        signedInAdmin();
        $text = bridgeCanned('vip-price', bridgeTheBrief());

        $page = Livewire::test(AiBridgePage::class)->set('answer', $text)->call('check')->call('apply');
        $exchange = AiExchange::query()->where('kind', 'paste')->firstOrFail();

        $page->call('undo', $exchange->id)->assertNotified(__('admin.ai.undone'));

        expect(ContentDefinition::query()->where('slug', 'starter')->firstOrFail()->hasDraft())->toBeFalse()
            ->and($exchange->fresh()->status)->toBe('reverted');

        $page->assertSee(__('admin.ai.status_reverted'));
    });

    it('says to paste something when nothing is pasted, and limits how often answers are checked', function () {
        $admin = signedInAdmin();
        RateLimiter::clear('ai-check:'.$admin->id);
        $text = bridgeCanned('vip-price', bridgeTheBrief());

        $page = Livewire::test(AiBridgePage::class)->set('answer', '   ')->call('check');
        $page->assertNotified(__('admin.ai.empty_answer'));

        $page->set('answer', $text);

        foreach (range(1, 10) as $_) {
            $page->call('check');
        }

        expect($page->get('result')['ok'])->toBeTrue();

        $page->set('answer', $text.' ')->call('check')->assertNotified(__('admin.ai.rate_limited'));
    });
    it('works out the effect of a checked answer on the balance, in a window, and keeps nothing', function () {
        signedInAdmin();
        $text = bridgeCanned('vip-price', bridgeTheBrief());

        $page = Livewire::test(AiBridgePage::class)->set('answer', $text)->call('check')->assertSeeHtml('data-ai-balance');
        $page->mountAction('balance')->assertActionMounted('balance');

        expect(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
    });
});
