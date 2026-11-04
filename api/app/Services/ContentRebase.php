<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\ContentException;
use App\Content\ContentSource;
use App\Content\Publishing\CandidatePack;
use App\Content\Rebase\Absent;
use App\Content\Rebase\JsonMerge;
use App\Content\Rebase\RebasePlan;
use App\Models\ContentDefinition;
use App\Models\ContentPack;
use App\Models\ContentPublication;
use App\Models\ContentUpstream;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brings the content of the panel up to the files of a release. When the game reads its content from the database, a release that adds or changes documents in
 * content/ does not change what the panel works on: the panel goes on from the pack it was imported from. This merges, document by document, what the files
 * changed since that base with what the panel changed since it (JsonMerge), and writes the result as drafts, which a publication then makes into a pack as usual
 * (the gates, the tables made again, the guards of what players use). The files it took in are kept as the base of the next rebase.
 */
final class ContentRebase
{
    public function __construct(private readonly ContentPacks $packs, private readonly CandidatePack $candidates) {}

    /**
     * What the content of the panel came from, when nobody says: the files that the last rebase took in, or else the last pack that no publication made (the import
     * of the files that the database started with).
     *
     * @return array{source: ContentSource, label: string}|null
     */
    public function defaultBase(): ?array
    {
        $upstream = ContentUpstream::query()->latest('id')->first();

        if ($upstream !== null) {
            return ['source' => $upstream->source(), 'label' => "the files that the last content:rebase took in ({$upstream->created_at?->format('Y-m-d H:i')}, ".substr($upstream->checksum, 0, 12).')'];
        }

        $pack = ContentPack::query()
            ->whereNotIn('id', ContentPublication::query()->whereNotNull('content_pack_id')->select('content_pack_id'))
            ->latest('id')
            ->first();

        return $pack === null ? null : ['source' => $this->packs->sourceOf($pack), 'label' => "pack {$pack->number} (made {$pack->created_at?->format('Y-m-d H:i')}), the last pack that no publication made"];
    }

    /**
     * A pack as the base, by its number.
     *
     * @return array{source: ContentSource, label: string}
     *
     * @throws ContentException when there is no such pack
     */
    public function packBase(string $number): array
    {
        $pack = ContentPack::query()->where('number', $number)->first() ?? throw new ContentException("There is no pack {$number}.");

        return ['source' => $this->packs->sourceOf($pack), 'label' => "pack {$pack->number} (made {$pack->created_at?->format('Y-m-d H:i')})"];
    }

    /** What the rebase would do, without writing anything. */
    public function plan(ContentSource $files, ContentSource $base, string $label, ?string $prefer = null): RebasePlan
    {
        if ($files->checksum() === $base->checksum()) {
            return new RebasePlan($label, nothingNew: true);
        }

        $panel = $this->packs->working();
        $archived = ContentDefinition::query()->where('status', ContentDefinition::STATUS_ARCHIVED)->get(['kind', 'slug'])
            ->map(fn (ContentDefinition $definition): string => array_search($definition->kind, ContentPacks::KINDS, true).'/'.$definition->slug)
            ->flip();

        $keys = array_values(array_unique([...$base->keys(), ...$panel->keys(), ...$files->keys()]));
        sort($keys);

        $drafts = [];
        $rows = [];
        $conflicts = [];

        foreach ($keys as $key) {
            $before = $base->raw($key);
            $mine = $panel->raw($key);
            $theirs = $files->raw($key);

            if ($theirs === null) {
                if ($before !== null && $mine !== null) {
                    $rows[] = ['key' => $key, 'change' => RebasePlan::KEPT];
                }

                continue;
            }

            if ($mine === null) {
                if (isset($archived[$key])) {
                    if ($before === null || ! JsonMerge::same(JsonMerge::decode($before), JsonMerge::decode($theirs))) {
                        $rows[] = ['key' => $key, 'change' => RebasePlan::ARCHIVED];
                    }

                    continue;
                }

                $drafts[$key] = $theirs;
                $rows[] = ['key' => $key, 'change' => RebasePlan::ADDED];

                continue;
            }

            $was = JsonMerge::decode($before);
            $now = JsonMerge::decode($mine);
            $new = JsonMerge::decode($theirs);

            // A table is made by commands out of the rest: the one the files made again wins (and a publication makes the bots, the pace and the units again anyway).
            if (str_starts_with($key, 'tables/')) {
                if (! JsonMerge::same($new, $was) && ! JsonMerge::same($new, $now)) {
                    $drafts[$key] = $theirs;
                    $rows[] = ['key' => $key, 'change' => RebasePlan::TABLE];
                }

                continue;
            }

            $merge = new JsonMerge($prefer ?? JsonMerge::PANEL);
            $merged = $merge->merge($was, $now, $new);

            foreach ($merge->conflicts() as $conflict) {
                $conflicts[] = ['key' => $key, ...$conflict];
            }

            if ($merged === Absent::Value || JsonMerge::same($merged, $now)) {
                continue;
            }

            $fromFiles = JsonMerge::same($merged, $new);
            $drafts[$key] = $fromFiles ? $theirs : JsonMerge::encode($merged);
            $rows[] = ['key' => $key, 'change' => $fromFiles ? RebasePlan::FILES : RebasePlan::MERGED];
        }

        return new RebasePlan($label, false, $drafts, $rows, $conflicts, $prefer, $this->validate($drafts));
    }

    /**
     * Writes the drafts of a plan and keeps the files as the base of the next rebase.
     *
     * @throws ContentException when the plan cannot be written (nothing new, the content does not pass, or a conflict has no winner)
     */
    public function apply(RebasePlan $plan, ContentSource $files, string $from): ContentUpstream
    {
        if (! $plan->canApply()) {
            throw new ContentException('This rebase cannot be written: '.($plan->nothingNew ? 'the files hold nothing new.' : ($plan->errors !== [] ? 'the content does not pass the validation.' : 'a place that both sides changed has no winner.')));
        }

        return DB::transaction(function () use ($plan, $files, $from): ContentUpstream {
            foreach ($plan->drafts as $key => $text) {
                [$folder, $slug] = explode('/', $key, 2);
                $definition = ContentDefinition::query()->firstOrNew(['kind' => ContentPacks::KINDS[$folder], 'slug' => $slug]);

                if (! $definition->exists) {
                    $definition->forceFill(['uid' => (string) Str::ulid(), 'status' => ContentDefinition::STATUS_DRAFT]);
                }

                $definition->forceFill(['draft' => $text, 'draft_admin_id' => null, 'draft_saved_at' => now()])->save();
            }

            $documents = ['pack' => (string) $files->raw('pack')];

            foreach ($files->keys() as $key) {
                $documents[$key] = (string) $files->raw($key);
            }

            return ContentUpstream::query()->create([
                'checksum' => $files->checksum(),
                'source' => Str::limit($from, 500, ''),
                'base' => Str::limit($plan->base, 200, ''),
                'documents' => $documents,
            ]);
        });
    }

    /**
     * The validation of the content as a publication would make it with these drafts (the tables are not judged: a publication makes them again).
     *
     * @param  array<string, string>  $drafts
     * @return list<array{file: string, pointer: string, message: string}>
     */
    private function validate(array $drafts): array
    {
        $number = $this->packs->active()?->number ?? now()->format('Y.n').'.0';

        return $this->packs->validate($this->candidates->make($number)->with($drafts), checkTables: false)->errors();
    }
}
