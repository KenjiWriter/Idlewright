<?php

declare(strict_types=1);

namespace App\Agent;

use App\Games\CurrentGame;
use App\Models\Admin;
use App\Models\AgentGrant;
use App\Models\Game;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Passport\Passport;

/**
 * The consents of AI assistants: giving one, finding the one in force for a key, and ending one. A consent is one person and one client of OAuth; a new consent of the same
 * person to the same client ends the earlier one and the keys made under it, so a key never carries a consent that the person did not give last.
 */
final class AgentGrants
{
    public function __construct(private readonly AdminAudit $audit, private readonly CurrentGame $current) {}

    /**
     * A person gives an assistant a consent: in a game they may enter, at a level their role there allows, for a number of hours.
     *
     * @throws InvalidArgumentException when the person may not give it
     */
    public function open(Admin $admin, Game $game, string $clientId, string $clientName, AgentLevel $level, int $hours): AgentGrant
    {
        if (! $admin->is_active || $admin->getAppAuthenticationSecret() === null) {
            throw new InvalidArgumentException('An admin who is switched off, or who has no authenticator app, gives no consent.');
        }

        if (! $admin->canEnter($game) || ! $level->allowedFor($admin->roleIn($game))) {
            throw new InvalidArgumentException("The admin may not give level {$level->value} in the game {$game->slug}.");
        }

        $hours = max(1, min($hours, (int) config('ride.agent.max_hours')));

        $grant = DB::transaction(function () use ($admin, $game, $clientId, $clientName, $level, $hours): AgentGrant {
            AgentGrant::query()->where('admin_id', $admin->id)->where('oauth_client_id', $clientId)->active()
                ->update(['revoked_at' => now(), 'revoked_by' => $admin->id]);
            $this->revokeKeys($admin->id, $clientId);

            return AgentGrant::query()->create([
                'admin_id' => $admin->id,
                'game_id' => $game->id,
                'oauth_client_id' => $clientId,
                'client_name' => mb_substr($clientName, 0, 255),
                'level' => $level,
                'expires_at' => now()->addHours($hours),
            ]);
        });

        $this->inGame($game, fn () => $this->audit->record('agent_connected', $admin, $clientName, ['grant' => $grant->id, 'level' => $level->value, 'hours' => $hours]));

        return $grant;
    }

    /** The consent in force for a person and a client of OAuth: the latest one, while nobody ended it and its time is not up. */
    public function activeFor(int|string $adminId, string $clientId): ?AgentGrant
    {
        return AgentGrant::query()->where('admin_id', $adminId)->where('oauth_client_id', $clientId)->active()->latest('id')->first();
    }

    /** Ends a consent now, with every key made under it. */
    public function revoke(AgentGrant $grant, ?Admin $by = null): void
    {
        if ($grant->revoked_at === null) {
            $grant->forceFill(['revoked_at' => now(), 'revoked_by' => $by?->id])->save();
        }

        $this->revokeKeys($grant->admin_id, $grant->oauth_client_id);
        $this->inGame($grant->game, fn () => $this->audit->record('agent_disconnected', $by ?? $grant->admin, $grant->client_name, ['grant' => $grant->id]));
    }

    /** Revokes the keys (and the keys to renew them) that a client of OAuth holds for a person: what is left of a consent that is not in force. */
    public function revokeKeys(int|string $adminId, string $clientId): void
    {
        $tokens = Passport::token()->newQuery()->where('user_id', $adminId)->where('client_id', $clientId)->where('revoked', false);
        $ids = $tokens->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        Passport::token()->newQuery()->whereIn('id', $ids)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $ids)->update(['revoked' => true]);
    }

    /** Does something with a game in force (the trail writes down the game in force), and puts back the one that was. */
    private function inGame(?Game $game, callable $work): void
    {
        $before = $this->current->game();
        $this->current->set($game);

        try {
            $work();
        } finally {
            $this->current->set($before);
        }
    }
}
