<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Agent\AgentPlanExecution;
use App\Games\CurrentGame;
use App\Models\AgentPlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Counts the drafts again after a plan of the AI assistant was written, for the report of what was promised against what was measured, on the queue `agent`. */
class ReportAgentPlan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $planId) {}

    public function handle(CurrentGame $current, AgentPlanExecution $execution): void
    {
        $plan = AgentPlan::query()->find($this->planId);

        if ($plan !== null && $plan->report_status === AgentPlan::MEASURING) {
            $current->within($plan->game_id, fn () => $execution->report($plan));
        }
    }

    public function failed(Throwable $problem): void
    {
        $plan = AgentPlan::query()->find($this->planId);

        if ($plan?->report_status === AgentPlan::MEASURING) {
            $plan->forceFill(['report_status' => AgentPlan::FAILED, 'report_error' => 'The worker stopped: '.$problem->getMessage(), 'reported_at' => now()])->save();
        }
    }
}
