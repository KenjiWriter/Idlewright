<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Content\Definitions\DefinitionForm;
use App\Content\Definitions\SchemaChoices;
use App\Content\Definitions\TutorialForm;
use App\Content\Units\Measures;
use App\Content\Vocabulary\MessageCatalog;
use App\Filament\Resources\Definitions\Forms\DefinitionFields;
use App\Filament\Support\FieldHelp;
use App\Models\ContentDefinition;
use App\Services\ContentImages;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;

/**
 * The tutorial as a form: the arrangements of the first run, the device the mentor writes on, the words of the tutorial, the story before the choice of a wreck,
 * and the steps, each with the fact that starts it and the one that ends it, the messages of the mentor and what the driver is given. What the schema asks is its
 * to say when the draft is saved.
 */
final class TutorialFields
{
    /** The longest sender, in every language. */
    private const SENDER_MAX = 40;

    /** The longest message of the mentor, which only a letter has room for; the other devices are held to the limit of the validator. */
    private const MESSAGE_MAX = 400;

    /**
     * @param  array<string, mixed>  $document  the tutorial being worked on: the words it says that the app no longer has are shown too, so that they can be cleared
     * @return list<mixed>
     */
    public static function schema(array $document = []): array
    {
        $travel = Measures::ofTheGame()->travel;
        $targets = SchemaChoices::targets();
        $conditions = [...SchemaChoices::conditions(), ...array_map(fn (string $name): string => $name.':', SchemaChoices::conditionParameters())];
        $jobs = SchemaChoices::arrangedJobs();
        $catalog = MessageCatalog::fromConfig();

        return [
            FieldHelp::guide('tutorial'),
            Section::make(__('admin.tutorial.script'))->description(__('admin.tutorial.script_hint'))->schema([
                Grid::make(4)->schema([
                    TextInput::make('version')->helpKey('tutorial.version')->required()->regex('/^[0-9]{4}\.[0-9]{1,2}\.[0-9]+$/'),
                    TextInput::make('first_run_fuel_ml')->helpKey('tutorial.first_run_fuel_ml')->numeric()->integer()->minValue(1)->required(),
                    TextInput::make('breakdown_lap')->helpKey('tutorial.breakdown_lap')->numeric()->integer()->minValue(1)->required(),
                    TextInput::make('first_mount_ms')->helpKey('tutorial.first_mount_ms')->numeric()->integer()->minValue(1)->required(),
                ]),
                Grid::make(4)->schema([
                    TextInput::make('covers_up_to')->helpKey('tutorial.covers_up_to')->numeric()->prefix('$')->minValue(0)->required(),
                ]),
                Fieldset::make(__('admin.tutorial.listing'))->schema([
                    Grid::make(5)->schema([
                        Select::make('listing_vehicle')->helpKey('tutorial.listing_vehicle')->options(fn (): array => ContentOptions::cars())->placeholder('-')->searchable(),
                        TextInput::make('listing_age_years')->helpKey('tutorial.listing_age_years')->numeric()->integer()->minValue(1),
                        TextInput::make('listing_mileage_km')
                            ->helpKey('tutorial.listing_mileage_km')
                            ->numeric()
                            ->minValue(0)
                            ->suffix($travel->shortFor())
                            ->formatStateUsing(fn (mixed $state): mixed => is_numeric($state) ? $travel->inBox($state * 1000) : $state)
                            ->dehydrateStateUsing(fn (mixed $state): mixed => is_numeric($state) ? (int) round($travel->toMetres($state) / 1000) : $state),
                        TextInput::make('listing_condition')->helpKey('tutorial.listing_condition')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100),
                        TextInput::make('listing_price')->helpKey('tutorial.listing_price')->numeric()->prefix('$')->minValue(0.01),
                    ]),
                ])->columns(1),
            ]),
            self::device(),
            self::words($catalog, $document),
            self::story($catalog, $document),
            Section::make(__('admin.tutorial.steps'))->description(__('admin.tutorial.steps_hint'))->schema([
                Repeater::make('steps')
                    ->helpKey('tutorial.steps')
                    ->hiddenLabel()
                    ->minItems(1)
                    ->collapsible()
                    ->collapsed()
                    ->reorderableWithButtons()
                    ->addActionLabel(__('admin.tutorial.step_add'))
                    ->itemLabel(fn (array $state): ?string => self::itemLabel($state))
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('key')->helpKey('tutorial.key')->required()->maxLength(64)->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')->distinct(),
                            Select::make('phase')->helpKey('tutorial.phase')->options(FieldHelp::options('tutorial.phase', array_map('strval', range(1, 4))))->required(),
                            TextInput::make('sender')->helpKey('tutorial.sender')->maxLength(self::SENDER_MAX),
                        ]),
                        self::senderTexts('sender', 'tutorial.sender_texts'),
                        Grid::make(3)->schema([
                            TextInput::make('trigger')->helpKey('tutorial.trigger')->datalist($conditions)->required(),
                            TextInput::make('completion')->helpKey('tutorial.completion')->datalist($conditions)->required(),
                            Select::make('target')->helpKey('tutorial.target')->options(FieldHelp::options('tutorial.target', $targets))->placeholder(__('admin.tutorial.no_target')),
                        ]),
                        DefinitionFields::texts('goal', __('admin.tutorial.goal'), 'tutorial.goal'),
                        DefinitionFields::texts('waiting', __('admin.tutorial.waiting'), 'tutorial.waiting', required: false),
                        Repeater::make('sms')
                            ->helpKey('tutorial.sms')
                            ->maxItems(2)
                            ->collapsible()
                            ->addActionLabel(__('admin.tutorial.sms_add'))
                            ->schema([self::message()]),
                        DefinitionFields::texts('reply', __('admin.tutorial.reply'), 'tutorial.reply', required: false),
                        Toggle::make('optional')->helpKey('tutorial.optional')->inline(false),
                        DefinitionFields::texts('decline', __('admin.tutorial.decline'), 'tutorial.decline', required: false),
                        CheckboxList::make('arranges')->helpKey('tutorial.arranges')->options(FieldHelp::options('tutorial.arranges', $jobs))->columns(5),
                        Fieldset::make(__('admin.tutorial.gift'))->schema([
                            Grid::make(3)->schema([
                                Select::make('gift_part')->helpKey('tutorial.gift_part')->options(fn (): array => ContentOptions::parts())->placeholder('-')->searchable(),
                                Select::make('gift_rarity')->helpKey('tutorial.gift_rarity')->options(FieldHelp::options('tutorial.gift_rarity', ['common', 'uncommon', 'rare', 'epic', 'legendary']))->placeholder('common'),
                                TextInput::make('gift_count')->helpKey('tutorial.gift_count')->numeric()->integer()->minValue(1)->maxValue(4),
                            ]),
                        ])->columns(1),
                        Fieldset::make(__('admin.tutorial.reward'))->schema([
                            Grid::make(5)->schema([
                                TextInput::make('reward_cash')->helpKey('tutorial.reward_cash')->numeric()->prefix('$')->minValue(0.01),
                                TextInput::make('reward_car_xp')->helpKey('tutorial.reward_car_xp')->numeric()->integer()->minValue(1),
                                TextInput::make('reward_xp')->helpKey('tutorial.reward_xp')->numeric()->integer()->minValue(1),
                                Select::make('reward_part')->helpKey('tutorial.reward_part')->options(fn (): array => ContentOptions::parts())->placeholder('-')->searchable(),
                                Select::make('reward_garage')->helpKey('tutorial.reward_garage')->options(fn (): array => self::cities())->placeholder('-'),
                            ]),
                        ])->columns(1),
                    ]),
            ]),
        ];
    }

    /** What the mentor writes on: the device, its parts, who writes when a step names nobody, the icon of the button, and a device of the game's own. */
    private static function device(): Section
    {
        $parts = array_map(
            static fn (string $part): Toggle => Toggle::make("part_{$part}")->helpKey("tutorial.part_{$part}")->inline(false)->default(true),
            TutorialForm::PARTS,
        );

        return Section::make(__('admin.tutorial.device'))->description(__('admin.tutorial.device_hint'))->collapsible()->schema([
            Grid::make(2)->schema([
                Select::make('device')->helpKey('tutorial.device')->options(FieldHelp::options('tutorial.device', TutorialForm::DEVICES))->selectablePlaceholder(false)->required()->live(),
                TextInput::make('messenger_sender')->helpKey('tutorial.messenger_sender')->maxLength(self::SENDER_MAX),
            ]),
            self::senderTexts('messenger_sender', 'tutorial.messenger_sender_texts'),
            Actions::make([self::senderEverywhereAction()])->key('senderEverywhere'),
            Grid::make(4)->schema($parts),
            Fieldset::make(__('admin.tutorial.icon'))->schema(self::picture('icon', 'tutorial.icon_upload', 'tutorial.icon_remove'))->columns(1),
            Fieldset::make(__('admin.tutorial.picture'))
                ->schema([
                    ...self::picture('frame', 'tutorial.frame_upload', 'tutorial.frame_remove', required: true),
                    Grid::make(4)->schema(array_map(
                        static fn (string $edge): TextInput => TextInput::make("screen_{$edge}")->helpKey("tutorial.screen_{$edge}")->numeric()->integer()->minValue(0)->maxValue(45)->suffix('%')->required(),
                        ['top', 'right', 'bottom', 'left'],
                    )),
                ])
                ->columns(1)
                ->visible(fn (Get $get): bool => $get('device') === 'picture'),
        ]);
    }

    /**
     * One sender for the whole tutorial: the move empties the sender of every step and of the story, so that every message comes from the sender of the messenger
     * (a step that names a sender of its own writes from it, which is how RideIdle changes its number at every step). It changes the form; saving keeps it.
     */
    private static function senderEverywhereAction(): Action
    {
        return Action::make('senderEverywhere')
            ->label(__('admin.tutorial.sender_everywhere'))
            ->icon('heroicon-m-arrow-down-on-square-stack')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin.tutorial.sender_everywhere'))
            ->modalDescription(__('admin.tutorial.sender_everywhere_hint'))
            ->disabled(fn (Get $get): bool => trim((string) $get('messenger_sender')) === '' && array_filter((array) $get('messenger_sender_texts'), 'filled') === [])
            ->action(function (Get $get, Set $set): void {
                $steps = (array) $get('steps');
                $empty = DefinitionForm::texts([]);

                foreach ($steps as $item => $step) {
                    $steps[$item]['sender'] = null;
                    $steps[$item]['sender_texts'] = $empty;
                }

                $set('steps', $steps);
                $set('story_sender', null);
                $set('story_sender_texts', $empty);

                Notification::make()->success()->title(__('admin.tutorial.sender_everywhere_done', ['count' => count($steps)]))->send();
            });
    }

    /**
     * The words of the tutorial that the game can say in its own way: a box in every language for each message of the app, with the text of the app as the hint of
     * an empty box. Without the texts of the web app there is nothing to say them against, and the section says so.
     *
     * @param  array<string, mixed>  $document
     */
    private static function words(MessageCatalog $catalog, array $document): Section
    {
        return Section::make(__('admin.tutorial.words'))
            ->description(__('admin.tutorial.words_hint'))
            ->collapsible()
            ->collapsed()
            ->schema($catalog->available()
                ? self::labelRows('labels', 'messenger', $catalog, (array) ($document['messenger']['labels'] ?? []), 'tutorial.label')
                : [Text::make(__('admin.tutorial.words_no_catalog'))]);
    }

    /**
     * The story before the choice of a wreck: the one of the app, one of the game's own or none, and for one of its own the picture, the scenes, the first message
     * and the words of the screen.
     *
     * @param  array<string, mixed>  $document
     */
    private static function story(MessageCatalog $catalog, array $document): Section
    {
        $own = fn (Get $get): bool => $get('story_mode') === TutorialForm::STORY_OWN;

        return Section::make(__('admin.tutorial.story'))->description(__('admin.tutorial.story_hint'))->collapsible()->collapsed()->schema([
            Select::make('story_mode')->helpKey('tutorial.story_mode')->options(FieldHelp::options('tutorial.story_mode', TutorialForm::STORY_MODES))->selectablePlaceholder(false)->required()->live(),
            Fieldset::make(__('admin.tutorial.story_picture'))->schema(self::picture('story_picture', 'tutorial.story_picture_upload', 'tutorial.story_picture_remove'))->columns(1)->visible($own),
            Repeater::make('story_scenes')
                ->helpKey('tutorial.story_scenes')
                ->minItems(1)
                ->maxItems(6)
                ->collapsible()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.tutorial.scene_add'))
                ->visible($own)
                ->schema([
                    DefinitionFields::texts('text', __('admin.tutorial.scene_text'), 'tutorial.scene_text', long: true),
                    Grid::make(4)->schema([
                        TextInput::make('seconds')->helpKey('tutorial.scene_seconds')->numeric()->minValue(2)->maxValue(20)->suffix('s')->required(),
                        Select::make('camera')->helpKey('tutorial.scene_camera')->options(FieldHelp::options('tutorial.scene_camera', TutorialForm::CAMERAS))->required(),
                        TextInput::make('focus_x')->helpKey('tutorial.scene_focus_x')->numeric()->integer()->minValue(0)->maxValue(100)->suffix('%')->required(),
                        TextInput::make('focus_y')->helpKey('tutorial.scene_focus_y')->numeric()->integer()->minValue(0)->maxValue(100)->suffix('%')->required(),
                    ]),
                ]),
            Grid::make(2)->schema([
                TextInput::make('story_sender')->helpKey('tutorial.story_sender')->maxLength(self::SENDER_MAX),
            ])->visible($own),
            self::senderTexts('story_sender', 'tutorial.story_sender_texts')->visible($own),
            DefinitionFields::texts('story_sms', __('admin.tutorial.story_sms'), 'tutorial.story_sms', long: true, required: false)->visible($own),
            Section::make(__('admin.tutorial.story_words'))
                ->description(__('admin.tutorial.story_words_hint'))
                ->compact()
                ->collapsible()
                ->collapsed()
                ->visible($own)
                ->schema($catalog->available()
                    ? self::labelRows('story_labels', 'story', $catalog, (array) ($document['story']['labels'] ?? []), 'tutorial.story_label')
                    : [Text::make(__('admin.tutorial.words_no_catalog'))]),
        ]);
    }

    /**
     * One row of boxes for each word of a section: those of the app, and those the document says that the app no longer has.
     *
     * @param  array<string, mixed>  $labels  the words the document says
     * @return list<Section>
     */
    private static function labelRows(string $field, string $section, MessageCatalog $catalog, array $labels, string $help): array
    {
        $prefix = TutorialForm::labelPrefix($section);
        $language = (string) config('ride.admin_locale');
        $rows = [];

        foreach (array_unique([...TutorialForm::labelNames($catalog)[$section], ...array_map('strval', array_keys($labels))]) as $name) {
            $key = "{$prefix}.{$name}";
            $said = $catalog->text($key, $language);

            $rows[] = Section::make(isset($labels[$name]) ? "{$key} ●" : $key)
                ->description($said === null ? __('admin.vocabulary.not_in_app') : __('admin.vocabulary.app_says', ['text' => $said]))
                ->compact()
                ->collapsible()
                ->collapsed(! isset($labels[$name]))
                ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
                ->schema(array_map(
                    static fn (string $locale): Field => FieldHelp::apply(
                        TextInput::make("{$field}.{$name}.{$locale}")->label($locale)->placeholder($catalog->text($key, $locale))->maxLength(200),
                        $help,
                        label: false,
                        help: false,
                    ),
                    DefinitionForm::LOCALES,
                ));
        }

        return $rows;
    }

    /**
     * The sender in the languages of the game, for a sender that is a name: folded away until a language is written, because most senders are a number, the same in
     * every language.
     */
    private static function senderTexts(string $field, string $help): Section
    {
        $boxes = [];

        foreach (DefinitionForm::LOCALES as $position => $locale) {
            $box = TextInput::make("{$field}_texts.{$locale}")->maxLength(self::SENDER_MAX)->label(config("ride.locale_names.{$locale}", $locale));
            $boxes[] = FieldHelp::apply($box, $help, label: false, help: $position === 0);
        }

        return Section::make(__('admin.tutorial.sender_texts'))
            ->compact()
            ->collapsible()
            ->collapsed(fn (Get $get): bool => array_filter((array) $get("{$field}_texts"), 'filled') === [])
            ->schema($boxes);
    }

    /**
     * A message of the mentor in the languages of the game: room for the longest, which only a letter may have (the validator holds the other devices to their
     * limit when the draft is saved).
     */
    private static function message(): Fieldset
    {
        $boxes = [];

        foreach (DefinitionForm::LOCALES as $position => $locale) {
            $box = Textarea::make($locale)->rows(2)->maxLength(self::MESSAGE_MAX)->label(config("ride.locale_names.{$locale}", $locale))->required(in_array($locale, ['en', 'pl'], true));
            $boxes[] = FieldHelp::apply($box, 'tutorial.message', label: false, help: $position === 0);
        }

        return Fieldset::make(__('admin.tutorial.message'))->schema($boxes)->columns(1);
    }

    /**
     * A picture of the tutorial: the one the draft names, a box for another one, and a way to take it off. The file is not kept by the box but by the page that saves
     * the draft, which puts its name in the field (see TutorialPage).
     *
     * @return list<mixed>
     */
    private static function picture(string $field, string $uploadHelp, string $removeHelp, bool $required = false): array
    {
        return [
            Hidden::make($field),
            View::make('filament.forms.definition-image')->viewData(fn (Get $get): array => ['name' => $get($field)]),
            FileUpload::make("{$field}_upload")
                ->helpKey($uploadHelp, ['min' => ContentImages::MIN_SIDE, 'max' => ContentImages::MAX_SIDE])
                ->storeFiles(false)
                ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                ->maxSize(intdiv(ContentImages::MAX_BYTES, 1024))
                ->required($required ? fn (Get $get): bool => blank($get($field)) : false),
            Toggle::make("{$field}_remove")->helpKey($removeHelp)->visible(fn (Get $get): bool => filled($get($field)))->default(false),
        ];
    }

    /**
     * The cities of the rules as they are being worked on: the garage that a step gives is in one of them.
     *
     * @return array<string, string>
     */
    private static function cities(): array
    {
        $rules = ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', 'starter')->with('latestVersion')->first();
        $options = [];

        foreach ($rules?->workingDocument()['regions'] ?? [] as $slug => $region) {
            $options[(string) $slug] = ContentOptions::name($region['name'] ?? [], (string) $slug)." ({$slug})";
        }

        return $options;
    }

    /** @param  array<string, mixed>  $state */
    private static function itemLabel(array $state): ?string
    {
        $key = is_string($state['key'] ?? null) ? trim($state['key']) : '';

        return $key === '' ? null : ($state['phase'] ?? '?').' - '.$key;
    }
}
