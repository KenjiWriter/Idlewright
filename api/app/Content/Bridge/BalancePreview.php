<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Publishing\CandidatePack;
use App\Content\Publishing\DerivedTables;
use App\Content\Publishing\PackGates;
use App\Games\CurrentGame;
use App\Services\ContentPacks;
use Illuminate\Support\Facades\Cache;

/**
 * What a block of changes would do to the balance of the game, before it is kept anywhere: the content as it is being worked on with the documents of the block put over it is
 * judged as a pack is, the derived tables are made again, the gates are run and the economy is measured, and the measure of the pack that is live is beside it. It is what the
 * studio's preview does for the drafts that are saved, for something that is not saved yet. The measure of the live pack is the one the studio keeps, so the first preview
 * after a pack goes live is the slow one.
 */
final class BalancePreview
{
    /** How many errors of the validation a report keeps. */
    private const ISSUES = 100;

    public function __construct(
        private readonly ContentPacks $packs,
        private readonly CandidatePack $candidates,
        private readonly DerivedTables $tables,
        private readonly PackGates $gates,
        private readonly CurrentGame $current,
    ) {}

    /**
     * @return array{errors: list<array{file: string, pointer: string, message: string}>, gates: list<array{name: string, passed: bool, ms: int, excerpt: string}>, now: array<string, mixed>, live: array<string, mixed>|null, changes: int}
     */
    public function of(ChangeCheck $check): array
    {
        // A request of the panel has a limit of seconds and the simulators take longer than that the first time; a process that has none is left alone.
        if ((int) ini_get('max_execution_time') > 0) {
            @set_time_limit(180);
        }

        $number = $this->packs->active()?->number ?? now()->format('Y.n').'.0';
        $candidate = $this->candidates->make($number)->with($check->replacements());
        $validation = $this->packs->validate($candidate, checkTables: false);

        if (! $validation->isValid()) {
            return ['errors' => array_slice($validation->errors(), 0, self::ISSUES), 'gates' => [], 'now' => [], 'live' => null, 'changes' => count($check->changes)];
        }

        $candidate = $candidate->with($this->tables->make($candidate));
        $live = $this->packs->activeSource();

        return [
            'errors' => [],
            'gates' => $this->gates->run($candidate),
            'now' => $this->gates->measure($candidate),
            'live' => $live === null ? null : Cache::remember('studio.live-measure.'.$this->current->id().'.'.$live->number(), 3600, fn (): array => $this->gates->measure($live)),
            'changes' => count($check->changes),
        ];
    }
}
