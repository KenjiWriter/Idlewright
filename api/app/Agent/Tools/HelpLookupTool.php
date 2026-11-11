<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolRefused;
use App\Filament\Support\FieldHelp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * The catalog of help of the panel, searched: what a field or a number of the game changes, with an example, the range and a tip, as the panel shows it under the field.
 */
final class HelpLookupTool extends AgentTool
{
    private const LIMIT = 20;

    protected string $name = 'help_lookup';

    protected string $title = 'Look up what a field changes';

    protected string $description = 'Searches the help catalog of the panel (the text shown under every field and number): what a field changes in the game, an example, its range and a tip. Search by a word ("experience", "fuel") or by a key of the catalog ("rules./player/early_xp/*").';

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('A word or a key of the catalog, at least 3 characters.')->required(),
            'locale' => $schema->string()->enum(['pl', 'en', 'fr', 'de', 'pt-BR'])->description('The language of the help. Default pl.'),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:120'],
            'locale' => ['nullable', 'in:pl,en,fr,de,pt-BR'],
        ]);

        $locale = str_replace('-', '_', (string) ($input['locale'] ?? 'pl'));
        $query = mb_strtolower((string) $input['query']);
        $found = [];
        $total = 0;

        foreach (glob(lang_path("{$locale}/help/*.php")) ?: [] as $file) {
            $area = explode('.', basename($file, '.php'))[0];

            foreach (FieldHelp::entries($area, $locale) as $key => $entry) {
                $haystack = mb_strtolower("{$area}.{$key} ".($entry['label'] ?? '').' '.($entry['help'] ?? ''));

                if (! str_contains($haystack, $query)) {
                    continue;
                }

                $total++;

                if (count($found) < self::LIMIT) {
                    $found["{$area}.{$key}"] = array_filter([
                        'label' => $entry['label'] ?? null,
                        'help' => $entry['help'] ?? null,
                        'example' => $entry['example'] ?? null,
                        'range' => $entry['range'] ?? null,
                        'tip' => $entry['tip'] ?? null,
                    ], static fn (mixed $value): bool => is_string($value) && $value !== '');
                }
            }
        }

        if ($found === []) {
            throw new ToolRefused("Nothing in the help catalog matches \"{$input['query']}\". Try another word.");
        }

        return ['query' => $input['query'], 'total' => $total, 'entries' => $found];
    }

    protected function summary(Request $request, array $data): string
    {
        return "help \"{$data['query']}\": {$data['total']} entries";
    }
}
