<?php

declare(strict_types=1);

use App\Agent\AgentContext;
use App\Filament\Pages\AgentPlansPage;
use App\Models\AgentPlan;
use App\Models\Game;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// The page "Plany asystenta" (most AI v2, V3): the plans of the game, one plan with its targets, changes (the ones outside the levers marked), gates and pace, and the
// three decisions of a person: approve, reject with a reason, a note for the assistant.

require_once __DIR__.'/../../Support/AgentHelpers.php';

beforeEach(function () {
    contentPacks()->import(repositoryContent(), activate: true, checkTables: false);
    app()->setLocale('pl');
    Queue::fake();
});

/** A plan of the game, measured, with one change outside its levers. */
function planOnThePage(): AgentPlan
{
    $grant = agentGrantInForce(2);
    $fuel = ['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/fuel/price_cents_per_liter', 'percent' => 10];
    $plan = agentPlanSubmitted($grant, [dearerLevelFive(), $fuel]);
    $plan->forceFill(['measure_status' => AgentPlan::READY, 'measured_at' => now(), 'measured' => [
        'targets' => [['type' => 'level_at_minute', 'target' => 'level 5 at minute 75 (±15%)', 'live' => 'minute 50', 'live_ok' => false, 'plan' => 'minute 78', 'plan_ok' => true]],
        'met' => true, 'gates' => [['name' => 'tracks:simulate --strict', 'passed' => true, 'excerpt' => '']], 'gates_passed' => true,
        'pace' => [['player' => 'A', 'car' => 'L1', 'target_day' => 2, 'live_day' => 2, 'plan_day' => 2, 'plan_ok' => true]],
    ]])->save();
    app(AgentContext::class)->set(null);

    return $plan;
}

it('lists the plans of the game and opens one with its targets, its changes and what is outside its levers', function () {
    $plan = planOnThePage();

    $this->get('/admin/agent-plans')->assertOk()->assertSee(__('admin.plans.title'))->assertSee('Poziom 5 wolniej')->assertSee(__('admin.plans.states.submitted'));

    $this->get('/admin/agent-plans?plan='.$plan->id)->assertOk()
        ->assertSee('Poziom 5 ma przyjsc pozniej.')
        ->assertSee('level 5 at minute 75')
        ->assertSee('minute 78')
        ->assertSee('/player/early_xp/3')
        ->assertSee('data-test="agent-plan-outside"', false)
        ->assertSee('rules:starter /fuel/price_cents_per_liter')
        ->assertSee(__('admin.plans.approve'));
});

it('approves, rejects and leaves a note from the page', function () {
    $plan = planOnThePage();

    Livewire::withQueryParams(['plan' => $plan->id])->test(AgentPlansPage::class)
        ->callAction('note', data: ['note' => 'Policz gracza B.'])
        ->assertNotified(__('admin.plans.done.noted'))
        ->callAction('approve')
        ->assertNotified(__('admin.plans.done.approved'));

    expect($plan->refresh()->status)->toBe(AgentPlan::APPROVED)->and($plan->note)->toBe('Policz gracza B.');

    Livewire::withQueryParams(['plan' => $plan->id])->test(AgentPlansPage::class)
        ->callAction('reject', data: ['reason' => 'Jednak nie.'])
        ->assertNotified(__('admin.plans.done.rejected'));

    expect($plan->refresh()->status)->toBe(AgentPlan::REJECTED)->and($plan->reject_reason)->toBe('Jednak nie.');
});

it('says why a plan cannot be approved, and shows no plan of another game', function () {
    $plan = planOnThePage();
    $plan->forceFill(['measure_status' => AgentPlan::MEASURING])->save();

    Livewire::withQueryParams(['plan' => $plan->id])->test(AgentPlansPage::class)
        ->callAction('approve')
        ->assertNotified(__('admin.plans.refused.measuring'));

    expect($plan->refresh()->status)->toBe(AgentPlan::SUBMITTED);

    $other = Game::query()->create(['slug' => 'statki', 'name' => 'Statki', 'domain' => 'statki.example.test', 'locale' => 'pl', 'theme_color' => '#0b3a5b', 'status' => Game::STATUS_LIVE, 'is_default' => false]);
    $plan->forceFill(['game_id' => $other->id])->save();

    $this->get('/admin/agent-plans?plan='.$plan->id)->assertOk()->assertDontSee('Poziom 5 ma przyjsc pozniej.');
});
