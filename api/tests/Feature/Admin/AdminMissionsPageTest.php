<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Content\Definitions\MissionsForm;
use App\Filament\Pages\MissionsPage;
use App\Models\Admin;
use App\Models\ContentDefinition;
use App\Models\User;
use App\Models\UserMission;
use App\Services\MissionBudget;
use Livewire\Livewire;

// The page of the missions: the numbers of the rules (when the missions are on, what part of the worth of its missions a set pays) and the document of the missions (what a unit of
// work is worth, the slots, the templates, the chains), kept together as the drafts of the two documents or not at all; and what the sets come to, which is what
// `missions:budget` says.

/** @return array<string, mixed> */
function missionsRulesDocument(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));
}

/** @return array<string, mixed> */
function missionsDocument(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/missions'));
}

function missionsDefinition(string $slug): ContentDefinition
{
    return ContentDefinition::query()->where('kind', 'rules')->where('slug', $slug)->firstOrFail();
}

/** The state of the form as the page shows it, changed by a function. @return array<string, mixed> */
function missionsState(?Closure $change = null): array
{
    $state = MissionsForm::toState(missionsDocument(), missionsRulesDocument());

    if ($change !== null) {
        $state = $change($state) ?? $state;
    }

    return $state;
}

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false);
    app()->setLocale('pl');
});

describe('the form of the missions', function () {
    it('shows what the sets pay as percents of the worth, the worth of work in tanks, and the goals as percents of a tank or counts', function () {
        $state = missionsState();
        $laps = collect($state['sets']['daily'][0]['templates'])->firstWhere('key', 'daily-laps');
        $mount = collect($state['sets']['daily'][3]['templates'])->firstWhere('key', 'daily-mount');

        expect($state['settings']['periods']['daily'])->toBe(['bonus_coins' => 2, 'cash_percent' => 10, 'xp_percent' => 6])
            ->and($state['settings']['periods']['monthly'])->toBe(['bonus_coins' => 25, 'cash_percent' => 10, 'xp_percent' => 6])
            ->and($state['settings']['main'])->toBe(['cash_percent' => 15, 'xp_percent' => 9])
            ->and(collect($state['effort'])->firstWhere('goal', 'upgrade'))->toBe(['goal' => 'upgrade', 'tanks' => 0.6])
            ->and(collect($state['effort'])->firstWhere('goal', 'rarity_owned:epic'))->toBe(['goal' => 'rarity_owned:epic', 'tanks' => 4])
            ->and($state['settings'])->toMatchArray(['enabled' => true, 'timezone' => 'Europe/Warsaw', 'reset_hour' => 4, 'unlock_after_step' => 'travel'])
            ->and($laps)->toMatchArray(['objective' => 'laps', 'tanks_percent' => 60, 'count' => null, 'weight' => 3, 'share' => 1])
            ->and($mount)->toMatchArray(['objective' => 'mount', 'tanks_percent' => null, 'count' => 1])
            ->and(array_column($state['chains'], 'key'))->toBe(['driver', 'mechanic', 'collector'])
            ->and($state['chains'][0]['missions'][0])->toMatchArray(['key' => 'driver-laps-50', 'objective' => 'laps', 'count' => 50, 'car_xp_laps' => null, 'items' => []]);
    });

    it('is the two documents again when nothing was changed', function () {
        [$missions, $rules] = MissionsForm::apply(missionsDocument(), missionsRulesDocument(), MissionsForm::toState(missionsDocument(), missionsRulesDocument()));

        expect($missions)->toEqual(missionsDocument())->and($rules)->toEqual(missionsRulesDocument());
    });

    it('turns the percents and the tanks back into parts per million, and keeps for a goal only what its objective asks for', function () {
        [$missions, $rules] = MissionsForm::apply(missionsDocument(), missionsRulesDocument(), missionsState(function (array $state): array {
            $state['settings']['periods']['weekly']['cash_percent'] = 17.5;
            $state['settings']['main']['xp_percent'] = 12.5;
            $state['effort'][array_search('laps', array_column($state['effort'], 'goal'), true)]['tanks'] = 1.5;
            // A goal that was a part of a tank becomes a number of times: the part of the tank goes.
            $state['sets']['daily'][0]['templates'][0]['objective'] = 'mount';
            $state['sets']['daily'][0]['templates'][0]['count'] = 3;

            return $state;
        }));

        $template = $missions['periodic']['daily'][0]['templates']['daily-laps'];

        expect($rules['missions']['periods']['weekly']['cash_ppm'])->toBe(175_000)
            ->and($rules['missions']['main']['xp_ppm'])->toBe(125_000)
            ->and($missions['effort']['laps'])->toBe(1_500_000)
            ->and($template)->toMatchArray(['objective' => 'mount', 'count' => 3])
            ->and($template)->not->toHaveKey('tanks_ppm');
    });

    it('keeps a main mission that gives nothing besides its worth with an empty object for a reward, which is what the schema asks for', function () {
        [$missions] = MissionsForm::apply(missionsDocument(), missionsRulesDocument(), MissionsForm::toState(missionsDocument(), missionsRulesDocument()));

        expect($missions['chains'][0]['missions'][0]['reward'])->toBeInstanceOf(stdClass::class)
            ->and(JsonDocument::encode($missions))->toContain('"reward": {}');
    });

    it('writes the items of a mission as the crate with its kind or the part with its rarity', function () {
        [$missions] = MissionsForm::apply(missionsDocument(), missionsRulesDocument(), missionsState(function (array $state): array {
            $state['sets']['weekly'][0]['templates'][0]['items'] = [
                ['type' => 'crate', 'kind' => 'premium', 'rarity' => 'epic'],
                ['type' => 'part', 'kind' => 'slot', 'rarity' => 'rare'],
                ['type' => null, 'kind' => null, 'rarity' => null],
            ];

            return $state;
        }));

        expect($missions['periodic']['weekly'][0]['templates']['weekly-laps']['items'])->toBe([['type' => 'crate', 'kind' => 'premium'], ['type' => 'part', 'rarity' => 'rare']]);
    });

    it('leaves a game that has no missions without them while they are off, and gives them the starting numbers when they are switched on', function () {
        $rules = missionsRulesDocument();
        unset($rules['missions']);
        $state = MissionsForm::toState([], $rules);

        expect($state['settings']['enabled'])->toBeFalse();

        [$missions, $same] = MissionsForm::apply([], $rules, $state);

        expect($same)->toBe($rules)
            ->and($missions['periodic'])->toBe(['daily' => [], 'weekly' => [], 'monthly' => []])
            ->and($missions)->not->toHaveKey('chains');

        $state['settings']['enabled'] = true;
        [, $on] = MissionsForm::apply([], $rules, $state);

        expect($on['missions'])->toBe(['enabled' => true, 'timezone' => 'Europe/Warsaw', 'reset_hour' => 4, 'unlock_after_step' => 'travel', 'periods' => [
            'daily' => ['bonus_coins' => 2, 'cash_ppm' => 100_000, 'xp_ppm' => 60_000],
            'weekly' => ['bonus_coins' => 8, 'cash_ppm' => 100_000, 'xp_ppm' => 60_000],
            'monthly' => ['bonus_coins' => 25, 'cash_ppm' => 100_000, 'xp_ppm' => 60_000],
        ], 'main' => ['cash_ppm' => 150_000, 'xp_ppm' => 90_000]]);
    });
});

