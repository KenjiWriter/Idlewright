<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\Publishing\PartPreview;
use App\Content\Publishing\TestCarPreview;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

/**
 * What test cars do on the tracks of the game, on the live content and on the draft, side by side (`TestCarPreview`): the lap, the place among the bots, what an hour
 * pays after the fuel and the experience of an hour. The cars are given as JSON, as the panel gives them, in `--cars` or in a file (`--file`); without them the test
 * car of the part preview is played (a car of the factory at the first level).
 *
 * It saves nothing and fails only for cars it cannot read.
 */
final class PreviewTestCars extends Command
{
    use RunsInAGame;

    private const INVALID_INPUT = 2;

    protected $signature = 'economy:preview
        {--cars= : The test cars as JSON: [{"car": "rdzak-gt-4", "level": 8, "parts": {"tires": {"slug": "street-tires-t2", "rarity": 3, "upgrade": 2}}}]}
        {--file= : A file with the test cars as JSON (instead of --cars)}
        {--track=* : Play only these tracks, by slug (all of them when left out)}
        {--json : Print the answer as JSON, the shape that the service gives}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Play test cars on the tracks of the live content and of the draft, side by side';

    public function handle(TestCarPreview $preview): int
    {
        try {
            $cars = $this->cars();
            $tracks = array_values(array_map('strval', (array) $this->option('track')));
            $answer = $preview->preview($cars, $tracks === [] ? null : $tracks);
        } catch (InvalidArgumentException|JsonException $problem) {
            $this->error($problem->getMessage());

            return self::INVALID_INPUT;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->print($answer);

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException when the file is missing
     * @throws JsonException when the cars are not JSON
     */
    private function cars(): array
    {
        $file = (string) $this->option('file');
        $text = (string) $this->option('cars');

        if ($file !== '') {
            if (! is_file($file)) {
                throw new InvalidArgumentException("There is no file {$file}.");
            }

            $text = (string) file_get_contents($file);
        }

        if ($text === '') {
            return [['car' => PartPreview::CAR]];
        }

        $cars = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($cars)) {
            throw new InvalidArgumentException('The test cars must be a list of cars.');
        }

        return array_is_list($cars) ? $cars : [$cars];
    }

    /** @param  array<string, mixed>  $answer */
    private function print(array $answer): void
    {
        $live = $answer['live'];
        $draft = $answer['draft'];
        $this->info('Live content'.($live['source']['pack'] === null ? '' : ' (pack '.$live['source']['pack'].')').($draft === null ? '; the game has no draft to set it against.' : ' against the draft.'));

        foreach ($answer['cars'] as $index => $car) {
            $this->newLine();
            $parts = implode(', ', array_map(static fn (string $slot, array $part): string => "{$slot}: {$part['slug']} r{$part['rarity']} +{$part['upgrade']}", array_keys($car['parts']), $car['parts']));
            $this->line("{$car['label']} ({$car['car']}, level {$car['level']}".($parts === '' ? ', stock' : ", {$parts}").'):');

            foreach (['live' => $live, 'draft' => $draft] as $side => $result) {
                foreach ($result['cars'][$index]['issues'] ?? [] as $issue) {
                    $this->warn("  {$side}: ".$this->describe($issue));
                }
            }

            $rows = [];

            foreach ($live['cars'][$index]['tracks'] as $track => $was) {
                $is = $draft['cars'][$index]['tracks'][$track] ?? null;
                $rows[] = isset($was['error'])
                    ? [$track, $was['error'], '', '', '', '', '', '']
                    : [
                        $track,
                        $this->lap($was['lap_ms']['dry']),
                        $is === null || isset($is['error']) ? '-' : $this->lap($is['lap_ms']['dry']),
                        $was['position'] === null ? '-' : "{$was['position']}/{$was['field_size']}",
                        $is === null || isset($is['error']) || $is['position'] === null ? '-' : "{$is['position']}/{$is['field_size']}",
                        $this->money($was['profit_cents_per_hour']).($is === null || isset($is['error']) ? '' : ' > '.$this->money($is['profit_cents_per_hour'])),
                        $was['xp_per_hour'].($is === null || isset($is['error']) ? '' : ' > '.$is['xp_per_hour']),
                        $this->lap($was['lap_ms']['rain'] ?? null).($is === null || isset($is['error']) ? '' : ' > '.$this->lap($is['lap_ms']['rain'] ?? null)),
                    ];
            }

            $this->table(['track', 'lap live', 'lap draft', 'place live', 'place draft', 'profit an hour', 'XP an hour', 'rain lap'], $rows);
        }
    }

    /** @param  array<string, mixed>  $issue */
    private function describe(array $issue): string
    {
        return match ($issue['code']) {
            'unknown_car' => "there is no car {$issue['car']}.",
            'unknown_part' => "there is no part {$issue['part']} ({$issue['slot']}).",
            'wrong_slot' => "{$issue['part']} is a part for {$issue['part_slot']}, not {$issue['slot']}.",
            'car_level_required' => "{$issue['part']} needs a car of level {$issue['required']}, the test car is {$issue['current']}.",
            'upgrade_above_cap' => "{$issue['part']} cannot be upgraded to +{$issue['upgrade']} at this level (the most is +{$issue['cap']}).",
            default => (string) ($issue['message'] ?? $issue['code']),
        };
    }

    private function lap(?int $ms): string
    {
        return $ms === null ? '-' : number_format($ms / 1000, 3).' s';
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 0, '.', ' ');
    }
}
