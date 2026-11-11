<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Agent\AgentJobs;
use App\Games\CurrentGame;
use App\Models\AgentJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Counts a long study of the AI assistant (the gates, the simulator of the tracks) on the queue `agent`, so that the call of the assistant does not hold a page of the panel. */
class RunAgentStudy implements ShouldQueue
{
    use Queueable;

    /** A study is counted once: one that failed is asked for again, as a new job. */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $jobId) {}

    /** The study is counted in the game of the consent: the worker is for no game, and the content it reads is that game's. */
    public function handle(CurrentGame $current, AgentJobs $jobs): void
    {
        $job = AgentJob::query()->find($this->jobId);

        if ($job !== null) {
            $current->within($job->game_id, fn () => $jobs->perform($job));
        }
    }

    /** The worker was stopped or ran out of time, so the study did not get to say what happened. */
    public function failed(Throwable $problem): void
    {
        $job = AgentJob::query()->find($this->jobId);

        if ($job?->isOpen()) {
            $job->forceFill(['status' => AgentJob::FAILED, 'error' => 'The worker stopped: '.$problem->getMessage(), 'finished_at' => now()])->save();
        }
    }
}
