<?php

declare(strict_types=1);

use App\Content\Bridge\BriefBuilder;
use App\Content\Bridge\BriefRecorder;
use App\Content\Bridge\ChangeChecker;
use App\Filament\Resources\Parts\Pages\EditPart;
use App\Models\AdminAuditLog;
use App\Models\AiExchange;
use App\Models\ContentDefinition;
use App\Models\Game;
use Livewire\Livewire;

// The instruction for ONE car or part: the record with its numbers and texts, the ones beside it to measure it by, and an answer that may change that record and nothing else.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

function recordLines(string $text): array
{
    preg_match('/<<<DATA nonce=([0-9a-z]+)>>>\n(.*?)\n<<<END nonce=\1>>>/s', $text, $found);

    return explode("\n", $found[2] ?? '');
}

function recordAnswer(array $ops, object $brief): string
{
    return bridgeFenced([
        'format' => 'ride-changes/1',
        'game' => (string) Game::query()->where('is_default', true)->value('slug'),
        'base' => ['pack' => $brief->pack, 'snapshot' => $brief->snapshot, 'brief' => $brief->code],
        'summary' => 'A change for the test',
        'ops' => $ops,
    ]);
}

it('holds the one record with its numbers and texts and the parts of its slot beside it, and nothing of other slots', function () {
    $brief = app(BriefBuilder::class)->buildRecord('part', 'engine-v6-2-5-t3', 'en', 'code1234');
    $lines = recordLines($brief->text);
    $joined = implode("\n", $lines);

    expect($brief->scope)->toBe(['part:engine-v6-2-5-t3'])
        ->and($joined)->toContain('RECORD {"kind":"part","slug":"engine-v6-2-5-t3"')
        ->and($joined)->toContain('["/stats/power_kw","plain",158.12,')
        ->and($joined)->toContain('["/price_cents","dollars",')
        ->and($joined)->toContain('TEXTS {"kind":"part","slug":"engine-v6-2-5-t3"}')
        ->and($joined)->toContain('"/name",{"en":')
        ->and($joined)->toContain('"engine-r4-2-0-t3"')
        ->and($joined)->not->toContain('"all-season-tires-t1"')
        ->and($brief->tokens)->toBeLessThan(8_000);
});

it('is the same instruction in manner and format, with a code that an answer carries back', function () {
    $brief = app(BriefRecorder::class)->prepareRecord('vehicle', 'rdzak-gt-4', 'pl', anAdmin());
    $row = AiExchange::query()->where('code', $brief->code)->firstOrFail();

    expect($brief->text)->toContain('asking what the person wants')
        ->and($brief->text)->toContain('"brief":"'.$brief->code.'"')
        ->and($row->scope)->toBe(['vehicle:rdzak-gt-4'])
        ->and($row->locale)->toBe('pl')
        ->and(AdminAuditLog::query()->where('action', 'ai_brief_prepared')->count())->toBe(1)
        ->and(app(BriefRecorder::class)->prepareRecord('vehicle', 'rdzak-gt-4', 'pl', anAdmin(['email' => 'second@example.com']))->code)->not->toBe($brief->code);
});

it('lets an answer change that record, and refuses it for any other record or for a new one', function () {
    $brief = app(BriefRecorder::class)->prepareRecord('part', 'engine-v6-2-5-t3', 'en', anAdmin());
    $check = fn (array $ops) => app(ChangeChecker::class)->check(recordAnswer($ops, $brief));

    $own = $check([['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'engine-v6-2-5-t3'], 'path' => '/stats/power_kw', 'percent' => 5]]);
    $other = $check([['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'engine-r4-2-0-t3'], 'path' => '/stats/power_kw', 'percent' => 5]]);
    $select = $check([['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine']], 'path' => '/price_cents', 'percent' => 5]]);
    $create = $check([['op' => 'create', 'kind' => 'part', 'from' => 'engine-v6-2-5-t3', 'slug' => 'engine-v6-2-5-t9', 'fields' => ['price_cents' => 3000000]]]);

    expect($own->errors())->toBe([])
        ->and($own->ok())->toBeTrue()
        ->and(array_map(fn ($issue) => $issue->code, $other->issues))->toBe(['out_of_scope'])
        ->and(array_map(fn ($issue) => $issue->code, $select->issues))->toContain('out_of_scope')
        ->and(array_map(fn ($issue) => $issue->code, $create->issues))->toBe(['out_of_scope']);
});

it('is refused for a record that is not there', function () {
    expect(fn () => app(BriefBuilder::class)->buildRecord('part', 'no-such-part', 'en', 'code1234'))->toThrow(InvalidArgumentException::class);
});

it('is a button on the form of a car or a part, that makes the instruction when its window is opened', function () {
    signedInAdmin();
    $part = ContentDefinition::query()->where('kind', 'part')->where('slug', 'engine-v6-2-5-t3')->firstOrFail();

    Livewire::test(EditPart::class, ['record' => $part->getRouteKey()])
        ->assertActionExists('ai')
        ->mountAction('ai')
        ->assertActionMounted('ai');

    expect(AiExchange::query()->where('kind', 'brief')->firstOrFail()->scope)->toBe(['part:engine-v6-2-5-t3']);
});
