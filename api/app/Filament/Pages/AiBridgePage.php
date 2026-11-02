<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Bridge\AnswerRecorder;
use App\Content\Bridge\ApplyRefused;
use App\Content\Bridge\BalancePreview;
use App\Content\Bridge\BridgeIssue;
use App\Content\Bridge\BriefBuilder;
use App\Content\Bridge\BriefRecorder;
use App\Content\Bridge\BriefSelection;
use App\Content\Bridge\ChangeApplier;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ChangeReverter;
use App\Content\Bridge\ResolvedChange;
use App\Filament\Concerns\ForAGame;
use App\Filament\Support\FieldHelp;
use App\Models\AiExchange;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The bridge to an AI chat. A person picks what the AI is to be told of the game and copies the instruction, talks to the AI in a chat of their own, and pastes its answer
 * back here: the panel checks it, shows every number it would change (what is live, what it is now with the drafts, what it would become), and only when the person says so
 * keeps it as drafts, which are published later by someone who publishes, through the gates, as they always were. The AI is given nothing but the text the person gives it,
 * and gives nothing back but the text the person pastes. The text of the instruction is never kept in the state of the page: it is made when asked for and goes straight to
 * the clipboard.
 */
class AiBridgePage extends Page implements HasSchemas
{
    use ForAGame;
    use InteractsWithSchemas;

    /** How many answers may be checked in a minute by one admin. */
    private const CHECKS_PER_MINUTE = 10;

    private const HISTORY = 10;

    protected static ?string $slug = 'ai-bridge';

    protected static ?int $navigationSort = 12;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array{tokens: int, bytes: int, scope: int}|null what the instruction would come to with the choices now */
    public ?array $size = null;

    /** The answer of the AI as it was pasted. */
    public string $answer = '';

    /** @var array<string, mixed>|null what the check of the answer came to, as the page shows it */
    public ?array $result = null;

    public bool $acknowledged = false;

