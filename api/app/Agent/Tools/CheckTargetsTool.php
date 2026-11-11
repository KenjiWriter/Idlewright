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
 * Checks targets in numbers (D7 of the design) on the variant of the content it is asked for: the levels and the cash by the progress simulator, the days of the cars of
 * the ladder by the economy simulator, and the gates. A plan of the assistant will carry these targets, and the person who approves it reads this table.
 */
final class CheckTargetsTool extends StudyTool
{
    protected string $name = 'check_targets';

    protected string $title = 'Check targets in numbers';

    protected string $description = 'Checks up to 20 targets in numbers on live, drafts or scratch and says for each the target, what was measured and whether it is met. Types: level_at_minute {level, minute, tolerance_percent?, plan?}, can_afford {level, items, count?, plan?}, days_to_car {player A|B|C, car (its name in the pace gate of simulate_economy, such as L3), day, tolerance_percent?}, gates {}. A plan is the options of simulate_progress (style, minutes, buy...). With gates it is long: counted by the worker, one long study at a time in a game, picked up with job_result when not done in time.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'targets' => $schema->array()->items($schema->object())->min(1)->max(20)->description('The targets, each {"type": ...} as described.')->required(),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'targets' => ['required', 'array', 'min:1', 'max:20'],
        ];
    }

    protected function run(Request $request): array
    {
        try {
            foreach (ProgressTargets::validate((array) $request->get('targets')) as $target) {
                if (isset($target['plan'])) {
                    ProgressPlan::fromArray((array) $target['plan']);
                }
            }
        } catch (InvalidArgumentException $problem) {
            throw new ToolRefused($problem->getMessage());
        }

        return parent::run($request);
    }
}
