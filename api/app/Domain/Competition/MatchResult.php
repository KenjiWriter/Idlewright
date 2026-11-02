<?php

declare(strict_types=1);

namespace App\Domain\Competition;

/** What a match came to: every run with the times of both sides, who won it, and who won the match. All times in whole milliseconds. */
final readonly class MatchResult
{
    /**
     * @param  list<array{track: string, weather: string, a_ms: int, b_ms: int, a_run_ms: int, b_run_ms: int, winner: string}>  $runs  `a_ms` and `b_ms` are the laps of the cars, `a_run_ms` and `b_run_ms` the laps with the luck of the run
     * @param  string  $winner  `a` or `b`
     * @param  int  $marginMs  how much quicker side A was over all the runs, with the luck (negative when B was quicker)
     */
    public function __construct(public array $runs, public int $aWins, public int $bWins, public string $winner, public int $marginMs) {}

    public function winnerIsA(): bool
    {
        return $this->winner === MatchEngine::SIDE_A;
    }

    /** The margin of the winner: how much quicker the side that won the match was over the runs, never negative on the winning side's own account. */
    public function marginOfWinnerMs(): int
    {
        return $this->winnerIsA() ? $this->marginMs : -$this->marginMs;
    }

    /** @return array<string, mixed> what is stored with a match */
    public function toArray(): array
    {
        return ['runs' => $this->runs, 'a_wins' => $this->aWins, 'b_wins' => $this->bWins, 'winner' => $this->winner, 'margin_ms' => $this->marginMs];
    }
}
