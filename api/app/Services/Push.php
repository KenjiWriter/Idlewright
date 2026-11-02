<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Games\CurrentGame;
use App\Jobs\SendPush;
use App\Models\Game;
use App\Models\PushSubscription;
use App\Models\User;
use App\Push\PushMessage;
use App\Support\Locale;

/**
 * Telling a driver by web push: whether the game can at all (the rules switch it on and the game has its keys), and the messages to the devices of a driver, which the queue
 * sends. The messages are written here, in the language of each device, so the service worker needs no texts.
 */
final class Push
{
    public function __construct(private readonly Rules $rules, private readonly CurrentGame $current) {}

    /** The game in play, whose keys sign the messages. */
    public function game(): Game
    {
        return $this->current->game() ?? Game::query()->findOrFail($this->current->id());
    }

    /** The public key that a browser subscribes with, or null when the game cannot send push messages (switched off in the rules, or no keys made). */
    public function publicKey(): ?string
    {
        $game = $this->game();

        return $this->rules->pushOn() && $game->hasPushKeys() ? $game->push_public_key : null;
    }

    /**
     * Sends the message to every device of the driver that is subscribed and not switched off, through the queue. Returns how many devices it was queued for.
     *
     * @param  array<string, scalar>  $replace
     */
    public function notify(User $user, string $kind, string $url, array $replace = [], ?string $tag = null): int
    {
        if ($this->publicKey() === null) {
            return 0;
        }

        $queued = 0;

        foreach (PushSubscription::query()->where('user_id', $user->id)->whereNull('disabled_at')->get() as $subscription) {
            SendPush::dispatch($subscription->id, $this->message($kind, $subscription->locale ?? $user->locale, $url, $replace, $tag ?? $kind)->toArray());
            $queued++;
        }

        return $queued;
    }

    /**
     * The message of a kind in a language: the texts are in `lang/{locale}/push.php` under the kind (`title` and `body`), with the name of the game for `:game`.
     *
     * @param  array<string, scalar>  $replace
     */
    public function message(string $kind, ?string $locale, string $url, array $replace, string $tag): PushMessage
    {
        $locale = Locale::normalize($locale) ?? Locale::default();
        $laravel = str_replace('-', '_', $locale);
        $replace += ['game' => $this->game()->name];

        return new PushMessage(
            kind: $kind,
            title: trans("push.{$kind}.title", $replace, $laravel),
            body: trans("push.{$kind}.body", $replace, $laravel),
            url: $url,
            tag: $tag,
            ttl: $this->rules->pushTtlSeconds($kind),
            lang: $locale,
        );
    }
}
