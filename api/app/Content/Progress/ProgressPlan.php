<?php

declare(strict_types=1);

namespace App\Content\Progress;

use InvalidArgumentException;

/**
 * What the progress simulator plays (D13 of the design of the AI bridge v2): one driver from a new account, with a car to start with, a way of playing, a way of buying,
 * and how long. Anything left out is what a new driver of the game has: the cheapest car to start with, the tutorial, no missions.
 */
final readonly class ProgressPlan
{
    /** `active` plays without a break; `every` comes back every few minutes for a while; A, B and C come as the players of the economy simulator do. */
    public const STYLES = ['active', 'every', 'A', 'B', 'C'];

    /** What the driver chooses a track by: the profit of an hour, or the experience of an hour. */
    public const GOALS = ['profit', 'xp'];

    /** What the driver buys: nothing, a list in order, or what adds the most to an hour for a dollar. */
    public const BUYING = ['none', 'list', 'best'];

    /** The longest the simulator plays: thirty days. */
    public const MAX_MINUTES = 43_200;

    /**
     * @param  list<string>  $list  what to buy, in order, by slug (parts and cars), for the way of buying `list`
     */
    public function __construct(
        public ?string $car = null,
        public string $style = 'active',
        public int $everyMinutes = 60,
        public int $visitMinutes = 10,
        public int $minutes = 120,
        public ?string $track = null,
        public string $goal = 'profit',
        public string $buy = 'none',
        public array $list = [],
        public bool $tutorial = true,
        public int $stepSeconds = 60,
        public bool $missions = false,
        public bool $travel = true,
    ) {
        if (! in_array($style, self::STYLES, true)) {
            throw new InvalidArgumentException('The way of playing is one of '.implode(', ', self::STYLES).'.');
        }

        if (! in_array($goal, self::GOALS, true)) {
            throw new InvalidArgumentException('The goal of the choice of a track is one of '.implode(', ', self::GOALS).'.');
        }

        if (! in_array($buy, self::BUYING, true)) {
            throw new InvalidArgumentException('The way of buying is one of '.implode(', ', self::BUYING).'.');
        }

        if ($minutes < 1 || $minutes > self::MAX_MINUTES) {
            throw new InvalidArgumentException('The simulator plays from 1 minute to '.self::MAX_MINUTES.' minutes (30 days).');
        }

        if ($everyMinutes < 1 || $visitMinutes < 1 || $visitMinutes > $everyMinutes) {
            throw new InvalidArgumentException('A visit lasts at least a minute and no longer than the time between visits.');
        }

        if ($stepSeconds < 1 || $stepSeconds > 3600) {
            throw new InvalidArgumentException('A step of the tutorial takes from 1 to 3600 seconds.');
        }

        if ($buy === 'list' && $list === []) {
            throw new InvalidArgumentException('Buying by a list needs the list.');
        }
    }

    /**
     * A plan from what a command or a tool was given, by the names of the options.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $list = $input['list'] ?? [];

        if (is_string($list)) {
            $list = array_values(array_filter(array_map('trim', explode(',', $list)), static fn (string $slug): bool => $slug !== ''));
        }

        return new self(
            car: self::text($input['car'] ?? null),
            style: (string) ($input['style'] ?? 'active'),
            everyMinutes: (int) ($input['every_minutes'] ?? 60),
            visitMinutes: (int) ($input['visit_minutes'] ?? 10),
            minutes: (int) ($input['minutes'] ?? 120),
            track: self::text($input['track'] ?? null),
            goal: (string) ($input['goal'] ?? 'profit'),
            buy: (string) ($input['buy'] ?? 'none'),
            list: array_values(array_map('strval', (array) $list)),
            tutorial: (bool) ($input['tutorial'] ?? true),
            stepSeconds: (int) ($input['step_seconds'] ?? 60),
            missions: (bool) ($input['missions'] ?? false),
            travel: (bool) ($input['travel'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'car' => $this->car, 'style' => $this->style, 'every_minutes' => $this->everyMinutes, 'visit_minutes' => $this->visitMinutes, 'minutes' => $this->minutes,
            'track' => $this->track, 'goal' => $this->goal, 'buy' => $this->buy, 'list' => $this->list, 'tutorial' => $this->tutorial, 'step_seconds' => $this->stepSeconds,
            'missions' => $this->missions, 'travel' => $this->travel,
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
