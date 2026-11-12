<?php

declare(strict_types=1);

namespace App\Agent;

use App\Content\Bridge\ApplyRefused;
use App\Content\Bridge\ChangeApplier;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ChangeReverter;
use App\Content\Bridge\ContentSnapshot;
use App\Content\Progress\ProgressTargets;
use App\Content\Publishing\PackGates;
use App\Content\Publishing\PublicationRefused;
use App\Jobs\ReportAgentPlan;
use App\Models\Admin;
use App\Models\AgentPlan;
use App\Models\AiExchange;
use App\Models\ContentDefinition;
use App\Services\AdminAudit;
use App\Services\ContentPacks;
use App\Services\ContentPublisher;
use Illuminate\Support\Str;
use Throwable;

/**
 * What comes of an approved plan of the AI assistant (D9 and D10 of the design). Its block is written as drafts through the bridge, as a pasted answer is (checked again,
 * one transaction, all or nothing), and only when it still makes exactly the documents that were approved. The server then counts the drafts again and sets what it
 * measures beside what it promised; the simulators are not random, so a difference is an alarm. The writing can be undone while nobody has touched the drafts. At the
 * third level, and only when every draft of the game comes from the plan, the assistant may ask for a pack; making it live stays with a person.
 */
final class AgentPlanExecution
{
    public function __construct(
        private readonly AgentPlans $plans,
        private readonly AgentAnchors $anchors,
        private readonly ChangeChecker $checker,
        private readonly ChangeApplier $applier,
        private readonly ChangeReverter $reverter,
        private readonly ContentSnapshot $snapshot,
        private readonly ContentPacks $packs,
        private readonly ContentPublisher $publisher,
        private readonly AgentVariants $variants,
        private readonly AgentStudies $studies,
        private readonly PackGates $gates,
        private readonly AdminAudit $audit,
        private readonly AgentContext $context,
    ) {}

    /**
     * Writes the block of an approved plan as drafts, on behalf of a person of the panel, and has the drafts counted again.
     *
     * @throws PlanRefused
     */
    public function execute(AgentPlan $plan, Admin $onBehalfOf): void
    {
        if ($plan->status !== AgentPlan::APPROVED) {
            throw new PlanRefused(in_array($plan->status, [AgentPlan::EXECUTED, AgentPlan::REVERTED], true) ? 'executed' : 'not_approved');
        }

        if (! $plan->isApprovedNow()) {
            throw new PlanRefused('approval_expired');
        }

        if ($this->plans->isOutdated($plan)) {
            throw new PlanRefused('outdated');
        }

        $this->plans->assertCanAct($plan, $onBehalfOf);

        $exchange = null;

        $this->plans->inGameOf($plan, function () use ($plan, $onBehalfOf, &$exchange): void {
            $brief = $this->briefOf($plan, $onBehalfOf);
            $block = [
                ...$plan->block,
                'base' => ['pack' => $this->packs->active()?->number ?? 'none', 'snapshot' => $this->snapshot->current(), 'brief' => $brief->code],
                'summary' => Str::limit($this->signature($plan), 290, '...'),
            ];
            $text = (string) json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $check = $this->checker->check($text);

            if (! $check->ok()) {
                throw new PlanRefused('changed', more: $check->repairMessage());
            }

            $approved = $plan->replacements;
            $now = $check->replacements();
            ksort($approved);
            ksort($now);

            if ($approved !== $now) {
                throw new PlanRefused('changed');
            }

            try {
                $exchange = $this->applier->apply($text, $onBehalfOf, $check->touched);
            } catch (ApplyRefused $refused) {
                throw new PlanRefused('apply_failed', $refused->details, $refused->getMessage());
            }
        });

        $plan->forceFill([
            'status' => AgentPlan::EXECUTED,
            'executed_at' => now(),
            'executed_by' => $onBehalfOf->id,
            'ai_exchange_id' => $exchange?->id,
            'report_status' => AgentPlan::MEASURING,
            'report' => ['executed_snapshot' => $this->snapshot->current()],
            'report_error' => null,
        ])->save();

        $this->plans->inGameOf($plan, fn () => $this->audit->record('agent_plan_executed', $onBehalfOf, $plan->title, ['plan' => $plan->id, 'version' => $plan->version, 'exchange' => $exchange?->code, 'approved_by' => $plan->decided_by]));

        try {
            ReportAgentPlan::dispatch($plan->id)->onQueue('agent');
        } finally {
            app()->instance(AgentContext::class, $this->context);
        }
    }

