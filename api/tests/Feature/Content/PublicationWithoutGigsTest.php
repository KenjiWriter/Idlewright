<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Models\Admin;
use App\Models\ContentDefinition;
use App\Models\ContentPublication;
use App\Services\ContentDrafts;
use App\Services\ContentPublisher;
use Illuminate\Support\Carbon;

// A game of a server whose first pack came before the gigs: the live pack has no document of them, and the panel has never had one to work on. A pack made of
// its drafts has no gigs either, and the gate of the gigs has nothing to hold against the ladder, so it lets the pack through.

beforeEach(function () {
    // The pack of the game is numbered 2026.10.0 (`ride.ruleset_version`), and the first pack asked for in a month is `.0`: it is `.1` only in October.
    Carbon::setTestNow('2026-10-15 12:00:00');

    contentPacks()->import(contentWith([], removed: ['rules/gigs']), activate: true, checkTables: false);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('goes through the gates of a pack of a game that has no gigs', function () {
    $publisher = Admin::query()->where('email', 'publisher@example.com')->first() ?? anAdmin(['email' => 'publisher@example.com', 'role' => Admin::ROLE_PUBLISHER]);
    $tutorial = ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', 'tutorial')->firstOrFail();
    $document = JsonDocument::decode((string) repositoryContent()->raw('rules/tutorial'));
    app(ContentDrafts::class)->save($tutorial, [...$document, 'messenger' => ['device' => 'letter']], $publisher);

    expect(ContentDefinition::query()->where('kind', ContentDefinition::KIND_RULES)->where('slug', 'gigs')->exists())->toBeFalse();

    $publication = app(ContentPublisher::class)->queue($publisher)->refresh();

    expect($publication->report['failed_step'])->toBeNull()
        ->and($publication->status)->toBe(ContentPublication::STATUS_READY)
        ->and(array_column($publication->report['gates'], 'name'))->toContain('gigs:report --strict')
        ->and(array_column($publication->report['gates'], 'passed'))->each->toBeTrue();
});
