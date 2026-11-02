<?php

return [
    'entries' => [
        '/streaks/goal/daily_done' => [
            'label' => 'Missions of the day that make the goal',
            'help' => 'How many missions of the daily set a player has to do (done, not necessarily taken) for the day to count towards the streak. A set with fewer missions asks for all of them. The streak is counted out of the daily set, so there are no extra tasks.',
            'example' => '3: three of the six missions of the day, so a player who looks in once a day can keep the streak.',
            'range' => '1 – 20 (missions).',
            'tip' => 'A lower number protects the streak of players who look in rarely; a higher one asks for real play every day.',
        ],
        '/streaks/shield/every_days' => [
            'label' => 'Days of streak per shield',
            'help' => 'A player earns a shield when the streak reaches a multiple of this many days. A shield saves the streak from one missed day: the streak stands where it is, and that day pays nothing.',
            'example' => '7: a shield on the 7th, the 14th and the 21st day of a streak.',
            'range' => '1 – 365 (days).',
        ],
        '/streaks/shield/cap' => [
            'label' => 'Most shields a player keeps',
            'help' => 'How many shields a player can keep at once. A shield earned above this number is lost. With 0 the game has no shields and a missed day ends the streak.',
            'example' => '2: after two missed days in a row the streak is saved twice, and the third ends it.',
            'range' => '0 – 10 (shields).',
            'tip' => 'Shields are earned by playing and cannot be bought, so a high number is gentle, not a way to sell the streak.',
        ],
        '/streaks/cycle/*/cash_ppm' => [
            'label' => 'Cash of a day of the cycle (part of a tank)',
            'help' => 'The cash a day of the streak pays, as a part of the net earnings of a tank of the best car of the player (100% is a whole tank), so it grows with the player and needs no editing. Every entry of the cycle is one day, and after the last one the cycle comes round again. It is on top of the earnings of the cars and does not count in the economy of the game.',
            'example' => '2%: the first day of the cycle pays two hundredths of a tank; the last day of a week usually pays four times as much.',
            'range' => '0 – 100% (a part of a tank).',
            'tip' => 'Check the sum for a month with the command streaks:budget: it should stay a few percent of what the cars earn.',
        ],
        '/streaks/cycle/*/xp_ppm' => [
            'label' => 'XP of a day of the cycle (part of a tank)',
            'help' => 'The XP a day of the streak pays, as a part of the XP of a tank of the best car of the player (100% is a whole tank). The level of a player is a gate of the game, so keep it small.',
            'example' => '1%: a day of the cycle pays one hundredth of the XP of a tank.',
            'range' => '0 – 100% (a part of a tank).',
        ],
        '/streaks/milestones/*/day' => [
            'label' => 'Length of the streak of a milestone (days)',
            'help' => 'The length of the streak, in days, on which the milestone pays what it gives (the crates, and the cash and XP if it has any) on top of the day of the cycle. Each length is named once.',
            'example' => '30: a streak of thirty days pays its milestone on the thirtieth day, once.',
            'range' => '2 – 3650 (days).',
        ],
    ],
];
