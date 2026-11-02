<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\ContentPackInvalid;
use App\Content\Definitions\DefinitionRefused;
use App\Models\Admin;
use App\Models\AiExchange;
use App\Models\ContentDefinition;
use App\Services\AdminAudit;
use App\Services\ContentDrafts;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a block of changes as drafts, and only as drafts: nothing here is published, and the publication goes through its gates as it did. The block is checked again at the
 * moment it is kept (what was shown may be old), every document goes through `ContentDrafts` (its schema, its rules, its trail), and all of them are kept in one transaction,
 * so that a block is kept whole or not at all. What each draft was before is written down, so that the application can be undone.
 */
final class ChangeApplier
{
    public function __construct(
        private readonly ChangeChecker $checker,
        private readonly AnswerRecorder $answers,
        private readonly ContentDrafts $drafts,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * @param  array<string, array{draft: bool, saved_at: string|null}>  $seen  the drafts of the documents as they were when the person looked at the check
     * @param  bool  $repeat  the person knows this text was applied before and means to apply it again (a scale adds up)
     *
     * @throws ApplyRefused
     */
    public function apply(string $text, Admin $admin, array $seen, bool $repeat = false): AiExchange
    {
        $check = $this->checker->check($text);

        if (! $check->ok()) {
            throw new ApplyRefused('not_ok', 'The block has errors, so nothing was kept.');
        }

        $now = $check->touched;
        ksort($now);
        ksort($seen);

        if ($seen !== $now) {
            throw new ApplyRefused('changed_meanwhile', 'A draft that the block goes on top of changed after it was checked. Check it again.', array_keys($check->touched));
        }

        $exchange = $this->answers->record($text, $check, $admin);

        if ($exchange->status === 'applied' && ! $repeat) {
            throw new ApplyRefused('already_applied', 'This text was applied before. Applying it again would change the numbers a second time.');
        }

        $before = [];
        $after = [];

        try {
            DB::transaction(function () use ($check, $admin, &$before, &$after): void {
                foreach ($check->documents as $key => $document) {
                    [$kind, $slug] = explode(':', $key, 2);

                    if (in_array($key, $check->created, true)) {
                        // A new car or part has no draft to go back to: it is made, and undoing it deletes it.
                        $definition = $this->drafts->create($kind, $document, $admin);
                        $before[$key] = null;
                    } else {
                        $definition = ContentDefinition::query()->where('kind', $kind)->where('slug', $slug)->firstOrFail();

                        $before[$key] = $definition->draft;
                        $this->drafts->save($definition, $document, $admin);
                    }

                    $after[$key] = $definition->fresh()?->draft === null ? null : sha1((string) $definition->fresh()->draft);
                }
            });
        } catch (ContentPackInvalid|DefinitionRefused $problem) {
            throw new ApplyRefused('invalid', $problem->getMessage());
        }

        $exchange->forceFill([
            'status' => 'applied',
            'applied_at' => now(),
            'reverted_at' => null,
            'before' => $before,
            'resolved' => [...(array) $exchange->resolved, 'after' => $after, 'created' => $check->created],
        ])->save();

        $this->audit->record('ai_changes_applied', $admin, 'ai_exchange:'.$exchange->code, ['changes' => count($check->changes), 'documents' => array_keys($check->documents), 'summary' => $check->block['summary'] ?? null, 'brief' => $exchange->parent?->code]);

        return $exchange;
    }
}
