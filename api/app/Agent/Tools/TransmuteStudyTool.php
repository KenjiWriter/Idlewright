<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class TransmuteStudyTool extends StudyTool
{
    protected string $name = 'transmute_study';

    protected string $title = 'Transmutation of parts';

    protected string $description = 'Transmutation of parts (economy:transmute): what a step costs against what the cash of a step is; with simulate it plays a player with coins in the economy simulator and says whether transmutation moves the day a car is bought by more than the pace gate lets it. A report as text.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'simulate' => $schema->boolean()->description('Also play it in the economy simulator.'),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'simulate' => ['nullable', 'boolean'],
        ];
    }
}
