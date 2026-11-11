<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentJobs;
use App\Agent\AgentVariants;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

/**
 * A simulator of the game as a tool of the assistant: it counts the study of its name (`AgentStudies`) on the variant of the content it is asked for, picks up what was counted before
 * on the same content, and for a long study gives the number of a job to ask `job_result` for when it is not done in time.
 */
abstract class StudyTool extends AgentTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'variant' => $schema->string()->enum(AgentVariants::ALL)->description('The content to count: "live" (the pack players have), "drafts" (what a pack made now would hold) or "scratch" (the drafts with your scratch over them).')->required(),
            ...$this->studySchema($schema),
        ];
    }

    /**
     * The inputs of the study besides the variant.
     *
     * @return array<string, Type>
     */
    protected function studySchema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * The rules of validation of those inputs.
     *
     * @return array<string, mixed>
     */
    protected function studyRules(): array
    {
        return [];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate(['variant' => ['required', Rule::in(AgentVariants::ALL)], ...$this->studyRules()]);

        return app(AgentJobs::class)->ask($this->name(), $input['variant'], $input);
    }

    protected function summary(Request $request, array $data): string
    {
        $line = "{$data['study']} on {$data['variant']}: {$data['status']}";

        if ($data['cached'] ?? false) {
            $line .= ' (counted before)';
        }

        return $data['job'] === null ? $line : "{$line}, job {$data['job']}";
    }
}
