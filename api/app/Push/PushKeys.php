<?php

declare(strict_types=1);

namespace App\Push;

use App\Models\Game;
use App\Models\PushSubscription;

/**
 * The key pair of a game for web push (VAPID). The browsers are given the public key when they subscribe, and the subscription belongs to it, so a new pair makes every
 * subscription of the game useless: it is made once, like the `APP_KEY` that the private key is encrypted with, and kept.
 */
final class PushKeys
{
    /**
     * Gives the game a new pair of keys. A game that has one is left alone unless `$replace` says otherwise; replacing it deletes the subscriptions of the game, which the new
     * key cannot serve (the drivers subscribe again when they next open the app).
     *
     * @return int|null the subscriptions that were deleted, or null when the game has keys and they were kept
     */
    public static function create(Game $game, bool $replace = false): ?int
    {
        if ($game->hasPushKeys() && ! $replace) {
            return null;
        }

        $pair = Vapid::generate();
        $deleted = PushSubscription::query()->withoutGlobalScopes()->where('game_id', $game->id)->delete();

        $game->forceFill([
            'push_public_key' => $pair['public'],
            'push_private_key' => $pair['private'],
            'push_subject' => $game->push_subject ?? self::defaultSubject($game),
        ])->save();

        return $deleted;
    }

    /** Who the push services can write to about this game: the contact address of the operator, else the address of the game. */
    public static function defaultSubject(Game $game): string
    {
        $email = (string) config('ride.legal.operator.email');

        if ($email !== '') {
            return 'mailto:'.$email;
        }

        return filled($game->domain) ? 'https://'.$game->domain : (string) config('ride.web_url');
    }
}
