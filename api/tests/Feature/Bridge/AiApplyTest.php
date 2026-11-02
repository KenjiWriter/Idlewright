<?php

declare(strict_types=1);

use App\Content\Bridge\ApplyRefused;
use App\Content\Bridge\Brief;
use App\Content\Bridge\ChangeApplier;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ChangeReverter;
use App\Models\AdminAuditLog;
use App\Models\AiExchange;
use App\Models\ContentDefinition;
use App\Models\ContentVersion;
use App\Services\ContentDrafts;

// Keeping what an AI proposed as drafts, and nothing more: all of it or none, checked again at the moment it is kept, with a trail, and undone only while nobody has worked
// on the drafts since.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

function enginesAnswer(?Brief $brief = null): string
{
    return bridgeCanned('engines-t3', $brief ?? bridgeTheBrief());
}

function partDefinition(string $slug = 'engine-v6-2-5-t3'): ContentDefinition
{
    return ContentDefinition::query()->where('kind', 'part')->where('slug', $slug)->firstOrFail();
}

/** What the drafts are, by slug: the power of the draft of a part, or null when it has none. */
function draftPower(string $slug): ?float
{
    return partDefinition($slug)->draftDocument()['stats']['power_kw'] ?? null;
}

/** @return array<string, array{draft: bool, saved_at: string|null}> */
function seenOf(string $text): array
{
    return app(ChangeChecker::class)->check($text)->touched;
}

