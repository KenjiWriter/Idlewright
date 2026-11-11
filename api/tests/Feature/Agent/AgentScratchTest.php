<?php

declare(strict_types=1);

use App\Agent\AgentAnchors;
use App\Agent\AgentServer;
use App\Agent\Tools\ScratchClearTool;
use App\Agent\Tools\ScratchReadTool;
use App\Agent\Tools\ScratchSetTool;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;

// The scratch of the AI assistant (V1, D12 and D16 of the design): a block of changes that is checked as the bridge checks one and kept under the consent, never saved as
// drafts; what is out of the assistant's reach (the yardstick of the gates, the world, the model and the brand) is refused in it.

require_once __DIR__.'/../../Support/AgentHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** @return array<string, mixed> the operation that sets the experience a driver needs for level 5 */
function earlyXpOp(int $value = 900): array
{
    return ['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/player/early_xp/3', 'value' => $value, 'unit' => 'plain'];
}

it('keeps a block that holds, says every number it changes, and saves nothing as a draft', function () {
    $grant = agentGrantInForce();

    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Poziom 5 wolniej', 'ops' => [earlyXpOp()]])
        ->assertOk()
        ->assertStructuredContentPath('kept', true)
        ->assertStructuredContentPath('changes', function (array $changes): bool {
            expect($changes)->toHaveCount(1)
                ->and($changes[0]['pointer'])->toBe('/player/early_xp/3')
                ->and($changes[0]['before'])->toBe('800')
                ->and($changes[0]['after'])->toBe('900');

            return true;
        });

    expect($grant->refresh()->scratch['ops'][0]['value'])->toBe(900)
        ->and(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);

    AgentServer::tool(ScratchReadTool::class)->assertOk()->assertStructuredContentPath('empty', false)->assertSee('Poziom 5 wolniej');
    AgentServer::tool(ScratchClearTool::class)->assertOk();
    AgentServer::tool(ScratchReadTool::class)->assertOk()->assertStructuredContentPath('empty', true);
});

it('refuses the world, the model, the brand and the yardstick of the gates, and keeps the scratch it had', function (array $op, string $code) {
    $grant = agentGrantInForce();
    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Najpierw dobry', 'ops' => [earlyXpOp()]])->assertOk();

    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Poza zasiegiem', 'ops' => [$op]])
        ->assertOk()
        ->assertStructuredContentPath('kept', false)
        ->assertSee($code);

    expect($grant->refresh()->scratch['summary'])->toBe('Najpierw dobry');
})->with([
    'a track of the world' => [['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/tracks/zlomowo-street/xp_per_hour', 'percent' => 50], 'out_of_scope'],
    'a city of the world' => [['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/regions/kruszyn/weather_ppm/dry', 'percent' => 5], 'out_of_scope'],
    'the model of the game' => [['op' => 'text', 'target' => ['rules' => 'model'], 'path' => '/slots/0/name', 'locale' => 'pl', 'value' => 'X'], 'out_of_scope'],
    'the anchors of the economy' => [['op' => 'set', 'target' => ['rules' => 'economy'], 'path' => '/anchors/net_per_hour/0', 'value' => 1, 'unit' => 'plain'], 'schema'],
]);

it('refuses text with characters a person cannot see', function () {
    $grant = agentGrantInForce();

    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Nazwa', 'ops' => [['op' => 'text', 'target' => ['kind' => 'vehicle', 'slug' => 'rdzak-gt-4'], 'path' => '/name', 'locale' => 'pl', 'value' => "Rdzak\u{200B}"]]])
        ->assertOk()
        ->assertStructuredContentPath('kept', false)
        ->assertSee('hidden_characters');

    expect($grant->refresh()->scratch)->toBeNull();
});

it('says the scratch no longer holds once the drafts under it change so that it cannot', function () {
    $grant = agentGrantInForce();
    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Tansza czesc', 'ops' => [['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'street-tires-t2'], 'path' => '/price_cents', 'percent' => -10]]])
        ->assertStructuredContentPath('kept', true);

    ContentDefinition::query()->where('kind', 'part')->where('slug', 'street-tires-t2')->update(['status' => ContentDefinition::STATUS_ARCHIVED]);

    AgentServer::tool(ScratchReadTool::class)->assertOk()->assertStructuredContentPath('kept', false)->assertSee('unknown_document');
    expect($grant->refresh()->scratch)->not->toBeNull();
});

it('lets the assistant change every section of the rules but the world, and never names the yardstick as something it may change', function () {
    $scope = app(AgentAnchors::class)->scope();

    expect($scope)->toContain('rules:starter#player', 'rules:starter#fuel', 'rules:starter#missions', 'rules:tutorial', 'rules:vocabulary', 'rules:missions', 'kind:part', 'kind:vehicle');

    foreach (AgentAnchors::READ_ONLY_SECTIONS as $section) {
        expect($scope)->not->toContain('rules:starter#'.$section);
    }

    expect($scope)->not->toContain('rules:model', 'rules:branding', 'rules:economy', 'rules:gigs');

    // The documents a block of changes can name at all: the yardstick is not one of them, so no block (of the bridge or of the assistant) can move it.
    $schema = json_decode((string) file_get_contents(base_path('../content/schema/changes.schema.json')), true);
    $targets = $schema['$defs']['target']['oneOf'][0]['properties']['rules']['enum'];

    expect($targets)->not->toContain('economy', 'gigs')
        ->and(array_keys(AgentAnchors::YARDSTICK))->toContain('rules/economy', 'config ride.missions.budget', 'config ride.tuning.budget');
});

it('needs a consent: a tool run from the command line has no scratch', function () {
    $this->artisan('agent:tool', ['name' => 'scratch_read'])->expectsOutputToContain('consent')->assertFailed();
});

it('keeps the drafts of the panel apart from the scratch', function () {
    $grant = agentGrantInForce();
    $definition = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();
    $document = $definition->workingDocument();
    $document['player']['early_xp'][3] = 850;
    app(ContentDrafts::class)->save($definition, $document, $grant->admin);

    AgentServer::tool(ScratchSetTool::class, ['summary' => 'Na szkicu', 'ops' => [earlyXpOp(950)]])
        ->assertStructuredContentPath('changes', fn (array $changes): bool => $changes[0]['before'] === '850' && $changes[0]['published'] === '800' && $changes[0]['after'] === '950');

    expect($definition->refresh()->workingDocument()['player']['early_xp'][3])->toBe(850);
});
