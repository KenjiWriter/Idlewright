<?php

declare(strict_types=1);

namespace App\Agent;

use App\Content\Bridge\BridgeIssue;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ContentSnapshot;
use App\Models\AgentGrant;
use App\Models\Game;
use App\Services\ContentPacks;

/**
 * The scratch of the AI assistant (D12 of the design): one block of changes of the bridge (`ride-changes/1`) that it keeps under its consent and that is never saved as
 * drafts. The simulators can count the game with it laid over the drafts ("what if"). Every block it sets is checked as the bridge checks a pasted one, within what the
 * assistant may change (`AgentAnchors`), so its mistakes come back to it at once and a block that is refused is not kept. A block that was right when it was set is checked
 * again whenever it is used, as the drafts under it may have changed since.
 */
final class AgentScratch
{
    /** The most operations the scratch holds, as one block of the bridge does. */
    public const MAX_OPS = 200;

    public function __construct(
        private readonly ChangeChecker $checker,
        private readonly ContentSnapshot $snapshot,
        private readonly ContentPacks $packs,
        private readonly AgentAnchors $anchors,
    ) {}

    /**
     * Checks a block made of these operations and keeps it as the scratch when nothing in it is refused.
     *
     * @param  list<array<string, mixed>>  $ops  the operations of `ride-changes/1`
     */
    public function set(AgentGrant $grant, string $summary, array $ops): ChangeCheck
    {
        $hidden = $this->hiddenIn(['summary' => $summary, 'ops' => $ops]);

        if ($hidden !== null) {
            return ChangeCheck::refused([BridgeIssue::error('hidden_characters', "The text at {$hidden} has characters that a person cannot see (zero width, tags of Unicode, switches of direction). Write it again without them.", where: $hidden)]);
        }

        $check = $this->checker->check($this->blockText($grant, $summary, $ops), $this->anchors->scope());

        if ($check->ok()) {
            $grant->forceFill(['scratch' => $check->block, 'scratch_at' => now()])->save();
        }

        return $check;
    }

    /** The scratch checked again on the content as it is now; null when there is none. */
    public function current(AgentGrant $grant): ?ChangeCheck
    {
        $block = $grant->scratch;

        if (! is_array($block)) {
            return null;
        }

        return $this->checker->check($this->blockText($grant, (string) ($block['summary'] ?? ''), (array) ($block['ops'] ?? [])), $this->anchors->scope());
    }

    /**
     * The documents of the scratch to be laid over the drafts, or the reasons it cannot be: there is no scratch, or the content under it has changed so that it no longer
     * holds.
     *
     * @return array<string, string>
     *
     * @throws ToolRefused
     */
    public function replacements(?AgentGrant $grant): array
    {
        if ($grant === null) {
            throw new ToolRefused('The scratch belongs to a consent of the panel; a tool run from the command line has none. Count on live or drafts.');
        }

        $check = $this->current($grant);

        if ($check === null) {
            throw new ToolRefused('The scratch is empty. Set it with scratch_set first, or count on live or drafts.');
        }

        if (! $check->ok()) {
            throw new ToolRefused("The scratch no longer holds on the content as it is now (someone changed the drafts under it). Set it again.\n".$check->repairMessage());
        }

        return $check->replacements();
    }

    public function clear(AgentGrant $grant): void
    {
        $grant->forceFill(['scratch' => null, 'scratch_at' => null])->save();
    }

    /**
     * The whole block, with what the bridge asks of its head filled in by the server: the game, the pack that is live, the mark of the content now, and a code of the
     * consent in place of the code of an instruction (a block of the assistant answers no instruction; its scope is what `AgentAnchors` allows).
     *
     * @param  list<array<string, mixed>>  $ops
     */
    private function blockText(AgentGrant $grant, string $summary, array $ops): string
    {
        return (string) json_encode([
            'format' => 'ride-changes/1',
            'game' => (string) Game::query()->whereKey($grant->game_id)->value('slug'),
            'base' => ['pack' => $this->packs->active()?->number ?? 'none', 'snapshot' => $this->snapshot->current(), 'brief' => 'agent'.$grant->id],
            'summary' => $summary,
            'ops' => $ops,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Where in what the assistant wrote there is a character a person cannot see, or null. */
    private function hiddenIn(mixed $value, string $where = ''): ?string
    {
        if (is_string($value)) {
            return AgentText::hasHidden($value) ? ($where === '' ? '/' : $where) : null;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $found = $this->hiddenIn($item, "{$where}/{$key}");

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
