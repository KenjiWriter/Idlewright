<?php

declare(strict_types=1);

namespace App\Content\Tracks;

use App\Domain\Run\Surface;

/**
 * What the track simulator found, and the gates of the design it checks it against (Roadmapa, F2, and Systemy I and II):
 *
 * - the tires: the best tires on every surface are the kind the design says, so that no tire is best everywhere (slick on dry
 *   asphalt, rain on wet, off-road on gravel and mud, winter on snow), which makes the rain change the winner;
 * - the terrain: a gravel track is not won by a car that wins an asphalt track, so that off-road is a reason to have another car;
 * - the gate M3, "no car wins more than two tracks of a kind" (a sprint, a standard track, an endurance track): whether the tracks make the drivers want different cars.
 *
 * The first two are about the data being coherent and fail the command. The third is the question of the gate M3, and it fails the
 * command only with --strict: it depends on how many different cars there are, and says so when they are too few.
 */
final readonly class TrackReport
{
    /** The tires of the design that win each surface. */
    public const EXPECTED_TIRES = [
        Surface::ASPHALT_DRY => 'slick',
        Surface::ASPHALT_WET => 'rain',
        Surface::GRAVEL_DRY => 'offroad',
        Surface::MUD => 'offroad',
        Surface::SNOW => 'winter',
    ];

    /** The most tracks of one kind that one car may win before the tracks stop asking for different cars. */
    public const MOST_TRACKS_PER_CAR = 2;

    /**
     * How far the slowest car may be from the fastest on a track that is driven on its thread, as the quotient of their best dry laps, before the track is worth a look: the
     * tracks that are driven by the formula of the demands are between 1.2 and 2.0, so a track below the lower bound asks for nothing of a car, and one above the upper bound
     * is lost by most of the cars.
     */
    public const SPREAD_NARROW = 1.15;

    public const SPREAD_WIDE = 2.2;

    /**
     * @param  list<SurfaceOutcome>  $surfaces
     * @param  list<TrackOutcome>  $tracks
     */
    public function __construct(public string $rulesVersion, public array $surfaces, public array $tracks) {}

    /**
     * Per surface: the tires of the design, the ones that were the best, and whether they are the same kind.
     *
     * @return list<array{surface: string, expected: string, best: string, kind: string, ok: bool}>
     */
    public function tireFindings(): array
    {
        return array_map(static fn (SurfaceOutcome $outcome): array => [
            'surface' => $outcome->surface,
            'expected' => self::EXPECTED_TIRES[$outcome->surface] ?? '-',
            'best' => $outcome->bestTires(),
            'kind' => $outcome->bestKind(),
            'ok' => $outcome->bestKind() === (self::EXPECTED_TIRES[$outcome->surface] ?? null),
        ], $this->surfaces);
    }

    public function tiresPassed(): bool
    {
        return $this->surfaces !== [] && ! in_array(false, array_column($this->tireFindings(), 'ok'), true);
    }

    /** No car that wins a gravel track wins an asphalt track. True when there is no gravel track, or no asphalt one, to compare. */
    public function terrainPassed(): bool
    {
        $gravel = $this->winnersOn('gravel');
        $asphalt = $this->winnersOn('asphalt');

        return array_intersect($gravel, $asphalt) === [];
    }

    /**
     * What is worth a look on the tracks that are driven on their thread (a warning, never a failure): the cars lap too nearly alike or too far apart, and the winner of the
     * track is a car that wins other tracks as well, so the track asks for what the other tracks ask for.
     *
     * @return list<array{track: string, type: string, spread?: float, car?: string, also?: list<string>}>
     */
    public function threadWarnings(): array
    {
        $wins = $this->winsByCar();
        $warnings = [];

        foreach ($this->tracks as $track) {
            if ($track->lapFrom !== 'thread') {
                continue;
            }

            $spread = $track->spread();

            if ($spread !== null && $spread < self::SPREAD_NARROW) {
                $warnings[] = ['track' => $track->track, 'type' => 'spread_narrow', 'spread' => round($spread, 2)];
            } elseif ($spread !== null && $spread > self::SPREAD_WIDE) {
                $warnings[] = ['track' => $track->track, 'type' => 'spread_wide', 'spread' => round($spread, 2)];
            }

            $also = array_values(array_diff($wins[$track->winner->vehicle] ?? [], [$track->track]));

            if (count($also) >= self::MOST_TRACKS_PER_CAR) {
                $warnings[] = ['track' => $track->track, 'type' => 'same_winner', 'car' => $track->winner->vehicle, 'also' => $also];
            }
        }

        return $warnings;
    }

    /**
     * The tracks each car wins, the cars that win the most first.
     *
     * @return array<string, list<string>>
     */
    public function winsByCar(): array
    {
        $wins = [];

        foreach ($this->tracks as $track) {
            $wins[$track->winner->vehicle][] = $track->track;
        }

        uasort($wins, static fn (array $a, array $b): int => count($b) <=> count($a));

        return $wins;
    }

    /**
     * The tracks each car wins, by the kind of the tracks (a sprint, a standard track, an endurance track): a car that is the best on a sprint and on a
     * long track is not the best on two tracks of one kind, which is what makes the drivers want different cars.
     *
     * @return array<string, array<string, list<string>>> the kind, then the car, then its tracks
     */
    public function winsByKind(): array
    {
        $wins = [];

        foreach ($this->tracks as $track) {
            $wins[$track->kind][$track->winner->vehicle][] = $track->track;
        }

        foreach ($wins as $kind => $byCar) {
            uasort($byCar, static fn (array $a, array $b): int => count($b) <=> count($a));
            $wins[$kind] = $byCar;
        }

        return $wins;
    }

    /**
     * The cars that win more tracks of one kind than the gate M3 allows.
     *
     * @return array<string, list<string>> the tracks, by the car and the kind (`bizon-m-2 (standard)`)
     */
    public function carsThatWinTooMuch(): array
    {
        $tooMuch = [];

        foreach ($this->winsByKind() as $kind => $byCar) {
            foreach ($byCar as $car => $tracks) {
                if (count($tracks) > self::MOST_TRACKS_PER_CAR) {
                    $tooMuch["{$car} ({$kind})"] = $tracks;
                }
            }
        }

        return $tooMuch;
    }

    public function m3Passed(): bool
    {
        return $this->carsThatWinTooMuch() === [];
    }

    /** The gates that fail the command whatever the options: the data does not tell a coherent story. */
    public function passed(): bool
    {
        return $this->tiresPassed() && $this->terrainPassed();
    }

    /** @return list<string> the cars that win the tracks of a surface */
    private function winnersOn(string $surface): array
    {
        return array_values(array_unique(array_map(
            static fn (TrackOutcome $track): string => $track->winner->vehicle,
            // A rally is driven on both surfaces, so it is neither a gravel track nor an asphalt one.
            array_filter($this->tracks, static fn (TrackOutcome $track): bool => $track->surface === $surface && $track->kind !== 'rally'),
        )));
    }
}