    /**
     * Counts the drafts again after the plan was written and sets every number beside what the plan promised: the targets, the gates and the pace of the players.
     */
    public function report(AgentPlan $plan): void
    {
        try {
            ['content' => $drafts] = $this->variants->content(AgentVariants::DRAFTS);
            $targets = array_values(array_filter($plan->targets, static fn (array $target): bool => $target['type'] !== 'gates'));
            $rows = $targets === [] ? [] : $this->studies->run('check_targets', $drafts, ['targets' => $targets])['targets'];
            $gates = $this->gates->run($drafts);
            $economy = $this->studies->run('simulate_economy', $drafts, [])['report'] ?? [];
            $promised = (array) ($plan->measured ?? []);

            $targetRows = [];
            $position = 0;

            foreach ($plan->targets as $index => $target) {
                $before = $promised['targets'][$index] ?? null;
                $measured = $target['type'] === 'gates' ? ProgressTargets::fromGates($gates)['measured'] : ($rows[$position++]['measured'] ?? null);
                $targetRows[] = ['target' => (string) ($before['target'] ?? $target['type']), 'promised' => $before['plan'] ?? null, 'measured' => $measured, 'same' => ($before['plan'] ?? null) === $measured];
            }

            $passedBefore = array_column((array) ($promised['gates'] ?? []), 'passed', 'name');
            $gateRows = array_map(static fn (array $gate): array => ['name' => $gate['name'], 'promised' => $passedBefore[$gate['name']] ?? null, 'measured' => $gate['passed'], 'same' => ($passedBefore[$gate['name']] ?? null) === $gate['passed']], $gates);

            $dayBefore = [];

            foreach ((array) ($promised['pace'] ?? []) as $row) {
                $dayBefore[$row['player'].'|'.$row['car']] = $row['plan_day'];
            }

            $paceRows = array_map(static fn (array $row): array => [
                'player' => (string) $row['player'], 'car' => (string) $row['car'], 'promised' => $dayBefore[$row['player'].'|'.$row['car']] ?? null, 'measured' => (int) $row['day'],
                'same' => ($dayBefore[$row['player'].'|'.$row['car']] ?? null) === (int) $row['day'],
            ], $economy['gate']['rows'] ?? []);

            $same = ! in_array(false, [...array_column($targetRows, 'same'), ...array_column($gateRows, 'same'), ...array_column($paceRows, 'same')], true);

            $plan->forceFill([
                'report_status' => AgentPlan::READY,
                'report' => [
                    ...(array) $plan->report,
                    'targets' => $targetRows,
                    'gates' => $gateRows,
                    'pace' => $paceRows,
                    'same' => $same,
                    'drafts_changed' => ($plan->report['executed_snapshot'] ?? null) !== $this->snapshot->current(),
                ],
                'report_error' => null,
                'reported_at' => now(),
            ])->save();
        } catch (Throwable $problem) {
            $plan->forceFill(['report_status' => AgentPlan::FAILED, 'report_error' => Str::limit($problem->getMessage(), 2000, '...'), 'reported_at' => now()])->save();
        }
    }

    /**
     * Undoes the writing of a plan: every draft goes back to what it was, as the bridge undoes an answer, only while nobody has worked on them since.
     *
     * @throws PlanRefused
     */
    public function undo(AgentPlan $plan, Admin $by): void
    {
        if ($plan->status !== AgentPlan::EXECUTED || $plan->exchange === null) {
            throw new PlanRefused('not_executed');
        }

        $this->plans->assertCanAct($plan, $by);

        if ($plan->publication?->isOpen() === true) {
            throw new PlanRefused('pack_in_progress');
        }

        $this->plans->inGameOf($plan, function () use ($plan, $by): void {
            try {
                $this->reverter->revert($plan->exchange, $by);
            } catch (ApplyRefused $refused) {
                throw new PlanRefused($refused->reason === 'conflict' ? 'undo_conflict' : 'apply_failed', $refused->details, $refused->getMessage());
            }
        });

        $plan->forceFill(['status' => AgentPlan::REVERTED, 'reverted_at' => now(), 'reverted_by' => $by->id])->save();
        $this->plans->inGameOf($plan, fn () => $this->audit->record('agent_plan_reverted', $by, $plan->title, ['plan' => $plan->id, 'version' => $plan->version]));
    }

