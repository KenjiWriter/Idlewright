<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\GameCrates;
use App\Content\Rules;
use App\Domain\Economy\EconomyRules;
use App\Domain\Economy\MergeEconomy;
use App\Domain\Economy\RetentionStudy;
use App\Domain\Economy\TransmuteEconomy;
use App\Domain\Run\Rarity;
use Illuminate\Console\Command;

/**
 * What transmutation of the rarity of a part costs next to what merging costs, from the rules of the game: for each of the three steps the cost of merging (in units of the price
 * of the model, p), the cash of the transmutation and its share of that; for some tiers the coins and what they are worth in money (at the price per coin of the packs); and what a
 * legendary part would cost if transmuted epic parts could be merged (they cannot: `transmute.merge_input`). The tool for tuning `transmute` before it is published.
 *
 * It fails when the cash of a step is below `--min-share` of what merging costs for it (the rule is about a half: below it, merging is not worth it for a driver without coins).
 */
final class EconomyTransmute extends Command
{
    use RunsInAGame;

    /** The tiers the coins are told for. */
    private const TIERS = [1, 3, 5, 8];

    protected $signature = 'economy:transmute
        {--min-share=0.45 : The least share of the cost of merging that the cash of a step may be}
        {--simulate : Play a player with coins in the economy simulator and say whether transmutation moves the day a car is bought by more than the pace gate lets it}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Show what transmutation of the rarity of a part costs next to merging';

    public function handle(Rules $game, RetentionStudy $retention, GameCrates $crates): int
    {
        $rules = $game->transmute();

        if ($rules === null) {
            $this->error('The rules of this game have no transmutation.');

            return self::FAILURE;
        }

        $merge = new MergeEconomy(
            static fn (string $rarity, int $failures): int => $game->mergeChancePpm(Rarity::fromKey($rarity), $failures),
            $game->mergeCostCents(1_000_000),
            $game->mergeScrapCents(1_000_000),
        );

        $zlotyPerCoin = array_values(array_map(static fn (array $pack): float => $pack['price_minor'] / 100 / max(1, $pack['coins']), $game->premiumProducts()));
        $study = new TransmuteEconomy(
            $merge->steps(),
            array_map(static fn (int $ppm): float => $ppm / 1_000_000, $rules['cash_ppm']),
            static fn (int $step, int $tier): int => $game->transmuteCost($step, $tier, 0)['coins'],
            $zlotyPerCoin === [] ? [0.0] : $zlotyPerCoin,
        );

        $this->info(sprintf('Transmutation is %s, coins are %s, the best rarity it makes is %s, a transmuted part %s be merged.', $rules['enabled'] ? 'on' : 'OFF', $rules['coins_enabled'] ? 'part of the price' : 'not asked for', $rules['max_rarity'], $rules['merge_input'] ? 'CAN' : 'cannot'));

        $report = $study->steps();
        $failed = false;
        $minShare = (float) $this->option('min-share');
        $rows = [];

        foreach ($report['steps'] as $step) {
            $below = $step['share'] < $minShare;
            $failed = $failed || $below;
            $rows[] = [$step['rarity'], sprintf('%.2f p', $step['merge_p']), sprintf('%.2f p', $step['cash_p']), sprintf('%d%%', round(100 * $step['share'])), $below ? 'BELOW' : 'ok'];
        }

        $this->table(['step to', 'merging costs (p)', 'transmutation cash (p)', 'share', ''], $rows);
        $this->line(sprintf('The whole way to epic: merging %.2f p, transmutation %.2f p of cash (%d%% of it), and the coins below. p is the price of the model of the part.', $report['whole_way']['merge_p'], $report['whole_way']['cash_p'], round(100 * $report['whole_way']['share'])));

        $this->newLine();
        $coinRows = [];

        foreach (self::TIERS as $tier) {
            $coins = $study->coins($tier);
            $coinRows[] = ["T{$tier}", ...$coins['coins'], $coins['total'], sprintf('%.1f to %.1f zl', $coins['zloty_min'], $coins['zloty_max'])];
        }

        $this->table(['tier', 'to uncommon', 'to rare', 'to epic', 'common to epic', 'worth in the packs'], $coinRows);

        $legendary = $study->legendaryThroughMerging(5, $merge->attempts('legendary'));
        $this->line(sprintf('If transmuted epic parts could be merged, a legendary part of tier 5 would take about %.1f epic parts and %d coins (%.0f to %.0f zl). That is why they cannot.', $legendary['epics'], $legendary['coins'], $legendary['zloty_min'], $legendary['zloty_max']));

        if ($rules['merge_input']) {
            $this->warn('transmute.merge_input is on: the coins buy legendary parts through merging.');
        }

        if ($failed) {
            $this->error(sprintf('The cash of a step is below %d%% of what merging costs for it.', round(100 * $minShare)));

            return self::FAILURE;
        }

        if ($this->option('simulate') && $this->simulate($game, (array) $game->transmute(), $retention, $crates)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Plays a player who has the coins for it: when the level opens a tier of parts and transmutation, the three slots of the table of the pace of the rarities are turned to epic
     * as soon as the cash is there. Whether the day on which a car is bought moved by more than the pace gate lets.
     *
     * @param  array{min_player_level: int, cash_ppm: list<int>}  $rules
     */
    private function simulate(Rules $game, array $rules, RetentionStudy $study, GameCrates $crates): bool
    {
        $table = json_decode((string) file_get_contents(config('ride.content_path').'/tables/rarity-pace.json'), true, 512, JSON_THROW_ON_ERROR);
        // The table lists the gain of uncommon, rare, epic and legendary parts for each slot: the third number is epic.
        $epic = array_map(static fn (array $gains): float => (float) $gains[2], array_intersect_key($table['rarity_gain'], array_flip(['tires', 'suspension', 'brakes'])));
        $economy = EconomyRules::fromArray(json_decode((string) file_get_contents(config('ride.content_path').'/rules/economy.json'), true, 512, JSON_THROW_ON_ERROR));
        $result = $study->transmute($economy, $crates->economy(), $epic, array_map(static fn (int $ppm): float => $ppm / 1_000_000, $rules['cash_ppm']), $rules['min_player_level']);

        $this->newLine();
        $this->line('A player with coins in the economy simulator: the tires, the suspension and the brakes of each tier turned to epic as soon as the cash is there.');
        $this->table(['player', 'income added', 'spent', 'first day'], collect($result['runs'])->map(fn ($run, $key): array => [$key, sprintf('%.1f%%', 100 * ($run->notes['transmute']['uplift'] ?? 0)), $run->notes['transmute']['spent'] ?? 0, $run->notes['transmute']['first_day'] ?? '-'])->values()->all());

        $failures = $result['gate']->failures();
        $this->table(['player', 'car', 'day without', 'day with', 'allowed'], array_map(fn ($row): array => [$row->player, $row->rung, $row->target, $row->day ?? '-', sprintf('%.0f%%', $result['gate']->tolerance * 100)], $failures));
        $failures === []
            ? $this->info(sprintf('Transmutation moves no car by more than %.0f%% of its day (and a day).', $result['gate']->tolerance * 100))
            : $this->warn(sprintf('Transmutation moves %d purchases by more than %.0f%% of the day.', count($failures), $result['gate']->tolerance * 100));

        return $failures !== [];
    }
}
