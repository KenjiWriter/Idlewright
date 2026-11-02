<?php

declare(strict_types=1);
use App\Content\Bridge\Brief;
use App\Content\Bridge\BriefRecorder;
use App\Content\Bridge\BriefSelection;
use App\Models\AiExchange;
use App\Models\Game;
use Illuminate\Support\Str;

// A block of changes the way an AI would write it, and the answer it is written in: shared by the tests of the bridge.

/** @param array<string, mixed> $over */
function bridgeBlockOf(array $over = []): array
{
    return [
        'format' => 'ride-changes/1',
        'game' => 'raceidle',
        'base' => ['pack' => '2026.11.1', 'snapshot' => '9f3a1c20', 'brief' => 'abcd1234'],
        'summary' => 'Engines of tier 3: 12% more power',
        'ops' => [['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => 3]], 'path' => '/stats/power_kw', 'percent' => 12]],
        ...$over,
    ];
}

/** @param array<string, mixed> $block */
function bridgeFenced(array $block, string $label = 'ride-changes'): string
{
    return "Here is the change we agreed on.\n\n```{$label}\n".json_encode($block, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n```\n\nSay if you want something else.\n";
}

/** An instruction that the game gave, with what it contained: what an answer carrying its code may change. @param list<string>|null $scope */
function bridgeBrief(?array $scope = null): AiExchange
{
    return AiExchange::query()->create([
        'code' => Str::lower(Str::random(8)),
        'kind' => AiExchange::KIND_BRIEF,
        'scope' => $scope ?? ['kind:vehicle', 'kind:part', 'rules:starter'],
        'selection' => 'test',
    ]);
}

/** A canned answer of an AI (tests/Fixtures/ai), with the instruction it answers filled into it. */
function bridgeCanned(string $name, Brief $brief): string
{
    return str_replace(
        ['{{game}}', '{{pack}}', '{{snapshot}}', '{{brief}}'],
        [(string) Game::query()->where('is_default', true)->value('slug'), $brief->pack, $brief->snapshot, $brief->code],
        (string) file_get_contents(__DIR__.'/../Fixtures/ai/'.$name.'.md'),
    );
}

/** An instruction made the way the page makes it, for a new admin. */
function bridgeTheBrief(?BriefSelection $selection = null): Brief
{
    return app(BriefRecorder::class)->prepare($selection ?? new BriefSelection(locale: 'en'), anAdmin(['email' => 'ai-'.uniqid().'@example.com']));
}
