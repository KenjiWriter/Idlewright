<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Catalog;
use App\Content\Rules;
use App\Domain\Settlement\EventType;
use App\Models\PushDelivery;
use App\Models\PushEvent;
use App\Models\PushSubscription;
use App\Models\RaceRun;
use App\Models\Travel;
use App\Models\User;
use App\Models\UserVisit;
use App\Models\WorkshopJob;
use App\Support\I18nText;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tells drivers by push what happened while they were away: a car stopped, a car arrived, a job is done. It runs every minute, for one game at a time.
 *
 * What has to be told of is found (a car to look at, from the forecast of `PushPlanner`; the journeys and the jobs that ended, by looking at the tables), and then for each driver,
 * before anything is sent: it is looked at whether it is still true (a car is settled and only one that has really stopped counts), whether the driver wants to be told of it,
 * whether they have not seen it themselves, whether it is not the quiet hours or too soon after the last message or too many for a day. What cannot be told now waits for its time
 * (and is told in one message with what has come meanwhile), what must not be told is written down as skipped with the reason.
 */
final class PushDispatcher
{
    /** A day, in milliseconds: the gap after the last message for a driver who leaves the messages unanswered. */
    private const DAY_MS = 86_400_000;

    /** A message has this long to be answered (the notification opened, or the driver in the game) before it counts as unanswered. */
    private const ANSWER_WINDOW_MS = 3_600_000;

    /** Messages that were queued within this time of each other (one for each device) are one message. */
    private const SAME_MESSAGE_MS = 60_000;

    /** How many events one run takes: the rest wait for the next minute. */
    private const BATCH = 500;

    /** A car that was seen by the driver this long ago is told of at least this much later (milliseconds), so the message does not come while the driver still looks at the screen. */
    private const SETTLE_AFTER_SEEN_MS = 60_000;

    public function __construct(
        private readonly Rules $rules,
        private readonly Push $push,
        private readonly PushPreferences $preferences,
        private readonly PushArm $arm,
        private readonly RunService $runs,
        private readonly Catalog $catalog,
    ) {}

    /** @return array{sent: int, skipped: int, deferred: int} what became of the events that were due */
    public function run(int $nowMs): array
    {
        $report = ['sent' => 0, 'skipped' => 0, 'deferred' => 0];

        if ($this->push->publicKey() === null) {
            return $report;
        }

        $this->scan($nowMs);

        $due = PushEvent::query()->where('state', PushEvent::STATE_PLANNED)->where('due_ms', '<=', $nowMs)->orderBy('due_ms')->limit(self::BATCH)->get()->groupBy('user_id');

        foreach ($due as $userId => $events) {
            $user = User::query()->find($userId);

            if ($user === null || $this->arm->isHoldout((int) $userId) || ! PushSubscription::query()->where('user_id', $userId)->whereNull('disabled_at')->exists()) {
                // Nobody to tell: the driver has no device any more, or is in the group that is never told.
                PushEvent::query()->whereIn('id', $events->modelKeys())->delete();

                continue;
            }

            $this->deliver($user, $events, $nowMs, $report);
        }

        return $report;
    }

    /** Writes down the journeys and the jobs that ended lately for the drivers who have a device, as events that are due (a car that stopped comes from the forecast). */
    private function scan(int $nowMs): void
    {
        $devices = PushSubscription::query()->whereNull('disabled_at')->select('user_id');

        $arrivedFrom = $nowMs - $this->rules->pushTtlSeconds(PushEvent::KIND_CAR_ARRIVED) * 1000;
        $travels = Travel::query()->whereIn('user_id', $devices)->where('arrive_ms', '>', $arrivedFrom)->where('arrive_ms', '<=', $nowMs)->orderBy('arrive_ms')->limit(self::BATCH)->get();

        foreach ($travels as $travel) {
            $this->register(PushEvent::KIND_CAR_ARRIVED, PushEvent::REF_TRAVEL, $travel->id, $travel->user_id, $travel->arrive_ms);
        }

        $doneFrom = $nowMs - $this->rules->pushTtlSeconds(PushEvent::KIND_JOB_DONE) * 1000;
        $jobs = WorkshopJob::query()->whereIn('user_id', $devices)->where('finish_ms', '>', $doneFrom)->where('finish_ms', '<=', $nowMs)->orderBy('finish_ms')->limit(self::BATCH)->get();

        foreach ($jobs as $job) {
            $this->register(PushEvent::KIND_JOB_DONE, PushEvent::REF_JOB, $job->id, $job->user_id, $job->finish_ms);
        }
    }

