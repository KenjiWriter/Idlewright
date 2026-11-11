<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Tools\ScratchSetTool;
use App\Content\Bridge\ContentSnapshot;
use App\Content\Progress\ProgressTargets;
use App\Content\Publishing\PackGates;
use App\Games\CurrentGame;
use App\Jobs\MeasureAgentPlan;
use App\Models\Admin;
use App\Models\AgentGrant;
use App\Models\AgentPlan;
use App\Models\Game;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * The plans of the AI assistant (D8 of the design). The assistant submits its scratch as a plan, with the goal, the targets in numbers, the strategy and the levers it
 * means to use; the server measures the plan itself (the targets on the live pack and on the plan, the pace of the players A, B and C, the gates), so the person reads
 * numbers the assistant did not write. Only a person of the panel decides: approve (for 24 hours), reject with a reason, or leave a note the assistant reads. A plan is
 * bound to its block and to the drafts it was made on: when the drafts change, the plan is outdated and cannot be approved; a corrected plan is a new version.
 */
final class AgentPlans
{
    /** How long an approval holds before the plan has to be approved again. */
    public const APPROVAL_HOURS = 24;

    /** What a lever is: a section of the rules, a document of the rules, every part or car, or one of them (the form the bridge reads a scope in). */
    private const LEVER = '/^(rules:starter#[a-z0-9_]+|rules:(tutorial|vocabulary|missions)|kind:(part|vehicle)|(part|vehicle):[a-z0-9]+(-[a-z0-9]+)*)$/';

    public function __construct(
        private readonly AgentScratch $scratch,
        private readonly ContentSnapshot $snapshot,
        private readonly AgentVariants $variants,
        private readonly AgentStudies $studies,
        private readonly PackGates $gates,
        private readonly AdminAudit $audit,
        private readonly CurrentGame $current,
        private readonly AgentContext $context,
    ) {}

    /**
     * Submits the scratch of a consent as a plan and has it measured.
     *
     * @param  array{title: string, goal: string, targets: list<array<string, mixed>>, strategy: string, levers: list<string>, assumptions?: string|null, alternatives?: list<array{option: string, why: string}>|null, risks?: string|null, unverified?: string|null, supersedes?: int|null}  $input
     *
     * @throws ToolRefused
     */
    public function submit(AgentGrant $grant, array $input): AgentPlan
    {
        $hidden = $this->hiddenIn($input);

        if ($hidden !== null) {
            throw new ToolRefused("The text at {$hidden} has characters that a person cannot see. Write it again without them.");
        }

        try {
            $targets = ProgressTargets::validate($input['targets']);
        } catch (InvalidArgumentException $problem) {
            throw new ToolRefused($problem->getMessage());
        }

        foreach ($input['levers'] as $lever) {
            if (preg_match(self::LEVER, $lever) !== 1) {
                throw new ToolRefused("The lever {$lever} is not one: a lever is rules:starter#<section>, rules:tutorial, rules:vocabulary, rules:missions, kind:part, kind:vehicle, part:<slug> or vehicle:<slug>.");
            }
        }

        $check = $this->scratch->current($grant) ?? throw new ToolRefused('The scratch is empty: a plan is the scratch, so set it with scratch_set first.');

        if (! $check->ok()) {
            throw new ToolRefused("The scratch does not hold on the content as it is now, so it cannot be a plan. Set it again.\n".$check->repairMessage());
        }

        $previous = null;

        if (isset($input['supersedes'])) {
            $previous = AgentPlan::query()->where('game_id', $grant->game_id)->find($input['supersedes'])
                ?? throw new ToolRefused("There is no plan {$input['supersedes']} in this game to replace.");

            if ($previous->status === AgentPlan::SUPERSEDED) {
                throw new ToolRefused("The plan {$previous->id} was already replaced by a newer version; replace that one.");
            }
        }

        $changes = ScratchSetTool::describe($check)['changes'];
        $snapshot = $this->snapshot->current();
        $block = (array) $check->block;

        $plan = DB::transaction(function () use ($grant, $input, $targets, $previous, $changes, $snapshot, $block, $check): AgentPlan {
            if ($previous !== null) {
                $previous->forceFill(['status' => AgentPlan::SUPERSEDED])->save();
            }

            return AgentPlan::query()->create([
                'game_id' => $grant->game_id,
                'agent_grant_id' => $grant->id,
                'admin_id' => $grant->admin_id,
                'client_name' => $grant->client_name,
                'version' => ($previous->version ?? 0) + 1,
                'previous_id' => $previous?->id,
                'status' => AgentPlan::SUBMITTED,
                'title' => $input['title'],
                'goal' => $input['goal'],
                'targets' => $targets,
                'assumptions' => $input['assumptions'] ?? null,
                'strategy' => $input['strategy'],
                'alternatives' => $input['alternatives'] ?? null,
                'levers' => array_values($input['levers']),
                'block' => $block,
                'replacements' => $check->replacements(),
                'changes' => $changes,
                'outside_levers' => $this->outsideLevers($changes, $input['levers']),
                'risks' => $input['risks'] ?? null,
                'unverified' => $input['unverified'] ?? null,
                'content_hash' => hash('sha256', json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'|'.$snapshot),
                'drafts_snapshot' => $snapshot,
                'measure_status' => AgentPlan::MEASURING,
            ]);
        });

        $this->inGameOf($plan, fn () => $this->audit->record('agent_plan_submitted', $grant->admin, $plan->title, ['plan' => $plan->id, 'version' => $plan->version, 'client' => $grant->client_name]));

        try {
            MeasureAgentPlan::dispatch($plan->id)->onQueue('agent');
        } finally {
            // When the queue runs at once (as in the tests), the simulators forget what the request holds; the consent of the call is put back.
            app()->instance(AgentContext::class, $this->context);
        }

        return $plan->refresh();
    }

