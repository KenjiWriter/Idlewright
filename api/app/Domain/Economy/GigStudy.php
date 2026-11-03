<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * Plays the gigs of the game on top of the scenario game (the fleet of the game) and sets the days on which a player buys the cars of the ladder against the same players
 * without them, with the shift gate that the crates and the storage units have: a gig is an addition to the income of the day, so it must not move a day of the ladder by
 * more than the tolerance of the pace gate. The gate is asked of the player who takes a gig on every visit, and of the one who sends every car of the fleet on every visit
 * (the most that a player who plays normally does); the player who takes as many as the rules allow in a day, whatever their visits (a script), is in the report, because
 * it says how far the daily limit is from the tolerance, but is not what the gate asks: a script that is online all day would drive laps all day too.
 */
final class GigStudy
{
    public function __construct(private readonly Simulator $simulator, private readonly ShiftGate $gate) {}

    /**
     * @param  list<string>  $only  the kinds of players to play, all of them when empty
     * @param  list<int>  $levels  the levels to say what a gig is worth at
     */
    public function run(EconomyRules $rules, GigEconomy $gigs, array $only = [], array $levels = []): GigResult
    {
        $baseline = $this->keep($this->simulator->simulateAllGame($rules), $only);
        $plans = [
            'usual' => ['label' => 'a gig on the visits a player takes it on', 'extra' => static fn (): array => [new GigsExtra($gigs)], 'binding' => false],
            'every-visit' => ['label' => 'a gig on every visit', 'extra' => static fn (): array => [new GigsExtra($gigs, ['A' => 1.0, 'B' => 1.0, 'C' => 1.0], nightTake: ['A' => 1.0, 'B' => 1.0, 'C' => 1.0])], 'binding' => true],
            'every-car' => ['label' => 'every car on a gig on every visit', 'extra' => static fn (): array => [new GigsExtra($gigs, everyCar: true, nightTake: ['A' => 1.0, 'B' => 1.0, 'C' => 1.0])], 'binding' => true],
            'daily-limit' => ['label' => 'the daily limit every day (a script)', 'extra' => static fn (): array => [new GigsExtra($gigs, perDay: PHP_INT_MAX)], 'binding' => false],
        ];
        $result = [];

        foreach ($plans as $name => $plan) {
            $runs = $this->keep($this->simulator->simulateAllGame($rules, $plan['extra']), $only);
            $result[$name] = ['label' => $plan['label'], 'runs' => $runs, 'gate' => $this->gate->evaluate($rules, $baseline, $runs), 'binding' => $plan['binding']];
        }

        $worth = [];

        foreach ($levels as $level) {
            $perGig = $gigs->perGig($level);

            if ($perGig !== null) {
                $worth[] = ['level' => $level, ...$perGig, 'limit' => $gigs->dailyLimit($level)];
            }
        }

        return new GigResult($baseline, $result, $worth);
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
