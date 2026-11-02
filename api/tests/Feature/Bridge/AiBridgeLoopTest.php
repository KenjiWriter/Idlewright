<?php

declare(strict_types=1);

use App\Content\Bridge\Brief;
use App\Content\Bridge\BriefRecorder;
use App\Content\Bridge\BriefSelection;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ChangeChecker;
use App\Models\Game;

// The whole conversation with the AI, without the AI: the instruction is made, a canned answer of the kind a model gives comes back, the panel checks it, and when it is
// wrong the message that goes back to the AI is the one that gets a right answer.

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** A canned answer, with the instruction it answers filled into it. */
function cannedAnswer(string $name, Brief $brief): string
{
    return str_replace(
        ['{{game}}', '{{pack}}', '{{snapshot}}', '{{brief}}'],
        [(string) Game::query()->where('is_default', true)->value('slug'), $brief->pack, $brief->snapshot, $brief->code],
        (string) file_get_contents(__DIR__.'/../../Fixtures/ai/'.$name.'.md'),
    );
}

function answerTo(Brief $brief, string $name): ChangeCheck
{
    return app(ChangeChecker::class)->check(cannedAnswer($name, $brief));
}

function theBrief(?BriefSelection $selection = null): Brief
{
    return app(BriefRecorder::class)->prepare($selection ?? new BriefSelection(locale: 'en'), anAdmin());
}

it('takes the answer for "all the engines of tier 3, 12% more power" to the two engines, with the power each would have', function () {
    $check = answerTo(theBrief(), 'engines-t3');

    $power = fn (string $slug): float => round(repositoryDocument("parts/{$slug}")['stats']['power_kw'] * 1.12, 2);

    expect($check->errors())->toBe([])
        ->and($check->ok())->toBeTrue()
        ->and(collect($check->changes)->mapWithKeys(fn ($change): array => [$change->document => $change->after])->all())
        ->toEqual(['part:engine-r4-2-0-t3' => $power('engine-r4-2-0-t3'), 'part:engine-v6-2-5-t3' => $power('engine-v6-2-5-t3')])
        ->and($check->changes[0]->opId)->toBe('t3-power');
});

it('takes the answer for "change the price of the VIP" to the one number', function () {
    $check = answerTo(theBrief(), 'vip-price');

    expect($check->ok())->toBeTrue()
        ->and($check->changes)->toHaveCount(1)
        ->and($check->changes[0])->toMatchObject(['document' => 'rules:starter', 'before' => 150, 'after' => 120]);
});

it('goes round the repair loop: a wrong answer is refused, the message for the AI says what to fix, and the corrected answer is taken', function () {
    $brief = theBrief();

    $wrong = answerTo($brief, 'vip-wrong-unit');
    $message = $wrong->repairMessage();

    // The first operation has the wrong unit and the second would make the VIP last longer than it may: both are named, with the id the AI gave them.
    expect($wrong->ok())->toBeFalse()
        ->and($message)->toContain('Fix only the points below')
        ->and($message)->toContain('1. op 1 (id vip-price) [unit_mismatch] at rules:starter /premium/vip/coins')
        ->and($message)->toContain('2. op 2 (id vip-days) [above_max] at rules:starter /premium/vip/days')
        ->and($wrong->changes)->toBe([]);

    // Nothing of a refused block is half-applied, and the corrected one is taken whole.
    $fixed = answerTo($brief, 'vip-fixed');

    expect($fixed->errors())->toBe([])->and($fixed->ok())->toBeTrue()->and($fixed->changes[0]->after)->toBe(120);
});

it('refuses an answer about what the instruction did not hold, and says so in the message for the AI', function () {
    $rulesOnly = theBrief(new BriefSelection(vehicles: false, parts: false, locale: 'en'));

    $check = answerTo($rulesOnly, 'engines-t3');

    expect($check->ok())->toBeFalse()
        ->and($check->repairMessage())->toContain('[out_of_scope]')
        ->and($check->repairMessage())->toContain('part:engine-');
});

it('refuses an answer that was made for another instruction, or for another game', function () {
    $brief = theBrief();

    $other = str_replace($brief->code, 'someoneelse', cannedAnswer('vip-price', $brief));
    $game = str_replace((string) Game::query()->where('is_default', true)->value('slug'), 'another-game', cannedAnswer('vip-price', $brief));

    expect(app(ChangeChecker::class)->check($other)->errors()[0]->code)->toBe('unknown_brief')
        ->and(app(ChangeChecker::class)->check($game)->errors()[0]->code)->toBe('wrong_game');
});
