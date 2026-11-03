<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * What the streak of days and transmutation do to the pace of the three kinds of players of the scenario game: each is played with its extra, and the day on which the player buys
 * each car of the ladder is set against the day of the same game without it (`ShiftGate`). The streak is a gift, so it is held to a stricter tolerance than the extras that cost the
 * player something; transmutation is held to the tolerance of the pace gate.
 */
final class RetentionStudy
{
    /** The most the streak may move the day of buying a car: 5% (and a day, which is the resolution of the model). */
    public const STREAK_TOLERANCE = 0.05;

    public function __construct(private readonly Simulator $simulator, private readonly ShiftGate $gate) {}

    /**
     * @param  list<array{cash_ppm: int, xp_ppm: int, crates: int}>  $cycle
     * @param  array<int, array{cash_ppm: int, xp_ppm: int, crates: int}>  $milestones
     * @param  list<string>  $only
     * @return array{baseline: array<string, PlayerRun>, runs: array<string, PlayerRun>, gate: GateReport}
     */
    public function streak(EconomyRules $rules, CrateEconomy $crates, array $cycle, array $milestones, array $only = []): array
    {
        $baseline = $this->keep($this->simulator->simulateAllGame($rules), $only);
        $runs = $this->keep($this->simulator->simulateAllGame($rules, static fn (): array => [new StreakExtra($cycle, $milestones, $crates)]), $only);

        return ['baseline' => $baseline, 'runs' => $runs, 'gate' => $this->gate->evaluate($rules, $baseline, $runs, self::STREAK_TOLERANCE)];
    }

    /**
     * @param  array<string, float>  $epicGain
     * @param  list<float>  $cashSteps
     * @param  list<string>  $only
     * @return array{baseline: array<string, PlayerRun>, runs: array<string, PlayerRun>, gate: GateReport}
     */
    public function transmute(EconomyRules $rules, CrateEconomy $crates, array $epicGain, array $cashSteps, int $minPlayerLevel, array $only = []): array
    {
        $baseline = $this->keep($this->simulator->simulateAllGame($rules), $only);
        $runs = $this->keep($this->simulator->simulateAllGame($rules, static fn (): array => [new TransmuteExtra($crates, $epicGain, $cashSteps, $minPlayerLevel)]), $only);

        return ['baseline' => $baseline, 'runs' => $runs, 'gate' => $this->gate->evaluate($rules, $baseline, $runs)];
    }

    /**
     * @param  array<string, PlayerRun>  $runs
     * @param  list<string>  $only
     * @return array<string, PlayerRun>
     */
    private function keep(array $runs, array $only): array
    {
        return $only === [] ? $runs : array_intersect_key($runs, array_flip($only));
    }
}
