<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\ContentPackInvalid;
use App\Content\Definitions\DefinitionRefused;
use App\Content\Definitions\JsonDocument;
use App\Models\Admin;
use App\Models\AiExchange;
use App\Models\ContentDefinition;
use App\Services\AdminAudit;
use App\Services\ContentDrafts;
use Illuminate\Support\Facades\DB;

/**
 * Undoes what a pasted block did to the drafts: each draft goes back to what it was before (or is thrown away, when there was none). It is only done while every draft is still
 * what the application made of it; a draft that someone has worked on since is not touched, and nothing is, so that undoing never loses later work.
 */
final class ChangeReverter
{
    public function __construct(private readonly ContentDrafts $drafts, private readonly AdminAudit $audit) {}

    /** @throws ApplyRefused */
    public function revert(AiExchange $exchange, Admin $admin): void
    {
        if ($exchange->status !== 'applied') {
            throw new ApplyRefused('not_applied', 'There is nothing to undo: this answer is not applied.');
        }

        /** @var array<string, string|null> $before */
        $before = (array) $exchange->before;
        /** @var array<string, string|null> $after */
        $after = (array) ($exchange->resolved['after'] ?? []);

        $definitions = [];
        $conflicts = [];

        foreach ($before as $key => $draft) {
            [$kind, $slug] = explode(':', $key, 2);
            $definition = ContentDefinition::query()->where('kind', $kind)->where('slug', $slug)->first();
            $current = $definition?->draft === null ? null : sha1((string) $definition->draft);

            if ($definition === null || $current !== ($after[$key] ?? null)) {
                $conflicts[] = $key;
            }

            $definitions[$key] = $definition;
        }

        if ($conflicts !== []) {
            throw new ApplyRefused('conflict', 'These drafts have been worked on since the answer was applied, so it is not undone: '.implode(', ', $conflicts).'.', $conflicts);
        }

        try {
            DB::transaction(function () use ($definitions, $before, $admin): void {
                foreach ($definitions as $key => $definition) {
                    if ($before[$key] === null) {
                        if ($definition->hasDraft()) {
                            $this->drafts->discard($definition, $admin);
                        }

                        continue;
                    }

                    $this->drafts->save($definition, JsonDocument::decode((string) $before[$key]), $admin);
                }
            });
        } catch (ContentPackInvalid|DefinitionRefused $problem) {
            throw new ApplyRefused('invalid', $problem->getMessage());
        }

        $exchange->forceFill(['status' => 'reverted', 'reverted_at' => now()])->save();

        $this->audit->record('ai_changes_reverted', $admin, 'ai_exchange:'.$exchange->code, ['documents' => array_keys($before)]);
    }
}
