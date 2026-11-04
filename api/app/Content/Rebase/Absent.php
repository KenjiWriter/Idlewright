<?php

declare(strict_types=1);

namespace App\Content\Rebase;

/** What a side of a merge has where it has nothing: a key, an item of a list or a whole document that is not there (null is a value of JSON, so it cannot stand for that). */
enum Absent
{
    case Value;
}
