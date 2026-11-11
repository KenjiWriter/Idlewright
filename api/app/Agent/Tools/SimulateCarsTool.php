<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentScratch;
use App\Agent\ToolRefused;
use App\Content\Publishing\TestCarPreview;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;

/**
 * Test cars on the tracks of the game (`economy:preview`, the preview the forms of the panel show): the lap, the pay, the experience and the profit of an hour, on the live
 * pack and on the drafts (with the scratch over them, when asked), side by side. It takes seconds, so it is counted while the call waits.
 */
final class SimulateCarsTool extends AgentTool
{
    protected string $name = 'simulate_cars';

    protected string $title = 'Test cars on the tracks';

    protected string $description = 'Plays up to 8 test cars on the tracks: lap, place among the bots, laps an hour, pay, fuel cost, profit and experience of an hour, on the live pack and on the drafts (with the scratch laid over the drafts when with_scratch is true), with the difference. A test car is a car slug, its level and its parts by slot, e.g. {"car":"rdzak-gt-4","level":3,"parts":{"tires":"street-tires-t2","engine":{"slug":"engine-r4-1-6-t2","rarity":2,"upgrade":1}}}. Read slugs with catalog_list.';

    /** The services are made when the tool runs: listing the tools must work in a game that has no live pack yet. */
    public function __construct(private readonly AgentContext $context) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'cars' => $schema->array()->items($schema->object())->min(1)->max(TestCarPreview::MAX_CARS)->description('The test cars: {label?, car, level?, parts?: {slot: slug or {slug, rarity 1-5, upgrade}}}.')->required(),
            'tracks' => $schema->array()->items($schema->string())->description('Only these tracks, by slug (all tracks when left out).'),
            'with_scratch' => $schema->boolean()->description('Lay the scratch over the drafts. Default false.'),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate([
            'cars' => ['required', 'array', 'min:1', 'max:'.TestCarPreview::MAX_CARS],
            'cars.*' => ['array'],
            'tracks' => ['nullable', 'array', 'max:40'],
            'tracks.*' => ['string', 'max:64'],
            'with_scratch' => ['nullable', 'boolean'],
        ]);

        $replace = ($input['with_scratch'] ?? false) ? app(AgentScratch::class)->replacements($this->context->grant()) : [];

        try {
            $answer = app(TestCarPreview::class)->preview(array_values($input['cars']), isset($input['tracks']) ? array_values($input['tracks']) : null, $replace);
        } catch (InvalidArgumentException $problem) {
            throw new ToolRefused($problem->getMessage());
        }

        if ($answer['draft'] !== null) {
            $answer['draft']['source']['kind'] = $replace === [] ? 'drafts' : 'drafts with the scratch';
        }

        return $answer;
    }

    protected function summary(Request $request, array $data): string
    {
        return 'test cars: '.count($data['cars']).($data['draft'] === null ? ', live only' : ', '.$data['draft']['source']['kind']);
    }
}
