<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Content\Definitions\SchemaChoices;
use App\Content\MissionCatalog;
use App\Filament\Resources\Definitions\Forms\DefinitionFields;
use App\Filament\Support\FieldHelp;
use App\Models\ContentDefinition;
use DateTimeZone;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The missions as a form: when they are on and what part of the worth of its missions a set pays (the first tab, with the budget below it), the slots of the daily, weekly and
 * monthly sets with the templates that each slot draws from (the second), the chains of main missions (the third) and what a unit of every kind of work is worth (the fourth).
 * What a set pays is a percent of the worth of the work its missions ask; a goal is a percent of a tank or a number of times, as its objective asks. What the schemas and the
 * checks of the pack say is theirs to say, and they say it when the draft is saved.
 */
final class MissionsFields
{
    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const RARITIES = ['common', 'uncommon', 'rare', 'epic', 'legendary'];

    /**
     * @param  list<mixed>  $budget  what the first tab shows under the numbers of the rewards: the choice of the car and the button that works out what they come to
     * @return list<mixed>
     */
    public static function schema(array $budget): array
    {
        return [
            FieldHelp::guide('missions'),
            Tabs::make('missions')->persistTabInQueryString()->tabs([
                Tab::make(__('admin.missions.rewards'))->schema([...self::settings(), ...$budget]),
                Tab::make(__('admin.missions.sets'))->schema([
                    Tabs::make('sets')->tabs(array_map(
                        static fn (string $period): Tab => Tab::make(__("admin.missions.period.{$period}"))->schema(self::slots($period)),
                        MissionCatalog::PERIODS,
                    )),
                ]),
                Tab::make(__('admin.missions.chains'))->schema(self::chains()),
                Tab::make(__('admin.missions.effort'))->schema(self::effort()),
            ]),
        ];
    }

    /**
     * The car and the track that the budget is worked out for, and the level of the driver: they only steer the preview and are not part of the document.
     *
     * @param  array<string, string>  $cars
     * @param  array<string, string>  $tracks
     * @return list<mixed>
     */
    public static function reference(array $cars, array $tracks): array
    {
        return [
            Grid::make(3)->schema([
                Select::make('budget.car')->helpKey('missions.budget_car')->options($cars)->searchable()->dehydrated(false),
                Select::make('budget.track')->helpKey('missions.budget_track')->options($tracks)->searchable()->dehydrated(false),
                TextInput::make('budget.level')->helpKey('missions.budget_level')->numeric()->integer()->minValue(1)->maxValue(100)->dehydrated(false),
            ]),
        ];
    }

    /** @return list<mixed> */
    private static function settings(): array
    {
        $zones = DateTimeZone::listIdentifiers();
        $periods = [];

        foreach (MissionCatalog::PERIODS as $position => $period) {
            // The three periods have the same boxes: the first one says what they are, the others only carry the label.
            $first = $position === 0;

            $periods[] = Fieldset::make(__("admin.missions.period.{$period}"))->columns(3)->schema([
                FieldHelp::apply(TextInput::make("settings.periods.{$period}.bonus_coins")->numeric()->integer()->minValue(0)->maxValue(SchemaChoices::missionCoinCap($period) ?? 100)->required(), 'missions.bonus_coins', help: $first),
                FieldHelp::apply(TextInput::make("settings.periods.{$period}.cash_percent")->numeric()->minValue(0)->maxValue(1000)->suffix('%')->required(), 'missions.cash_percent', help: $first),
                FieldHelp::apply(TextInput::make("settings.periods.{$period}.xp_percent")->numeric()->minValue(0)->maxValue(1000)->suffix('%')->required(), 'missions.xp_percent', help: $first),
            ]);
        }

        // What the main missions pay is a part of their worth too, and they have one share for all of them.
        $periods[] = Fieldset::make(__('admin.missions.main'))->columns(2)->schema([
            TextInput::make('settings.main.cash_percent')->helpKey('missions.main_cash_percent')->numeric()->minValue(0)->maxValue(1000)->suffix('%')->required(),
            TextInput::make('settings.main.xp_percent')->helpKey('missions.main_xp_percent')->numeric()->minValue(0)->maxValue(1000)->suffix('%')->required(),
        ]);

        return [
            Toggle::make('settings.enabled')->helpKey('missions.enabled')->inline(false),
            Grid::make(3)->schema([
                Select::make('settings.timezone')->helpKey('missions.timezone')->options(array_combine($zones, $zones))->searchable()->required(),
                TextInput::make('settings.reset_hour')->helpKey('missions.reset_hour')->numeric()->integer()->minValue(0)->maxValue(23)->suffix(':00')->required(),
                Select::make('settings.unlock_after_step')->helpKey('missions.unlock_after_step')->options(self::tutorialSteps())->required(),
            ]),
            ...$periods,
        ];
    }

