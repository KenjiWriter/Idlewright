<?php

declare(strict_types=1);

use App\Content\Bridge\BalancePreview;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ChangeChecker;
use App\Content\Definitions\JsonDocument;
use App\Models\ContentDefinition;
use App\Models\Game;

// What a block would do to the balance of the game before anything is kept: the gates and the simulators of the economy on the content with the block over it, beside the
// same for the content that is live.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** @param list<array<string, mixed>> $ops */
function balanceCheck(array $ops): ChangeCheck
{
    $brief = bridgeTheBrief();

    return app(ChangeChecker::class)->check(bridgeFenced([
        'format' => 'ride-changes/1',
        'game' => (string) Game::query()->where('is_default', true)->value('slug'),
        'base' => ['pack' => $brief->pack, 'snapshot' => $brief->snapshot, 'brief' => $brief->code],
        'summary' => 'A change for the test',
        'ops' => $ops,
    ]));
}

it('runs the gates and measures the economy on the content with the block over it, beside the live pack, and keeps nothing', function () {
    $check = balanceCheck([['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/coins', 'value' => 120, 'unit' => 'plain']]);

    $report = app(BalancePreview::class)->of($check);

    expect($report['errors'])->toBe([])
        ->and(array_column($report['gates'], 'name'))->toContain('tracks:simulate --strict', 'missions:budget --strict')
        ->and(collect($report['gates'])->every(fn (array $gate): bool => $gate['passed']))->toBeTrue()
        ->and($report['now'])->toHaveKeys(['pace', 'ladder'])
        ->and($report['live'])->not->toBeNull()
        ->and($report['changes'])->toBe(1)
        ->and(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
});

it('says what the content does not accept instead of running the simulators, for a block that was not checked', function () {
    $rules = JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));
    $rules['fuel']['price_cents_per_liter'] = -5;

    $report = app(BalancePreview::class)->of(new ChangeCheck(documents: ['rules:starter' => $rules]));

    expect($report['errors'])->not->toBe([])
        ->and($report['errors'][0]['file'])->toContain('starter')
        ->and($report['gates'])->toBe([])
        ->and($report['now'])->toBe([]);
});
