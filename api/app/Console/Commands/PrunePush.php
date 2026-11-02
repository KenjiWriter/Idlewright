<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PushDelivery;
use App\Models\PushEvent;
use App\Models\PushSubscription;
use Illuminate\Console\Command;

/**
 * Throws away what the push messages no longer need: a device that was switched off (its messages failed over and over) for more than the days given, and the notes of what was
 * told that are older than the same days (a day is all the limits look at). The privacy policy promises it, so the days here and in the text are the same.
 */
final class PrunePush extends Command
{
    /** The days a device that was switched off, and a note of a message, are kept. */
    public const KEEP_DAYS = 30;

    protected $signature = 'push:prune';

    protected $description = 'Delete the push devices that were switched off long ago and the old notes of messages';

    public function handle(): int
    {
        $before = now()->subDays(self::KEEP_DAYS);

        $devices = PushSubscription::query()->withoutGlobalScopes()->whereNotNull('disabled_at')->where('disabled_at', '<', $before)->delete();
        $events = PushEvent::query()->withoutGlobalScopes()->whereIn('state', [PushEvent::STATE_SENT, PushEvent::STATE_SKIPPED])->where('updated_at', '<', $before)->delete();

        PushDelivery::query()->withoutGlobalScopes()->where('created_at', '<', $before)->delete();

        $this->info("Push: {$devices} devices and {$events} notes deleted.");

        return self::SUCCESS;
    }
}
