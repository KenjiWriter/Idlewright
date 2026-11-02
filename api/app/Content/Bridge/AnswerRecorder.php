<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Models\Admin;
use App\Models\AiExchange;
use App\Services\AdminAudit;
use Illuminate\Support\Str;

/**
 * Keeps what was pasted: the whole text of the answer, which instruction it answers, and what the panel made of it. The same person pasting the same text again is the same
 * record (checking a text twice is not two answers), so what was applied and undone stays one story.
 */
final class AnswerRecorder
{
    public function __construct(private readonly AdminAudit $audit) {}

    public function record(string $text, ChangeCheck $check, Admin $admin): AiExchange
    {
        $sha = hash('sha256', $text);
        $brief = $check->block === null ? null : AiExchange::query()->where('kind', AiExchange::KIND_BRIEF)->where('code', $check->block['base']['brief'] ?? '')->first();

        $exchange = AiExchange::query()->where('kind', AiExchange::KIND_PASTE)->where('admin_id', $admin->id)->where('sha256', $sha)->first() ?? new AiExchange([
            'admin_id' => $admin->id,
            'code' => $this->code(),
            'kind' => AiExchange::KIND_PASTE,
            'sha256' => $sha,
            'bytes' => strlen($text),
            'text' => substr($text, 0, ChangeBlockParser::MAX_BYTES),
        ]);

        $applied = in_array($exchange->status, ['applied', 'reverted'], true);

        $exchange->forceFill([
            'parent_id' => $brief?->id,
            'scope' => $brief?->scope ?? [],
            'pack' => $check->block['base']['pack'] ?? null,
            'snapshot' => $check->block['base']['snapshot'] ?? null,
            // What was applied keeps its story: checking the text again does not make it unapplied.
            'status' => $applied ? $exchange->status : ($check->ok() ? 'checked' : 'refused'),
            'resolved' => $applied ? $exchange->resolved : ['summary' => $check->block['summary'] ?? null, 'changes' => array_map(static fn (ResolvedChange $change): array => $change->toArray(), $check->changes)],
        ])->save();

        $this->audit->record('ai_changes_checked', $admin, 'ai_exchange:'.$exchange->code, ['ok' => $check->ok(), 'changes' => count($check->changes), 'errors' => array_map(static fn (BridgeIssue $issue): string => $issue->code, $check->errors()), 'brief' => $brief?->code]);

        return $exchange;
    }

    private function code(): string
    {
        do {
            $code = Str::lower(Str::random(10));
        } while (AiExchange::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }
}