    /** Whether the drafts changed since the plan was made: its numbers were counted on other content, so it cannot be approved. */
    public function isOutdated(AgentPlan $plan): bool
    {
        return in_array($plan->status, [AgentPlan::SUBMITTED, AgentPlan::APPROVED], true) && $plan->drafts_snapshot !== $this->snapshot->current();
    }

    /**
     * Counts the plan as the server sees it: the targets on the live pack and on the plan (the drafts with its block), the gates on the plan, and the days the players
     * A, B and C buy the cars of the ladder on both.
     */
    public function measure(AgentPlan $plan): void
    {
        try {
            $targets = array_values(array_filter($plan->targets, static fn (array $target): bool => $target['type'] !== 'gates'));
            ['content' => $live] = $this->variants->content(AgentVariants::LIVE);
            ['content' => $planned] = $this->variants->content(AgentVariants::SCRATCH, $plan->replacements);

            $liveRows = $targets === [] ? [] : $this->studies->run('check_targets', $live, ['targets' => $targets])['targets'];
            $planRows = $targets === [] ? [] : $this->studies->run('check_targets', $planned, ['targets' => $targets])['targets'];
            $gates = $this->gates->run($planned);
            $economyLive = $this->studies->run('simulate_economy', $live, [])['report'] ?? [];
            $economyPlan = $this->studies->run('simulate_economy', $planned, [])['report'] ?? [];

            $rows = [];
            $position = 0;

            foreach ($plan->targets as $target) {
                if ($target['type'] === 'gates') {
                    $row = ProgressTargets::fromGates($gates);
                    $rows[] = ['type' => 'gates', 'target' => $row['target'], 'live' => null, 'live_ok' => null, 'plan' => $row['measured'], 'plan_ok' => $row['ok']];

                    continue;
                }

                $rows[] = [
                    'type' => $target['type'],
                    'target' => $planRows[$position]['target'] ?? $target['type'],
                    'live' => $liveRows[$position]['measured'] ?? null,
                    'live_ok' => $liveRows[$position]['ok'] ?? null,
                    'plan' => $planRows[$position]['measured'] ?? null,
                    'plan_ok' => $planRows[$position]['ok'] ?? false,
                ];
                $position++;
            }

            $measured = [
                'targets' => $rows,
                'met' => $rows !== [] && ! in_array(false, array_column($rows, 'plan_ok'), true),
                'gates' => array_map(static fn (array $gate): array => ['name' => $gate['name'], 'passed' => $gate['passed'], 'excerpt' => Str::limit($gate['excerpt'], 1500, '...')], $gates),
                'gates_passed' => ! in_array(false, array_column($gates, 'passed'), true),
                'pace' => $this->pace($economyLive, $economyPlan),
            ];

            $plan->forceFill(['measure_status' => AgentPlan::READY, 'measured' => $measured, 'measure_error' => null, 'measured_at' => now()])->save();
        } catch (Throwable $problem) {
            $plan->forceFill(['measure_status' => AgentPlan::FAILED, 'measure_error' => Str::limit($problem->getMessage(), 2000, '...'), 'measured_at' => now()])->save();
        }
    }

    /**
     * A person of the panel approves the plan: it holds for 24 hours, and only for the block and the drafts it was measured on.
     *
     * @throws PlanRefused
     */
    public function approve(AgentPlan $plan, Admin $by): void
    {
        $this->assertDecidable($plan, $by);

        if ($plan->measure_status !== AgentPlan::READY) {
            throw new PlanRefused($plan->measure_status === AgentPlan::MEASURING ? 'measuring' : 'measure_failed');
        }

        $plan->forceFill(['status' => AgentPlan::APPROVED, 'decided_by' => $by->id, 'decided_at' => now(), 'approved_until' => now()->addHours(self::APPROVAL_HOURS)])->save();
        $this->inGameOf($plan, fn () => $this->audit->record('agent_plan_approved', $by, $plan->title, ['plan' => $plan->id, 'version' => $plan->version, 'hash' => $plan->content_hash]));
    }

    /** @throws PlanRefused */
    public function reject(AgentPlan $plan, Admin $by, string $reason): void
    {
        $this->assertCanAct($plan, $by);

        if (! in_array($plan->status, [AgentPlan::SUBMITTED, AgentPlan::APPROVED], true)) {
            throw new PlanRefused('decided');
        }

        $plan->forceFill(['status' => AgentPlan::REJECTED, 'decided_by' => $by->id, 'decided_at' => now(), 'approved_until' => null, 'reject_reason' => $reason])->save();
        $this->inGameOf($plan, fn () => $this->audit->record('agent_plan_rejected', $by, $plan->title, ['plan' => $plan->id, 'version' => $plan->version]));
    }

