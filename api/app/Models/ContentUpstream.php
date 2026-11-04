<?php

declare(strict_types=1);

namespace App\Models;

use App\Content\PackContent;
use App\Games\BelongsToGame;
use Illuminate\Database\Eloquent\Model;

/** The files of a release that the content of the panel took in with content:rebase: what the next rebase starts from. */
class ContentUpstream extends Model
{
    use BelongsToGame;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['documents' => 'array'];
    }

    /** The files as a source of content, to merge against. */
    public function source(): PackContent
    {
        return new PackContent('upstream', $this->documents);
    }
}
