<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class ClassJumpTool extends StudyTool
{
    protected string $name = 'class_jump';

    protected string $title = 'A car through the classes';

    protected string $description = 'How a car moves through the classes as parts go on it, and its lap on some tracks at each step (competition:class-jump). About a second.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'car' => $schema->string()->description('The car to try (default rdzak-gt-4).'),
            'tracks' => $schema->string()->description('The tracks, by slug, separated by commas (default four tracks of different kinds).'),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'car' => ['nullable', 'string', 'max:64'],
            'tracks' => ['nullable', 'string', 'max:400'],
        ];
    }
}
