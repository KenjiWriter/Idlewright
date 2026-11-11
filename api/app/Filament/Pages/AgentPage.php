<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Agent\AgentGrants;
use App\Filament\Concerns\ForAGame;
use App\Games\CurrentGame;
use App\Models\Admin;
use App\Models\AgentAction;
use App\Models\AgentGrant;
use App\Models\AgentJob;
use App\Models\Game;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * The AI assistant of the panel (most AI v2): how to connect an app of Claude to the panel, the message to start a conversation with, the consents in force in this game
 * with "Rozłącz", and what the assistant did lately. The consent itself is given on the page of OAuth that Claude opens; this page never shows a key.
 */
class AgentPage extends Page implements HasSchemas
{
    use ForAGame;
    use InteractsWithSchemas;

    /** How many of the last calls of the assistant the page shows. */
    private const TRAIL = 30;

    /** How many of the last studies the assistant had counted the page shows. */
    private const STUDIES = 20;

    protected static ?string $slug = 'agent';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.agent.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.agent.nav');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-cpu-chip';
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.agent.title');
    }

    public function mount(): void
    {
        $this->form->fill(['goal' => '']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Textarea::make('goal')->helpKey('agent.goal')->rows(3)->maxLength(2000),
        ]);
    }

    /** The message to start a conversation with: plain text, no key in it, the goal of the person at the end. */
    public function starter(): string
    {
        $goal = trim((string) ($this->data['goal'] ?? ''));

        return __('admin.agent.page.starter_text', ['game' => (string) Game::query()->find(app(CurrentGame::class)->id())?->name])
            .($goal === '' ? '' : "\n".__('admin.agent.page.starter_goal', ['goal' => $goal]));
    }

    public function disconnect(int $id): void
    {
        $admin = Auth::guard('admin')->user();
        $grant = AgentGrant::query()->where('game_id', app(CurrentGame::class)->id())->find($id);

        if (! $admin instanceof Admin || $grant === null || ($grant->admin_id !== $admin->id && ! $admin->isPublisher())) {
            Notification::make()->danger()->title(__('admin.agent.page.not_yours'))->send();

            return;
        }

        app(AgentGrants::class)->revoke($grant, $admin);
        Notification::make()->success()->title(__('admin.agent.page.disconnected'))->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $game = app(CurrentGame::class)->id();

        return [
            'enabled' => (bool) config('ride.agent.enabled') && Route::has('agent.mcp'),
            'address' => Route::has('agent.mcp') ? route('agent.mcp') : null,
            'grants' => AgentGrant::query()->where('game_id', $game)->active()->with('admin')->withCount('actions')->latest('id')->get(),
            'trail' => AgentAction::query()->where('game_id', $game)->latest('id')->limit(self::TRAIL)->get(),
            'studies' => AgentJob::query()->where('game_id', $game)->latest('id')->limit(self::STUDIES)->get(),
        ];
    }
}
