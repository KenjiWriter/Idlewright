<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\ContentPackInvalid;
use App\Content\Definitions\DefinitionRefused;
use App\Content\Definitions\JsonDocument;
use App\Content\Definitions\MissionsForm;
use App\Content\PackValidationResult;
use App\Content\PackValidator;
use App\Content\Publishing\CandidatePack;
use App\Content\Publishing\PackGates;
use App\Filament\Forms\ContentOptions;
use App\Filament\Forms\MissionsFields;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;
use App\Services\ContentPacks;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\View;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The missions of the game: when they are on and what a whole set pays (numbers of the rules), the slots of the sets of a day, a week and a month with the missions they draw
 * from, and the chains of main missions (the document of the missions), kept together as the drafts of the two documents, or not at all. Under the numbers it works out what the
 * sets come to for a car on a track, with the numbers of `missions:budget`, and says whether the gate of the publication would let it through.
 */
class MissionsPage extends RulesDocumentPage
{
    protected static ?string $slug = 'missions';

    protected static ?int $navigationSort = 11;

    /** @var array<string, mixed>|null what the sets came to when it was last asked (the report of the budget) */
    public ?array $budget = null;

    private ?ContentDefinition $rulesLoaded = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.missions.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.missions.title');
    }

    protected function documentSlug(): string
    {
        return 'missions';
    }

    /** A game that has no missions (a pack that was made before them) is worked on as an empty document; the first draft makes it. */
    protected function unstartedDefinition(): ?ContentDefinition
    {
        return (new ContentDefinition)->forceFill([
            'uid' => (string) Str::ulid(),
            'kind' => ContentDefinition::KIND_RULES,
            'slug' => $this->documentSlug(),
            'status' => ContentDefinition::STATUS_DRAFT,
        ]);
    }

    protected function fields(array $document): array
    {
        $rules = $this->rulesDocument();
        $tracks = array_map('strval', array_keys((array) ($rules['tracks'] ?? [])));

        return MissionsFields::schema([
            ...MissionsFields::reference(ContentOptions::cars(), $tracks === [] ? [] : array_combine($tracks, $tracks)),
            Actions::make([
                Action::make('budget')
                    ->label(__('admin.missions.budget.button'))
                    ->icon('heroicon-m-calculator')
                    ->color('gray')
                    ->action(fn () => $this->calculateBudget()),
            ])->key('budget-actions'),
            View::make('filament.missions.budget')->viewData(fn (): array => ['budget' => $this->budget]),
        ]);
    }

    protected function toState(array $document): array
    {
        $rules = $this->rulesDocument();
        $cars = array_keys(ContentOptions::cars());
        $tracks = array_map('strval', array_keys((array) ($rules['tracks'] ?? [])));

        return MissionsForm::toState($document, $rules) + ['budget' => [
            'car' => in_array('rdzak-gt-4', $cars, true) ? 'rdzak-gt-4' : ($cars[0] ?? null),
            'track' => in_array('zlomowo-street', $tracks, true) ? 'zlomowo-street' : ($tracks[0] ?? null),
            'level' => 1,
        ]];
    }

    protected function toDocument(array $document, array $state): array
    {
        return MissionsForm::apply($document, $this->rulesDocument(), $state)[0];
    }

    /** What the bar of changes leaves out: the choice of the car and the track that the budget is worked out for is not part of what is saved. */
    protected function changesIgnoredFields(): array
    {
        return ['budget.car', 'budget.track', 'budget.level'];
    }

    /** Keeps the form as the drafts of the two documents it changes, if both are accepted, and says what is wrong when they are not. */
    public function save(): void
    {
        $missions = $this->definition();
        $rules = $this->rulesDefinition();

        if ($missions === null || $rules === null) {
            return;
        }

        [$missionsDocument, $rulesDocument] = MissionsForm::apply($missions->workingDocument(), $rules->workingDocument(), $this->form->getState());

        $this->keepBoth($missions, $missionsDocument, $rules, $rulesDocument);
    }

    /**
     * What the sets come to for the car and the track of the form, with the content as the form has it now (nothing is saved): the content is written as it would be and the
     * game is pointed at it for as long as the budget is worked out, as the gates of the publication do.
     */
    public function calculateBudget(): void
    {
        $missions = $this->definition();
        $rules = $this->rulesDefinition();

        if ($missions === null || $rules === null) {
            return;
        }

        $state = $this->form->getRawState();
        [$missionsDocument, $rulesDocument] = MissionsForm::apply($missions->workingDocument(), $rules->workingDocument(), $state);

        // Working out what the sets come to runs the game on a pack of its own, which takes a moment longer than a request of the panel is meant to.
        if ((int) ini_get('max_execution_time') > 0) {
            @set_time_limit(120);
        }

        try {
            $content = app(CandidatePack::class)->make('working')->with([
                'rules/missions' => JsonDocument::encode($missionsDocument),
                'rules/starter' => JsonDocument::encode($rulesDocument),
            ]);

            $this->budget = app(PackGates::class)->missionsBudget(
                $content,
                (string) ($state['budget']['car'] ?? ''),
                (string) ($state['budget']['track'] ?? ''),
                max(1, (int) ($state['budget']['level'] ?? 1)),
            );
        } catch (Throwable $problem) {
            $this->budget = null;

            Notification::make()->danger()->title(__('admin.missions.budget.failed'))->body(Str::limit($problem->getMessage(), 300))->persistent()->send();

            return;
        }

        if ($this->budget === null) {
            Notification::make()->warning()->title(__('admin.missions.budget.none'))->send();
        }
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('discard')
                ->label(__('admin.definitions.discard'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('admin.definitions.discard_description'))
                ->visible(fn (): bool => collect($this->definitions())->contains(fn (ContentDefinition $definition): bool => $definition->hasDraft() && $definition->versions()->exists()))
                ->action(function (): void {
                    try {
                        DB::transaction(function (): void {
                            foreach ($this->definitions() as $definition) {
                                if ($definition->hasDraft() && $definition->versions()->exists()) {
                                    app(ContentDrafts::class)->discard($definition, $this->admin());
                                }
                            }
                        });
                    } catch (DefinitionRefused $refusal) {
                        $this->say($refusal);

                        return;
                    }

                    $this->fillForm();

                    Notification::make()->success()->title(__('admin.definitions.done'))->send();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        $drafts = array_filter($this->definitions(), fn (ContentDefinition $definition): bool => $definition->hasDraft());

        if ($this->definition() === null) {
            return null;
        }

        if ($drafts === []) {
            return __('admin.rules.no_draft');
        }

        $when = max(array_map(fn (ContentDefinition $definition): int => (int) $definition->draft_saved_at?->getTimestamp(), $drafts));

        return __('admin.definitions.draft_since', ['when' => date('Y-m-d H:i', $when)]);
    }

    protected function fillForm(): void
    {
        $this->rulesLoaded = null;

        parent::fillForm();
    }

    /** @return list<ContentDefinition> the two documents that the page changes, those that exist */
    private function definitions(): array
    {
        return array_values(array_filter([$this->definition(), $this->rulesDefinition()]));
    }

    private function rulesDefinition(): ?ContentDefinition
    {
        return $this->rulesLoaded ??= ContentDefinition::query()
            ->where('kind', ContentDefinition::KIND_RULES)
            ->where('slug', 'starter')
            ->with('latestVersion')
            ->first();
    }

    /** @return array<string, mixed> the rules as they are being worked on */
    private function rulesDocument(): array
    {
        return $this->rulesDefinition()?->workingDocument() ?? [];
    }

    /**
     * Keeps both documents as drafts, or neither: each is judged on its own, then the two together (the missions point at the steps of the tutorial and at the premium
     * currency), and they are kept in one transaction so that a document that is refused leaves the other one as it was.
     *
     * @param  array<string, mixed>  $missionsDocument
     * @param  array<string, mixed>  $rulesDocument
     */
    private function keepBoth(ContentDefinition $missions, array $missionsDocument, ContentDefinition $rules, array $rulesDocument): void
    {
        $drafts = app(ContentDrafts::class);
        $results = [$drafts->validate(ContentDefinition::KIND_RULES, $missionsDocument, 'missions'), $drafts->validate(ContentDefinition::KIND_RULES, $rulesDocument, 'starter')];
        $errors = array_merge(...array_map(fn (PackValidationResult $result): array => $result->errors(), $results));
        $warnings = array_merge(...array_map(fn (PackValidationResult $result): array => $result->warnings(), $results));

        if ($errors === []) {
            $together = PackValidator::fromConfig()->validateReferences(app(ContentPacks::class)->working([
                'rules/missions' => JsonDocument::encode($missionsDocument),
                'rules/starter' => JsonDocument::encode($rulesDocument),
            ]));
            $errors = $together->errors();
            $warnings = [...$warnings, ...$together->warnings()];
        }

        if ($errors !== []) {
            Notification::make()
                ->danger()
                ->title(__('admin.definitions.invalid'))
                ->body(implode("\n", array_map(fn (array $issue): string => ($issue['pointer'] === '' ? '' : "{$issue['pointer']}: ").$issue['message'], array_slice($errors, 0, 8))))
                ->persistent()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($drafts, $missions, $missionsDocument, $rules, $rulesDocument): void {
                $drafts->save($missions, $missionsDocument, $this->admin());
                $drafts->save($rules, $rulesDocument, $this->admin());
            });
        } catch (ContentPackInvalid) {
            // The documents were checked above; these are documents that stopped being valid between the two.
            Notification::make()->danger()->title(__('admin.definitions.invalid'))->persistent()->send();

            return;
        } catch (DefinitionRefused $refusal) {
            $this->say($refusal);

            return;
        }

        $this->forgetDefinition();
        $this->fillForm();

        Notification::make()->success()->title(__('admin.definitions.saved'))->send();

        $missing = self::missingLanguages($warnings);

        if ($missing !== []) {
            Notification::make()->warning()->title(__('admin.definitions.missing_languages', ['languages' => implode(', ', $missing)]))->send();
        }
    }
}
