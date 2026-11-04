<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\ContentException;
use App\Content\FileContentSource;
use App\Content\Rebase\Absent;
use App\Content\Rebase\JsonMerge;
use App\Content\Rebase\RebasePlan;
use App\Services\ContentRebase;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;

/**
 * Brings the content of the panel up to the files of a release (ContentRebase): what the files changed since the base goes into drafts, with what the panel changed
 * kept. The drafts are then published in the panel as any are.
 */
final class RebaseContent extends Command
{
    use RunsInAGame;

    protected $signature = 'content:rebase
        {path? : The content folder of the release (default: the one of the configuration)}
        {--pack=core : The pack inside packs/ of the folder}
        {--base= : The number of the pack the content of the panel came from (default: the files the last rebase took in, or the last pack no publication made)}
        {--prefer= : Who wins where the panel and the files changed the same place in two ways: panel or files (without it such a place stops the rebase)}
        {--dry-run : Only say what would change}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Bring the content of the panel up to the files of a release: what the files changed goes into drafts, and what the panel changed stays';

    private const CHANGES = [
        RebasePlan::FILES => 'taken from the files (the panel had not changed it)',
        RebasePlan::MERGED => 'merged: changed in the panel and in the files',
        RebasePlan::ADDED => 'new in the files: a draft of a new definition',
        RebasePlan::TABLE => 'table made again by the files',
        RebasePlan::KEPT => 'not in the files any more: left as it is (archive it in the panel if it is to go)',
        RebasePlan::ARCHIVED => 'archived in the panel: left archived',
    ];

    public function handle(ContentRebase $rebase): int
    {
        $prefer = $this->option('prefer') ?: null;

        if ($prefer !== null && ! in_array($prefer, [JsonMerge::PANEL, JsonMerge::FILES], true)) {
            $this->error('--prefer is panel or files.');

            return self::FAILURE;
        }

        $path = (string) ($this->argument('path') ?: config('ride.content_path'));
        $files = new FileContentSource($path, (string) $this->option('pack'));

        try {
            $base = $this->option('base') ? $rebase->packBase((string) $this->option('base')) : $rebase->defaultBase();

            if ($base === null) {
                $this->error('There is nothing to start from: the database has no pack. Import one with content:import first.');

                return self::FAILURE;
            }

            $this->line("Base: {$base['label']}.");
            $this->line("Files: {$path}.");

            $plan = $rebase->plan($files, $base['source'], $base['label'], $prefer);
        } catch (ContentException|JsonException $problem) {
            $this->error($problem->getMessage());

            return self::FAILURE;
        }

        if ($plan->nothingNew) {
            $this->info('The files hold nothing that the base did not: nothing to do.');

            return self::SUCCESS;
        }

        $this->report($plan);

        if ($plan->errors !== []) {
            $this->error('The content with these drafts does not pass the validation. Nothing was written.');

            return self::FAILURE;
        }

        if ($plan->conflicts !== [] && $prefer === null) {
            $this->error('The panel and the files changed the places above in two ways. Nothing was written: give --prefer=panel or --prefer=files, or change one side so that they agree.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $upstream = $rebase->apply($plan, $files, $path);

        $this->info(count($plan->drafts).' draft(s) written. These files are the base of the next content:rebase (record '.$upstream->id.').');
        $this->line('Next, in the panel: "Zmiany do publikacji", look at the changes, publish them and make the new pack live.');

        return self::SUCCESS;
    }

    private function report(RebasePlan $plan): void
    {
        if ($plan->rows === []) {
            $this->line('No document changes: the panel has everything the files changed already.');
        } else {
            $this->table(['document', 'what happens'], array_map(fn (array $row): array => [$row['key'], self::CHANGES[$row['change']]], $plan->rows));
        }

        if ($plan->conflicts !== []) {
            $winner = match ($plan->prefer) {
                JsonMerge::PANEL => ' (the panel wins)',
                JsonMerge::FILES => ' (the files win)',
                default => '',
            };

            $this->warn(count($plan->conflicts).' place(s) changed both in the panel and in the files'.$winner.':');

            foreach ($plan->conflicts as $conflict) {
                $this->line("  {$conflict['key']} {$conflict['path']}: base {$this->shown($conflict['base'])}, panel {$this->shown($conflict['panel'])}, files {$this->shown($conflict['files'])}");
            }
        }

        foreach ($plan->errors as $issue) {
            $this->error("{$issue['file']} {$issue['pointer']}: {$issue['message']}");
        }
    }

    private function shown(mixed $value): string
    {
        return $value === Absent::Value ? '(none)' : Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '?', 60);
    }
}
