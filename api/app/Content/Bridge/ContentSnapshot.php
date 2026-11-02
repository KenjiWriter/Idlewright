<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Models\ContentDefinition;

/**
 * A short mark of the content of the game as it is being worked on (the drafts over what is live): the same content has the same mark, and any change to a car, a part or
 * the rules changes it. An instruction for an AI carries it, and a block that comes back with another one was made from content that has changed since.
 */
final class ContentSnapshot
{
    public function current(): string
    {
        $lines = [];

        $definitions = ContentDefinition::query()
            ->where('status', '!=', ContentDefinition::STATUS_ARCHIVED)
            ->with('latestVersion')
            ->get();

        foreach ($definitions as $definition) {
            $text = $definition->draft ?? $definition->latestVersion?->document;

            if ($text !== null) {
                $lines[] = "{$definition->kind}:{$definition->slug}:".sha1($text);
            }
        }

        sort($lines);

        return substr(hash('sha256', implode("\n", $lines)), 0, 8);
    }
}
