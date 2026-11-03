<?php

declare(strict_types=1);

namespace App\Models;

use App\Games\BelongsToGame;
use Illuminate\Database\Eloquent\Model;

/**
 * Something a driver may be told of by push: a car that is about to stop, a car that has arrived, a job that is done. One row for one thing, so it is told once (the unique
 * kind, what it is about and when). For a car on the road `due_ms` is the next moment the server looks at the car.
 */
class PushEvent extends Model
{
    use BelongsToGame;

    public const KIND_CAR_STOPPED = 'car_stopped';

    public const KIND_CAR_ARRIVED = 'car_arrived';

    public const KIND_JOB_DONE = 'job_done';

    public const KIND_STREAK_RISK = 'streak_risk';

    /** The kinds a driver can switch on and off. */
    public const KINDS = [self::KIND_CAR_STOPPED, self::KIND_CAR_ARRIVED, self::KIND_JOB_DONE, self::KIND_STREAK_RISK];

    public const REF_RUN = 'race_run';

    public const REF_TRAVEL = 'travel';

    public const REF_JOB = 'workshop_job';

    public const REF_STREAK = 'streak';

    public const STATE_PLANNED = 'planned';

    public const STATE_SENT = 'sent';

    public const STATE_SKIPPED = 'skipped';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_ms' => 'integer', 'sent_at' => 'datetime'];
    }
}
