<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A round trip of a car with people or a load that its driver took from the board of a city: the car leaves its track at `start_ms` and is paid when it is back at `finish_ms`. */
class Gig extends Model
{
    /** @use HasFactory<GigFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DONE = 'done';

    /** How a night run ended: nobody stopped it, the car got away from the police, or the driver was arrested. */
    public const POLICE_CLEAN = 'clean';

    public const POLICE_ESCAPED = 'escaped';

    public const POLICE_ARRESTED = 'arrested';

    /** The driver took it back in the first seconds: nothing was paid and the fuel was returned. */
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'race_run_id' => 'integer',
            'distance_km' => 'integer',
            'amount' => 'integer',
            'fuel_ml' => 'integer',
            'start_ms' => 'integer',
            'finish_ms' => 'integer',
            'reward_cents' => 'integer',
            'bonus_cents' => 'integer',
            'xp' => 'integer',
            'car_xp' => 'integer',
            'done_ms' => 'integer',
            'police_control_ppm' => 'integer',
            'police_at_ms' => 'integer',
            'arrest_until_ms' => 'integer',
            'bailed_ms' => 'integer',
            'fine_cents' => 'integer',
            'bail_cents' => 'integer',
            'paid_cents' => 'integer',
            'fine_paid_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<RaceRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(RaceRun::class, 'race_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
