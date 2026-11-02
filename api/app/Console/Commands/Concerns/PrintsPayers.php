<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Domain\Economy\EconomyRules;
use App\Domain\Economy\PayerResult;
use App\Domain\Economy\PerkExtra;

/**
 * The payers table of `economy:simulate --payers`: for each kind of player, how much sooner the cars of the ladder are bought with the missions, the VIP account, the
 * auto-refill and the pass than without them, what each adds to the cash and the experience of the first thirty days, and what it costs in a month. For a command.
 */
trait PrintsPayers
{
    /** The rungs that the table shows (the first car after the wreck, the middle ones and the last). */
    private function payerRungs(EconomyRules $economy): array
    {
        $last = $economy->rungs() - 1;

        return array_values(array_unique(array_filter([1, 3, 5, $last], static fn (int $rung): bool => $rung >= 1 && $rung <= $last)));
    }

    private function printPayers(EconomyRules $economy, PayerResult $payers): void
    {
        $rungs = $this->payerRungs($economy);

        $this->newLine();
        $this->line('Payers and missions, on top of the game scenario: each player is played without them and with each of them, and the table says on which day it has the car of the rung (and how many days that saves).');
        $this->line('Missions are played at the share of them that the player completes; the VIP account, the auto-refill (charges a day) and the pass are held every day. The extras are applied to the main car (VIP also to the wrecks).');

        foreach (array_keys($payers->baseline) as $player) {
            $this->newLine();
            $this->line("Player {$player}:");
            $rows = [['none', ...array_map(fn (int $rung): string => (string) ($payers->dayOf(null, (string) $player, $rung) ?? '-'), $rungs), '-', '-', '-', '-']];

            foreach ($payers->variants as $key => $variant) {
                $notes = $variant['runs'][$player]->notes;
                $single = count($notes) === 1 ? array_values($notes)[0] : null;
                $rows[] = [
                    $variant['label'],
                    ...array_map(function (int $rung) use ($payers, $key, $player): string {
                        $day = $payers->dayOf($key, (string) $player, $rung);
                        $saved = $payers->saved($key, (string) $player, $rung);

                        return $day === null ? '-' : $day.($saved === null ? '' : sprintf(' (%+.0f%%)', -$saved * 100));
                    }, $rungs),
                    $single === null ? 'see above' : sprintf('%+.0f%%', $single['uplift_cash_30d'] * 100),
                    $single === null ? 'see above' : sprintf('%+.0f%%', $single['uplift_xp_30d'] * 100),
                    $this->spent($notes),
                    $this->coinsEarned($notes),
                ];
            }

            $this->table(['', ...array_map(fn (int $rung): string => 'day of '.$economy->rungName($rung), $rungs), 'cash '.PerkExtra::WINDOW_DAYS.' d', 'XP '.PerkExtra::WINDOW_DAYS.' d', 'costs a month', 'coins earned'], $rows);
        }

        $beyond = $payers->beyondTolerance();
        $this->newLine();

        if ($beyond === []) {
            $this->info('No payer buys a car more than '.sprintf('%.0f%%', $payers->tolerance * 100).' of the days sooner than a player who pays nothing.');

            return;
        }

        $this->warn('Payers that buy a car more than '.sprintf('%.0f%%', $payers->tolerance * 100).' of the days sooner than a player who pays nothing (information: this fails nothing):');
        $this->table(['variant', 'player', 'car', 'days saved'], array_map(fn (array $row): array => [$payers->variants[$row['variant']]['label'], $row['player'], $economy->rungName($row['rung']), sprintf('%.0f%%', $row['saved'] * 100)], $beyond));
    }

    /** @param  array<string, array<string, mixed>>  $notes */
    private function spent(array $notes): string
    {
        $price = array_sum(array_map(static fn (array $note): float => (float) ($note['price_per_30d'] ?? 0.0), $notes));
        $currency = (string) (array_values(array_filter(array_column($notes, 'currency')))[0] ?? '');

        return $price > 0.0 ? number_format($price, 2, '.', ' ').' '.$currency : '-';
    }

    /** @param  array<string, array<string, mixed>>  $notes */
    private function coinsEarned(array $notes): string
    {
        $coins = (float) ($notes['missions']['coins_per_30d'] ?? 0.0);

        return $coins > 0.0 ? sprintf('%.0f', $coins) : '-';
    }

    /**
     * The payers as data: by variant and player, the days of every car of the ladder, the days saved against the player who pays nothing, and what each extra said.
     *
     * @return array<string, mixed>
     */
    private function payersAsArray(EconomyRules $economy, PayerResult $payers): array
    {
        $rungs = range(1, $economy->rungs() - 1);

        return [
            'tolerance' => $payers->tolerance,
            'baseline' => array_map(fn ($run): array => ['days' => array_map(fn (int $rung): ?int => $run->dayOf($rung), $rungs)], $payers->baseline),
            'variants' => array_map(fn (array $variant): array => [
                'label' => $variant['label'],
                'players' => array_map(fn ($run): array => ['days' => array_map(fn (int $rung): ?int => $run->dayOf($rung), $rungs), 'notes' => $run->notes], $variant['runs']),
            ], $payers->variants),
            'saved' => array_map(fn (string $key): array => array_combine(
                array_keys($payers->variants[$key]['runs']),
                array_map(fn (string $player): array => array_map(fn (int $rung): ?float => ($saved = $payers->saved($key, $player, $rung)) === null ? null : round($saved, 4), $rungs), array_keys($payers->variants[$key]['runs'])),
            ), array_combine(array_keys($payers->variants), array_keys($payers->variants))),
            'beyond_tolerance' => $payers->beyondTolerance(),
        ];
    }
}
