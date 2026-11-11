<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\Catalog;
use App\Content\ContentSource;
use App\Content\Progress\ProgressPlan;
use App\Content\Progress\ProgressSimulator;
use App\Content\Progress\ProgressTargets;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Plays one driver of the game from a new account (the progress simulator, D13 of the design of the AI bridge v2) and says at which minute each level is reached, with
 * what, what was bought and where the driver drove; with `--targets` it also checks targets of the levels and of the cash (D7). The AI assistant of the panel and the page
 * "Symulator postępu" ask the same.
 */
class SimulateProgress extends Command
{
    use RunsInAGame;

    protected $signature = 'economy:progress
        {--car= : The car to start with (the cheapest car a new driver may start with when left out)}
        {--style=active : How the driver plays: active (no breaks), every (comes back every --every minutes for --visit minutes), or A, B, C (as the economy simulator)}
        {--every=60 : For --style=every: the minutes from one visit to the next}
        {--visit=10 : For --style=every: the minutes of a visit}
        {--minutes=120 : How long to play, in minutes (at most 43200, 30 days)}
        {--days= : How long to play, in days, instead of --minutes}
        {--track= : Drive this track once it is open (the best open track when left out)}
        {--goal=profit : What a track is chosen by: profit or xp (of an hour)}
        {--buy=none : What the driver buys: none, list (the slugs of --list in order) or best (the most added to an hour for a dollar)}
        {--list= : For --buy=list: the slugs of the parts and the cars to buy, in order, separated by commas}
        {--no-tutorial : Start with no tutorial}
        {--step-seconds=60 : How long a step of the tutorial takes at the game, in seconds}
        {--missions : Count the missions of every day}
        {--no-travel : Never go to another city}
        {--targets= : Targets to check, as a JSON list (level_at_minute, can_afford)}
        {--json : Print the answer as JSON}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Play one driver from a new account and say when each level is reached, with what, and what was bought';

    public function handle(ProgressSimulator $simulator, Catalog $catalog, ContentSource $content): int
    {
        try {
            $plan = ProgressPlan::fromArray([
                'car' => $this->option('car'),
                'style' => $this->option('style'),
                'every_minutes' => $this->option('every'),
                'visit_minutes' => $this->option('visit'),
                'minutes' => $this->option('days') !== null ? (int) round((float) $this->option('days') * 1440) : $this->option('minutes'),
                'track' => $this->option('track'),
                'goal' => $this->option('goal'),
                'buy' => $this->option('buy'),
                'list' => (string) $this->option('list'),
                'tutorial' => ! $this->option('no-tutorial'),
                'step_seconds' => $this->option('step-seconds'),
                'missions' => (bool) $this->option('missions'),
                'travel' => ! $this->option('no-travel'),
            ]);
            $targets = $this->option('targets') === null ? [] : ProgressTargets::validate((array) json_decode((string) $this->option('targets'), true));
            $player = in_array($plan->style, ['A', 'B', 'C'], true) ? $this->player($content, $plan->style) : null;
            $result = $simulator->simulate($plan, $player);
        } catch (InvalidArgumentException $problem) {
            $this->error($problem->getMessage());

            return self::FAILURE;
        }

        if ($targets !== []) {
            $result['targets'] = array_map(fn (array $target): array => in_array($target['type'], ['level_at_minute', 'can_afford'], true)
                ? ProgressTargets::fromProgress($target, $result, $this->prices($catalog, (array) ($target['items'] ?? [])))
                : ['type' => $target['type'], 'target' => $target['type'], 'measured' => 'not counted by economy:progress: the assistant checks it with check_targets', 'ok' => false], $targets);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->print($result);

        return self::SUCCESS;
    }

    /**
     * The player of the economy simulator (its visits a day), from the anchors of the game.
     *
     * @return array{visits_per_day: int}
     */
    private function player(ContentSource $content, string $key): array
    {
        $anchors = $content->has('rules/economy')
            ? $content->document('rules/economy')
            : (array) json_decode((string) @file_get_contents(config('ride.content_path').'/rules/economy.json'), true);

        $player = $anchors['players'][$key] ?? throw new InvalidArgumentException("The anchors of the economy have no player {$key}.");

        return ['visits_per_day' => (int) $player['visits_per_day']];
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, int>
     */
    private function prices(Catalog $catalog, array $slugs): array
    {
        $prices = [];

        foreach ($slugs as $slug) {
            $price = ($catalog->part((string) $slug) ?? $catalog->vehicle((string) $slug))['price_cents'] ?? null;

            if ($price !== null) {
                $prices[(string) $slug] = (int) $price;
            }
        }

        return $prices;
    }

    /** @param  array<string, mixed>  $result */
    private function print(array $result): void
    {
        $summary = $result['summary'];
        $this->info("After {$summary['minutes']} minutes: level {$summary['level']}, {$summary['xp']} XP (tutorial {$summary['xp_tutorial']}, laps {$summary['xp_laps']}, missions {$summary['xp_missions']}), {$summary['cash_usd']} USD, {$summary['laps']} laps, driving {$summary['driving_minutes']} min.");

        $this->table(['level', 'minute', 'XP', 'from the tutorial', 'from laps', 'from missions', 'cash (USD)', 'car', 'track'], array_map(
            static fn (array $row): array => [$row['level'], $row['minute'], $row['xp'], $row['xp_tutorial'], $row['xp_laps'], $row['xp_missions'], $row['cash_usd'], $row['car'].' (L'.$row['car_level'].')', $row['track'] ?? '-'],
            $result['levels'],
        ));

        if ($result['purchases'] !== []) {
            $this->table(['minute', 'bought', 'price (USD)', 'level', 'cash after (USD)'], array_map(
                static fn (array $row): array => [$row['minute'], $row['slug'], $row['price_usd'], $row['level'], $row['cash_after_usd']],
                $result['purchases'],
            ));
        }

        if ($result['moves'] !== []) {
            $this->table(['minute', 'what', 'from', 'to', 'level'], array_map(
                static fn (array $row): array => [$row['minute'], $row['kind'], $row['from'] ?? '-', $row['to'], $row['level']],
                $result['moves'],
            ));
        }

        foreach ($result['targets'] ?? [] as $target) {
            $this->line(($target['ok'] ? 'MET    ' : 'NOT MET ').$target['target'].': '.$target['measured']);
        }

        foreach ([...array_column($result['notes'], 'text'), ...array_values($result['assumptions'])] as $line) {
            $this->line('- '.$line);
        }
    }
}
