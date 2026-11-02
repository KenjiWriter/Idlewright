<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Bridge\BriefBuilder;
use App\Content\Bridge\BriefRecorder;
use App\Content\Bridge\BriefSelection;
use App\Filament\Concerns\ForAGame;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The bridge to an AI chat: a person picks what the AI is to be told of the game, copies the instruction, talks to the AI in a chat of their own, and brings its answer back
 * (a later step of this page). The AI is given nothing but the text the person gives it, and gives nothing back but the text the person pastes; this page makes the first
 * text and says how big it is. The text itself is never kept in the state of the page: it is made when it is asked for and goes straight to the clipboard.
 */
class AiBridgePage extends Page implements HasSchemas
{
    use ForAGame;
    use InteractsWithSchemas;

    protected static ?string $slug = 'ai-bridge';

    protected static ?int $navigationSort = 12;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array{tokens: int, bytes: int, scope: int}|null what the instruction would come to with the choices now */
    public ?array $size = null;

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
        $locale = app()->getLocale();

        $this->form->fill([
            'include' => ['vehicles', 'parts', 'rules'],
            'simple' => true,
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
            Form::make([EmbeddedSchema::make('form')])->id('form'),
            View::make('filament.ai.actions')->viewData(fn (): array => ['size' => $this->size]),
        ]);
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
