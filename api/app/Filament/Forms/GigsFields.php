<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Content\Definitions\GigsForm;
use App\Content\Definitions\SchemaChoices;
use App\Filament\Resources\Definitions\Forms\DefinitionFields;
use App\Filament\Support\FieldHelp;
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
 * The gigs as a form: the board (when it opens and how long it stays, how many offers each phase of the day has, how rare the good offers are), the pay (the speed, the anchors of
 * the ladder and the bands of the levels), the kinds, and the templates that the offers are drawn from. A part is a percent, a wait is minutes or seconds and what an hour pays is
 * dollars. What the schema and the checks of the pack say is theirs to say, and they say it when the draft is saved; under the form a button works out what the pay comes to for every
 * car of the pack.
 */
final class GigsFields
{
    private const SLUG = '/^[a-z][a-z0-9-]*$/';

    /**
     * @param  list<mixed>  $below  what the page shows under the tabs: the button that measures the pay and the answer of it
     * @return list<mixed>
     */
    public static function schema(array $below): array
    {
        return [
            FieldHelp::guide('gigs'),
            Tabs::make('gigs')->persistTabInQueryString()->tabs([
                Tab::make(__('admin.gigs.board'))->schema(self::board()),
                Tab::make(__('admin.gigs.pay'))->schema(self::pay()),
                Tab::make(__('admin.gigs.kinds'))->schema(self::kinds()),
                Tab::make(__('admin.gigs.templates'))->schema(self::templates()),
            ]),
            ...$below,
        ];
    }

