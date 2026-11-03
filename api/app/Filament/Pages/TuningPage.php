<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Rules;
use App\Domain\Market\Tuning;
use App\Filament\Concerns\ForAGame;
use App\Filament\Support\FieldHelp;
use App\Services\TuningBudget;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * The bonuses of the tuning that a part can be given in the game: for each one its name in every language, what it does and where, the slots it fits, its weight in a draw and
 * the level it opens at, and the bell of what a try gives (the mean and the spread of its rules) with how many tries it takes on average to reach a value. Under the list it works out
 * what the best set of them is worth, with the numbers of `tuning:budget`, and says whether the gate of the publication would let it through. It changes nothing: the numbers are
 * changed in the parameter studio.
 */
class TuningPage extends Page
{
    use ForAGame;

    protected static ?string $slug = 'tuning';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.tuning.page';

    /** The values a try is asked for in "how many tries on average", in percent. */
    private const REACH = [6, 8, 10, 12];

    /** @var array<string, mixed>|null what the best set came to when it was last asked (the report of the budget) */
    public ?array $budget = null;

    public bool $calculated = false;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.tuning.nav');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tuning.title');
    }

    /** Works out the best set of the bonuses against the limits of the gate. */
    public function calculateBudget(): void
    {
        $this->budget = app(TuningBudget::class)->report();
        $this->calculated = true;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $tuning = app(Rules::class)->tuning();
        $bonuses = [];

        foreach ((array) ($tuning['definitions'] ?? []) as $key => $bonus) {
            $bonuses[$key] = [...$bonus, 'bars' => $this->bars($bonus), 'reach' => $this->reach($bonus)];
        }

        return [
            'settings' => array_diff_key($tuning, ['definitions' => 1]),
            'bonuses' => $bonuses,
            'studioUrl' => StudioPage::getUrl(),
            'guide' => FieldHelp::guideBody('tuning'),
            'budget' => $this->budget,
            'calculated' => $this->calculated,
            'guideTitle' => new HtmlString(e((string) (FieldHelp::entry('guide.tuning')['label'] ?? ''))),
        ];
    }

    /**
     * The bell of what a try gives: the chance of each whole percent between the least and the most of the bonus, from the exact distribution (nothing is drawn).
     *
     * @param  array<string, mixed>  $bonus
     * @return list<array{percent: int, chance: float}>
     */
    private function bars(array $bonus): array
    {
        $bars = [];

        for ($percent = (int) round($bonus['min_ppm'] / 10_000); $percent <= (int) round($bonus['max_ppm'] / 10_000); $percent++) {
            $bars[] = ['percent' => $percent, 'chance' => Tuning::chanceAtLeast($bonus, $percent * 10_000 - 5_000) - Tuning::chanceAtLeast($bonus, $percent * 10_000 + 5_000)];
        }

        return $bars;
    }

    /**
     * How many tries it takes on average to give at least each of the values, or null for one that no try can give.
     *
     * @param  array<string, mixed>  $bonus
     * @return array<int, float|null>
     */
    private function reach(array $bonus): array
    {
        $reach = [];

        foreach (self::REACH as $percent) {
            $reach[$percent] = Tuning::triesToReach($bonus, $percent * 10_000);
        }

        return $reach;
    }
}
