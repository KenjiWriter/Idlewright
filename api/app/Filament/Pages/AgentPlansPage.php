<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Agent\AgentPlans;
use App\Agent\PlanRefused;
use App\Filament\Concerns\ForAGame;
use App\Games\CurrentGame;
use App\Models\Admin;
use App\Models\AgentPlan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * The plans of the AI assistant (D8 of the design): the list of the plans of the game, and one plan with its goal, the targets in numbers as the server measured them on
 * the live pack and on the plan, the strategy, the changes (the ones outside the levers in red), the gates, the pace of the players, the risks, and the decision. Only a
 * person decides here: "Zatwierdzam plan" (for 24 hours), "Odrzuć" with a reason, or a note for the assistant.
 */
class AgentPlansPage extends Page
{
    use ForAGame;

    /** How many of the last plans the list shows. */
    private const LIST = 50;

    protected static ?string $slug = 'agent-plans';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.agent.plans';

    /** The plan that is open, by its number. */
    #[Url]
    public ?int $plan = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.plans.nav');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    /** The plans that wait for a decision in the game, on the menu. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = AgentPlan::query()->where('game_id', app(CurrentGame::class)->id())->where('status', AgentPlan::SUBMITTED)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.plans.title');
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('admin.plans.approve'))
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('admin.plans.approve_heading'))
            ->modalDescription(__('admin.plans.approve_description', ['hours' => AgentPlans::APPROVAL_HOURS]))
            ->action(fn () => $this->decide(fn (AgentPlans $plans, AgentPlan $plan, Admin $admin) => $plans->approve($plan, $admin), 'approved'));
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('admin.plans.reject'))
            ->color('danger')
            ->schema([Textarea::make('reason')->helpKey('plans.reason')->required()->maxLength(2000)->rows(3)])
            ->action(fn (array $data) => $this->decide(fn (AgentPlans $plans, AgentPlan $plan, Admin $admin) => $plans->reject($plan, $admin, (string) $data['reason']), 'rejected'));
    }

    public function noteAction(): Action
    {
        return Action::make('note')
            ->label(__('admin.plans.note'))
            ->color('gray')
            ->schema([Textarea::make('note')->helpKey('plans.note')->required()->maxLength(2000)->rows(3)])
            ->action(fn (array $data) => $this->decide(fn (AgentPlans $plans, AgentPlan $plan, Admin $admin) => $plans->note($plan, $admin, (string) $data['note']), 'noted'));
    }

    public function open(int $id): void
    {
        $this->plan = $id;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $game = app(CurrentGame::class)->id();
        $open = $this->plan === null ? null : AgentPlan::query()->where('game_id', $game)->with(['admin', 'decider', 'noter'])->find($this->plan);

        return [
            'plans' => AgentPlan::query()->where('game_id', $game)->with('admin')->latest('id')->limit(self::LIST)->get(),
            'open' => $open,
            'status' => $open === null ? null : app(AgentPlans::class)->statusOf($open),
        ];
    }

    /** @param  callable(AgentPlans, AgentPlan, Admin): void  $decision */
    private function decide(callable $decision, string $done): void
    {
        $admin = Auth::guard('admin')->user();
        $plan = $this->plan === null ? null : AgentPlan::query()->where('game_id', app(CurrentGame::class)->id())->find($this->plan);

        if (! $admin instanceof Admin || $plan === null) {
            Notification::make()->danger()->title(__('admin.plans.refused.role'))->send();

            return;
        }

        try {
            $decision(app(AgentPlans::class), $plan, $admin);
        } catch (PlanRefused $refused) {
            Notification::make()->danger()->title(__('admin.plans.refused.'.$refused->reason))->send();

            return;
        }

        Notification::make()->success()->title(__('admin.plans.done.'.$done))->send();
    }
}
