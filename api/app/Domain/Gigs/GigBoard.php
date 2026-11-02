<?php

declare(strict_types=1);

namespace App\Domain\Gigs;

use App\Content\GigCatalog;
use App\Domain\Run\GameClock;
use App\Domain\Run\Rng;

/**
 * The board of gigs of a city. Nothing is stored: the offers of a wave are made again, the same, from the seed of the driver, the city and the number of the wave
 * (the wave is a stretch of `wave_ms` of the server's clock), so every driver has a board of their own and a driver who looks twice sees the same one. An offer carries
 * a fingerprint of what it is, so that a key which was not on the board (or whose offer the level of the driver has changed) is nothing: the server makes the offer
 * again from the key and compares.
 *
 * What an offer is depends on the phase of the day in which its wave began (the templates have a weight for each), on the level of the driver (a template has a
 * level it starts at, and the band of the level says how long a trip may be) and on the cities that the driver can go to.
 */
final class GigBoard
{
    public function __construct(private readonly GigCatalog $catalog) {}

    /**
     * The offers that are on the board of a city at this moment, the newest wave first.
     *
     * @param  list<array{region: string, km: int}>  $destinations  the cities that the level of the driver has opened and a road leads to, with the kilometres of the way
     * @return list<GigOffer>
     */
    public function offers(string $seed, string $region, int $nowMs, int $level, array $destinations): array
    {
        $current = intdiv($nowMs, $this->catalog->waveMs());
        $reach = max(array_map(static fn (array $template): int => $template['window_waves'], $this->catalog->templates()) ?: [1]);
        $offers = [];

        for ($wave = $current; $wave > $current - $reach && $wave >= 0; $wave--) {
            $slots = $this->catalog->slots(GameClock::phaseAt($wave * $this->catalog->waveMs()));

            for ($slot = 0; $slot < $slots; $slot++) {
                $offer = $this->offer($seed, $region, $wave, $slot, $level, $destinations);

                if ($offer !== null && $offer->untilMs > $nowMs) {
                    $offers[] = $offer;
                }
            }
        }

        return $offers;
    }

    /**
     * The offer a key stands for, if it is on the board now.
     *
     * @param  list<array{region: string, km: int}>  $destinations
     */
    public function find(string $key, string $seed, int $nowMs, int $level, array $destinations): ?GigOffer
    {
        if (preg_match('/^([a-z0-9-]+)\.(\d+)\.(\d+)\.([0-9a-f]{8})$/', $key, $match) !== 1) {
            return null;
        }

        $offer = $this->offer($seed, $match[1], (int) $match[2], (int) $match[3], $level, $destinations);

        if ($offer === null || $offer->key !== $key || $offer->untilMs <= $nowMs || (int) $match[2] > intdiv($nowMs, $this->catalog->waveMs())) {
            return null;
        }

        return $offer;
    }

    /**
     * The offer of a slot of a wave, or null when the slot has nothing to draw (no template fits the phase, the level and the cities).
     *
     * @param  list<array{region: string, km: int}>  $destinations
     */
    public function offer(string $seed, string $region, int $wave, int $slot, int $level, array $destinations): ?GigOffer
    {
        $startMs = $wave * $this->catalog->waveMs();
        $phase = GameClock::phaseAt($startMs);

        if ($slot >= $this->catalog->slots($phase)) {
            return null;
        }

        $domain = "gig:{$region}:{$wave}";
        $band = $this->catalog->band($level);
        $capMinutes = $band['max_ref_minutes'];
        $refSpeed = $this->catalog->refSpeedKmh();

        // The templates that can make an offer here: in the phase, at the level, and with a trip that fits the longest one the band allows.
        $fitting = [];

        foreach ($this->catalog->templates() as $key => $template) {
            if ($template['phases'][$phase] <= 0 || $template['min_player_level'] > $level) {
                continue;
            }

            if ($template['mode'] === 'local' && $template['local_km']['min'] * 60 > $capMinutes * $refSpeed) {
                continue;
            }

            if ($template['mode'] === 'intercity' && $this->reachable($destinations, $capMinutes, $refSpeed) === []) {
                continue;
            }

            $fitting[$key] = $template['phases'][$phase];
        }

        if ($fitting === []) {
            return null;
        }

        $templateKey = $this->weighted($fitting, Rng::roll($seed, "{$domain}:template", $slot));
        $template = $this->catalog->template($templateKey);
        $rarity = $this->weighted(array_column($this->catalog->rarities(), 'weight', 'key'), Rng::roll($seed, "{$domain}:rarity", $slot));

        if ($template['mode'] === 'local') {
            $km = $this->between($seed, "{$domain}:km", $slot, $template['local_km']['min'], min($template['local_km']['max'], intdiv($capMinutes * $refSpeed, 60)));
            $toRegion = null;
        } else {
            $reachable = $this->reachable($destinations, $capMinutes, $refSpeed);
            $chosen = $reachable[$this->between($seed, "{$domain}:to", $slot, 0, count($reachable) - 1)];
            $km = 2 * $chosen['km'];
            $toRegion = $chosen['region'];
        }

        $amount = $this->between($seed, "{$domain}:amount", $slot, $template['amount']['min'], $template['amount']['max']);

        // A load is in round tens of kilograms, so that the offers read like offers.
        if ($template['capacity'] === 'cargo_kg' && $template['amount']['max'] >= 100) {
            $amount = max($template['amount']['min'], min($template['amount']['max'], intdiv($amount + 5, 10) * 10));
        }

        $fingerprint = substr(hash('sha256', "{$seed}|{$region}|{$wave}|{$slot}|{$templateKey}|{$toRegion}|{$km}|{$amount}|{$rarity}"), 0, 8);

        return new GigOffer(
            key: "{$region}.{$wave}.{$slot}.{$fingerprint}",
            region: $region,
            toRegion: $toRegion,
            wave: $wave,
            slot: $slot,
            phase: $phase,
            template: $templateKey,
            kind: $template['kind'],
            capacity: $template['capacity'],
            amount: $amount,
            classIn: $template['class_in'] ?? null,
            rarity: $rarity,
            km: $km,
            refMs: intdiv($km * 3_600_000, $refSpeed),
            payPpm: $template['pay_ppm'],
            deadlinePpm: $template['deadline_ppm'] ?? null,
            untilMs: ($wave + $template['window_waves']) * $this->catalog->waveMs(),
        );
    }

    /**
     * The cities whose round trip fits the longest gig the level allows.
     *
     * @param  list<array{region: string, km: int}>  $destinations
     * @return list<array{region: string, km: int}>
     */
    private function reachable(array $destinations, int $capMinutes, int $refSpeed): array
    {
        return array_values(array_filter($destinations, static fn (array $city): bool => 2 * $city['km'] * 60 <= $capMinutes * $refSpeed));
    }

    /**
     * The key a roll falls on, when each key has a share of the whole as big as its weight.
     *
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights, int $roll): string
    {
        $total = array_sum($weights);
        $target = intdiv($roll * $total, 1_000_000);

        foreach ($weights as $key => $weight) {
            if ($target < $weight) {
                return (string) $key;
            }

            $target -= $weight;
        }

        return (string) array_key_last($weights);
    }

    private function between(string $seed, string $domain, int $index, int $min, int $max): int
    {
        return $min + intdiv(Rng::roll($seed, $domain, $index) * ($max - $min + 1), 1_000_000);
    }
}
