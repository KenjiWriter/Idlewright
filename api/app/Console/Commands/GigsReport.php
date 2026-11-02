<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GigReport;
use Illuminate\Console\Command;

/** What an hour of a gig is worth against an hour of laps, for every car of the pack and every template it can take (the rule of the design: 60 to 90%). Reads, writes nothing. */
class GigsReport extends Command
{
    protected $signature = 'gigs:report
        {--min=0.55 : the least that a gig may pay an hour, against the rung of the ladder of the car (R1)}
        {--max=0.95 : the most that a gig may pay an hour, against the rung of the ladder of the car (R1)}
        {--json : Print the report as JSON}
        {--strict : Fail when a row is out of the bounds, or a gig beats the laps of a car that is made for them (R2 above 1)}';

    protected $description = 'Measure the pay of an hour of gigs against the rung of the ladder (R1) and against the laps of the same car (R2), for every car of the pack';

    public function handle(GigReport $report): int
    {
        $rows = $report->rows();
        $offences = $report->offences($rows, (float) $this->option('min'), (float) $this->option('max'));

        if ($this->option('json')) {
            $this->line((string) json_encode(['rows' => $rows, 'offences' => $offences], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $offences !== [] && $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->table(
            ['car', 'level', 'gig', 'km/h', 'net $/h', 'rung $/h', 'R1', 'best track', 'track $/h', 'R2'],
            array_map(static fn (array $row): array => [$row['car'], $row['level'], $row['template'], $row['speed_kmh'], $row['net_per_hour'], $row['anchor'], $row['r1'], $row['track'], $row['track_net'], $row['r2']], $rows),
        );

        if ($offences === []) {
            $this->info('Every gig pays an hour inside the bounds, and none beats the laps of a car that is made for them.');

            return self::SUCCESS;
        }

        foreach ($offences as $offence) {
            $this->warn(sprintf('%s on %s: %s (R1 %.3f, R2 %.3f)', $offence['car'], $offence['template'], $offence['why'] === 'r1' ? 'the pay of an hour is out of the bounds' : 'it beats the laps of this car', $offence['r1'], $offence['r2']));
        }

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
