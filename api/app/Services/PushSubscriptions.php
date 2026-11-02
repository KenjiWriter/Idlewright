<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RuleRefusedException;
use App\Games\CurrentGame;
use App\Models\PushSubscription;
use App\Models\User;
use App\Push\Base64Url;
use App\Push\P256;
use App\Support\Locale;
use DateTimeZone;

/**
 * The devices of a driver that agreed to be told: saving what the browser gave when it subscribed (the address of the push service and the keys that only the browser can read
 * a message with), and taking it away. The server will send to the address it is given, so it is only given the addresses of the push services of the browsers.
 */
final class PushSubscriptions
{
    /** How many devices a driver can have subscribed: the oldest goes when another comes. */
    public const MAX_DEVICES = 10;

    public function __construct(private readonly Push $push, private readonly CurrentGame $current) {}

    /**
     * Saves the subscription of a device for the driver (one device is one address, so a device that another driver subscribed from moves to this one).
     *
     * @param  array{endpoint: string, p256dh: string, auth: string, timezone?: string|null, locale?: string|null}  $data
     */
    public function save(User $user, array $data, ?string $userAgent): PushSubscription
    {
        $public = $this->push->publicKey() ?? throw new RuleRefusedException('push_unavailable');

        if (! $this->isPushService($data['endpoint'])) {
            throw new RuleRefusedException('push_endpoint');
        }

        $browserPoint = Base64Url::decode($data['p256dh']);
        $secret = Base64Url::decode($data['auth']);

        if ($browserPoint === null || $secret === null || strlen($secret) !== 16 || ! P256::isPoint($browserPoint)) {
            throw new RuleRefusedException('push_keys');
        }

        $zone = $data['timezone'] ?? null;
        $subscription = PushSubscription::query()->withoutGlobalScopes()->firstOrNew(['endpoint_hash' => PushSubscription::hashOf($data['endpoint'])]);
        $subscription->forceFill([
            'game_id' => $this->current->id(),
            'user_id' => $user->id,
            'endpoint' => $data['endpoint'],
            'p256dh' => Base64Url::encode($browserPoint),
            'auth' => Base64Url::encode($secret),
            'vapid_key' => $public,
            'timezone' => is_string($zone) && in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : null,
            'locale' => Locale::normalize($data['locale'] ?? null) ?? Locale::normalize($user->locale),
            'ua_family' => self::browserOf($userAgent),
            'failures' => 0,
            'disabled_at' => null,
        ])->save();

        $this->keepNewest($user);

        return $subscription;
    }

    /** Takes the subscription of this address away, when it is the driver's. Whether there was one. */
    public function remove(User $user, string $endpoint): bool
    {
        return PushSubscription::query()->where('user_id', $user->id)->where('endpoint_hash', PushSubscription::hashOf($endpoint))->delete() > 0;
    }

    /** How many devices of the driver are subscribed and will be sent to. */
    public function devices(User $user): int
    {
        return PushSubscription::query()->where('user_id', $user->id)->whereNull('disabled_at')->count();
    }

    /** Whether the address is one of a push service of a browser: an https address on the usual port, on a host of the list (the server calls this address). */
    public function isPushService(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || ! isset($parts['host'])) {
            return false;
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach ((array) config('ride.push.hosts') as $allowed) {
            $allowed = strtolower(trim((string) $allowed));

            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return true;
            }
        }

        return false;
    }

    /** The family of the browser, for whoever has to find out why a device is not told (Chrome, Firefox, Safari, Edge or other). */
    public static function browserOf(?string $userAgent): ?string
    {
        return match (true) {
            $userAgent === null || $userAgent === '' => null,
            str_contains($userAgent, 'Edg/') => 'edge',
            str_contains($userAgent, 'Firefox/') => 'firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'chrome',
            str_contains($userAgent, 'Safari/') => 'safari',
            default => 'other',
        };
    }

    private function keepNewest(User $user): void
    {
        $extra = PushSubscription::query()->where('user_id', $user->id)->orderByDesc('updated_at')->orderByDesc('id')->pluck('id')->slice(self::MAX_DEVICES);

        if ($extra->isNotEmpty()) {
            PushSubscription::query()->whereIn('id', $extra)->delete();
        }
    }
}