describe('the page', function () {
    it('is for any admin to open, starts from what is published, and saving it as it is keeps no draft', function () {
        signedInAdmin(Admin::ROLE_EDITOR);

        $this->get('/admin/missions')->assertOk()->assertSee(__('admin.missions.title'));

        $page = Livewire::test(MissionsPage::class);

        expect($page->get('data.settings.periods.daily.bonus_coins'))->toBe(2)
            ->and($page->get('data.settings.main.cash_percent'))->toEqual(15)
            ->and(array_column(array_values($page->get('data.chains')), 'key'))->toBe(['driver', 'mechanic', 'collector'])
            ->and(count($page->get('data.sets.daily')))->toBe(6)
            ->and($page->get('data.budget'))->toBe(['car' => 'rdzak-gt-4', 'track' => 'zlomowo-street', 'level' => 1]);

        $page->call('save')->assertHasNoFormErrors();

        expect(missionsDefinition('missions')->hasDraft())->toBeFalse()
            ->and(missionsDefinition('starter')->hasDraft())->toBeFalse();
    });

    it('keeps the draft of the rules for a number of the rules and leaves the document of the missions alone', function () {
        $admin = signedInAdmin();

        Livewire::test(MissionsPage::class)
            ->set('data.settings.periods.daily.bonus_coins', 3)
            ->set('data.settings.periods.daily.cash_percent', 7)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.definitions.saved'));

        $rules = missionsRulesDocument();
        $rules['missions']['periods']['daily']['bonus_coins'] = 3;
        $rules['missions']['periods']['daily']['cash_ppm'] = 70_000;

        expect(missionsDefinition('starter')->draftDocument())->toEqual($rules)
            ->and(missionsDefinition('starter')->draft_admin_id)->toBe($admin->id)
            ->and(missionsDefinition('missions')->hasDraft())->toBeFalse();
    });

    it('keeps both drafts when a number of the rules and a mission are changed together', function () {
        signedInAdmin();

        $page = Livewire::test(MissionsPage::class);
        $daily = array_key_first($page->get('data.sets.daily'));
        $driver = array_key_first($page->get('data.chains'));
        $laps = array_key_first($page->get("data.chains.{$driver}.missions"));
        $template = array_key_first($page->get("data.sets.daily.{$daily}.templates"));

        $page
            ->set('data.settings.reset_hour', 5)
            ->set("data.sets.daily.{$daily}.templates.{$template}.tanks_percent", 70)
            ->set("data.chains.{$driver}.missions.{$laps}.count", 60)
            ->call('save')
            ->assertHasNoFormErrors();

        $missions = missionsDefinition('missions')->draftDocument();

        expect(missionsDefinition('starter')->draftDocument()['missions']['reset_hour'])->toBe(5)
            ->and($missions['periodic']['daily'][0]['templates']['daily-laps']['tanks_ppm'])->toBe(700_000)
            ->and($missions['chains'][0]['missions'][0]['count'])->toBe(60);
    });

    it('keeps neither when one of them is refused: a main mission that players have cannot be taken out', function () {
        signedInAdmin();
        $driver = User::factory()->guest()->create();
        UserMission::query()->create(['user_id' => $driver->id, 'kind' => 'main', 'period_key' => 'driver', 'slot' => 1, 'mission_key' => 'driver-laps-50', 'objective' => 'laps', 'target' => 50, 'progress' => 3, 'reward' => ['cash_cents' => 100]]);

        $page = Livewire::test(MissionsPage::class);
        $chain = array_key_first($page->get('data.chains'));
        $first = array_key_first($page->get("data.chains.{$chain}.missions"));
        $missions = $page->get("data.chains.{$chain}.missions");
        unset($missions[$first]);

        $page
            ->set('data.settings.reset_hour', 5)
            ->set("data.chains.{$chain}.missions", $missions)
            ->call('save')
            ->assertNotified();

        expect(missionsDefinition('missions')->hasDraft())->toBeFalse()
            ->and(missionsDefinition('starter')->hasDraft())->toBeFalse();
    });

    it('does not keep sets that pay more coins than the schema lets them', function () {
        signedInAdmin();

        Livewire::test(MissionsPage::class)
            ->set('data.settings.periods.daily.bonus_coins', 6)
            ->call('save')
            ->assertHasFormErrors(['settings.periods.daily.bonus_coins']);

        expect(missionsDefinition('starter')->hasDraft())->toBeFalse();
    });

    it('does not keep a slot that leaves a level without a mission', function () {
        signedInAdmin();

        $page = Livewire::test(MissionsPage::class);
        $slot = array_keys($page->get('data.sets.daily'))[3];
        $templates = $page->get("data.sets.daily.{$slot}.templates");

        // The only template of the slot opens at level 5: levels 1 to 4 would have no mission.
        $page
            ->set("data.sets.daily.{$slot}.templates", [array_merge(array_values($templates)[0], ['min_level' => 5])])
            ->call('save')
            ->assertNotified(__('admin.definitions.invalid'));

        expect(missionsDefinition('missions')->hasDraft())->toBeFalse();
    });

    it('offers the steps of the tutorial for the one that unlocks the missions', function () {
        signedInAdmin();

        $steps = Livewire::test(MissionsPage::class)->instance()->form->getFlatFields(withHidden: true)['settings.unlock_after_step']->getOptions();

        expect(array_keys($steps))->toContain('travel')->and(count($steps))->toBeGreaterThanOrEqual(10);
    });
});

