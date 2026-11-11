<?php

declare(strict_types=1);

namespace App\Agent;

use App\Models\AgentGrant;

/**
 * The consent under which the tools of the assistant run in this request: put in force by the connection of the assistant after its key is checked. A tool run by a person
 * of the server (`agent:tool`) has none, and is then at the first level and leaves no trail.
 */
final class AgentContext
{
    private ?AgentGrant $grant = null;

    public function set(?AgentGrant $grant): void
    {
        $this->grant = $grant;
    }

    public function grant(): ?AgentGrant
    {
        return $this->grant;
    }

    /** The level the tools may use: the one of the consent, or the first one without a consent. */
    public function level(): AgentLevel
    {
        return $this->grant?->level ?? AgentLevel::Read;
    }
}
