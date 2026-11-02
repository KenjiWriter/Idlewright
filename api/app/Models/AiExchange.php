<?php

declare(strict_types=1);

namespace App\Models;

use App\Games\BelongsToGame;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One instruction that was made for an AI in the panel (and, later, an answer that was pasted back): what it contained, which content it was made from, and who asked.
 * The code of an instruction is what an answer carries to say which instruction it answers.
 */
class AiExchange extends Model
{
    use BelongsToGame;

    public const KIND_BRIEF = 'brief';

    public const KIND_PASTE = 'paste';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scope' => 'array', 'resolved' => 'array', 'before' => 'array', 'applied_at' => 'datetime', 'reverted_at' => 'datetime'];
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
