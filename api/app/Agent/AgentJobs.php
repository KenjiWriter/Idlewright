<?php

declare(strict_types=1);

namespace App\Agent;

use App\Jobs\RunAgentStudy;
use App\Models\AgentGrant;
use App\Models\AgentJob;
use Illuminate\Support\Str;
use Throwable;

/**
 * Has the simulators count a study for the AI assistant (D12, D14 and D18 of the design). The same study on the same content is answered from what was counted before,
 * as the simulators are not random. A light study is counted while the call waits. A heavy one goes to the worker of the queue `agent`: one at a time in a game, at most
 * `ride.agent.heavy_per_hour` an hour for a consent, and the call waits for it for a while and otherwise gives the number of the job, which `job_result` answers later.
 * A tool run from the command line has no consent: it is counted at once and kept nowhere.
 */
final class AgentJobs
{
    /** How often a waiting call looks whether its job is done, in microseconds. */
    private const POLL_US = 500_000;

    public function __construct(
        private readonly AgentStudies $studies,
        private readonly AgentVariants $variants,
        private readonly AgentScratch $scratch,
        private readonly AgentContext $context,
    ) {}

    /**
     * Counts a study, or picks up what was counted, or queues it.
     *
     * @param  array<string, mixed>  $input  what the tool was given
     * @return array<string, mixed>
     *
     * @throws ToolRefused
     */
    public function ask(string $study, string $variant, array $input): array
    {
        try {
            return $this->counted($study, $variant, $input);
        } finally {
            // The simulators run with the game pointed at the content of the study, and forget what the request holds when they are done (see `PackGates`): the
            // consent of the call is put back, so that what comes after in the same request still works under it.
            app()->instance(AgentContext::class, $this->context);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function counted(string $study, string $variant, array $input): array
    {
        $grant = $this->context->grant();
        $options = $this->studies->options($study, $input);
        $full = (bool) ($input['full'] ?? false);
        $replacements = $variant === AgentVariants::SCRATCH ? $this->scratch->replacements($grant) : [];
        ['content' => $content, 'mark' => $mark] = $this->variants->content($variant, $replacements);

        if ($grant === null) {
            return ['job' => null, 'study' => $study, 'variant' => $variant, 'status' => AgentJob::DONE, 'cached' => false, 'result' => $this->studies->run($study, $content, $options, $full)];
        }

        $key = hash('sha256', $study.'|'.$mark.'|'.json_encode($options).'|'.($full ? 'full' : 'short'));
        $known = AgentJob::query()
            ->where('game_id', $grant->game_id)
            ->where('key', $key)
            ->where('status', AgentJob::DONE)
            ->where('finished_at', '>', now()->subHours((int) config('ride.agent.cache_hours')))
            ->latest('id')
            ->first();

        if ($known !== null) {
            return $this->answer($known, cached: true);
        }

        if (! $this->studies->isHeavy($study)) {
            $job = $this->open($grant, $study, $variant, $options, $full, $replacements, $key, AgentJob::RUNNING);
            $this->finish($job, fn (): array => $this->studies->run($study, $content, $options, $full));

            return $this->answer($job, cached: false);
        }

        $busy = AgentJob::query()
            ->where('game_id', $grant->game_id)
            ->whereIn('status', [AgentJob::QUEUED, AgentJob::RUNNING])
            ->whereIn('study', $this->heavyStudies())
            ->where('created_at', '>', now()->subMinutes((int) config('ride.agent.stale_minutes')))
            ->latest('id')
            ->first();

        if ($busy !== null) {
            if ($busy->key === $key) {
                return $this->wait($busy);
            }

            throw new ToolRefused("Another long study of this game is being counted now (job {$busy->id}, {$busy->study}). Long studies go one at a time: ask job_result for job {$busy->id}, then ask again.");
        }

        $lastHour = AgentJob::query()->where('agent_grant_id', $grant->id)->whereIn('study', $this->heavyStudies())->where('created_at', '>', now()->subHour())->count();

        if ($lastHour >= (int) config('ride.agent.heavy_per_hour')) {
            throw new ToolRefused('This consent has had '.$lastHour.' long studies counted in the last hour, which is the limit. Use what was counted (the same question on the same content is answered at once), or wait.');
        }

        $job = $this->open($grant, $study, $variant, $options, $full, $replacements, $key, AgentJob::QUEUED);
        RunAgentStudy::dispatch($job->id)->onQueue('agent');

        return $this->wait($job->refresh());
    }

    /**
     * What came of a job of this consent, waiting for it for a while when it is not done.
     *
     * @return array<string, mixed>
     *
     * @throws ToolRefused
     */
    public function result(int $id): array
    {
        $grant = $this->context->grant() ?? throw new ToolRefused('Jobs belong to a consent of the panel; a tool run from the command line counts at once and has none.');
        $job = AgentJob::query()->where('agent_grant_id', $grant->id)->find($id) ?? throw new ToolRefused("There is no job {$id} of this consent.");

        return $this->wait($job);
    }

    /** Counts the study of a job that the worker took. */
    public function perform(AgentJob $job): void
    {
        if (! $job->isOpen()) {
            return;
        }

        $job->forceFill(['status' => AgentJob::RUNNING, 'started_at' => now()])->save();

        $options = $job->options;
        $full = (bool) ($options['__full'] ?? false);
        unset($options['__full']);

        $this->finish($job, function () use ($job, $options, $full): array {
            ['content' => $content] = $this->variants->content($job->variant, $job->replacements ?? []);

            return $this->studies->run($job->study, $content, $options, $full);
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $replacements
     */
    private function open(AgentGrant $grant, string $study, string $variant, array $options, bool $full, array $replacements, string $key, string $status): AgentJob
    {
        return AgentJob::query()->create([
            'agent_grant_id' => $grant->id,
            'game_id' => $grant->game_id,
            'study' => $study,
            'variant' => $variant,
            // Whether the whole report is asked for goes with the options, so that the worker counts the same thing.
            'options' => $full ? [...$options, '__full' => true] : $options,
            'replacements' => $replacements === [] ? null : $replacements,
            'key' => $key,
            'status' => $status,
            'started_at' => $status === AgentJob::RUNNING ? now() : null,
        ]);
    }

    /** @param  callable(): array<string, mixed>  $count */
    private function finish(AgentJob $job, callable $count): void
    {
        $started = hrtime(true);

        try {
            $result = $count();
            $job->forceFill(['status' => AgentJob::DONE, 'result' => $result, 'error' => null]);
        } catch (Throwable $problem) {
            $job->forceFill(['status' => AgentJob::FAILED, 'error' => Str::limit($problem->getMessage(), 4000, '...')]);
        }

        $job->forceFill(['ms' => (int) ((hrtime(true) - $started) / 1_000_000), 'finished_at' => now()])->save();
    }

    /** @return array<string, mixed> */
    private function wait(AgentJob $job): array
    {
        $until = microtime(true) + (int) config('ride.agent.wait_seconds');

        while ($job->isOpen() && microtime(true) < $until) {
            usleep(self::POLL_US);
            $job->refresh();
        }

        return $this->answer($job, cached: false);
    }

    /** @return array<string, mixed> */
    private function answer(AgentJob $job, bool $cached): array
    {
        $answer = ['job' => $job->id, 'study' => $job->study, 'variant' => $job->variant, 'status' => $job->status, 'cached' => $cached];

        return match ($job->status) {
            AgentJob::DONE => [...$answer, 'ms' => $job->ms, 'counted_at' => $job->finished_at?->toIso8601String(), 'result' => $job->result],
            AgentJob::FAILED => [...$answer, 'error' => $job->error],
            default => [...$answer, 'next' => "Not counted yet. Ask job_result with job {$job->id} in a minute; do not ask for the same study again meanwhile."],
        };
    }

    /** @return list<string> */
    private function heavyStudies(): array
    {
        return array_values(array_filter(AgentStudies::names(), fn (string $study): bool => $this->studies->isHeavy($study)));
    }
}
