<?php

declare(strict_types=1);

use App\Content\ContentException;
use App\Models\ContentDefinition;
use App\Models\ContentPack;
use App\Models\ContentPublication;
use App\Models\ContentUpstream;
use App\Services\ContentRebase;

// content:rebase: the content of the panel brought up to the files of a release. The database started from an import of older files (fuel at 1.80, no
// vocabulary yet), the panel published its own changes on top of that, and the files of the repository are the release.

/** The pack the database started with: the files as they were then. */
function rebaseStart(): ContentPack
{
    return contentPacks()->import(contentWith(['rules/starter' => function (array $starter): array {
        $starter['fuel']['price_cents_per_liter'] = 180;

        return $starter;
    }], removed: ['rules/vocabulary']), number: '2026.10.0', activate: true, checkTables: false)->pack;
}

/** A pack that a publication of the panel made from the start, with Złomowo moved on the map, live. */
function rebasePublished(): ContentPack
{
    $pack = contentPacks()->import(contentWith(['rules/starter' => function (array $starter): array {
        $starter['fuel']['price_cents_per_liter'] = 180;
        $starter['regions']['zlomowo']['map']['x'] = 15;

        return $starter;
    }], removed: ['rules/vocabulary']), number: '2026.11.1', activate: true, checkTables: false)->pack;

    ContentPublication::query()->create(['number' => '2026.11.1', 'status' => ContentPublication::STATUS_READY, 'content_pack_id' => $pack->id]);

    return $pack;
}

/** Puts a draft of the starter rules in the panel. */
function rebaseDraftStarter(Closure $change): void
{
    $definition = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();
    $document = contentArray(json_decode($definition->draft ?? $definition->latestVersion->document));

    $definition->forceFill(['draft' => json_encode($change($document), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)])->save();
}

/** @return array<string, mixed> */
function rebaseStarterDraft(): array
{
    return json_decode((string) ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->value('draft'), true);
}

describe('content:rebase', function () {
    it('takes in what the files changed since the pack the database started with, and keeps what the panel published', function () {
        rebaseStart();
        rebasePublished();

        $this->artisan('content:rebase')
            ->expectsOutputToContain('Base: pack 2026.10.0')
            ->expectsOutputToContain('merged: changed in the panel and in the files')
            ->expectsOutputToContain('new in the files: a draft of a new definition')
            ->expectsOutputToContain('2 draft(s) written')
            ->assertExitCode(0);

        $vocabulary = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'vocabulary')->firstOrFail();

        expect(rebaseStarterDraft()['fuel']['price_cents_per_liter'])->toBe(200)
            ->and(rebaseStarterDraft()['regions']['zlomowo']['map']['x'])->toBe(15)
            ->and($vocabulary->status)->toBe(ContentDefinition::STATUS_DRAFT)
            ->and($vocabulary->draft)->toBe(repositoryContent()->raw('rules/vocabulary'))
            ->and(ContentUpstream::query()->sole()->checksum)->toBe(repositoryContent()->checksum())
            ->and(contentPacks()->validate(contentPacks()->working(['pack' => (string) repositoryContent()->raw('pack')]), checkTables: false)->isValid())->toBeTrue();

        // The next one starts from the files it took in, which hold nothing new now.
        $this->artisan('content:rebase')
            ->expectsOutputToContain('Base: the files that the last content:rebase took in')
            ->expectsOutputToContain('nothing to do')
            ->assertExitCode(0);

        expect(ContentUpstream::query()->count())->toBe(1);
    });

    it('stops at a place the panel and the files changed in two ways and writes nothing, until a side is preferred', function () {
        rebaseStart();
        rebaseDraftStarter(function (array $starter): array {
            $starter['fuel']['price_cents_per_liter'] = 250;

            return $starter;
        });

        $this->artisan('content:rebase')
            ->expectsOutputToContain('rules/starter /fuel/price_cents_per_liter: base 180, panel 250, files 200')
            ->expectsOutputToContain('Nothing was written')
            ->assertExitCode(1);

        expect(rebaseStarterDraft()['fuel']['price_cents_per_liter'])->toBe(250)
            ->and(ContentUpstream::query()->count())->toBe(0)
            ->and(ContentDefinition::query()->where('slug', 'vocabulary')->exists())->toBeFalse();

        $this->artisan('content:rebase', ['--prefer' => 'files'])
            ->expectsOutputToContain('(the files win)')
            ->assertExitCode(0);

        expect(rebaseStarterDraft()['fuel']['price_cents_per_liter'])->toBe(200);
    });

    it('keeps the side of the panel with --prefer=panel', function () {
        rebaseStart();
        rebaseDraftStarter(function (array $starter): array {
            $starter['fuel']['price_cents_per_liter'] = 250;

            return $starter;
        });

        $this->artisan('content:rebase', ['--prefer' => 'panel'])->assertExitCode(0);

        expect(rebaseStarterDraft()['fuel']['price_cents_per_liter'])->toBe(250)
            ->and(ContentUpstream::query()->count())->toBe(1);
    });

    it('only says what would change with --dry-run', function () {
        rebaseStart();

        $this->artisan('content:rebase', ['--dry-run' => true])
            ->expectsOutputToContain('rules/vocabulary')
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertExitCode(0);

        expect(ContentUpstream::query()->count())->toBe(0)
            ->and(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
    });

    it('starts from the pack it is given, and refuses one that does not exist', function () {
        rebaseStart();

        $this->artisan('content:rebase', ['--base' => '2026.10.0', '--dry-run' => true])->expectsOutputToContain('Base: pack 2026.10.0')->assertExitCode(0);
        $this->artisan('content:rebase', ['--base' => '2026.9.9'])->expectsOutputToContain('There is no pack 2026.9.9.')->assertExitCode(1);
        $this->artisan('content:rebase', ['--prefer' => 'both'])->expectsOutputToContain('--prefer is panel or files.')->assertExitCode(1);
    });

    it('says when there is nothing to start from', function () {
        $this->artisan('content:rebase')->expectsOutputToContain('the database has no pack')->assertExitCode(1);
    });
});

describe('the plan of a rebase', function () {
    it('does not write content that does not pass the validation', function () {
        $start = rebaseStart();
        $rebase = app(ContentRebase::class);
        $files = contentWith(['rules/starter' => function (array $starter): array {
            $starter['fuel'] = 'a lot';

            return $starter;
        }]);

        $plan = $rebase->plan($files, contentPacks()->sourceOf($start), 'pack 2026.10.0');

        expect($plan->errors)->not->toBe([])
            ->and($plan->canApply())->toBeFalse()
            ->and(fn () => $rebase->apply($plan, $files, 'test'))->toThrow(ContentException::class);
    });
});