    /** An event for a thing that ended, once: a thing that was written down before (told, skipped or waiting) is not written down again. */
    private function register(string $kind, string $refType, int $refId, int $userId, int $dueMs): void
    {
        $known = PushEvent::query()->where('kind', $kind)->where('ref_type', $refType)->where('ref_id', $refId)->exists();

        if (! $known) {
            PushEvent::query()->create(['user_id' => $userId, 'kind' => $kind, 'ref_type' => $refType, 'ref_id' => $refId, 'due_ms' => $dueMs, 'state' => PushEvent::STATE_PLANNED]);
        }
    }

    /**
     * What is told to one driver now: of the events that are due, those that are still true and wanted are told in one message, unless it is not the time for it.
     *
     * @param  Collection<int, PushEvent>  $events
     * @param  array{sent: int, skipped: int, deferred: int}  $report
     */
    private function deliver(User $user, Collection $events, int $nowMs, array &$report): void
    {
        $settings = $this->rules->push() ?? throw new \LogicException('The rules have no push.');
        $seenMs = PushSeen::at($user);
        $kinds = $this->preferences->kindsOf($user);
        $items = [];

        foreach ($events as $event) {
            $item = $this->resolve($event, $nowMs);

            if ($item === null) {
                // It is not true any more (the job was cancelled, the car is gone).
                $event->delete();
            } elseif ($item !== false) {
                if (! ($kinds[$event->kind] ?? false)) {
                    $this->skip($event, 'off', $report);
                } elseif ($seenMs !== null && $seenMs >= $event->due_ms) {
                    // The driver was in the game after it happened: they know.
                    $this->skip($event, 'known', $report);
                } else {
                    $items[] = [$event, $item];
                }
            }
        }

        if ($items === []) {
            return;
        }

        // A driver who leaves the messages unanswered is told less: after a few only once a day, and after many not at all until they come back.
        $unanswered = $this->unanswered($user, $nowMs);

        if ($unanswered['count'] >= $settings['adapt_pause_after'] && ($seenMs === null || $seenMs <= $unanswered['last_ms'])) {
            foreach ($items as [$event]) {
                $this->skip($event, 'paused', $report);
            }

            return;
        }

        $gapMs = $unanswered['count'] >= $settings['adapt_digest_after'] ? self::DAY_MS : $settings['min_gap_ms'];

        $now = Carbon::createFromTimestampMs($nowMs);
        $sent = PushEvent::query()->where('user_id', $user->id)->where('state', PushEvent::STATE_SENT)->where('sent_at', '>', $now->copy()->subDay());

        if ((clone $sent)->distinct()->count('sent_at') >= $settings['per_day']) {
            foreach ($items as [$event]) {
                $this->skip($event, 'limit', $report);
            }

            return;
        }

        $wait = $this->waitUntil($user, $nowMs, $seenMs, (clone $sent)->max('sent_at'), $settings, $gapMs);

        if ($wait !== null) {
            foreach ($items as [$event]) {
                $event->forceFill(['due_ms' => $wait])->save();
                $report['deferred']++;
            }

            return;
        }

        if ($this->tell($user, $items) === 0) {
            foreach ($items as [$event]) {
                $this->skip($event, 'no_device', $report);
            }

            return;
        }

        PushEvent::query()->whereIn('id', array_map(static fn (array $item): int => $item[0]->id, $items))->update(['state' => PushEvent::STATE_SENT, 'sent_at' => $now, 'reason' => null]);
        $report['sent'] += count($items);
    }