    public bool $repeat = false;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.ai.nav');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-sparkles';
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.ai.title');
    }

    public function mount(): void
    {
        // The panel says pt_BR where the languages of the content say pt-BR.
        $locale = str_replace('_', '-', app()->getLocale());

        $this->form->fill([
            'include' => ['vehicles', 'parts', 'rules'],
            'simple' => true,
            'texts' => false,
            'design' => false,
            'sections' => [],
            'locale' => in_array($locale, BriefSelection::LOCALES, true) ? $locale : 'pl',
        ]);

        $this->measure();
    }

    public function form(Schema $schema): Schema
    {
        $sections = app(BriefBuilder::class)->sections();

        return $schema->statePath('data')->columns(2)->components([
            CheckboxList::make('include')
                ->helpKey('ai.include')
                ->options(['vehicles' => __('admin.ai.vehicles'), 'parts' => __('admin.ai.parts'), 'rules' => __('admin.ai.rules')])
                ->live()
                ->columnSpanFull(),
            Toggle::make('simple')->helpKey('ai.simple')->live(),
            Toggle::make('texts')->helpKey('ai.texts')->live(),
            Toggle::make('design')->helpKey('ai.design')->live(),
            Select::make('locale')
                ->helpKey('ai.locale')
                ->options(['pl' => 'Polski', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'pt-BR' => 'Português (Brasil)'])
                ->selectablePlaceholder(false)
                ->live(),
            CheckboxList::make('sections')
                ->helpKey('ai.sections')
                ->options(collect($sections)->mapWithKeys(fn (array $row, string $section): array => [
                    $section => __('admin.ai.section_option', ['name' => StudioPage::sectionName($section), 'numbers' => $row['numbers'], 'simple' => $row['simple']]),
                ])->all())
                ->columns(3)
                ->searchable()
                ->bulkToggleable()
                ->live()
                ->columnSpanFull(),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.ai.intro'),
            Tabs::make('ai')->persistTabInQueryString()->tabs([
                Tab::make(__('admin.ai.tab_prepare'))->schema([
                    Form::make([EmbeddedSchema::make('form')])->id('form'),
                    View::make('filament.ai.actions')->viewData(fn (): array => ['size' => $this->size]),
                ]),
                Tab::make(__('admin.ai.tab_paste'))->schema([
                    View::make('filament.ai.paste')->viewData(fn (): array => [
                        'result' => $this->result,
                        'history' => $this->history(),
                        'acknowledged' => $this->acknowledged,
                        'repeat' => $this->repeat,
                    ]),
                ]),
            ]),
        ]);
    }

    /**
     * What the checked answer would do to the balance of the game, in a window: the gates and the simulators of the economy run on the content with the answer put over it,
     * beside the same for the content that is live. Nothing is kept, and the answer is checked again first, because what was shown may be old.
     */
    public function balanceAction(): Action
    {
        return Action::make('balance')
            ->label(__('admin.ai.balance'))
            ->icon('heroicon-m-chart-bar')
            ->color('gray')
            ->modalHeading(__('admin.ai.balance_title'))
            ->modalDescription(__('admin.ai.balance_hint'))
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.ai.close'))
            ->modalContent(fn () => view('filament.studio.preview', ['report' => $this->balanceReport()]));
    }

    /** Re-counts the instruction when a choice is changed. */
    public function updatedData(): void
    {
        $this->measure();
    }

    /**
     * The instruction as text, for the browser to put on the clipboard: it is made and recorded here and not kept (a return value is not part of the state of the page).
     */
    public function brief(): string
    {
        return app(BriefRecorder::class)->prepare($this->selection())->text;
    }

    /** Looks at the answer that was pasted, as the panel will keep it: changes nothing, and says what it would change and what is wrong. */
    public function check(): void
    {
        $text = trim($this->answer);
        $this->acknowledged = false;
        $this->repeat = false;

        if ($text === '') {
            $this->result = null;
            Notification::make()->warning()->title(__('admin.ai.empty_answer'))->send();

            return;
        }

        $admin = Auth::guard('admin')->user();
        $key = 'ai-check:'.$admin->id;

        if (RateLimiter::tooManyAttempts($key, self::CHECKS_PER_MINUTE)) {
            Notification::make()->warning()->title(__('admin.ai.rate_limited'))->send();

            return;
        }

        RateLimiter::hit($key, 60);

        $check = app(ChangeChecker::class)->check($text);
        $exchange = app(AnswerRecorder::class)->record($text, $check, $admin);

        $this->result = [
            'ok' => $check->ok(),
            'errors' => count($check->errors()),
            'warnings' => count($check->warnings()),
            'documents' => count($check->documents),
            'rows' => array_map(fn (ResolvedChange $change): array => $this->row($change), $check->changes),
            'issues' => array_map(fn (BridgeIssue $issue): array => $this->issue($issue), $check->issues),
            'repair' => $check->repairMessage(),
            'touched' => $check->touched,
            'applied_before' => $exchange->status === 'applied',
            'code' => $exchange->code,
        ];
    }

    /** Keeps what was checked as drafts, nothing else: nothing is published. */
    public function apply(): void
    {
        if ($this->result === null || ! $this->result['ok']) {
            return;
        }

        try {
            $exchange = app(ChangeApplier::class)->apply(trim($this->answer), Auth::guard('admin')->user(), (array) $this->result['touched'], $this->repeat);
        } catch (ApplyRefused $refused) {
            $this->refused($refused);

            // What was shown is old: it is shown again as it is now.
            if ($refused->reason === 'changed_meanwhile') {
                $this->check();
            }

            return;
        }

        $changes = count((array) ($exchange->resolved['changes'] ?? []));

        Notification::make()->success()->title(__('admin.ai.applied', ['changes' => $changes]))
            ->actions([Action::make('pending')->label(__('admin.ai.applied_link'))->url(PendingChangesPage::getUrl())])
            ->persistent()
            ->send();

        $this->answer = '';
        $this->result = null;
        $this->acknowledged = false;
        $this->repeat = false;
    }

    /** Undoes what an answer did to the drafts, if they are still as it made them. */
    public function undo(int $id): void
    {
        $exchange = AiExchange::query()->where('kind', AiExchange::KIND_PASTE)->findOrFail($id);

        try {
            app(ChangeReverter::class)->revert($exchange, Auth::guard('admin')->user());
        } catch (ApplyRefused $refused) {
            $this->refused($refused);

            return;
        }

        Notification::make()->success()->title(__('admin.ai.undone'))->send();
    }

    /** @return array<string, mixed> */
    private function balanceReport(): array
    {
        $check = app(ChangeChecker::class)->check(trim($this->answer));

        if (! $check->ok()) {
            return ['errors' => [['file' => '', 'pointer' => '', 'message' => __('admin.ai.refused.not_ok')]], 'gates' => [], 'now' => [], 'live' => null, 'changes' => 0];
        }

        return app(BalancePreview::class)->of($check);
    }

    private function refused(ApplyRefused $refused): void
    {
        Notification::make()->danger()->title(__('admin.ai.refused.'.$refused->reason, ['details' => implode(', ', $refused->details)]))->persistent()->send();
    }

    /** @return array<string, mixed> */
    private function row(ResolvedChange $change): array
    {
        $entry = str_starts_with($change->document, 'rules:') && $change->pattern !== null ? FieldHelp::entry('rules.'.$change->pattern) : null;

        return [
            'op' => $change->op.($change->opId !== null ? " ({$change->opId})" : ''),
            'document' => $change->document,
            'pointer' => $change->pointer(),
            'label' => $change->label,
            'what' => isset($entry['help']) ? Str::limit((string) $entry['help'], 180) : null,
            'published' => $change->publishedText(),
            'before' => $change->beforeText(),
            'after' => $change->afterText(),
            'percent' => $change->type === ResolvedChange::NUMBER ? sprintf('%+.2f%%', $change->percent()) : '',
            'notes' => $change->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function issue(BridgeIssue $issue): array
    {
        $label = 'admin.ai.issues.'.$issue->code;

        return [
            'error' => $issue->isError(),
            'label' => __($label) === $label ? $issue->code : __($label),
            'message' => $issue->message,
            'op' => $issue->op,
            'id' => $issue->opId,
            'where' => $issue->where,
        ];
    }

    /** @return list<array{id: int, when: string, who: string, summary: string, changes: int, status: string}> */
    private function history(): array
    {
        return AiExchange::query()
            ->where('kind', AiExchange::KIND_PASTE)
            ->with('admin')
            ->latest('updated_at')
            ->limit(self::HISTORY)
            ->get()
            ->map(fn (AiExchange $exchange): array => [
                'id' => $exchange->id,
                'when' => $exchange->updated_at?->format('d.m H:i') ?? '',
                'who' => (string) ($exchange->admin?->name ?? '-'),
                'summary' => Str::limit((string) ($exchange->resolved['summary'] ?? '-'), 80),
                'changes' => count((array) ($exchange->resolved['changes'] ?? [])),
                'status' => $exchange->status,
            ])
            ->all();
    }

    private function measure(): void
    {
        $brief = app(BriefBuilder::class)->build($this->selection(), 'estimate0');

        $this->size = ['tokens' => $brief->tokens, 'bytes' => $brief->bytes, 'scope' => count($brief->scope)];
    }

    private function selection(): BriefSelection
    {
        return BriefSelection::fromState((array) $this->data);
    }
}
