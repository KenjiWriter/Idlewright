<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Domain\Run\FrozenRun;
use App\Domain\Settlement\EventType;
use App\Domain\Settlement\RunState;
use App\Domain\Settlement\Settler;
use App\Models\PushEvent;
use App\Models\PushSubscription;
use App\Models\RaceRun;

/**
 * Plans when to look at a car that drives, so that a driver who has left can be told it stopped. Settlement is lazy: a car that runs out of fuel has not "stopped" for the server
 * until somebody looks, so the moment is forecast. The settler is run forward on the state of the car as it is, without writing anything, to the first stop (an empty tank or a
 * breakdown, with the auto-refill the driver has bought taken into account) or to the end of the horizon, and the moment is kept in `push_events`. The forecast is only a
 * moment to look: the dispatcher settles the car then, and only a car that has really stopped is told of. A forecast that went wrong (the driver filled the tank, a part changed)
 * costs a look and nothing else, and every look makes the forecast again.
 */
final class PushPlanner
{
    /** How far ahead a car is followed: a car that drives for longer is looked at again when the horizon is reached. */
    public const HORIZON_MS = 86_400_000;

    public function __construct(private readonly Rules $rules, private readonly Settler $settler = new Settler) {}

    /** Whether any device of the driver can be told (the forecast is made only for them: most drivers have none). */
    public function wanted(int $userId): bool
    {
        return $this->rules->pushOn() && PushSubscription::query()->where('user_id', $userId)->whereNull('disabled_at')->exists();
    }

    /** Makes the forecast for a car after it was looked at (called with the run locked). */
    public function planRun(int $runId): void
    {
        $run = RaceRun::query()->find($runId);

        if ($run === null || ! $this->wanted((int) $run->user_id)) {
            return;
        }

        $planned = PushEvent::query()->where('kind', PushEvent::KIND_CAR_STOPPED)->where('ref_type', PushEvent::REF_RUN)->where('ref_id', $run->id)->where('state', PushEvent::STATE_PLANNED);

        // A car that stands for a reason of its own (the workshop, the road, the garage, storage) is not going to stop: it is not driving.
        if (in_array($run->stop_reason, RaceRun::SERVICE_STOPS, true)) {
            $planned->delete();

            return;
        }

        // A car that has stopped is for the dispatcher to tell of, when its time comes; it is not planned again.
        if ($run->status !== RaceRun::STATUS_RUNNING) {
            return;
        }

        $dueMs = $this->forecast($run);
        $event = $planned->first();

        if ($event === null) {
            PushEvent::query()->create(['user_id' => $run->user_id, 'kind' => PushEvent::KIND_CAR_STOPPED, 'ref_type' => PushEvent::REF_RUN, 'ref_id' => $run->id, 'due_ms' => $dueMs, 'state' => PushEvent::STATE_PLANNED]);
        } elseif ($event->due_ms !== $dueMs) {
            $event->forceFill(['due_ms' => $dueMs])->save();
        }
    }

    /** The moment the car is to be looked at next: the moment it stops, the moment its plan has to be made again, or the end of the horizon. */
    public function forecast(RaceRun $run): int
    {
        $state = new RunState($run->cursor_ms, $run->laps_done, $run->fuel_ml, $run->break_after_laps, $run->recompute_after_laps);
        [$refill, $bonus] = (new SettlementPerks($this->rules, app(RunInputsFactory::class)))->forRun($run);
        $horizon = $run->cursor_ms + self::HORIZON_MS;

        $result = $this->settler->settleInChunks($state, $horizon, FrozenRun::configFromArray($run->config), refill: $refill, bonus: $bonus);

        $ends = $result->stopReason() !== null || array_filter($result->events, static fn ($event): bool => $event->type === EventType::Recompute) !== [];

        return $ends ? $result->state->cursorMs : $horizon;
    }
}
