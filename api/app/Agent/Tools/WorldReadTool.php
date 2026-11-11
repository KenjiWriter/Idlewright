<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolRefused;
use App\Models\ContentDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * The world of the game, to read only (the editor of the map is being rebuilt): the regions (cities), the roads between them and the tracks with what each needs and pays.
 */
final class WorldReadTool extends AgentTool
{
    protected string $name = 'world_read';

    protected string $title = 'Read the world';

    protected string $description = 'The world of the game (read only): regions (cities), roads with their kilometres, and every track with its region, surface, kind, the driver level that opens it, length, reference speed, pay per hour and experience per hour. Change the numbers of tracks only through the rules (section "tracks").';

    public function schema(JsonSchema $schema): array
    {
        return [
            'locale' => $schema->string()->enum(['pl', 'en', 'fr', 'de', 'pt-BR'])->description('The language of the names. Default pl.'),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate(['locale' => ['nullable', 'in:pl,en,fr,de,pt-BR']]);
        $locale = (string) ($input['locale'] ?? 'pl');

        $rules = ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', 'starter')->first()?->workingDocument()
            ?? throw new ToolRefused('This game has no rules document.');

        $name = static fn (array $item, string $slug): string => (string) ($item['name'][$locale] ?? $item['name']['en'] ?? $slug);
        $regions = [];
        $tracks = [];

        foreach ((array) ($rules['regions'] ?? []) as $slug => $region) {
            $regions[] = ['slug' => (string) $slug, 'name' => $name((array) $region, (string) $slug)];
        }

        foreach ((array) ($rules['tracks'] ?? []) as $slug => $track) {
            $track = (array) $track;
            $tracks[] = [
                'slug' => (string) $slug,
                'name' => $name($track, (string) $slug),
                'region' => $track['region'] ?? null,
                'surface' => $track['surface'] ?? null,
                'kind' => $track['kind'] ?? null,
                'min_player_level' => $track['min_player_level'] ?? null,
                'length_m' => $track['length_m'] ?? null,
                'speed_kmh' => $track['speed_kmh'] ?? null,
                'pay_per_hour_usd' => isset($track['pay_cents_per_hour']) ? $track['pay_cents_per_hour'] / 100 : null,
                'xp_per_hour' => $track['xp_per_hour'] ?? null,
            ];
        }

        return ['regions' => $regions, 'roads' => array_values((array) ($rules['roads'] ?? [])), 'tracks' => $tracks];
    }

    protected function summary(Request $request, array $data): string
    {
        return 'world: '.count($data['regions']).' regions, '.count($data['tracks']).' tracks';
    }
}
