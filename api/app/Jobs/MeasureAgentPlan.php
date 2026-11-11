<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Agent\AgentPlans;
use App\Games\CurrentGame;
use App\Models\AgentPlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Measures a plan of the AI assistant on the queue `agent` (the targets, the gates and the pace take up to minutes), so that submitting it does not hold a call. */
class MeasureAgentPlan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $planId) {}

    /** The plan is measured in its game: the worker is for no game, and the content it reads is that game's. */
    public function handle(CurrentGame $current, AgentPlans $plans): void
    {
        $plan = AgentPlan::query()->find($this->planId);

        if ($plan !== null && $plan->measure_status === AgentPlan::MEASURING) {
            $current->within($plan->game_id, fn () => $plans->measure($plan));
        }
    }

    /** The worker was stopped or ran out of time, so the measure did not get to say what happened. */
    public function failed(Throwable $problem): void
    {
        $plan = AgentPlan::query()->find($this->planId);

        if ($plan?->measure_status === AgentPlan::MEASURING) {
            $plan->forceFill(['measure_status' => AgentPlan::FAILED, 'measure_error' => 'The worker stopped: '.$problem->getMessage(), 'measured_at' => now()])->save();
        }
    }
}