describe('the budget', function () {
    it('is worked out for the form as it is, with the numbers of missions:budget, and says that the gate lets them through', function () {
        signedInAdmin();
        $report = app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street', 12);

        $page = Livewire::test(MissionsPage::class)->set('data.budget.level', 12)->call('calculateBudget');
        $budget = $page->get('budget');

        expect($budget['month'])->toMatchArray(['cash_percent' => 73, 'xp_percent' => 44])
            ->and($budget['month']['cash_cents'])->toBe($report['month']['cash_cents'])
            ->and($budget['periods']['daily'])->toMatchArray(['coins' => 2, 'cash_cents' => $report['periods']['daily']['cash_cents'], 'xp' => $report['periods']['daily']['xp']])
            ->and($budget['main']['cash_cents'])->toBe($report['main']['cash_cents'])
            ->and(array_column($budget['checks'], 'ok'))->toBe([true, true]);

        $page->assertSeeHtml('data-test="budget-verdict"')->assertSee(__('admin.missions.budget.gate_passes'));
    });

    it('is worked out for what was typed and not yet saved, and says that the gate would stop it', function () {
        signedInAdmin();

        $page = Livewire::test(MissionsPage::class)
            ->set('data.budget.level', 12)
            ->set('data.settings.periods.daily.cash_percent', 100)
            ->call('calculateBudget');

        $cash = collect($page->get('budget')['checks'])->firstWhere('name', 'cash_share');

        expect($cash['ok'])->toBeFalse()
            ->and($page->get('budget')['periods']['daily']['cash_ppm'])->toBe(1_000_000)
            ->and(missionsDefinition('starter')->hasDraft())->toBeFalse();

        $page->assertSee(__('admin.missions.budget.gate_stops'));
    });

    it('has the shares and no amounts for a car that cannot drive the track', function () {
        signedInAdmin();

        $page = Livewire::test(MissionsPage::class)
            ->set('data.budget.car', 'no-such-car')
            ->set('data.budget.level', 12)
            ->call('calculateBudget');

        expect($page->get('budget')['tank'])->toBeNull()->and($page->get('budget')['month']['cash_share'])->toBeBetween(0.15, 1.0);

        $page->assertSeeHtml('data-test="budget-no-tank"');
    });
});
