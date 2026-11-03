<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\Rules;
use App\Domain\Economy\FleetCapStudy;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

/**
 * What the limit of active cars does to the pace of the three kinds of player of the economy simulator, and what the slots of the VIP account are worth: the days to every
 * car of the ladder for the player as the anchors have them, for a free player under the limit and for a player with the VIP account, and the income of the late game of
 * the last two next to each other. The tool for tuning `slots` and `premium.vip` of the rules before they are published (see `FleetCapStudy` for what is played).
 *
 * It fails when the limit changes the pace of a free player by more than the tolerance of the pace gate (the limit would be a change of the game for the players who
 * do not pay), and with `--strict` also when what the VIP account gives is out of the band the owner asked for.
 */
final class EconomyFleet extends Command
{
    use RunsInAGame;

    /** The band, as a multiple of the income of a free player, that the VIP account is meant to give at the end of the ladder. */
    public const BAND = [2.0, 3.5];

    protected $signature = 'economy:fleet
        {--ruleset= : The anchors to play, a file of the shape of content/rules/economy.json (that file when left out)}
        {--player=* : Play only these kinds of players, by their keys (A, B or C)}
        {--strict : Fail also when the income of the VIP account is out of the band}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Show what the limit of active cars and the slots of the VIP account do to the pace of the three kinds of player';

    public function handle(Rules $game, FleetCapStudy $study): int
    {
        $slots = $game->slots();
        $vip = $game->vip();

        if ($slots === null || ! $slots['enabled']) {
            $this->error('The rules of this game have no limit of active cars.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('ruleset') ?: config('ride.content_path').'/rules/economy.json');

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $report = $study->run($data, $slots['base'], $slots['vip'], $vip['cash_bonus_ppm'] ?? 0, array_map('strval', (array) $this->option('player')), $slots['yield_ppm']);
        } catch (JsonException|InvalidArgumentException $problem) {
            $this->error($problem->getMessage());

            return self::FAILURE;
        }

        $tolerance = (float) ($data['gate']['tolerance'] ?? 0.25);
        $failed = false;
        $outside = false;

        $this->info(sprintf('%d cars on the road for everybody, %d more with the VIP account (+%d%% of the cash of a lap). The days are those of the simulator of the anchors, with cash and the level of the driver.', $slots['base'], $slots['vip'], intdiv($vip['cash_bonus_ppm'] ?? 0, 10_000)));

        foreach ($report as $key => $player) {
            $this->newLine();
            $this->line("Player {$key}: {$player['name']}");
            $this->table(
                ['car', 'as the anchors have it (day)', 'free, under the limit (day)', 'with the VIP account (day)', 'VIP is faster by'],
                array_map(static fn (array $row): array => [
                    $row['rung'],
                    $row['baseline_day'] ?? 'never',
                    $row['free_day'] ?? 'never',
                    $row['vip_day'] ?? 'never',
                    $row['free_day'] !== null && $row['vip_day'] !== null && $row['free_day'] > 0 ? sprintf('%d%%', round(100 * ($row['free_day'] - $row['vip_day']) / $row['free_day'])) : '',
                ], $player['rows']),
            );

            $over = $player['free_deviation'] > $tolerance;
            $failed = $failed || $over;
            $this->line(sprintf('The limit moves the pace of a free player by at most %.1f%% (the gate allows %.0f%%).%s', 100 * $player['free_deviation'], 100 * $tolerance, $over ? ' TOO MUCH.' : ''));

            $inBand = $player['ratio'] >= self::BAND[0] && $player['ratio'] <= self::BAND[1];
            $outside = $outside || ! $inBand;
            $this->line(sprintf('At the end of the ladder a free player drives %.2f cars and earns %s a day; with the VIP account %.2f cars and %s: %.2f times as much (the band is %.1f to %.1f).%s', $player['fleet_free'], '$'.number_format($player['free_income']), $player['fleet_vip'], '$'.number_format($player['vip_income']), $player['ratio'], self::BAND[0], self::BAND[1], $inBand ? '' : ' OUTSIDE THE BAND.'));
        }

        $this->newLine();
        $this->line(sprintf('The plain count: %d of %d cars times %.2f of the bonus is %.2f times what a player with two cars earns. Five cars need five tanks on a visit, so a player who looks in once a day gets less.', $slots['base'] + $slots['vip'], $slots['base'], 1 + ($vip['cash_bonus_ppm'] ?? 0) / 1_000_000, ($slots['base'] + $slots['vip']) / max(1, $slots['base']) * (1 + ($vip['cash_bonus_ppm'] ?? 0) / 1_000_000)));

        if ($failed) {
            $this->error('The limit changes the pace of a free player by more than the gate allows.');

            return self::FAILURE;
        }

        if ($outside && $this->option('strict')) {
            $this->error('What the VIP account gives is out of the band.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