    /**
     * Whether the event is still true and what to say of it.
     *
     * @return array{text: string, url: string, tag: string, car: Closure|null}|false|null the words of the message, false when something else keeps the event (the forecast of a
     *                                                                                     car), null when it is not true any more
     */
    private function resolve(PushEvent $event, int $nowMs): array|false|null
    {
        return match ($event->kind) {
            PushEvent::KIND_CAR_STOPPED => $this->carStopped($event, $nowMs),
            PushEvent::KIND_CAR_ARRIVED => $this->carArrived($event),
            PushEvent::KIND_JOB_DONE => WorkshopJob::query()->whereKey($event->ref_id)->exists()
                ? ['text' => 'job_done', 'url' => '/workshop', 'tag' => 'job-done', 'car' => null]
                : null,
            default => null,
        };
    }

    /**
     * A car is told of when it has really stopped: the car is settled first, because the forecast is only a moment to look.
     *
     * @return array{text: string, url: string, tag: string, car: Closure|null}|false|null
     */
    private function carStopped(PushEvent $event, int $nowMs): array|false|null
    {
        try {
            $this->runs->settle($event->ref_id, $nowMs);
        } catch (ModelNotFoundException) {
            return null;
        }

        $run = RaceRun::query()->find($event->ref_id);

        if ($run === null) {
            return null;
        }

        $text = match ($run->stop_reason) {
            EventType::FuelEmpty->value => 'car_stopped_fuel',
            EventType::Breakdown->value => 'car_stopped_breakdown',
            default => null,
        };

        // Not stopped (the driver filled the tank, a part changed): the settlement has made the forecast again, and the event is the forecast.
        if ($run->status !== RaceRun::STATUS_STOPPED || $text === null) {
            return false;
        }

        return ['text' => $text, 'url' => '/garage', 'tag' => "car-{$run->id}-stop", 'car' => $this->carName($run)];
    }

    /** @return array{text: string, url: string, tag: string, car: Closure|null}|null */
    private function carArrived(PushEvent $event): ?array
    {
        $travel = Travel::query()->find($event->ref_id);
        $run = $travel === null ? null : RaceRun::query()->find($travel->race_run_id);

        return $run === null ? null : ['text' => 'car_arrived', 'url' => '/garage', 'tag' => "car-{$run->id}-arrive", 'car' => $this->carName($run)];
    }

    /** The name of the car of a run in a language: a function of the language, because each device has its own. */
    private function carName(RaceRun $run): Closure
    {
        $slug = $run->car?->vehicle_slug ?? $run->car_slug;
        $vehicle = $this->catalog->vehicle($slug);

        return static fn (string $locale): array => ['car' => $vehicle === null ? $slug : I18nText::from($vehicle['name'])->for($locale)];
    }

    /**
     * When the message may come at the earliest, as a time in milliseconds, or null when it may come now: not while the driver is still looking at the game, not in the quiet hours
     * of the time zone of their device, and not too soon after the last message.
     *
     * @param  array{seen_ms: int, quiet_from_min: int, quiet_to_min: int, min_gap_ms: int}  $settings
     * @param  int  $gapMs  the least time after the last message: the gap of the rules, or a day for a driver who leaves the messages unanswered
     */
    private function waitUntil(User $user, int $nowMs, ?int $seenMs, mixed $lastSent, array $settings, int $gapMs): ?int
    {
        $times = [];

        if ($seenMs !== null && $seenMs > $nowMs - $settings['seen_ms']) {
            $times[] = $seenMs + $settings['seen_ms'] + self::SETTLE_AFTER_SEEN_MS;
        }

        $quiet = $this->quietEndsAt($nowMs, $this->timezoneOf($user), $settings['quiet_from_min'], $settings['quiet_to_min']);

        if ($quiet !== null) {
            $times[] = $quiet;
        }

        if ($lastSent !== null && Carbon::parse($lastSent)->getTimestampMs() + $gapMs > $nowMs) {
            $times[] = Carbon::parse($lastSent)->getTimestampMs() + $gapMs;
        }

        return $times === [] ? null : max($times);
    }

