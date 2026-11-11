<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolRefused;
use App\Content\Progress\ProgressPlan;
use App\Content\Progress\ProgressTargets;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;

/**
 * The progress simulator (`economy:progress`, D13 of the design): one driver from a new account, minute by minute, on the variant of the content it is asked for, with the
 * targets of the levels and of the cash checked when it is given them (D7).
 */
final class SimulateProgressTool extends StudyTool
{
    protected string $name = 'simulate_progress';

    protected string $title = 'Progress of a new driver';

    protected string $description = 'Plays one ideal driver from a new account: the tutorial and its rewards, laps on the best open track (or the one given), fuel, the workshop, purchases and journeys. Answers the minute each level is reached with where its experience came from (tutorial, laps, missions), the cash, the car and the track, what was bought when, and what the simulator leaves out (assumptions). Ways of playing: active (no breaks), every (comes back every every_minutes for visit_minutes), A, B, C (the players of the economy simulator). Give targets (level_at_minute, can_afford) to have them checked. About a second, even for 30 days.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'car' => $schema->string()->description('The car to start with (the cheapest car a new driver may start with when left out).'),
            'style' => $schema->string()->enum(ProgressPlan::STYLES)->description('How the driver plays (default active).'),
            'every_minutes' => $schema->integer()->min(1)->description('For style every: the minutes from one visit to the next (default 60).'),
            'visit_minutes' => $schema->integer()->min(1)->description('For style every: the minutes of a visit (default 10).'),
            'minutes' => $schema->integer()->min(1)->max(ProgressPlan::MAX_MINUTES)->description('How long to play, in minutes (default 120, at most 43200).'),
            'days' => $schema->number()->description('How long to play, in days, instead of minutes (at most 30).'),
            'track' => $schema->string()->description('Drive this track once it is open (the best open track when left out).'),
            'goal' => $schema->string()->enum(ProgressPlan::GOALS)->description('What a track is chosen by: profit or xp of an hour (default profit).'),
            'buy' => $schema->string()->enum(ProgressPlan::BUYING)->description('What the driver buys: none, list (the slugs of list, in order) or best (the most added to an hour for a dollar). Default none.'),
            'list' => $schema->array()->items($schema->string())->description('For buy list: slugs of parts and cars, in order.'),
            'tutorial' => $schema->boolean()->description('Play the tutorial first (default true).'),
            'step_seconds' => $schema->integer()->min(1)->max(3600)->description('The seconds a step of the tutorial takes at the game (default 60).'),
            'missions' => $schema->boolean()->description('Count the missions of every day (default false).'),
            'travel' => $schema->boolean()->description('Go to another city where the driver has a garage when it pays (default true).'),
            'targets' => $schema->array()->items($schema->object())->description('Targets to check: {"type":"level_at_minute","level":15,"minute":120,"tolerance_percent":15} or {"type":"can_afford","level":8,"items":["street-tires-t2"],"count":1}.'),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'car' => ['nullable', 'string', 'max:64'],
            'style' => ['nullable', 'string'],
            'every_minutes' => ['nullable', 'integer', 'min:1'],
            'visit_minutes' => ['nullable', 'integer', 'min:1'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:'.ProgressPlan::MAX_MINUTES],
            'days' => ['nullable', 'numeric', 'min:0.01', 'max:30'],
            'track' => ['nullable', 'string', 'max:64'],
            'goal' => ['nullable', 'string'],
            'buy' => ['nullable', 'string'],
            'list' => ['nullable', 'array', 'max:50'],
            'list.*' => ['string', 'max:64'],
            'tutorial' => ['nullable', 'boolean'],
            'step_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'missions' => ['nullable', 'boolean'],
            'travel' => ['nullable', 'boolean'],
            'targets' => ['nullable', 'array', 'max:20'],
        ];
    }

    protected function run(Request $request): array
    {
        try {
            ProgressPlan::fromArray($request->all());

            if (! empty($request->get('targets'))) {
                foreach (ProgressTargets::validate((array) $request->get('targets')) as $target) {
                    if (! in_array($target['type'], ['level_at_minute', 'can_afford'], true)) {
                        throw new InvalidArgumentException("simulate_progress checks level_at_minute and can_afford; check {$target['type']} with check_targets.");
                    }
                }
            }
        } catch (InvalidArgumentException $problem) {
            throw new ToolRefused($problem->getMessage());
        }

        return parent::run($request);
    }
}