describe('keeping the answer as drafts', function () {
    it('keeps every number it changes as a draft of its document, and publishes nothing', function () {
        $text = enginesAnswer();
        $versions = ContentVersion::query()->count();
        $live = repositoryDocument('parts/engine-v6-2-5-t3')['stats']['power_kw'];

        $exchange = app(ChangeApplier::class)->apply($text, anAdmin(), seenOf($text));

        expect(draftPower('engine-v6-2-5-t3'))->toBe(round($live * 1.12, 2))
            ->and(draftPower('engine-r4-2-0-t3'))->toBe(round(repositoryDocument('parts/engine-r4-2-0-t3')['stats']['power_kw'] * 1.12, 2))
            ->and(partDefinition()->publishedDocument()['stats']['power_kw'])->toBe($live)
            ->and(ContentVersion::query()->count())->toBe($versions)
            ->and($exchange->status)->toBe('applied')
            ->and($exchange->applied_at)->not->toBeNull()
            ->and($exchange->text)->toBe($text)
            ->and($exchange->parent->kind)->toBe('brief')
            ->and(collect($exchange->before)->values()->all())->toBe([null, null]);
    });

    it('writes it to the trail: the answer, and the draft of each document', function () {
        $text = enginesAnswer();

        $exchange = app(ChangeApplier::class)->apply($text, $admin = anAdmin(), seenOf($text));

        expect(AdminAuditLog::query()->where('action', 'ai_changes_applied')->where('subject', 'ai_exchange:'.$exchange->code)->count())->toBe(1)
            ->and(AdminAuditLog::query()->where('action', 'ai_changes_checked')->count())->toBe(1)
            ->and(AdminAuditLog::query()->where('action', 'definition_draft_saved')->where('admin_id', $admin->id)->count())->toBe(2)
            ->and(AdminAuditLog::query()->where('action', 'ai_changes_applied')->first()->context['changes'])->toBe(2);
    });

    it('goes on top of a draft that is there, and keeps it for the undoing', function () {
        $part = repositoryDocument('parts/engine-v6-2-5-t3');
        $part['price_cents'] += 100;
        app(ContentDrafts::class)->save(partDefinition(), $part, anAdmin(['email' => 'first@example.com']));
        $draftBefore = partDefinition()->draft;

        $text = enginesAnswer();
        $exchange = app(ChangeApplier::class)->apply($text, anAdmin(), seenOf($text));

        expect(partDefinition()->draftDocument()['price_cents'])->toBe($part['price_cents'])
            ->and(draftPower('engine-v6-2-5-t3'))->toBe(round($part['stats']['power_kw'] * 1.12, 2))
            ->and($exchange->before['part:engine-v6-2-5-t3'])->toBe($draftBefore);
    });

    it('is refused when the answer has errors, and keeps nothing', function () {
        $text = bridgeCanned('vip-wrong-unit', bridgeTheBrief());

        expect(fn () => app(ChangeApplier::class)->apply($text, anAdmin(), []))->toThrow(ApplyRefused::class, 'errors');
        expect(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
    });

    it('is refused when a draft it goes on top of changed after it was checked', function () {
        $text = enginesAnswer();
        $seen = seenOf($text);

        $part = repositoryDocument('parts/engine-v6-2-5-t3');
        $part['price_cents'] += 100;
        app(ContentDrafts::class)->save(partDefinition(), $part, anAdmin());

        try {
            app(ChangeApplier::class)->apply($text, anAdmin(['email' => 'second@example.com']), $seen);
            $this->fail('It was kept.');
        } catch (ApplyRefused $refused) {
            expect($refused->reason)->toBe('changed_meanwhile');
        }

        expect(draftPower('engine-r4-2-0-t3'))->toBeNull();
    });

    it('keeps all of it or none: a document that cannot be kept takes the others with it', function () {
        $text = enginesAnswer();
        $seen = seenOf($text);
        $saved = 0;
        $armed = true;

        // The listener stays after the test (the events of a model are set up once), so it is switched off when the test is done.
        ContentDefinition::saving(function () use (&$saved, &$armed): void {
            if ($armed && ++$saved === 2) {
                throw new RuntimeException('the second document could not be kept');
            }
        });

        expect(fn () => app(ChangeApplier::class)->apply($text, anAdmin(), $seen))->toThrow(RuntimeException::class);

        $armed = false;

        expect(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0)
            ->and(AdminAuditLog::query()->where('action', 'ai_changes_applied')->count())->toBe(0);
    });

    it('does not apply the same text twice unless it is meant to, because a scale adds up', function () {
        $text = enginesAnswer();
        $applier = app(ChangeApplier::class);
        $admin = anAdmin();

        $applier->apply($text, $admin, seenOf($text));
        $once = draftPower('engine-v6-2-5-t3');

        try {
            $applier->apply($text, $admin, seenOf($text));
            $this->fail('It was kept twice.');
        } catch (ApplyRefused $refused) {
            expect($refused->reason)->toBe('already_applied');
        }

        expect(draftPower('engine-v6-2-5-t3'))->toBe($once);

        $applier->apply($text, $admin, seenOf($text), repeat: true);

        expect(draftPower('engine-v6-2-5-t3'))->toBe(round($once * 1.12, 2));
    });
});

describe('undoing it', function () {
    it('puts every draft back as it was, and throws away a draft that was not there', function () {
        $text = enginesAnswer();
        $admin = anAdmin();
        $exchange = app(ChangeApplier::class)->apply($text, $admin, seenOf($text));

        app(ChangeReverter::class)->revert($exchange, $admin);

        expect(draftPower('engine-v6-2-5-t3'))->toBeNull()
            ->and(draftPower('engine-r4-2-0-t3'))->toBeNull()
            ->and($exchange->fresh()->status)->toBe('reverted')
            ->and($exchange->fresh()->reverted_at)->not->toBeNull()
            ->and(AdminAuditLog::query()->where('action', 'ai_changes_reverted')->count())->toBe(1);
    });

    it('puts a draft that was there back as it was', function () {
        $part = repositoryDocument('parts/engine-v6-2-5-t3');
        $part['price_cents'] += 100;
        app(ContentDrafts::class)->save(partDefinition(), $part, anAdmin(['email' => 'first@example.com']));
        $draftBefore = partDefinition()->draft;

        $text = enginesAnswer();
        $admin = anAdmin();
        $exchange = app(ChangeApplier::class)->apply($text, $admin, seenOf($text));

        app(ChangeReverter::class)->revert($exchange, $admin);

        expect(partDefinition()->draft)->toBe($draftBefore)
            ->and(draftPower('engine-r4-2-0-t3'))->toBeNull();
    });

    it('does not undo what somebody has worked on since, and touches nothing when it does not', function () {
        $text = enginesAnswer();
        $admin = anAdmin();
        $exchange = app(ChangeApplier::class)->apply($text, $admin, seenOf($text));

        $later = partDefinition()->draftDocument();
        $later['price_cents'] += 500;
        app(ContentDrafts::class)->save(partDefinition(), $later, $admin);

        try {
            app(ChangeReverter::class)->revert($exchange, $admin);
            $this->fail('It was undone.');
        } catch (ApplyRefused $refused) {
            expect($refused->reason)->toBe('conflict')->and($refused->details)->toBe(['part:engine-v6-2-5-t3']);
        }

        expect(draftPower('engine-r4-2-0-t3'))->not->toBeNull()
            ->and($exchange->fresh()->status)->toBe('applied');
    });

    it('is only for an answer that was kept, and only once', function () {
        $text = enginesAnswer();
        $admin = anAdmin();
        $exchange = app(ChangeApplier::class)->apply($text, $admin, seenOf($text));

        app(ChangeReverter::class)->revert($exchange, $admin);

        expect(fn () => app(ChangeReverter::class)->revert($exchange->fresh(), $admin))->toThrow(ApplyRefused::class)
            ->and(AiExchange::query()->where('kind', 'paste')->count())->toBe(1);
    });
});
