<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentJobs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/** What came of a long study that was not done in time: waits for it for a while, then gives the result, the error, or that it is still being counted. */
final class JobResultTool extends AgentTool
{
    protected string $name = 'job_result';

    protected string $title = 'Pick up a long study';

    protected string $description = 'Picks up a long study (run_gates, tracks_simulate) by the job number a simulation tool gave when it was not done in time. Waits for it for up to about a minute; then gives the result, the error, or that it is still being counted.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'job' => $schema->integer()->min(1)->description('The number of the job.')->required(),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate(['job' => ['required', 'integer', 'min:1']]);

        return app(AgentJobs::class)->result((int) $input['job']);
    }

    protected function summary(Request $request, array $data): string
    {
        return "job {$data['job']} {$data['study']}: {$data['status']}";
    }
}
