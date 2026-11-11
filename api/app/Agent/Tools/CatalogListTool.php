<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Content\Bridge\BridgeUnits;
use App\Content\Bridge\DocumentPointer;
use App\Content\Bridge\ParameterIndex;
use App\Models\ContentDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * The cars or the parts of the game as a table: the slug, the name, the facts that tell one from another (slot, tier, drivetrain, class, kind of tire) and every number
 * with its unit, as they are with the drafts. The parts can be narrowed to a slot and a tier.
 */
final class CatalogListTool extends AgentTool
{
    /** The fields that tell one car or part from another, as the bridge names them. */
    private const FACTS = ['class', 'drivetrain', 'powertrain', 'slot', 'tier', 'tire_kind', 'engine/layout'];

    protected string $name = 'catalog_list';

    protected string $title = 'List cars or parts';

    protected string $description = 'The cars (kind "vehicle") or the parts (kind "part") of the game as a table: slug, name, facts (slot, tier, drivetrain, class) and every number with its unit, as it is now with the drafts. Withdrawn items are marked; archived ones are left out. Narrow the parts with slot and tier.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['vehicle', 'part'])->description('vehicle or part')->required(),
            'slot' => $schema->string()->description('Only parts of this slot, e.g. "engine", "tires".'),
            'tier' => $schema->integer()->description('Only parts of this tier.'),
            'locale' => $schema->string()->enum(['pl', 'en', 'fr', 'de', 'pt-BR'])->description('The language of the names. Default pl.'),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate([
            'kind' => ['required', 'in:vehicle,part'],
            'slot' => ['nullable', 'string', 'max:64'],
            'tier' => ['nullable', 'integer'],
            'locale' => ['nullable', 'in:pl,en,fr,de,pt-BR'],
        ]);

        $locale = (string) ($input['locale'] ?? 'pl');
        $units = [];
        $items = [];

        $definitions = ContentDefinition::query()->where('kind', $input['kind'])->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)->orderBy('slug')->get();

        foreach ($definitions as $definition) {
            $document = $definition->workingDocument();

            if (isset($input['slot']) && ($document['slot'] ?? null) !== $input['slot']) {
                continue;
            }

            if (isset($input['tier']) && (int) ($document['tier'] ?? -1) !== (int) $input['tier']) {
                continue;
            }

            $facts = [];

            foreach (self::FACTS as $fact) {
                $value = DocumentPointer::get($document, explode('/', $fact));

                if (is_string($value) || is_int($value)) {
                    $facts[$fact] = $value;
                }
            }

            $numbers = [];

            foreach ((new ParameterIndex("{$definition->kind}:{$definition->slug}", $document))->all() as $parameter) {
                $numbers[$parameter->pointer()] = $parameter->display();
                $units[$parameter->pointer()] = BridgeUnits::token($parameter->unit);
            }

            $items[] = [
                'slug' => $definition->slug,
                'name' => (string) ($document['name'][$locale] ?? $document['name']['en'] ?? $definition->slug),
                'status' => $definition->status,
                'has_draft' => $definition->hasDraft(),
                'facts' => $facts,
                'numbers' => $numbers,
            ];
        }

        return ['kind' => $input['kind'], 'count' => count($items), 'units' => $units, 'items' => $items];
    }

    protected function summary(Request $request, array $data): string
    {
        return "{$data['kind']}: {$data['count']} items";
    }
}
