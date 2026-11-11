<?php

declare(strict_types=1);

namespace App\Agent;

use App\Content\PackContent;
use App\Content\Publishing\PackGates;
use InvalidArgumentException;

/**
 * The simulators of the game that the AI assistant may have count (D14 of the design), each on the content of a variant (`AgentVariants`): the commands that the panel and
 * the gates of publication already use, run as they are, with only the options that do not move the yardstick (the bounds of a gate are never an option the assistant
 * gives). A study is heavy when it can take longer than a request may wait; those go to the worker of the queue `agent`.
 */
final class AgentStudies
{
    /**
     * The studies: the command, whether it prints JSON, whether it is heavy, and which options it takes (the option of the command for each input of the tool).
     *
     * @var array<string, array{command: string|null, json: bool, heavy: bool, options: array<string, string>}>
     */
    private const STUDIES = [
        'simulate_economy' => ['command' => 'economy:simulate', 'json' => true, 'heavy' => false, 'options' => ['players' => '--player', 'payers' => '--payers', 'gigs' => '--gigs']],
        'run_gates' => ['command' => null, 'json' => true, 'heavy' => true, 'options' => []],
        'missions_budget' => ['command' => 'missions:budget', 'json' => true, 'heavy' => false, 'options' => ['car' => '--car', 'track' => '--track', 'level' => '--level']],
        'tuning_budget' => ['command' => 'tuning:budget', 'json' => true, 'heavy' => false, 'options' => []],
        'gigs_report' => ['command' => 'gigs:report', 'json' => true, 'heavy' => false, 'options' => []],
        'streaks_budget' => ['command' => 'streaks:budget', 'json' => false, 'heavy' => false, 'options' => ['car' => '--car', 'track' => '--track', 'days' => '--days', 'simulate' => '--simulate']],
        'fleet_study' => ['command' => 'economy:fleet', 'json' => false, 'heavy' => false, 'options' => ['players' => '--player']],
        'transmute_study' => ['command' => 'economy:transmute', 'json' => false, 'heavy' => false, 'options' => ['simulate' => '--simulate']],
        'pi_report' => ['command' => 'competition:pi-report', 'json' => true, 'heavy' => false, 'options' => []],
        'class_jump' => ['command' => 'competition:class-jump', 'json' => true, 'heavy' => false, 'options' => ['car' => '--car', 'tracks' => '--tracks']],
        'tracks_simulate' => ['command' => 'tracks:simulate', 'json' => true, 'heavy' => true, 'options' => []],
    ];

    /** The parts of the report of the economy that are given unless the whole report is asked for: the rest (the extras, the upkeep) is long and seldom needed. */
    private const ECONOMY_PARTS = ['ruleset', 'gate', 'game_ladder', 'game_fleet', 'consistency', 'players', 'merge', 'payers', 'gigs'];

    /** The most characters of a report printed as text that are given back. */
    private const TEXT_LIMIT = 20_000;

    public function __construct(private readonly PackGates $gates) {}

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::STUDIES);
    }

    public function isHeavy(string $study): bool
    {
        return $this->definition($study)['heavy'];
    }

    /**
     * The options of the command for what the tool was given, only those the study takes, in a fixed order (so the same question has the same mark).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function options(string $study, array $input): array
    {
        $options = [];

        foreach ($this->definition($study)['options'] as $name => $option) {
            $value = $input[$name] ?? null;

            if ($value === null || $value === false || $value === [] || $value === '') {
                continue;
            }

            $options[$option] = $value;
        }

        ksort($options);

        return $options;
    }

    /**
     * Counts a study on a content.
     *
     * @param  array<string, mixed>  $options  as `options()` gives them
     * @return array<string, mixed>
     */
    public function run(string $study, PackContent $content, array $options, bool $full = false): array
    {
        $definition = $this->definition($study);

        if ($study === 'run_gates') {
            $gates = $this->gates->run($content);

            return ['passed' => ! in_array(false, array_column($gates, 'passed'), true), 'gates' => $gates];
        }

        $arguments = $definition['json'] ? [...$options, '--json' => true] : $options;
        ['exit' => $exit, 'output' => $output] = $this->gates->command($content, (string) $definition['command'], $arguments);

        if (! $definition['json']) {
            return ['exit' => $exit, 'report' => mb_strlen($output) > self::TEXT_LIMIT ? mb_substr($output, 0, self::TEXT_LIMIT).'...' : $output];
        }

        $report = json_decode($output, true);

        if (! is_array($report)) {
            return ['exit' => $exit, 'report' => mb_substr($output, 0, 4000)];
        }

        if ($study === 'simulate_economy' && ! $full) {
            $report = array_intersect_key($report, array_flip(self::ECONOMY_PARTS));
        }

        return ['exit' => $exit, 'report' => $report];
    }

    /** @return array{command: string|null, json: bool, heavy: bool, options: array<string, string>} */
    private function definition(string $study): array
    {
        return self::STUDIES[$study] ?? throw new InvalidArgumentException("There is no study {$study}.");
    }
}
