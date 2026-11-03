<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Catalog;
use App\Content\Definitions\ThreadCannotClose;
use App\Content\Definitions\ThreadCloser;
use App\Content\Definitions\ThreadForm;
use App\Content\ThreadPreview;
use App\Filament\Forms\ContentOptions;
use App\Filament\Support\FieldHelp;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The thread of a track: its sections in order (straights and corners, each with a surface, a speed limit and a roughness), the line they draw seen from above,
 * and how long the reference car takes on it and the cars of the catalog take on it and by the formula of the demands. One track at a time; what is saved is the
 * draft of the rules of the world, like the page of the world.
 */
class ThreadPage extends RulesDocumentPage
{
    protected static ?string $slug = 'thread';

    protected static ?int $navigationSort = 11;

    /** The track whose thread the form shows, so that the form starts from it again after a save. */
    public ?string $trackSlug = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.thread.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.thread.title');
    }

    protected function documentSlug(): string
    {
        return 'starter';
    }

    public function save(): void
    {
        $this->trackSlug = is_string($this->data['track'] ?? null) ? $this->data['track'] : $this->trackSlug;

        parent::save();
    }

    /** Shows the thread of another track in the form. What was changed in the track that is left and not saved is not kept. */
    public function loadTrack(?string $slug): void
    {
        $this->trackSlug = $slug;
        $this->form->fill($this->toState($this->definition()?->workingDocument() ?? []));
    }

    protected function fields(array $document): array
    {
        $tracks = [];

        foreach ((array) ($document['tracks'] ?? []) as $slug => $track) {
            $tracks[(string) $slug] = ContentOptions::name($track['name'] ?? [], (string) $slug)." ({$slug})";
        }

        return [
            Grid::make(3)->schema([
                Select::make('track')
                    ->helpKey('thread.track')
                    ->options($tracks)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state) => $this->loadTrack($state)),
                Select::make('lap_from')->helpKey('thread.lap_from')->options(FieldHelp::options('thread.lap_from', ['stats', 'thread']))->required()->live(),
                Select::make('mode')->helpKey('thread.mode')->options(FieldHelp::options('thread.mode', ['circuit', 'run']))->required()->live(),
            ]),
            TextInput::make('return_s')
                ->helpKey('thread.return_s')
                ->numeric()
                ->minValue(0)
                ->maxValue(600)
                ->suffix('s')
                ->visible(fn (Get $get): bool => $get('mode') === 'run')
                ->live(onBlur: true),
            Repeater::make('sections')
                ->helpKey('thread.sections')
                ->hiddenLabel()
                ->live()
                ->reorderableWithButtons()
                ->cloneable()
                ->itemNumbers()
                ->defaultItems(0)
                ->addActionLabel(__('admin.thread.add_section'))
                ->itemLabel(fn (array $state): string => self::itemLabel($state))
                ->schema([
                    Grid::make(4)->schema([
                        Select::make('type')->helpKey('thread.type')->options(FieldHelp::options('thread.type', ['straight', 'corner']))->required()->live(),
                        TextInput::make('length_m')->helpKey('thread.length')->numeric()->minValue(5)->maxValue(5000)->suffix('m')->visible(fn (Get $get): bool => $get('type') === 'straight')->required(fn (Get $get): bool => $get('type') === 'straight')->live(onBlur: true),
                        TextInput::make('radius_m')->helpKey('thread.radius')->numeric()->minValue(8)->maxValue(1000)->suffix('m')->visible(fn (Get $get): bool => $get('type') === 'corner')->required(fn (Get $get): bool => $get('type') === 'corner')->live(onBlur: true),
                        TextInput::make('angle_deg')->helpKey('thread.angle')->numeric()->minValue(5)->maxValue(360)->suffix('°')->visible(fn (Get $get): bool => $get('type') === 'corner')->required(fn (Get $get): bool => $get('type') === 'corner')->live(onBlur: true),
                        Select::make('turn')->helpKey('thread.turn')->options(FieldHelp::options('thread.turn', ['left', 'right']))->visible(fn (Get $get): bool => $get('type') === 'corner')->live(),
                    ]),
                    Grid::make(3)->schema([
                        Select::make('surface')->helpKey('thread.surface')->options(FieldHelp::options('thread.surface', ['asphalt', 'gravel']))->live(),
                        TextInput::make('limit_kmh')->helpKey('thread.limit')->numeric()->minValue(20)->maxValue(400)->suffix('km/h')->live(onBlur: true),
                        TextInput::make('rough')->helpKey('thread.rough')->numeric()->minValue(0)->maxValue(100)->live(onBlur: true),
                    ]),
                ]),
            Text::make(__('admin.thread.presets'))->color('gray'),
            Actions::make([
                $this->addPiece('hairpin'),
                $this->addPiece('tight'),
                $this->addPiece('bend'),
                $this->addPiece('chicane'),
                Action::make('mirror')
                    ->label(__('admin.thread.mirror'))
                    ->color('gray')
                    ->action(fn (Get $get, Set $set) => $set('sections', ThreadForm::mirrored(array_values((array) $get('sections'))))),
                Action::make('reverse')
                    ->label(__('admin.thread.reverse'))
                    ->color('gray')
                    ->action(fn (Get $get, Set $set) => $set('sections', ThreadForm::reversed(array_values((array) $get('sections'))))),
                $this->closeAction(),
                Action::make('scale')
                    ->label(__('admin.thread.scale'))
                    ->color('gray')
                    ->modalHeading(__('admin.thread.scale'))
                    ->modalDescription(__('admin.thread.scale_hint'))
                    ->modalSubmitActionLabel(__('admin.thread.scale_use'))
                    ->schema([TextInput::make('factor')->helpKey('thread.scale_factor')->numeric()->minValue(0.2)->maxValue(5)->step(0.05)->default(1.2)->required()])
                    ->action(fn (array $data) => $this->scaleRadii((float) $data['factor'])),
                Action::make('stretch')
                    ->label(__('admin.thread.stretch'))
                    ->color('gray')
                    ->modalHeading(__('admin.thread.stretch'))
                    ->modalDescription(__('admin.thread.stretch_hint'))
                    ->modalSubmitActionLabel(__('admin.thread.stretch_use'))
                    ->schema([TextInput::make('length')->helpKey('thread.stretch_length')->numeric()->minValue(5)->maxValue(5000)->suffix('m')->required()])
                    ->action(fn (array $data) => $this->stretchLongest((float) $data['length'])),
            ]),
            Text::make(__('admin.thread.templates'))->color('gray'),
            Actions::make(array_map(fn (string $name): Action => $this->templateAction($name), ['city', 'drag', 'hillclimb', 'rally', 'oval'])),
            View::make('filament.forms.thread-preview')->viewData(fn (Get $get): array => ['preview' => $this->previewOf([
                'track' => $get('track'),
                'lap_from' => $get('lap_from'),
                'mode' => $get('mode'),
                'return_s' => $get('return_s'),
                'sections' => $get('sections'),
            ])]),
        ];
    }

    protected function toState(array $document): array
    {
        return ThreadForm::toState($document, $this->trackSlug);
    }

    protected function toDocument(array $document, array $state): array
    {
        return ThreadForm::apply($document, $state);
    }

    /**
     * The numbers and the line for the thread of the form, with the cars of the catalog that burn fuel; null when the thread cannot be driven yet.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    protected function previewOf(array $state): ?array
    {
        $document = $this->definition()?->workingDocument();
        $slug = is_string($state['track'] ?? null) ? $state['track'] : null;

        if ($document === null || $slug === null) {
            return null;
        }

        $made = ThreadForm::apply($document, $state);
        $cars = array_keys(array_filter(app(Catalog::class)->vehicles(), static fn (array $vehicle): bool => ($vehicle['powertrain'] ?? null) === 'ice'));
        sort($cars);

        return app(ThreadPreview::class)->of($made, $slug, $cars);
    }

    /** Makes the sections of a circuit into a loop (the last corner and two straights are moved), or says why they cannot be. */
    private function closeAction(): Action
    {
        return Action::make('close')
            ->label(__('admin.thread.close'))
            ->color('gray')
            ->action(fn () => $this->closeLoop());
    }

    /** Makes the sections of the form into a loop (the last corner and two straights are moved), or says why they cannot be. */
    public function closeLoop(): void
    {
        if (($this->data['mode'] ?? null) === 'run') {
            Notification::make()->warning()->title(__('admin.thread.close_run'))->send();

            return;
        }

        try {
            $closed = ThreadCloser::close($this->sections());
        } catch (ThreadCannotClose $why) {
            Notification::make()->warning()->title(__('admin.thread.close_'.$why->reason, $why->replace))->send();

            return;
        }

        $this->data['sections'] = $closed['sections'];
        Notification::make()->success()->title(__('admin.thread.close_done', ['first' => $closed['moved']['straights'][0], 'second' => $closed['moved']['straights'][1], 'corner' => $closed['moved']['corner'], 'gap' => round($closed['gap_m'], 1)]))->send();
    }

    /** Multiplies the radius of every corner of the form. */
    public function scaleRadii(float $factor): void
    {
        $this->data['sections'] = ThreadForm::scaledRadii($this->sections(), $factor);
    }

    /** Makes the longest straight of the form as long as asked. */
    public function stretchLongest(float $length): void
    {
        $this->data['sections'] = ThreadForm::stretched($this->sections(), $length);
    }

    /** Puts a whole track in the form in place of how it is driven and its sections. */
    public function useTemplate(string $name): void
    {
        $template = ThreadForm::template($name);

        $this->data['mode'] = $template['mode'];
        $this->data['return_s'] = $template['return_s'];
        $this->data['sections'] = $template['sections'];
    }

    /** @return list<array<string, mixed>> the sections of the form, in their order */
    private function sections(): array
    {
        return array_values((array) ($this->data['sections'] ?? []));
    }

    /** A whole track to start from: it replaces how the track is driven and all its sections, so it asks first. */
    private function templateAction(string $name): Action
    {
        return Action::make('template_'.$name)
            ->label(__('admin.thread.template_'.$name))
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin.thread.template_'.$name))
            ->modalDescription(__('admin.thread.template_hint'))
            ->action(fn () => $this->useTemplate($name));
    }

    private function addPiece(string $name): Action
    {
        return Action::make('preset_'.$name)
            ->label(__('admin.thread.preset_'.$name))
            ->color('gray')
            ->action(fn (Get $get, Set $set) => $set('sections', [...array_values((array) $get('sections')), ...ThreadForm::preset($name)]));
    }

    /** @param  array<string, mixed>  $state */
    private static function itemLabel(array $state): string
    {
        return match ($state['type'] ?? null) {
            'straight' => __('admin.thread.straight_label', ['length' => $state['length_m'] ?? '?']),
            'corner' => __('admin.thread.corner_label', ['radius' => $state['radius_m'] ?? '?', 'angle' => $state['angle_deg'] ?? '?']),
            default => __('admin.thread.unfinished_label'),
        };
    }
}
