<?php

declare(strict_types=1);

namespace App\Agent;

use App\Models\Admin;

/**
 * How far a consent lets an AI assistant go (D5 of the design): read and compute; also plan and, once a person approves the plan in the panel, write drafts; also order a
 * pack. Making a pack live is on no level: it is always a person's.
 */
enum AgentLevel: int
{
    case Read = 1;
    case Plan = 2;
    case Pack = 3;

    /** The role a person needs in the game to give this level: ordering a pack is what a publisher does. */
    public function role(): string
    {
        return $this === self::Pack ? Admin::ROLE_PUBLISHER : Admin::ROLE_EDITOR;
    }

    /** Whether a person with this role in the game may give this level. */
    public function allowedFor(string $role): bool
    {
        return $this->role() === Admin::ROLE_EDITOR || $role === Admin::ROLE_PUBLISHER;
    }

    /** Whether this level covers what another one allows. */
    public function covers(self $level): bool
    {
        return $this->value >= $level->value;
    }
}
