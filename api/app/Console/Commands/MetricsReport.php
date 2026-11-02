<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Services\Metrics;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** The numbers of the alpha: do the drivers come back, how long do they stay, and do they get to what the first minutes lead to. Worked out from the game's own data. */
final class MetricsReport extends Command
{
    use RunsInAGame;

    protected $signature = 'metrics:report
        {--days=30 : The accounts made in this many last days make the cohort}
        {--by= : `arm` compares the drivers who are told by push with the group that never is}
        {--json : Print the report as JSON}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Tell whether the drivers come back (D1, D2, D3, D7), how long they stay and how far the first minutes take them';

    public function handle(Metrics $metrics): int
    {
        $days = (string) $this->option('days');

        if (! ctype_digit($days) || (int) $days < 1 || (int) $days > 3650) {
            $this->error('The days are a whole number from 1 to 3650.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now();

        if ($this->option('by') !== null) {
            return $this->byArm($metrics, $now, (int) $days);
        }

        $report = $metrics->report($now->startOfDay()->subDays((int) $days - 1), $now->addSecond(), $now);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $cohort = $report['cohort'];
        $this->info("Accounts made from {$cohort['from']} to {$cohort['to']}: {$cohort['accounts']} ({$cohort['secured']} secured, {$cohort['guests']} guests).");

        $this->newLine();
        $this->line('Retention: of the accounts for which the day is over, how many came on that day after the day they were made');
        $this->table(['day', 'eligible', 'returned', 'rate'], collect($report['retention'])->map(fn (array $row, string $day): array => [strtoupper($day), $row['eligible'], $row['returned'], $this->percent($row['rate'])])->values()->all());

        $sessions = $report['sessions'];
        $this->line("Sessions: {$sessions['sessions']} of {$sessions['drivers']} drivers who came, ".($sessions['per_driver'] ?? '-').' a driver, the median is '.($sessions['median_minutes'] ?? '-').' minutes (visits less than '.Metrics::SESSION_BREAK_MINUTES.' minutes apart are one session; the length is counted from the first visit to the last, and a visit is at least '.Metrics::VISIT_MINUTES.' minutes).');

        $this->newLine();
        $this->line('The first minutes of the cohort');
        $this->table(
            ['step', 'did it', 'rate', 'median (min)', 'within the target'],
            collect($report['first_steps'])->map(fn (array $row, string $step): array => [$step, $row['reached'], $this->percent($row['rate']), $row['median_minutes'] ?? '-', "{$row['within_target']} within {$row['target_minutes']} min"])->values()->all(),
        );
        $this->table(['tutorial', 'reached', 'rate'], collect($report['tutorial'])->map(fn (array $row, string $step): array => [str_replace('_', ' ', $step), $row['reached'], $this->percent($row['rate'])])->values()->all());

        $this->newLine();
        $this->line('Who came on each of the last fourteen days (all accounts)');
        $this->table(['day', 'active', 'new', 'returning'], $report['activity']);

        $this->newLine();
        $this->line("The missions of the day (all accounts); the bonuses gave {$report['missions']['coins_30_days']} premium coins in thirty days");
        $this->table(['day', 'sets drawn', 'done whole', 'bonus taken'], $report['missions']['days']);
        $this->line("The gigs of the day (all accounts); they paid {$report['gigs']['paid_cents_30_days']} cents in thirty days");
        $this->table(['day', 'gigs taken', 'drivers', 'paid, cents'], $report['gigs']['days']);

        return self::SUCCESS;
    }

    /** The comparison of the two groups of the push experiment. */
    private function byArm(Metrics $metrics, CarbonImmutable $now, int $days): int
    {
        if ($this->option('by') !== 'arm') {
            $this->error('--by takes `arm`.');

            return self::FAILURE;
        }

        $report = $metrics->byArm($now->startOfDay()->subDays($days - 1), $now->addSecond(), $now);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info("Do the notifications make the drivers come back less? The accounts of the last {$days} days, by the group of the experiment.");

        foreach ($report['arms'] as $name => $arm) {
            $this->newLine();
            $this->line(($name === 'push' ? 'Told by push' : 'Never told (holdout)').": {$arm['accounts']} accounts, {$arm['sessions']['sessions']} sessions of {$arm['sessions']['drivers']} drivers who came");
            $this->table(['day', 'eligible', 'returned', 'rate'], collect($arm['retention'])->map(fn (array $row, string $day): array => [strtoupper($day), $row['eligible'], $row['returned'], $this->percent($row['rate'])])->values()->all());
        }

        $this->newLine();
        $this->line('The difference of the retention (told minus never told) with the interval of 95 of 100; an interval that holds zero says nothing yet');
        $this->table(['day', 'difference', 'from', 'to'], collect($report['difference'])->map(fn (?array $row, string $day): array => $row === null
            ? [strtoupper($day), '-', '-', '-']
            : [strtoupper($day), $this->points($row['diff']), $this->points($row['low']), $this->points($row['high'])])->values()->all());

        $held = $report['arms']['holdout']['accounts'];
        $this->line($held < 300 ? "The holdout has {$held} accounts: about 300 would show a difference of ten points and about 1250 a difference of five, so this is a direction at most." : "The holdout has {$held} accounts.");
        $this->line("Of the told group {$report['subscribed']['accounts']} have a device. (Comparing only them with the holdout is a biased comparison: those who agree to be told are those who would have come back anyway.)");

        $messages = $report['messages'];
        $this->newLine();
        $this->line("Messages queued: {$messages['sent']}, opened {$messages['opened']} (".$this->percent($messages['opened_rate']).'), followed by a visit within an hour '.$messages['returned_within_hour'].' ('.$this->percent($messages['returned_rate']).').');

        return self::SUCCESS;
    }

    /** A difference of two rates, in percentage points. */
    private function points(float $difference): string
    {
        return sprintf('%+.1f points', $difference * 100);
    }

    private function percent(?float $rate): string
    {
        return $rate === null ? '-' : number_format($rate * 100, 1).'%';
    }
}