    /** @return list<mixed> */
    private static function slots(string $period): array
    {
        return [
            Repeater::make("sets.{$period}")
                ->helpKey('missions.slots')
                ->hiddenLabel()
                ->minItems(1)
                ->maxItems(12)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.missions.slot_add'))
                ->itemLabel(fn (array $state): ?string => self::slotLabel($state))
                ->schema([
                    TextInput::make('key')->helpKey('missions.slot_key')->required()->maxLength(64)->regex(self::SLUG),
                    Repeater::make('templates')
                        ->helpKey('missions.templates')
                        ->minItems(1)
                        ->collapsible()
                        ->collapsed()
                        ->reorderableWithButtons()
                        ->addActionLabel(__('admin.missions.template_add'))
                        ->itemLabel(fn (array $state): ?string => self::templateLabel($state))
                        ->schema(self::template()),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function template(): array
    {
        $tanks = static fn (Get $get): bool => in_array($get('objective'), MissionCatalog::TANK_OBJECTIVES, true);

        return [
            Grid::make(3)->schema([
                TextInput::make('key')->helpKey('missions.template_key')->required()->maxLength(64)->regex(self::SLUG),
                Select::make('objective')->helpKey('missions.template_objective')->options(FieldHelp::options('missions.template_objective', MissionCatalog::PERIODIC_OBJECTIVES))->required()->live(),
                TextInput::make('tanks_percent')->helpKey('missions.template_tanks')->numeric()->minValue(0.0001)->maxValue(10000)->suffix('%')->required()->visible($tanks),
                TextInput::make('count')->helpKey('missions.template_count')->numeric()->integer()->minValue(1)->maxValue(100000)->required()->visible(fn (Get $get): bool => ! $tanks($get)),
            ]),
            Grid::make(5)->schema([
                TextInput::make('weight')->helpKey('missions.template_weight')->numeric()->integer()->minValue(1)->maxValue(1000)->required(),
                TextInput::make('share')->helpKey('missions.template_share')->numeric()->integer()->minValue(1)->maxValue(1000)->required(),
                TextInput::make('min_level')->helpKey('missions.template_min_level')->numeric()->integer()->minValue(1)->maxValue(100),
                TextInput::make('max_level')->helpKey('missions.template_max_level')->numeric()->integer()->minValue(1)->maxValue(100),
                TextInput::make('car_xp_laps')->helpKey('missions.template_car_xp')->numeric()->integer()->minValue(1)->maxValue(100000),
            ]),
            self::items(),
        ];
    }

    /** @return list<mixed> */
    private static function chains(): array
    {
        return [
            Repeater::make('chains')
                ->helpKey('missions.chains')
                ->hiddenLabel()
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.missions.chain_add'))
                ->itemLabel(fn (array $state): ?string => self::chainLabel($state))
                ->schema([
                    TextInput::make('key')->helpKey('missions.chain_key')->required()->maxLength(64)->regex(self::SLUG),
                    DefinitionFields::texts('title', __('admin.missions.chain_title'), 'missions.chain_title'),
                    Repeater::make('missions')
                        ->helpKey('missions.missions')
                        ->minItems(1)
                        ->collapsible()
                        ->collapsed()
                        ->reorderableWithButtons()
                        ->addActionLabel(__('admin.missions.mission_add'))
                        ->itemLabel(fn (array $state): ?string => self::missionLabel($state))
                        ->schema(self::mission()),
                ]),
        ];
    }

    /** @return list<mixed> what a unit of every kind of work is worth, in tanks of the best car */
    private static function effort(): array
    {
        return [
            Repeater::make('effort')
                ->helpKey('missions.effort')
                ->hiddenLabel()
                ->minItems(1)
                ->addActionLabel(__('admin.missions.effort_add'))
                ->columns(2)
                ->schema([
                    TextInput::make('goal')->helpKey('missions.effort_goal')->required()->maxLength(64)->regex('/^[a-z_]+(:[a-z_]+)?$/'),
                    TextInput::make('tanks')->helpKey('missions.effort_tanks')->numeric()->minValue(0)->maxValue(1000)->suffix(__('admin.missions.tanks'))->required(),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function mission(): array
    {
        $takesParam = static fn (Get $get): bool => in_array($get('objective'), ['rarity_owned', 'tires_mounted'], true);

        return [
            Grid::make(3)->schema([
                TextInput::make('key')->helpKey('missions.mission_key')->required()->maxLength(64)->regex(self::SLUG),
                Select::make('objective')->helpKey('missions.mission_objective')->options(FieldHelp::options('missions.mission_objective', SchemaChoices::missionObjectives()))->required()->live(),
                TextInput::make('count')->helpKey('missions.mission_count')->numeric()->integer()->minValue(1)->maxValue(100000000)->required(),
                Select::make('param')
                    ->helpKey('missions.mission_param')
                    ->options(fn (Get $get): array => self::paramOptions((string) $get('objective')))
                    ->required($takesParam)
                    ->visible($takesParam),
                TextInput::make('min_level')->helpKey('missions.mission_min_level')->numeric()->integer()->minValue(1)->maxValue(100),
            ]),
            DefinitionFields::texts('title', __('admin.missions.mission_title'), 'missions.mission_title'),
            DefinitionFields::texts('text', __('admin.missions.mission_text'), 'missions.mission_text', long: true),
            TextInput::make('car_xp_laps')->helpKey('missions.mission_car_xp')->numeric()->integer()->minValue(1)->maxValue(100000),
            self::items(),
        ];
    }

    /** What a mission gives besides cash and XP: up to four crates and parts. */
    private static function items(): Repeater
    {
        return Repeater::make('items')
            ->helpKey('missions.items')
            ->maxItems(4)
            ->addActionLabel(__('admin.missions.item_add'))
            ->columns(3)
            ->schema([
                Select::make('type')->helpKey('missions.item_type')->options(FieldHelp::options('missions.item_type', ['crate', 'part']))->required()->live(),
                Select::make('kind')->helpKey('missions.item_kind')->options(FieldHelp::options('missions.item_kind', ['slot', 'mixed', 'premium']))->required()->visible(fn (Get $get): bool => $get('type') === 'crate'),
                Select::make('rarity')->helpKey('missions.item_rarity')->options(FieldHelp::options('missions.item_rarity', self::RARITIES))->required()->visible(fn (Get $get): bool => $get('type') === 'part'),
            ]);
    }

    /** @return array<string, string> the words that the parameter of a goal may be, by what the goal counts */
    private static function paramOptions(string $objective): array
    {
        return match ($objective) {
            'rarity_owned' => FieldHelp::options('missions.mission_param', self::RARITIES),
            'tires_mounted' => (static function (): array {
                $tires = array_map('strval', array_keys((array) (self::working('starter')['tire_matrix'] ?? [])));

                return array_combine($tires, $tires);
            })(),
            default => [],
        };
    }

    /** @return array<string, string> the steps of the tutorial as they are being worked on, by key */
    public static function tutorialSteps(): array
    {
        $keys = array_values(array_filter(array_map(static fn (mixed $step): ?string => is_array($step) ? ($step['key'] ?? null) : null, (array) (self::working('tutorial')['steps'] ?? []))));

        return $keys === [] ? [] : array_combine($keys, $keys);
    }

    /** @return array<string, mixed> a document of the rules as it is being worked on: the draft, or what was published */
    private static function working(string $slug): array
    {
        $definition = ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', $slug)->with('latestVersion')->first();

        return $definition?->workingDocument() ?? [];
    }

    /** @param  array<string, mixed>  $state */
    private static function slotLabel(array $state): ?string
    {
        $key = (string) ($state['key'] ?? '');

        return $key === '' ? null : $key.' ('.count((array) ($state['templates'] ?? [])).')';
    }

    /** @param  array<string, mixed>  $state */
    private static function templateLabel(array $state): ?string
    {
        $key = (string) ($state['key'] ?? '');

        return $key === '' ? null : trim($key.' · '.(string) ($state['objective'] ?? ''), ' ·');
    }

    /** @param  array<string, mixed>  $state */
    private static function chainLabel(array $state): ?string
    {
        $title = (string) ($state['title']['pl'] ?? $state['title']['en'] ?? '');
        $key = (string) ($state['key'] ?? '');

        return $title === '' ? ($key === '' ? null : $key) : "{$title} ({$key}, ".count((array) ($state['missions'] ?? [])).')';
    }

    /** @param  array<string, mixed>  $state */
    private static function missionLabel(array $state): ?string
    {
        $title = (string) ($state['title']['pl'] ?? $state['title']['en'] ?? '');
        $key = (string) ($state['key'] ?? '');

        return $title === '' ? ($key === '' ? null : $key) : "{$title} ({$key})";
    }
}
