<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\Bridge\BridgeIssue;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ResolvedChange;
use Illuminate\Console\Command;

/**
 * Looks at the answer of an AI (the whole message, or the block of changes alone) the way the panel will: says what it would change, number by number, and what is wrong
 * with it. Nothing is written. With `--repair` it prints the message to give back to the AI when the block is refused; the exit code is 1 when it is.
 */
final class AiCheck extends Command
{
    use RunsInAGame;

    protected $signature = 'ai:check
        {file : A text file with the answer of the AI}
        {--scope=* : What the instruction contained: rules:starter, kind:part, part:<slug>. Without it nothing is out of scope}
        {--repair : Print the message to give back to the AI, if the block is refused}
        {--json : Print the result as JSON}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Check a block of changes from an AI against the content of the game, without changing anything';

    public function handle(ChangeChecker $checker): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("There is no file {$file}.");

            return self::FAILURE;
        }

        $scope = array_values(array_filter((array) $this->option('scope')));
        $check = $checker->check((string) file_get_contents($file), $scope === [] ? null : $scope);

        if ($this->option('json')) {
            $this->line((string) json_encode($check->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $check->errors() === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($check->changes !== []) {
            $this->table(
                ['op', 'document', 'where', 'was live', 'now', 'becomes', 'change', 'notes'],
                array_map(static fn (ResolvedChange $change): array => [
                    $change->op.($change->opId !== null ? " ({$change->opId})" : ''),
                    $change->document,
                    $change->label.' '.$change->pointer(),
                    $change->publishedText(),
                    $change->beforeText(),
                    $change->afterText(),
                    sprintf('%+.2f%%', $change->percent()),
                    implode(', ', $change->notes),
                ], $check->changes),
            );
        }

        foreach ($check->issues as $issue) {
            $issue->isError() ? $this->error($this->describe($issue)) : $this->warn($this->describe($issue));
        }

        if ($check->errors() === []) {
            $this->info($check->changes === [] ? 'The block changes nothing.' : count($check->changes).' numbers in '.count($check->documents).' documents would change. Nothing was written.');

            return self::SUCCESS;
        }

        $this->error(count($check->errors()).' errors: the block is not accepted.');

        if ($this->option('repair')) {
            $this->newLine();
            $this->line($check->repairMessage());
        }

        return self::FAILURE;
    }

    private function describe(BridgeIssue $issue): string
    {
        return ($issue->isError() ? 'ERROR ' : 'remark ').$issue->line();
    }
}
