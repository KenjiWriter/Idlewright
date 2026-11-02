<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Models\Admin;
use App\Models\AiExchange;
use App\Services\AdminAudit;
use App\Services\ContentPacks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Makes the instruction for an AI and keeps a record of it: the code it carries, what it contains (which is what an answer to it may change), the content it was made from,
 * and who asked. The same person asking for the same choices over the same content gets the same code, so that asking twice does not fill the record.
 */
final class BriefRecorder
{
    public function __construct(private readonly BriefBuilder $builder, private readonly ContentSnapshot $snapshot, private readonly AdminAudit $audit, private readonly ContentPacks $packs) {}

    public function prepare(BriefSelection $selection, ?Admin $admin = null): Brief
    {
        $admin ??= Auth::guard('admin')->user();
        $pack = (string) ($this->packs->active()?->number ?? '');
        $snapshot = $this->snapshot->current();
        $key = substr(hash('sha256', $selection->key().'|'.$snapshot.'|'.$pack), 0, 32);

        $exchange = AiExchange::query()
            ->where('kind', AiExchange::KIND_BRIEF)
            ->where('admin_id', $admin?->id)
            ->where('selection', $key)
            ->first() ?? $this->open($selection, $admin, $pack, $snapshot, $key);

        $brief = $this->builder->build($selection, $exchange->code);

        $exchange->forceFill([
            'scope' => $brief->scope,
            'bytes' => $brief->bytes,
            'tokens' => $brief->tokens,
            'sha256' => hash('sha256', $brief->text),
        ])->save();

        $this->audit->record('ai_brief_prepared', $admin, 'ai_exchange:'.$exchange->code, ['scope' => $brief->scope, 'bytes' => $brief->bytes, 'tokens' => $brief->tokens, 'locale' => $selection->locale]);

        return $brief;
    }

    private function open(BriefSelection $selection, ?Admin $admin, string $pack, string $snapshot, string $key): AiExchange
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (AiExchange::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return AiExchange::query()->create([
            'admin_id' => $admin?->id,
            'code' => $code,
            'kind' => AiExchange::KIND_BRIEF,
            'scope' => [],
            'locale' => $selection->locale,
            'pack' => $pack,
            'snapshot' => $snapshot,
            'selection' => $key,
        ]);
    }
}
