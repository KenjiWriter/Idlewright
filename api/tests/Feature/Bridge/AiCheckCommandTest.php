<?php

declare(strict_types=1);

use App\Content\Bridge\ContentSnapshot;
use App\Models\ContentDefinition;
use App\Models\Game;
use Illuminate\Support\Facades\Artisan;

// The command that looks at the answer of an AI the way the panel will, and writes nothing.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(function (): void {
    contentPacks()->import(repositoryContent(), activate: true, checkTables: false);
    $this->answer = tempnam(sys_get_temp_dir(), 'ai-answer');
});

afterEach(fn () => is_file($this->answer) && unlink($this->answer));

/** @param list<array<string, mixed>> $ops */
function bridgeAnswer(string $path, array $ops, array $over = []): void
{
    file_put_contents($path, bridgeFenced(bridgeBlockOf([
        'game' => Game::query()->where('is_default', true)->value('slug'),
        'base' => ['pack' => contentPacks()->active()->number, 'snapshot' => app(ContentSnapshot::class)->current(), 'brief' => bridgeBrief()->code],
        'ops' => $ops,
        ...$over,
    ])));
}

it('shows what an accepted block would change, number by number, and says that nothing was written', function () {
    bridgeAnswer($this->answer, [bridgeBlockOf()['ops'][0]]);

    $this->artisan('ai:check', ['file' => $this->answer])
        ->expectsOutputToContain('engine-v6-2-5-t3')
        ->expectsOutputToContain('Power kw')
        ->expectsOutputToContain('Nothing was written.')
        ->assertSuccessful();

    expect(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
});

it('refuses a block that is wrong with a failure, and prints the message for the AI when asked', function () {
    bridgeAnswer($this->answer, [['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/coins', 'value' => 120, 'unit' => 'percent']]);

    $exit = Artisan::call('ai:check', ['file' => $this->answer, '--repair' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('[unit_mismatch]')
        ->and($output)->toContain('Fix only the points below');
});

it('keeps a block to the scope it is given', function () {
    bridgeAnswer($this->answer, [bridgeBlockOf()['ops'][0]]);

    $this->artisan('ai:check', ['file' => $this->answer, '--scope' => ['rules:starter']])->expectsOutputToContain('out_of_scope')->assertFailed();
    $this->artisan('ai:check', ['file' => $this->answer, '--scope' => ['kind:part']])->assertSuccessful();
});

it('prints the same as JSON for a program to read', function () {
    bridgeAnswer($this->answer, [bridgeBlockOf()['ops'][0]]);

    $this->artisan('ai:check', ['file' => $this->answer, '--json' => true])->expectsOutputToContain('"ok":true')->assertSuccessful();
});

it('says when there is no such file or no such game', function () {
    $this->artisan('ai:check', ['file' => '/no/such/file.txt'])->expectsOutputToContain('There is no file')->assertFailed();
    $this->artisan('ai:check', ['file' => $this->answer, '--game' => 'no-such-game'])->expectsOutputToContain('There is no game no-such-game.')->assertFailed();
});

it('makes the instruction from the console as the panel does, and says what it holds', function () {
    $out = tempnam(sys_get_temp_dir(), 'ai-brief');

    Artisan::call('ai:brief', ['--out' => $out, '--locale' => 'en', '--without' => ['vehicles'], '--section' => ['premium'], '--all-numbers' => true]);

    $text = (string) file_get_contents($out);
    unlink($out);

    expect(Artisan::output())->toContain('it holds kind:part rules:starter#premium')
        ->and($text)->toContain('Assistant for changing the parameters of one game')
        ->and($text)->toContain('RULES {"document":"starter","section":"premium"}')
        ->and($text)->not->toContain('"kind":"vehicle"');
});
