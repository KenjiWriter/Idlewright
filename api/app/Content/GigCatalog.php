<?php

declare(strict_types=1);

namespace App\Content;

use InvalidArgumentException;

/**
 * The gigs of the game (content/rules/gigs.json): the numbers of the board of a city, what a gig pays at each level of the driver, and the templates that the offers
 * are drawn from. The shape is guaranteed by the schema and by the validator of the pack, so this class only reads. A pack with no such document has no gigs.
 */
final class GigCatalog
{
    /** The name of the template that a night run is filed under (it is not one of the templates of the document, but its offers and its gigs need one). */
    public const NIGHT = 'night';

    /** The phases of the day of the game, as the clock names them. */
    public const PHASES = ['dawn', 'day', 'dusk', 'night'];

    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    public static function fromConfig(): self
    {
        return self::fromSource(app(ContentSource::class));
    }

    public static function fromSource(ContentSource $source): self
    {
        return new self($source->has('rules/gigs') ? $source->document('rules/gigs') : []);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** Whether the pack has anything to draw from. */
    public function isEmpty(): bool
    {
        return ($this->data['templates'] ?? []) === [];
    }

    /**
     * The document as it is, for the study that reads all of it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /** Whether the game has gigs: the document is there and switched on. */
    public function enabled(): bool
    {
        return ! $this->isEmpty() && ($this->data['enabled'] ?? false) === true;
    }

    public function unlockAfterStep(): string
    {
        return (string) ($this->data['unlock_after_step'] ?? '');
    }

    public function timezone(): string
    {
        return (string) ($this->data['timezone'] ?? 'UTC');
    }

    public function resetHour(): int
    {
        return (int) ($this->data['reset_hour'] ?? 0);
    }

    /** How long the offers of a city stay the same, in ms. */
    public function waveMs(): int
    {
        return (int) ($this->data['wave_ms'] ?? 1_350_000);
    }

    public function cancelWindowMs(): int
    {
        return (int) ($this->data['cancel_window_ms'] ?? 0);
    }

    /** How many offers a wave has that begins in this phase of the day. */
    public function slots(string $phase): int
    {
        return (int) ($this->data['slots'][$phase] ?? 0);
    }

    /**
     * How good the offers can be, the ordinary one first.
     *
     * @return list<array{key: string, weight: int, pay_ppm: int}>
     */
    public function rarities(): array
    {
        return array_values($this->data['rarities'] ?? []);
    }

    /** @return array{pay_ppm: int}|null */
    public function rarity(string $key): ?array
    {
        foreach ($this->rarities() as $rarity) {
            if ($rarity['key'] === $key) {
                return $rarity;
            }
        }

        return null;
    }

    public function refSpeedKmh(): int
    {
        return (int) ($this->data['pay']['ref_speed_kmh'] ?? 70);
    }

    public function speedCapKmh(): int
    {
        return (int) ($this->data['pay']['speed_cap_kmh'] ?? 80);
    }

    public function xpPerHour(): int
    {
        return (int) ($this->data['pay']['xp_per_hour'] ?? 0);
    }

    public function carXpPerHour(): int
    {
        return (int) ($this->data['pay']['car_xp_per_hour'] ?? 0);
    }

    /** What an hour of driving pays after the fuel at the rung that this level opens, in cents (the highest `from_level` that is not above it). */
    public function anchorCentsPerHour(int $level): int
    {
        return (int) $this->floorRow($this->data['pay']['anchors'] ?? [], $level)['cents_per_hour'];
    }

    /**
     * What the level of the driver changes.
     *
     * @return array{from_level: int, ratio_ppm: int, max_ref_minutes: int, daily_limit: int}
     */
    public function band(int $level): array
    {
        return $this->floorRow($this->data['bands'] ?? [], $level);
    }

    /** @return array<string, array{name: array<string, string>}> */
    public function kinds(): array
    {
        return $this->data['kinds'] ?? [];
    }

    /**
     * The night run, or null when the game has none or it is switched off.
     *
     * @return array<string, mixed>|null
     */
    public function night(): ?array
    {
        $night = $this->data['night'] ?? null;

        return $this->enabled() && is_array($night) && ($night['enabled'] ?? false) === true ? $night : null;
    }

    /** @return array<string, array<string, mixed>> */
    public function templates(): array
    {
        return $this->data['templates'] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function template(string $key): ?array
    {
        return $this->templates()[$key] ?? null;
    }

    /**
     * The row of a list whose rows start at a level: the last one that starts at or below the level, or the first when the level is below them all.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function floorRow(array $rows, int $level): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException('The gigs have no rows to choose by the level.');
        }

        $chosen = $rows[0];

        foreach ($rows as $row) {
            if ($row['from_level'] <= $level && $row['from_level'] >= $chosen['from_level']) {
                $chosen = $row;
            }
        }

        return $chosen;
    }
}