    /**
     * How many of the last messages in a row have gone unanswered: a message is answered when its notification was opened or the driver came into the game within an hour of it. A
     * message that has not had its hour yet counts for neither. The test message is not one. Returns also the moment of the last message, of any kind of answer.
     *
     * @return array{count: int, last_ms: int|null}
     */
    private function unanswered(User $user, int $nowMs): array
    {
        $deliveries = PushDelivery::query()->where('user_id', $user->id)->where('kind', '!=', 'test')->orderByDesc('id')->limit(60)->get(['created_at', 'opened_at']);

        if ($deliveries->isEmpty()) {
            return ['count' => 0, 'last_ms' => null];
        }

        // The deliveries of one message (one for each device) become one.
        $messages = [];

        foreach ($deliveries as $delivery) {
            $at = Carbon::parse($delivery->created_at)->getTimestampMs();
            $last = count($messages) - 1;

            if ($last >= 0 && abs($messages[$last]['at'] - $at) <= self::SAME_MESSAGE_MS) {
                $messages[$last]['opened'] = $messages[$last]['opened'] || $delivery->opened_at !== null;
            } else {
                $messages[] = ['at' => $at, 'opened' => $delivery->opened_at !== null];
            }
        }

        $visits = UserVisit::query()->where('user_id', $user->id)->where('at', '>=', Carbon::createFromTimestampMs(end($messages)['at']))->pluck('at')->map(fn ($at): int => Carbon::parse($at)->getTimestampMs());
        $count = 0;

        foreach ($messages as $message) {
            if ($message['at'] > $nowMs - self::ANSWER_WINDOW_MS) {
                continue;
            }

            $answered = $message['opened'] || $visits->contains(fn (int $at): bool => $at > $message['at'] && $at <= $message['at'] + self::ANSWER_WINDOW_MS);

            if ($answered) {
                break;
            }

            $count++;
        }

        return ['count' => $count, 'last_ms' => $messages[0]['at']];
    }

    /** When the quiet hours that the moment is in end, or null when it is not in them. The hours are minutes of the day; a start after the end is a night. */
    public function quietEndsAt(int $nowMs, string $timezone, int $fromMinute, int $toMinute): ?int
    {
        if ($fromMinute === $toMinute) {
            return null;
        }

        $local = Carbon::createFromTimestampMs($nowMs, $timezone);
        $minute = $local->hour * 60 + $local->minute;
        $inside = $fromMinute < $toMinute ? ($minute >= $fromMinute && $minute < $toMinute) : ($minute >= $fromMinute || $minute < $toMinute);

        if (! $inside) {
            return null;
        }

        $end = $local->copy()->startOfDay()->addMinutes($toMinute);

        return ($end->lte($local) ? $end->addDay() : $end)->getTimestampMs();
    }

    /** The time zone of the device the driver used last, else the one of the missions of the game. */
    private function timezoneOf(User $user): string
    {
        $zone = PushSubscription::query()->where('user_id', $user->id)->whereNull('disabled_at')->whereNotNull('timezone')->orderByDesc('updated_at')->value('timezone');

        return $zone ?? ($this->rules->missions()['timezone'] ?? (string) config('app.timezone'));
    }

    /**
     * Queues the message: of the thing itself when it is one, else one that says how many things wait.
     *
     * @param  list<array{0: PushEvent, 1: array{text: string, url: string, tag: string, car: Closure|null}}>  $items
     * @return int to how many devices
     */
    private function tell(User $user, array $items): int
    {
        if (count($items) === 1) {
            [$event, $item] = $items[0];

            return $this->push->notify($user, $event->kind, $item['url'], $item['car'] ?? [], $item['tag'], $item['text']);
        }

        // Several things: one message that says how many, and what it is about keeps the shortest time of the kinds in it.
        $kind = collect($items)->map(fn (array $item): string => $item[0]->kind)->sortBy(fn (string $kind): int => $this->rules->pushTtlSeconds($kind))->first();

        return $this->push->notify($user, $kind, '/garage', [], 'digest', 'digest', count($items));
    }

    /** @param  array{sent: int, skipped: int, deferred: int}  $report */
    private function skip(PushEvent $event, string $reason, array &$report): void
    {
        $event->forceFill(['state' => PushEvent::STATE_SKIPPED, 'reason' => $reason])->save();
        $report['skipped']++;
    }
}
