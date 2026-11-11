<?php

return [
    'entries' => [
        'variant' => [
            'label' => 'Count on',
            'help' => 'The live pack is the game players have; the drafts are what a pack made now would hold.',
            'options' => [
                'live' => [
                    'label' => 'Live pack',
                    'help' => 'The game as players have it today.',
                ],
                'drafts' => [
                    'label' => 'Drafts',
                    'help' => 'The game with the saved drafts, before a pack.',
                ],
            ],
        ],
        'car' => [
            'label' => 'Car to start with',
            'help' => 'The car the driver starts with. Empty: the cheapest starting car of the game.',
            'example' => 'rdzak-gt-4',
        ],
        'style' => [
            'label' => 'Way of playing',
            'help' => 'How often the driver is at the game. When it is not, the car drives on its own while the fuel lasts.',
            'options' => [
                'active' => [
                    'label' => 'Plays without breaks',
                    'help' => 'The driver is at the game all the time: it refuels and buys at once.',
                ],
                'every' => [
                    'label' => 'Comes back every N minutes',
                    'help' => 'The driver comes back every so many minutes and stays so many minutes.',
                ],
                'A' => [
                    'label' => 'Player A',
                    'help' => 'Player A of the economy simulator: 3 visits a day.',
                ],
                'B' => [
                    'label' => 'Player B',
                    'help' => 'Player B of the economy simulator: 1 visit a day.',
                ],
                'C' => [
                    'label' => 'Player C',
                    'help' => 'Player C of the economy simulator: 8 visits a day.',
                ],
            ],
        ],
        'every_minutes' => [
            'label' => 'Comes back every (minutes)',
            'help' => 'The minutes from the start of one visit to the start of the next.',
            'example' => '60',
        ],
        'visit_minutes' => [
            'label' => 'Stays (minutes)',
            'help' => 'How long a visit is, in minutes; no longer than the time between visits.',
            'example' => '10',
        ],
        'minutes' => [
            'label' => 'Minutes to play',
            'help' => 'How long the simulator plays, from the new account; at most 43,200 minutes (30 days).',
            'example' => '120 is two hours, 1440 is a day.',
        ],
        'track' => [
            'label' => 'Track',
            'help' => 'The driver drives this track as soon as it is open. Empty: always the best open track.',
        ],
        'goal' => [
            'label' => 'Choose a track by',
            'help' => 'What the driver measures the best track by.',
            'options' => [
                'profit' => [
                    'label' => 'Profit of an hour',
                    'help' => 'The pay less the fuel, for an hour of driving.',
                ],
                'xp' => [
                    'label' => 'XP of an hour',
                    'help' => 'The experience of the driver for an hour of driving.',
                ],
            ],
        ],
        'buy' => [
            'label' => 'Purchases',
            'help' => 'What the driver buys; it always keeps the cash of a full tank.',
            'options' => [
                'none' => [
                    'label' => 'Nothing',
                    'help' => 'The driver drives what it was given.',
                ],
                'list' => [
                    'label' => 'A list, in order',
                    'help' => 'It buys the parts and cars of the list one by one, when the cash and the level allow.',
                ],
                'best' => [
                    'label' => 'The most profit for a dollar',
                    'help' => 'It buys what raises the result of an hour the most for its price.',
                ],
            ],
        ],
        'list' => [
            'label' => 'Shopping list',
            'help' => 'Slugs of parts and cars separated by commas, in the order of buying.',
            'example' => 'street-tires-t2, disc-brakes-t2',
        ],
        'step_seconds' => [
            'label' => 'A step of the tutorial (s)',
            'help' => 'How many seconds of play one step of the tutorial takes. The real time will be measured later from the data of players.',
            'example' => '60',
        ],
        'tutorial' => [
            'label' => 'With the tutorial',
            'help' => 'The driver first goes through the tutorial and gets its rewards.',
        ],
        'missions' => [
            'label' => 'With missions',
            'help' => 'Adds the daily rewards of missions at the end of every day of play.',
        ],
        'travel' => [
            'label' => 'Journeys',
            'help' => 'The driver goes to another city where it has a garage, when that pays in the time that is left.',
        ],
    ],
];
