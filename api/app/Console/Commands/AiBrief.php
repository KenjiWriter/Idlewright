<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\Bridge\BriefRecorder;
use App\Content\Bridge\BriefSelection;
use Illuminate\Console\Command;

/**
 * The instruction for an AI chat, from the console: the same text the panel copies, made and recorded the same way (its code is what an answer to it carries). Without
 * `--out` the text is printed; with it the text goes to the file and a line says how big it is and what it holds.
 */
final class AiBrief extends Command
{
    use RunsInAGame;

    protected $signature = 'ai:brief
        {--out= : A file to write the instruction to (it is printed when left out)}
        {--locale=pl : The language of the descriptions (pl, en, fr, de, pt-BR)}
        {--without=* : What to leave out: vehicles, parts or rules}
        {--section=* : Only these sections of the rules (the top level of the rules, as the studio lists them)}
        {--all-numbers : Every number of the sections, not only those of the simple mode of the studio}
        {--texts : Also the names, descriptions and texts of the content, in every language}
        {--design : The manner of designing a whole game in stages, not of changing parameters}
        {--tutorial : Also the tutorial: its steps, numbers, choices and texts}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Make the instruction for an AI chat about the content of the game, as the panel does';

    public function handle(BriefRecorder $recorder): int
    {
        $without = (array) $this->option('without');
        $selection = new BriefSelection(
            vehicles: ! in_array('vehicles', $without, true),
            parts: ! in_array('parts', $without, true),
            rules: ! in_array('rules', $without, true),
            sections: array_values(array_filter((array) $this->option('section'))),
            simple: ! $this->option('all-numbers'),
            locale: in_array((string) $this->option('locale'), BriefSelection::LOCALES, true) ? (string) $this->option('locale') : 'pl',
            texts: (bool) $this->option('texts'),
            design: (bool) $this->option('design'),
            tutorial: (bool) $this->option('tutorial'),
        );

        $brief = $recorder->prepare($selection);
        $out = (string) $this->option('out');

        if ($out === '') {
            $this->getOutput()->write($brief->text);

            return self::SUCCESS;
        }

        file_put_contents($out, $brief->text);
        $this->info(sprintf('Written to %s: %d KB, about %d tokens (an estimate), code %s, it holds %s.', $out, (int) round($brief->bytes / 1024), $brief->tokens, $brief->code, implode(' ', $brief->scope) ?: 'nothing'));

        return self::SUCCESS;
    }
}
