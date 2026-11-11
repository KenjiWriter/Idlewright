<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Agent\AgentStudies;
use App\Agent\AgentVariants;
use App\Agent\ToolRefused;
use App\Content\Catalog;
use App\Content\ContentException;
use App\Content\Progress\ProgressPlan;
use App\Content\Publishing\PackGates;
use App\Content\Rules;
use App\Filament\Concerns\ForAGame;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

/**
 * The progress simulator for people (D13 of the design of the AI bridge v2): one driver from a new account, on the live pack or on the drafts, and when each level is
 * reached, with what, what was bought and where the driver drove. The AI assistant asks the same through its tool `simulate_progress`; this is the page where a person
 * sees it for themselves.
 */
class ProgressPage extends Page implements HasSchemas
{
    use ForAGame;
    use InteractsWithSchemas;

    protected static ?string $slug = 'progress';

    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.progress.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null what the simulator answered last */
    public ?array $result = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.progress.nav');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-chart-bar';
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.progress.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'variant' => AgentVariants::LIVE, 'car' => null, 'style' => 'active', 'every_minutes' => 60, 'visit_minutes' => 10, 'minutes' => 120, 'track' => null,
            'goal' => 'profit', 'buy' => 'none', 'list' => '', 'tutorial' => true, 'step_seconds' => 60, 'missions' => false, 'travel' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        // A game that has no live pack yet has no cars and no tracks to offer: the page still opens, and counting on the drafts is what is left.
        try {
            $cars = array_map(static fn (array $vehicle): string => (string) $vehicle['slug'], app(Catalog::class)->vehicles());
            $tracks = array_keys(app(Rules::class)->tracks());
            $tracks = array_combine($tracks, $tracks);
        } catch (ContentException) {
            [$cars, $tracks] = [[], []];
        }

        return $schema->statePath('data')->components([
            Grid::make(3)->schema([
                Select::make('variant')->helpKey('progress.variant')->options([AgentVariants::LIVE => __('admin.agent.page.variants.live'), AgentVariants::DRAFTS => __('admin.agent.page.variants.drafts')])->required()->selectablePlaceholder(false),
                Select::make('car')->helpKey('progress.car')->options($cars)->searchable(),
                Select::make('style')->helpKey('progress.style')->options(array_combine(ProgressPlan::STYLES, array_map(static fn (string $style): string => __('admin.progress.styles.'.$style), ProgressPlan::STYLES)))->required()->live()->selectablePlaceholder(false),
                TextInput::make('every_minutes')->helpKey('progress.every_minutes')->numeric()->integer()->minValue(1)->visible(fn (Get $get): bool => $get('style') === 'every'),
                TextInput::make('visit_minutes')->helpKey('progress.visit_minutes')->numeric()->integer()->minValue(1)->visible(fn (Get $get): bool => $get('style') === 'every'),
                TextInput::make('minutes')->helpKey('progress.minutes')->numeric()->integer()->minValue(1)->maxValue(ProgressPlan::MAX_MINUTES)->required(),
                Select::make('track')->helpKey('progress.track')->options($tracks)->searchable(),
                Select::make('goal')->helpKey('progress.goal')->options(array_combine(ProgressPlan::GOALS, array_map(static fn (string $goal): string => __('admin.progress.goals.'.$goal), ProgressPlan::GOALS)))->required()->selectablePlaceholder(false),
                Select::make('buy')->helpKey('progress.buy')->options(array_combine(ProgressPlan::BUYING, array_map(static fn (string $buy): string => __('admin.progress.buying.'.$buy), ProgressPlan::BUYING)))->required()->live()->selectablePlaceholder(false),
                TextInput::make('list')->helpKey('progress.list')->maxLength(1000)->visible(fn (Get $get): bool => $get('buy') === 'list')->columnSpan(3),
                TextInput::make('step_seconds')->helpKey('progress.step_seconds')->numeric()->integer()->minValue(1)->maxValue(3600)->required(),
                Toggle::make('tutorial')->helpKey('progress.tutorial'),
                Toggle::make('missions')->helpKey('progress.missions'),
                Toggle::make('travel')->helpKey('progress.travel'),
            ]),
        ]);
    }

    /** Plays the driver of the form on the content chosen, as the assistant's `simulate_progress` does, and keeps the answer for the page. */
    public function simulate(): void
    {
        $data = $this->form->getState();

        try {
            $plan = [...$data, 'list' => (string) ($data['list'] ?? '')];
            ProgressPlan::fromArray($plan);
            ['content' => $content] = app(AgentVariants::class)->content((string) $data['variant']);
            ['output' => $output] = app(PackGates::class)->command($content, 'economy:progress', [...AgentStudies::progressOptions($plan), '--json' => true]);
        } catch (InvalidArgumentException|ToolRefused $problem) {
            Notification::make()->danger()->title(__('admin.progress.failed'))->body($problem->getMessage())->send();

            return;
        }

        $result = json_decode($output, true);

        if (! is_array($result)) {
            Notification::make()->danger()->title(__('admin.progress.failed'))->body(mb_substr($output, 0, 500))->send();

            return;
        }

        $this->result = $result;
    }
}
