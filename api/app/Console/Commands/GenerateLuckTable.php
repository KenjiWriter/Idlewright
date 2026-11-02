<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Competition\LuckTable;
use Illuminate\Console\Command;

/** Writes the table of the luck of a run that matches read, so no logarithm or root is computed while the game runs. */
final class GenerateLuckTable extends Command
{
    protected $signature = 'content:luck-table
        {--check : Only check that the committed table is up to date}';

    protected $description = 'Generate content/tables/luck-ppm.json';

    public function handle(): int
    {
        $path = config('ride.content_path').'/tables/luck-ppm.json';
        $json = LuckTable::computedJson();

        if ($this->option('check')) {
            if (is_file($path) && file_get_contents($path) === $json) {
                $this->info('The luck table is up to date.');

                return self::SUCCESS;
            }

            $this->error('The luck table is missing or stale. Run php artisan content:luck-table.');

            return self::FAILURE;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $json);
        $this->info("Wrote {$path}");

        return self::SUCCESS;
    }
}