    /** @return list<mixed> */
    private static function board(): array
    {
        $zones = DateTimeZone::listIdentifiers();

        return [
            Toggle::make('settings.enabled')->helpKey('gigs.enabled')->inline(false),
            Grid::make(3)->schema([
                Select::make('settings.unlock_after_step')->helpKey('gigs.unlock_after_step')->options(MissionsFields::tutorialSteps())->required(),
                Select::make('settings.timezone')->helpKey('gigs.timezone')->options(array_combine($zones, $zones))->searchable()->required(),
                TextInput::make('settings.reset_hour')->helpKey('gigs.reset_hour')->numeric()->integer()->minValue(0)->maxValue(23)->suffix(':00')->required(),
            ]),
            Grid::make(2)->schema([
                TextInput::make('settings.wave_minutes')->helpKey('gigs.wave_minutes')->numeric()->minValue(1)->maxValue(60)->suffix('min')->required(),
                TextInput::make('settings.cancel_seconds')->helpKey('gigs.cancel_seconds')->numeric()->minValue(0)->maxValue(600)->suffix('s')->required(),
            ]),
            // The four phases have the same boxes: the first one says what they are, the others only carry the label.
            Fieldset::make(__('admin.gigs.slots'))->columns(4)->schema(array_map(
                static fn (string $phase): mixed => FieldHelp::apply(
                    TextInput::make("settings.slots.{$phase}")->label(__("admin.gigs.phase.{$phase}"))->numeric()->integer()->minValue(0)->maxValue(12)->required(),
                    'gigs.slots',
                    label: false,
                    help: $phase === GigsForm::PHASES[0],
                ),
                GigsForm::PHASES,
            )),
            Repeater::make('rarities')
                ->helpKey('gigs.rarities')
                ->minItems(1)
                ->maxItems(5)
                ->collapsible()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.gigs.rarity_add'))
                ->itemLabel(fn (array $state): ?string => self::label($state['key'] ?? null, $state['pay_percent'] ?? null, '%'))
                ->columns(3)
                ->schema([
                    TextInput::make('key')->helpKey('gigs.rarity_key')->required()->maxLength(32)->regex('/^[a-z]+$/')->distinct(),
                    TextInput::make('weight')->helpKey('gigs.rarity_weight')->numeric()->integer()->minValue(0)->maxValue(1000)->required(),
                    TextInput::make('pay_percent')->helpKey('gigs.rarity_pay')->numeric()->minValue(10)->maxValue(500)->suffix('%')->required(),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function pay(): array
    {
        return [
            Grid::make(4)->schema([
                TextInput::make('speed.ref_speed_kmh')->helpKey('gigs.ref_speed_kmh')->numeric()->integer()->minValue(10)->maxValue(300)->suffix('km/h')->required(),
                TextInput::make('speed.speed_cap_kmh')->helpKey('gigs.speed_cap_kmh')->numeric()->integer()->minValue(10)->maxValue(300)->suffix('km/h')->required(),
                TextInput::make('speed.xp_per_hour')->helpKey('gigs.xp_per_hour')->numeric()->integer()->minValue(0)->maxValue(3600)->suffix('XP')->required(),
                TextInput::make('speed.car_xp_per_hour')->helpKey('gigs.car_xp_per_hour')->numeric()->integer()->minValue(0)->maxValue(3600)->suffix('XP')->required(),
            ]),
            Repeater::make('anchors')
                ->helpKey('gigs.anchors')
                ->minItems(1)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.gigs.anchor_add'))
                ->itemLabel(fn (array $state): ?string => self::label(isset($state['from_level']) ? __('admin.gigs.from_level', ['level' => $state['from_level']]) : null, $state['dollars'] ?? null, '$/h'))
                ->columns(2)
                ->schema([
                    TextInput::make('from_level')->helpKey('gigs.anchor_level')->numeric()->integer()->minValue(1)->maxValue(100)->required(),
                    TextInput::make('dollars')->helpKey('gigs.anchor_dollars')->numeric()->minValue(0.01)->maxValue(1_000_000)->prefix('$')->suffix('/h')->required(),
                ]),
            Repeater::make('bands')
                ->helpKey('gigs.bands')
                ->minItems(1)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.gigs.band_add'))
                ->itemLabel(fn (array $state): ?string => isset($state['from_level']) ? __('admin.gigs.from_level', ['level' => $state['from_level']]) : null)
                ->columns(4)
                ->schema([
                    TextInput::make('from_level')->helpKey('gigs.band_level')->numeric()->integer()->minValue(1)->maxValue(100)->required(),
                    TextInput::make('ratio_percent')->helpKey('gigs.band_ratio')->numeric()->minValue(10)->maxValue(200)->suffix('%')->required(),
                    TextInput::make('max_ref_minutes')->helpKey('gigs.band_max_minutes')->numeric()->integer()->minValue(1)->maxValue(1440)->suffix('min')->required(),
                    TextInput::make('daily_limit')->helpKey('gigs.band_daily_limit')->numeric()->integer()->minValue(1)->maxValue(200)->required(),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function kinds(): array
    {
        return [
            Repeater::make('kinds')
                ->helpKey('gigs.kinds')
                ->minItems(1)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.gigs.kind_add'))
                ->itemLabel(fn (array $state): ?string => self::label($state['key'] ?? null, $state['name']['pl'] ?? $state['name']['en'] ?? null))
                ->schema([
                    TextInput::make('key')->helpKey('gigs.kind_key')->required()->maxLength(64)->regex(self::SLUG)->distinct()->live(onBlur: true),
                    DefinitionFields::texts('name', __('admin.definitions.name'), 'gigs.kind_name'),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function templates(): array
    {
        return [
            Repeater::make('templates')
                ->helpKey('gigs.templates')
                ->minItems(1)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.gigs.template_add'))
                ->itemLabel(fn (array $state): ?string => self::label($state['key'] ?? null, $state['kind'] ?? null))
                ->schema(self::template()),
        ];
    }

    /** @return list<mixed> */
    private static function template(): array
    {
        $amount = static fn (Get $get): string => $get('capacity') === 'seats' ? __('admin.gigs.people') : 'kg';

        return [
            Grid::make(3)->schema([
                TextInput::make('key')->helpKey('gigs.template_key')->required()->maxLength(64)->regex(self::SLUG)->distinct(),
                Select::make('kind')->helpKey('gigs.template_kind')->options(fn (Get $get): array => self::kindOptions((array) $get('../../kinds')))->required(),
                TextInput::make('min_level')->helpKey('gigs.template_min_level')->numeric()->integer()->minValue(1)->maxValue(100)->required(),
            ]),
            DefinitionFields::texts('title', __('admin.definitions.name'), 'gigs.template_title'),
            Grid::make(5)->schema([
                Select::make('capacity')->helpKey('gigs.template_capacity')->options(FieldHelp::options('gigs.template_capacity', ['seats', 'cargo_kg']))->required()->live(),
                TextInput::make('amount_min')->helpKey('gigs.template_amount_min')->numeric()->integer()->minValue(1)->suffix($amount)->required(),
                TextInput::make('amount_max')->helpKey('gigs.template_amount_max')->numeric()->integer()->minValue(1)->suffix($amount)->required(),
                Select::make('mode')->helpKey('gigs.template_mode')->options(FieldHelp::options('gigs.template_mode', ['local', 'intercity']))->required()->live(),
                TextInput::make('window_waves')->helpKey('gigs.template_window')->numeric()->integer()->minValue(1)->maxValue(48)->required(),
            ]),
            Grid::make(2)->visible(fn (Get $get): bool => $get('mode') === 'local')->schema([
                TextInput::make('local_min')->helpKey('gigs.template_local_min')->numeric()->integer()->minValue(1)->suffix('km')->required(),
                TextInput::make('local_max')->helpKey('gigs.template_local_max')->numeric()->integer()->minValue(1)->suffix('km')->required(),
            ]),
            Select::make('class_in')->helpKey('gigs.template_class_in')->options(FieldHelp::options('gigs.template_class_in', SchemaChoices::vehicleClasses()))->multiple(),
            Grid::make(2)->schema([
                TextInput::make('pay_percent')->helpKey('gigs.template_pay')->numeric()->minValue(10)->maxValue(500)->suffix('%')->required(),
                TextInput::make('deadline_percent')->helpKey('gigs.template_deadline')->numeric()->minValue(50)->maxValue(500)->suffix('%'),
            ]),
            Fieldset::make(__('admin.gigs.phase_pay'))->columns(4)->schema(self::perPhase('phase_pay', 'gigs.template_phase_pay', 10, 500, '%', percent: true)),
            Fieldset::make(__('admin.gigs.phase_weights'))->columns(4)->schema(self::perPhase('phases', 'gigs.template_phases', 0, 1000, null, percent: false)),
        ];
    }

    /**
     * The four boxes of a template that are asked once for each phase of the day: the first one says what they are, the others only carry the label.
     *
     * @return list<mixed>
     */
    private static function perPhase(string $path, string $key, int $min, int $max, ?string $suffix, bool $percent): array
    {
        return array_map(static function (string $phase) use ($path, $key, $min, $max, $suffix, $percent): mixed {
            $box = TextInput::make("{$path}.{$phase}")->label(__("admin.gigs.phase.{$phase}"))->numeric()->minValue($min)->maxValue($max)->required();

            if (! $percent) {
                $box->integer();
            }

            if ($suffix !== null) {
                $box->suffix($suffix);
            }

            return FieldHelp::apply($box, $key, label: false, help: $phase === GigsForm::PHASES[0]);
        }, GigsForm::PHASES);
    }

    /**
     * The kinds of the form as they are now, by key, with their name: what a template can be of.
     *
     * @param  array<int|string, mixed>  $kinds  the state of the kinds
     * @return array<string, string>
     */
    private static function kindOptions(array $kinds): array
    {
        $options = [];

        foreach ($kinds as $kind) {
            $key = is_array($kind) && is_string($kind['key'] ?? null) ? trim($kind['key']) : '';

            if ($key !== '') {
                $options[$key] = ContentOptions::name($kind['name'] ?? [], $key)." ({$key})";
            }
        }

        return $options;
    }

    /** The title of a line of a repeater: its name, and what is worth seeing of it beside the name. */
    private static function label(mixed $name, mixed $detail, string $unit = ''): ?string
    {
        $name = is_scalar($name) ? trim((string) $name) : '';
        $detail = is_scalar($detail) && trim((string) $detail) !== '' ? trim((string) $detail) : null;

        if ($name === '') {
            return null;
        }

        return $detail === null ? $name : "{$name} ({$detail}".($unit === '' ? '' : " {$unit}").')';
    }
}
