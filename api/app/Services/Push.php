<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Games\CurrentGame;
use App\Jobs\SendPush;
use App\Models\Game;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Push\PushMessage;
use App\Support\Locale;
use Closure;
use Illuminate\Support\Str;

/**
 * Telling a driver by web push: whether the game can at all (the rules switch it on and the game has its keys), and the messages to the devices of a driver, which the queue
 * sends. The messages are written here, in the language of each device, so the service worker needs no texts.
 */
final class Push
{
    public function __construct(private readonly Rules $rules, private readonly CurrentGame $current, private readonly PushArm $arm) {}

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

    /** The public key for this driver: null for one who is never told (the group that the others are compared with, see `PushArm`) as well as when the game cannot send. */
    public function publicKeyFor(User $user): ?string
    {
        return $this->arm->isHoldout($user->id) ? null : $this->publicKey();
    }

    /**
     * Sends the message to every device of the driver that is subscribed and not switched off, through the queue. Returns how many devices it was queued for.
     *
     * `$kind` is what the message is about (it decides how long the push service keeps it), `$text` the key of the words under `lang/{locale}/push.php` (the kind when it is not
     * given), `$replace` the values of the words or a function of the language of a device that gives them (a car has a name in each language), and `$count` the number that the
     * words are in the plural for.
     *
     * @param  array<string, scalar>|Closure(string): array<string, scalar>  $replace
     */
    public function notify(User $user, string $kind, string $url, array|Closure $replace = [], ?string $tag = null, ?string $text = null, ?int $count = null): int
    {
        if ($this->publicKeyFor($user) === null) {
            return 0;
        }

        $queued = 0;

        foreach (PushSubscription::query()->where('user_id', $user->id)->whereNull('disabled_at')->get() as $subscription) {
            $locale = Locale::normalize($subscription->locale ?? $user->locale) ?? Locale::default();
            $values = $replace instanceof Closure ? $replace($locale) : $replace;

            // The message carries a number that comes back when the notification is opened (the worker has no token of the driver to say who opened it).
            $nid = Str::random(24);
            PushDelivery::query()->create(['user_id' => $user->id, 'nid' => $nid, 'kind' => $kind]);

            SendPush::dispatch($subscription->id, $this->message($kind, $locale, $url, $values, $tag ?? $kind, $text ?? $kind, $count, $nid)->toArray());
            $queued++;
        }

        return $queued;
    }

    /**
     * The message of a kind in a language: the words are in `lang/{locale}/push.php` under the key `$text` (`title` and `body`), with the name of the game for `:game`.
     *
     * @param  array<string, scalar>  $replace
     */
    public function message(string $kind, ?string $locale, string $url, array $replace, string $tag, ?string $text = null, ?int $count = null, ?string $nid = null): PushMessage
    {
        $locale = Locale::normalize($locale) ?? Locale::default();
        $laravel = str_replace('-', '_', $locale);
        $text ??= $kind;
        $replace += ['game' => $this->game()->name];

        return new PushMessage(
            kind: $kind,
            title: trans("push.{$text}.title", $replace, $laravel),
            body: $count === null ? trans("push.{$text}.body", $replace, $laravel) : trans_choice("push.{$text}.body", $count, $replace + ['count' => $count], $laravel),
            url: $url,
            tag: $tag,
            ttl: $this->rules->pushTtlSeconds($kind),
            lang: $locale,
            nid: $nid,
        );
    }
}