    /** A note for the assistant, which it reads with `plan_status`. @throws PlanRefused */
    public function note(AgentPlan $plan, Admin $by, string $note): void
    {
        $this->assertCanAct($plan, $by);

        $plan->forceFill(['note' => $note, 'noted_by' => $by->id, 'noted_at' => now()])->save();
        $this->inGameOf($plan, fn () => $this->audit->record('agent_plan_noted', $by, $plan->title, ['plan' => $plan->id]));
    }

    /**
     * What the assistant (and the page) is told of a plan: its state as it is now, the decision, the note, the numbers the server measured, and what replaced it.
     *
     * @return array<string, mixed>
     */
    public function statusOf(AgentPlan $plan): array
    {
        $state = $plan->status;

        if ($state === AgentPlan::APPROVED && ! $plan->isApprovedNow()) {
            $state = 'approval_expired';
        }

        $newer = AgentPlan::query()->where('previous_id', $plan->id)->value('id');

        return [
            'plan' => $plan->id,
            'version' => $plan->version,
            'title' => $plan->title,
            'state' => $state,
            'outdated' => $this->isOutdated($plan),
            'approved_until' => $plan->approved_until?->toIso8601String(),
            'decided_by' => $plan->decider?->email,
            'reject_reason' => $plan->reject_reason,
            'note' => $plan->note,
            'noted_at' => $plan->noted_at?->toIso8601String(),
            'measure_status' => $plan->measure_status,
            'measure_error' => $plan->measure_error,
            'measured' => $plan->measured,
            'outside_levers' => $plan->outside_levers ?? [],
            'replaced_by' => $newer,
            'hash' => $plan->content_hash,
        ];
    }

    /**
     * The changes of a plan that are not in the levers it named (D17, point 5): the page marks them, as a plan for the levels has no business changing a text.
     *
     * @param  list<array<string, mixed>>  $changes
     * @param  list<string>  $levers
     * @return list<string> where they are, as document and pointer
     */
    public function outsideLevers(array $changes, array $levers): array
    {
        $outside = [];

        foreach ($changes as $change) {
            $document = (string) $change['document'];
            $section = explode('/', ltrim((string) $change['pointer'], '/'))[0];
            $kind = explode(':', $document, 2)[0];
            $inside = in_array($document, $levers, true) || in_array('kind:'.$kind, $levers, true) || in_array($document.'#'.$section, $levers, true);

            if (! $inside) {
                $outside[] = trim($document.' '.$change['pointer']);
            }
        }

        return array_values(array_unique($outside));
    }

    /**
     * The days the players buy the cars of the ladder, on the live pack and on the plan, against the days of the design.
     *
     * @param  array<string, mixed>  $live
     * @param  array<string, mixed>  $planned
     * @return list<array{player: string, car: string, target_day: int, live_day: int|null, plan_day: int|null, plan_ok: bool|null}>
     */
    private function pace(array $live, array $planned): array
    {
        $before = [];

        foreach ($live['gate']['rows'] ?? [] as $row) {
            $before[$row['player'].'|'.$row['car']] = (int) $row['day'];
        }

        return array_map(static fn (array $row): array => [
            'player' => (string) $row['player'],
            'car' => (string) $row['car'],
            'target_day' => (int) $row['target_day'],
            'live_day' => $before[$row['player'].'|'.$row['car']] ?? null,
            'plan_day' => (int) $row['day'],
            'plan_ok' => (bool) $row['passed'],
        ], $planned['gate']['rows'] ?? []);
    }

    /** @throws PlanRefused */
    private function assertDecidable(AgentPlan $plan, Admin $by): void
    {
        $this->assertCanAct($plan, $by);

        if ($plan->status !== AgentPlan::SUBMITTED) {
            throw new PlanRefused($plan->status === AgentPlan::SUPERSEDED ? 'superseded' : 'decided');
        }

        if ($this->isOutdated($plan)) {
            throw new PlanRefused('outdated');
        }
    }

    /**
     * Only a person of the panel decides, who works in the game of the plan with a role that may write drafts: an editor or a publisher, as the plans of this stage
     * only write drafts.
     *
     * @throws PlanRefused
     */
    private function assertCanAct(AgentPlan $plan, Admin $by): void
    {
        if (! $by->is_active || ! $by->canEnter($plan->game_id) || ! in_array($by->roleIn($plan->game_id), [Admin::ROLE_EDITOR, Admin::ROLE_PUBLISHER], true)) {
            throw new PlanRefused('role');
        }
    }

    /** Writes the trail with the game of the plan in force, and puts back the one that was. */
    private function inGameOf(AgentPlan $plan, callable $work): void
    {
        $before = $this->current->game();
        $this->current->set(Game::query()->find($plan->game_id));

        try {
            $work();
        } finally {
            $this->current->set($before);
        }
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