    /**
     * Asks for a pack of the drafts after a plan was written: only for a publisher of the game, and only when every draft of the game is one the plan wrote, so that a
     * pack the assistant asks for holds nothing a person did not approve. The pack goes through its gates as always; making it live stays with a person.
     *
     * @throws PlanRefused
     */
    public function orderPack(AgentPlan $plan, Admin $publisher): void
    {
        if ($plan->status !== AgentPlan::EXECUTED || $plan->exchange === null) {
            throw new PlanRefused('not_executed');
        }

        if (! $publisher->is_active || ! $publisher->canEnter($plan->game_id) || $publisher->roleIn($plan->game_id) !== Admin::ROLE_PUBLISHER) {
            throw new PlanRefused('not_publisher');
        }

        if ($plan->publication !== null && $plan->publication->status !== 'failed') {
            throw new PlanRefused('pack_ordered');
        }

        $foreign = $this->foreignDrafts($plan);

        if ($foreign !== []) {
            throw new PlanRefused('foreign_drafts', $foreign);
        }

        $publication = null;

        $this->plans->inGameOf($plan, function () use ($plan, $publisher, &$publication): void {
            try {
                $publication = $this->publisher->queue($publisher, __('admin.plans.pack_note', ['number' => $plan->id, 'version' => $plan->version, 'email' => $publisher->email]));
            } catch (PublicationRefused $refused) {
                throw new PlanRefused($refused->reason === 'busy' ? 'pack_busy' : 'nothing_to_publish');
            }
        });

        $plan->forceFill(['content_publication_id' => $publication?->id])->save();
        $this->plans->inGameOf($plan, fn () => $this->audit->record('agent_plan_pack_ordered', $publisher, $plan->title, ['plan' => $plan->id, 'publication' => $publication?->number]));
    }

    /**
     * The drafts of the game that the plan did not write (or that were changed after it wrote them), by `kind:slug`.
     *
     * @return list<string>
     */
    public function foreignDrafts(AgentPlan $plan): array
    {
        $after = (array) ($plan->exchange?->resolved['after'] ?? []);
        $foreign = [];

        foreach (ContentDefinition::query()->whereNotNull('draft')->get(['kind', 'slug', 'draft']) as $definition) {
            $key = "{$definition->kind}:{$definition->slug}";

            if (($after[$key] ?? null) !== sha1((string) $definition->draft)) {
                $foreign[] = $key;
            }
        }

        sort($foreign);

        return $foreign;
    }

    /**
     * The drafts that are still exactly what a plan wrote, with the line that says so ("assistant, plan 12, approved by X"), by `kind:slug`: the page "Zmiany do
     * publikacji" shows it. A draft a person worked on after the plan has no such line.
     *
     * @return array<string, string>
     */
    public function signatures(int $gameId): array
    {
        $plans = AgentPlan::query()->where('game_id', $gameId)->where('status', AgentPlan::EXECUTED)->with(['decider', 'exchange'])->get();
        $drafts = ContentDefinition::query()->whereNotNull('draft')->get(['kind', 'slug', 'draft'])->mapWithKeys(fn (ContentDefinition $definition): array => ["{$definition->kind}:{$definition->slug}" => sha1((string) $definition->draft)])->all();
        $signatures = [];

        foreach ($plans as $plan) {
            foreach ((array) ($plan->exchange?->resolved['after'] ?? []) as $key => $hash) {
                if ($hash !== null && ($drafts[$key] ?? null) === $hash) {
                    $signatures[$key] = $this->signature($plan);
                }
            }
        }

        return $signatures;
    }

    /** The line a draft of a plan carries: the assistant, the number and the version of the plan, and who approved it. */
    public function signature(AgentPlan $plan): string
    {
        return __('admin.plans.signature', ['number' => $plan->id, 'version' => $plan->version, 'email' => $plan->decider?->email ?? '-', 'title' => $plan->title]);
    }

    /** The instruction of the bridge a plan answers: its scope is what the assistant may change, and it is the parent of the answer the plan is written as. */
    private function briefOf(AgentPlan $plan, Admin $admin): AiExchange
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (AiExchange::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return AiExchange::query()->create([
            'admin_id' => $admin->id,
            'code' => $code,
            'kind' => AiExchange::KIND_BRIEF,
            'scope' => $this->anchors->scope(),
            'locale' => app()->getLocale(),
            'pack' => (string) ($this->packs->active()?->number ?? ''),
            'snapshot' => $this->snapshot->current(),
            'selection' => 'agent-plan:'.$plan->id,
            'status' => 'prepared',
        ]);
    }
}
