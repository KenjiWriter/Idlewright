<?php

declare(strict_types=1);

namespace App\Models;

use App\Games\BelongsToGame;
use Illuminate\Database\Eloquent\Model;

/** What a driver wants to be told of by push: the kinds of message they switched on or off. A kind that is not here has the default of the game. */
class PushPreference extends Model
{
    use BelongsToGame;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kinds' => 'array', 'prompted_at' => 'datetime'];
    }
}
