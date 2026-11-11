<?php

declare(strict_types=1);

use App\Agent\AgentLevel;
use App\Filament\Pages\AgentPage;
use App\Models\Admin;
use App\Models\AgentAction;
use App\Models\AgentGrant;
use App\Models\Game;
use Illuminate\Support\Str;
use Livewire\Livewire;

// The page of the AI assistant (most AI v2, V0): how to connect Claude, the message to start with (no key in it), the consents in force in the game with "Rozłącz", and
// the last calls of the assistant.

beforeEach(function () {
    contentPacks()->import(repositoryContent(), activate: true, checkTables: false);
    app()->setLocale('pl');
});

function grantOf(Admin $admin, int $level = 2): AgentGrant
{
    return AgentGrant::query()->create([
        'admin_id' => $admin->id, 'game_id' => Game::query()->where('is_default', true)->value('id'), 'oauth_client_id' => (string) Str::uuid(),
        'client_name' => 'Claude', 'level' => AgentLevel::from($level), 'expires_at' => now()->addHours(8),
    ]);
}

it('says how to connect Claude, with the address of the connection, and shows what the assistant did', function () {
    $admin = signedInAdmin();
    $grant = grantOf($admin);
    AgentAction::query()->create(['agent_grant_id' => $grant->id, 'game_id' => $grant->game_id, 'tool' => 'rules_read', 'summary' => 'rules player: 15 numbers', 'ok' => true, 'ms' => 12]);

    $this->get('/admin/agent')->assertOk()
        ->assertSee(__('admin.agent.title'))
        ->assertSee(url('/agent/mcp'))
        ->assertSee('claude mcp add --transport http idlewright')
        ->assertSee('boss@example.com')
        ->assertSee('rules player: 15 numbers');
});

it('makes the message to start with from the goal, with no key or secret in it', function () {
    signedInAdmin();

    $starter = Livewire::test(AgentPage::class)->set('data.goal', 'Poziomy 1–15 w około 2 godziny.')->instance()->starter();

    expect($starter)->toContain('RideIdle')
        ->toContain('`zasady`')
        ->toContain('Mój cel: Poziomy 1–15 w około 2 godziny.')
        ->not->toContain('Bearer');
});

it('lets the person who gave a consent, or a publisher, end it, and nobody else', function () {
    $owner = signedInAdmin();
    $grant = grantOf($owner);

    $other = anAdmin(['email' => 'editor@example.com', 'role' => Admin::ROLE_EDITOR]);
    $other->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->actingAs($other, 'admin');

    Livewire::test(AgentPage::class)->call('disconnect', $grant->id)->assertNotified(__('admin.agent.page.not_yours'));
    expect($grant->refresh()->revoked_at)->toBeNull();

    $this->actingAs($owner, 'admin');
    Livewire::test(AgentPage::class)->call('disconnect', $grant->id)->assertNotified(__('admin.agent.page.disconnected'));

    expect($grant->refresh()->revoked_at)->not->toBeNull()
        ->and($grant->revoked_by)->toBe($owner->id);
});
