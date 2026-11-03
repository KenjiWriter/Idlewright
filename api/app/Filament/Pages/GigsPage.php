<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Definitions\GigsForm;
use App\Content\Definitions\JsonDocument;
use App\Content\Publishing\CandidatePack;
use App\Content\Publishing\PackGates;
use App\Filament\Forms\GigsFields;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;
use App\Services\GigReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\View;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Throwable;

/**
 * The side jobs of the game: when the board opens and how long it stays, how many offers each phase of the day has, how much an hour of a gig pays at each level and how long a
 * gig may be, the kinds and the templates that the offers are drawn from (the document of the gigs). Under the form it works out, for the form as it is now, what an hour of every
 * template pays on every car of the pack against the rung of the ladder and against the laps of the same car, and says whether the gate of the publication would let it through.
 */
class GigsPage extends RulesDocumentPage
{
    protected static ?string $slug = 'gigs';

    protected static ?int $navigationSort = 13;

    /** @var array{rows: list<array<string, mixed>>, offences: list<array<string, mixed>>}|null what the pay came to when it was last asked (the report of the gigs) */
    public ?array $report = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.gigs.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.gigs.title');
    }

    protected function documentSlug(): string
    {
        return 'gigs';
    }

    /** A game that has no gigs (a pack that was made before them) is worked on as an empty document; the first draft makes it. */
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
        return GigsFields::schema([
            Actions::make([
                Action::make('measure')
                    ->label(__('admin.gigs.report.button'))
                    ->icon('heroicon-m-calculator')
                    ->color('gray')
                    ->action(fn () => $this->measurePay()),
            ])->key('report-actions'),
            View::make('filament.gigs.report')->viewData(fn (): array => ['report' => $this->report, 'summary' => $this->report === null ? [] : GigReport::byTemplate($this->report['rows'])]),
        ]);
    }

    /** A game that has no gigs starts from the document that the code ships, switched off: nothing is on the board until someone has read the numbers and switched it on. */
    protected function toState(array $document): array
    {
        return GigsForm::toState($document === [] ? $this->shipped() : $document);
    }

    protected function toDocument(array $document, array $state): array
    {
        return GigsForm::apply($document, $state);
    }

    /**
     * What an hour of the gigs pays on every car of the pack, with the content as the form has it now (nothing is saved): the content is written as it would be and the game is
     * pointed at it for as long as the pay is worked out, as the gates of the publication do.
     */
    public function measurePay(): void
    {
        $definition = $this->definition();

        if ($definition === null) {
            return;
        }

        $document = GigsForm::apply($definition->workingDocument(), $this->form->getRawState());
        $result = app(ContentDrafts::class)->validate($definition->kind, $document, $definition->slug);

        // A document that the schema refuses cannot be worked out; the page says what is wrong with it, as it does when it is saved.
        if (! $result->isValid()) {
            $this->report = null;

            Notification::make()
                ->danger()
                ->title(__('admin.definitions.invalid'))
                ->body(implode("\n", array_map(fn (array $issue): string => ($issue['pointer'] === '' ? '' : "{$issue['pointer']}: ").$issue['message'], array_slice($result->errors(), 0, 8))))
                ->persistent()
                ->send();

            return;
        }

        // Working out the pay runs the game on a pack of its own, which takes a moment longer than a request of the panel is meant to.
        if ((int) ini_get('max_execution_time') > 0) {
            @set_time_limit(120);
        }

        try {
            $content = app(CandidatePack::class)->make('working')->with(['rules/gigs' => JsonDocument::encode($document)]);

            $this->report = app(PackGates::class)->gigsReport($content);
        } catch (Throwable $problem) {
            $this->report = null;

            Notification::make()->danger()->title(__('admin.gigs.report.failed'))->body(Str::limit($problem->getMessage(), 300))->persistent()->send();
        }
    }

    /** @return array<string, mixed> the document of the gigs that the code ships, switched off */
    private function shipped(): array
    {
        $file = rtrim((string) config('ride.content_path'), '/\\').'/rules/gigs.json';

        return ['enabled' => false] + (array) (is_file($file) ? json_decode((string) file_get_contents($file), true) : []);
    }
}
