<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Filament\Pages\TuningPage;
use Livewire\Livewire;

// The page of the bonuses of the tuning: the list of what a part can be given, with the bell of each and how many tries it takes to reach a value, and the best set against the gate.

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false, activate: true);
    app()->setLocale('pl');
});

describe('the page of the bonuses', function () {
    it('lists every bonus of the rules with its names in all languages, its slots, its weight and the level it opens at', function () {
        signedInAdmin();

        $page = $this->get(TuningPage::getUrl())->assertOk();
        $bonuses = count(app(Rules::class)->tuning()['definitions']);

        $page->assertSee('data-test="tuning-page"', false)
            ->assertSee("Bonusów, które można dać części: {$bonuses}")
            ->assertSee('data-key="gravel-grip"', false)
            ->assertSee('Przyczepność na żwirze')
            ->assertSee('pt-BR = Aderência no cascalho', false)
            ->assertSee('Otwiera się od poziomu');
        expect(substr_count($page->getContent(), 'data-test="tuning-bonus"'))->toBe($bonuses);
    });

    it('draws the bell of a bonus from the exact distribution, and says how many tries it takes on average to reach a value', function () {
        signedInAdmin();

        $html = $this->get(TuningPage::getUrl())->assertOk()->getContent();

        // 11 bonuses, each with a chart of twelve bars (1% to 12%).
        expect(substr_count($html, 'data-test="tuning-chart"'))->toBe(11)
            ->and(substr_count($html, 'data-test="tuning-reach"'))->toBe(11);

        // The mean is 5% and the spread 2.3%: a try gives 6% or more about one time in three, and 12% about one in a thousand.
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);
        $six = (float) $xpath->query('(//*[@data-test="tuning-bonus"])[1]//td[@data-reach="6"]')->item(0)->textContent;
        $twelve = (float) str_replace([' ', "\u{a0}"], '', $xpath->query('(//*[@data-test="tuning-bonus"])[1]//td[@data-reach="12"]')->item(0)->textContent);

        expect($six)->toBeGreaterThan(2.0)->and($six)->toBeLessThan(4.5)
            ->and($twelve)->toBeGreaterThan(500.0);
    });

    it('works out the best set only when asked, and shows the worst case with the warning of the gate', function () {
        signedInAdmin();

        $this->get(TuningPage::getUrl())->assertOk()->assertDontSee('data-test="tuning-budget-row"', false);

        Livewire::test(TuningPage::class)
            ->call('calculateBudget')
            ->assertSee('data-test="tuning-budget-row"', false)
            ->assertSee('data-test="tuning-budget-warning"', false)
            ->assertSee('bizon-m-2');
    });

    it('has the guide of the bonuses on the page, with its steps', function () {
        signedInAdmin();

        $this->get(TuningPage::getUrl())->assertOk()->assertSee('Jak działają bonusy części')->assertSee('Naciśnij „Policz” na dole');
    });
});
