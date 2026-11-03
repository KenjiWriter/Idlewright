<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Models\PushDelivery;
use App\Models\PushEvent;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * How the notifications of the game in play stand, for the page of the panel: whether it can send them (the rules and the keys), how many devices and accounts have agreed, and what
 * was sent in the last seven days. It reads and changes nothing, and it never shows the private key.
 */
final class PushStats
{
    /** The days the table of messages goes back. */
    public const DAYS = 7;

    public function __construct(private readonly Push $push, private readonly PushArm $arm, private readonly Rules $rules) {}

    /**
     * @return array{
     *     enabled: bool,
     *     has_keys: bool,
     *     public_key: string|null,
     *     subject: string|null,
     *     holdout_ppm: int,
     *     devices: array{active: int, disabled: int, browsers: array<string, int>},
     *     accounts: array{eligible: int, with_device: int, share: float|null, holdout: int},
     *     week: array{queued: int, opened: int, per_device_per_day: float|null, switched_off: int},
     *     days: list<array{day: string, queued: int, opened: int, told: int}>,
     *     skipped: array<string, int>
     * }
     */
    public function report(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->startOfDay()->subDays(self::DAYS - 1);
        $game = $this->push->game();
        $active = PushSubscription::query()->whereNull('disabled_at');

        $ids = User::query()->pluck('id');
        $holdout = $ids->filter(fn (int $id): bool => $this->arm->isHoldout($id))->count();
        $withDevice = (clone $active)->distinct()->count('user_id');
        $eligible = $ids->count() - $holdout;

        $deliveries = PushDelivery::query()->where('created_at', '>=', $since)->get(['created_at', 'opened_at']);
        $told = PushEvent::query()->where('state', PushEvent::STATE_SENT)->where('sent_at', '>=', $since)->get(['sent_at'])->groupBy(fn (PushEvent $event): string => $event->sent_at->format('Y-m-d H:i:s'));
        $activeDevices = (clone $active)->count();

        $days = [];

        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $day = $now->startOfDay()->subDays($i)->toDateString();
            $ofDay = $deliveries->filter(fn (PushDelivery $delivery): bool => CarbonImmutable::instance($delivery->created_at)->toDateString() === $day);

            $days[] = [
                'day' => $day,
                'queued' => $ofDay->count(),
                'opened' => $ofDay->whereNotNull('opened_at')->count(),
                // A thing told is one event; several that went in one message are counted once each.
                'told' => $told->filter(fn ($events, string $moment): bool => substr($moment, 0, 10) === $day)->flatten()->count(),
            ];
        }

        return [
            'enabled' => $this->rules->pushOn(),
            'has_keys' => $game->hasPushKeys(),
            'public_key' => $game->push_public_key,
            'subject' => $game->push_subject,
            'holdout_ppm' => $this->rules->push()['holdout_ppm'] ?? 0,
            'devices' => [
                'active' => $activeDevices,
                'disabled' => PushSubscription::query()->whereNotNull('disabled_at')->count(),
                'browsers' => (clone $active)->get(['ua_family'])->groupBy(fn (PushSubscription $device): string => $device->ua_family ?? 'other')->map->count()->sortDesc()->all(),
            ],
            'accounts' => [
                'eligible' => $eligible,
                'with_device' => $withDevice,
                'share' => $eligible === 0 ? null : round($withDevice / $eligible, 4),
                'holdout' => $holdout,
            ],
            'week' => [
                'queued' => $deliveries->count(),
                'opened' => $deliveries->whereNotNull('opened_at')->count(),
                'per_device_per_day' => $activeDevices === 0 ? null : round($deliveries->count() / $activeDevices / self::DAYS, 2),
                'switched_off' => PushSubscription::query()->where('disabled_at', '>=', $since)->count(),
            ],
            'days' => $days,
            'skipped' => PushEvent::query()->where('state', PushEvent::STATE_SKIPPED)->where('updated_at', '>=', $since)->get(['reason'])->groupBy(fn (PushEvent $event): string => $event->reason ?? 'other')->map->count()->sortDesc()->all(),
        ];
    }
}
