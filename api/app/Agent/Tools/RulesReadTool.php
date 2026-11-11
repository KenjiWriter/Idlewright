<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolRefused;
use App\Content\Bridge\BridgeUnits;
use App\Content\Studio\Parameter;
use App\Content\Studio\ParameterCatalog;
use App\Content\Studio\SimpleMode;
use App\Filament\Support\FieldHelp;
use App\Models\ContentDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;

/**
 * The numbers of the rules of the game, as the Studio shows them: a section, or the numbers under a pointer, with the unit, the value with the drafts, the published value,
 * the limits of the schema and what the number is for (from the catalog of help of the panel).
 */
final class RulesReadTool extends AgentTool
{
    /** The most numbers one call gives back: narrow it down with a pointer. */
    private const LIMIT = 400;

    private const EXPLAIN_LIMIT = 360;

    protected string $name = 'rules_read';

    protected string $title = 'Read the numbers of the rules';

    protected string $description = 'The numbers of the rules of the game (document rules/starter) in a section or under a pointer: unit, value now (with drafts), published value, limits, and what the number does. Sections are listed by game_overview. Example: section "player" gives the experience thresholds of the levels of a driver.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()->description('A section of the rules, e.g. "player", "car", "tracks", "fuel".'),
            'pointer' => $schema->string()->description('Only numbers whose pointer starts with this, e.g. "/tracks/zlomowo-street/".'),
            'simple_only' => $schema->boolean()->description('Only the numbers of the simple mode of the Studio (prices, rewards, times, levels). Default false.'),
            'locale' => $schema->string()->enum(['pl', 'en', 'fr', 'de', 'pt-BR'])->description('The language of the explanations. Default pl.'),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate([
            'section' => ['nullable', 'string', 'max:64'],
            'pointer' => ['nullable', 'string', 'max:300'],
            'simple_only' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'in:pl,en,fr,de,pt-BR'],
        ]);

        $section = $input['section'] ?? null;
        $pointer = $input['pointer'] ?? null;

        if ($section === null && $pointer === null) {
            throw new ToolRefused('Give a section or a pointer. The sections are listed by game_overview.');
        }

        $definition = ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', 'starter')->first()
            ?? throw new ToolRefused('This game has no rules document.');

        $working = $definition->workingDocument();
        $published = $definition->publishedDocument();
        $section ??= explode('/', ltrim((string) $pointer, '/'))[0];

        if (! array_key_exists($section, $working)) {
            throw new ToolRefused("There is no section {$section}. The sections are listed by game_overview.");
        }

        $simple = new SimpleMode;
        $locale = (string) ($input['locale'] ?? 'pl');
        $rows = [];
        $explain = [];

        foreach (ParameterCatalog::ofTheGame()->parameters($working, $section, $published) as $parameter) {
            if (($pointer !== null && ! str_starts_with($parameter->pointer(), $pointer)) || (($input['simple_only'] ?? false) && ! $simple->keeps($parameter))) {
                continue;
            }

            $rows[] = [
                'pointer' => $parameter->pointer(),
                'unit' => BridgeUnits::token($parameter->unit),
                'value' => $parameter->display(),
                'published' => $parameter->liveDisplay(),
                'min' => $parameter->minDisplay(),
                'max' => $parameter->maxDisplay(),
                'pattern' => $parameter->pattern,
            ];

            $explain[$parameter->pattern] ??= $this->explanation($parameter, $locale);
        }

        $total = count($rows);

        return [
            'document' => 'rules/starter',
            'section' => $section,
            'numbers' => array_slice($rows, 0, self::LIMIT),
            'total' => $total,
            'truncated' => $total > self::LIMIT,
            'explain' => $explain,
        ];
    }

    protected function summary(Request $request, array $data): string
    {
        return "rules {$data['section']}: {$data['total']} numbers";
    }

    private function explanation(Parameter $parameter, string $locale): string
    {
        $entry = FieldHelp::entry('rules.'.$parameter->pattern, str_replace('-', '_', $locale));
        $text = $entry !== null ? trim((string) ($entry['label'] ?? '').': '.(string) ($entry['help'] ?? ''), ': ') : ($parameter->description ?? $parameter->group);

        return Str::limit($text, self::EXPLAIN_LIMIT);
    }
}
