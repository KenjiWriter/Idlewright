<?php

declare(strict_types=1);

namespace App\Agent;

use App\Models\ContentDefinition;

/**
 * What the AI assistant may change and what it may not (D5 and D16 of the design). The yardstick of the gates (the anchors of the economy and the limits the gates hold a
 * pack to) is out of its reach, so it can never make a gate pass by moving the gate. The world (cities, roads, tracks) is only read while its editor is being rebuilt, and
 * the model of the game and the brand of the site are never changed by it. Everything else of the content is within its reach: the rules of the game, the tutorial, the
 * vocabulary, the missions, the cars and the parts, always as a block of changes that the bridge checks.
 */
final class AgentAnchors
{
    /** The sections of the rules that the assistant reads but does not change: the world, while the editor of the map is being rebuilt. */
    public const READ_ONLY_SECTIONS = ['regions', 'roads', 'travel', 'tracks', 'track_model'];

    /** The documents of the rules (besides `starter`) that a block of the assistant may change. */
    public const DOCUMENTS = ['tutorial', 'vocabulary', 'missions'];

    /**
     * The yardstick of the gates: where each part of it is kept, and why it is out of the assistant's reach. None of it is a target a block of changes can name (the
     * schema of the block does not have it, or it is not content at all), and the test of the anchors holds that.
     *
     * @var array<string, string>
     */
    public const YARDSTICK = [
        'rules/economy' => 'the anchors of the economy: the profit of an hour, the ladder of the levels and the cars, the prices of the parts, the players A, B and C, the days of the pace and the tolerance of the gate',
        'config ride.missions.budget' => 'the share of the cash and the experience that a month of missions may give (gate missions:budget --strict)',
        'config ride.tuning.budget' => 'how much of a lap and of the income the best set of bonuses may take (gate tuning:budget --strict)',
        'code GigReport::offences' => 'the bounds of the pay of an hour of gigs against the ladder and the laps (gate gigs:report --strict)',
        'code TrackSimulator' => 'how many tracks one car may win and that every tire asks for its surface (gate tracks:simulate --strict)',
    ];

    /** What the assistant never does at any level: there is no tool for it. */
    public const NEVER = [
        'make a pack live or go back to an older pack (only a person who publishes)',
        'import packs; delete, archive or withdraw cars and parts',
        'players: lists, accounts, exports',
        'the trail of the panel with addresses, admins, games, domains and certificates',
        'payments and prices in real money (*_minor)',
        'sending push messages, images, the brand and the own CSS of the site, the model of the game (slots and stats)',
        'the yardstick of the gates (see out_of_reach)',
        'the world, roads and tracks: only read while the editor of the map is being rebuilt',
    ];

    /**
     * What a block of the assistant may name, in the form the bridge checks a scope in: every section of the rules except the world, the other documents of the rules it
     * may change, and every car and part.
     *
     * @return list<string>
     */
    public function scope(): array
    {
        $scope = [];

        foreach ($this->sectionsOfTheRules() as $section) {
            if (! in_array($section, self::READ_ONLY_SECTIONS, true)) {
                $scope[] = 'rules:starter#'.$section;
            }
        }

        foreach (self::DOCUMENTS as $document) {
            $scope[] = 'rules:'.$document;
        }

        return [...$scope, 'kind:part', 'kind:vehicle'];
    }

    /**
     * What the assistant is told it cannot change and why.
     *
     * @return array{yardstick: array<string, string>, read_only_sections: list<string>, never: list<string>}
     */
    public function explain(): array
    {
        return ['yardstick' => self::YARDSTICK, 'read_only_sections' => self::READ_ONLY_SECTIONS, 'never' => self::NEVER];
    }

    /** @return list<string> the sections of the rules of the game as they are being worked on */
    private function sectionsOfTheRules(): array
    {
        $rules = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->first()?->workingDocument() ?? [];

        return array_values(array_filter(array_keys($rules), static fn (string $key): bool => ! in_array($key, ['schema', 'version'], true)));
    }
}
