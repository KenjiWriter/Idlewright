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
        '/slots/base' => [
            'label' => 'Cars every driver can have on the road',
            'help' => 'How many cars a driver can have active (driving) at a time. The other cars stand in storage in the garage: they do not drive, burn no fuel, wear nothing and earn nothing, and they keep their place in the garage. The cars a driver had before the limit was made are kept until they put one in storage.',
            'example' => '2: a driver can have two cars on the road; a third car goes to storage when they get it.',
            'range' => '1 – 50 (cars).',
            'tip' => 'The economy simulator assumes about 1.7 cars on the road for a regular player, so 2 does not change the pace of players who do not pay. Check it with the command economy:simulate after a change.',
        ],
        '/slots/vip' => [
            'label' => 'More cars on the road with the VIP account',
            'help' => 'How many more cars a driver with the VIP account can have on the road, for as long as the account lasts. The cars in these slots stop earning the moment the account ends and go to storage; nothing is lost.',
            'example' => '3: with the VIP account a driver can have five cars on the road (2 + 3), which with the bonus of the account is about three times what a driver with two cars earns.',
            'range' => '0 – 50 (cars).',
            'tip' => 'This is the biggest part of what the VIP account is worth: more cars earn more than the cash and XP bonus. Check the advantage with the economy simulator before you raise it.',
        ],
        '/slots/swap_ms' => [
            'label' => 'Time to put a car on the road',
            'help' => 'How long a car taken out of storage is made ready for: it drives from that moment on and earns nothing before. It keeps a driver from changing cars at every shower.',
            'example' => '120 s: two minutes.',
            'range' => '0 – 3600 s.',
        ],
        '/slots/swap_fee_ppm' => [
            'label' => 'Cost of a swap (part of a tank)',
            'help' => 'What it costs to put a car from storage on the road in place of one that is on it, as a part of the net earnings of a tank of the best car of the driver (100% is a whole tank), in whole dollars and at least one. Putting a car on a free slot costs nothing.',
            'example' => '2%: for a car that earns $375 a tank a swap costs $8.',
            'range' => '0 – 100% (a part of a tank).',
        ],
        '/transmute/min_player_level' => [
            'label' => 'Level from which parts can be transmuted',
            'help' => 'The level of the driver from which a part of the inventory can be made one rarity better with cash and coins. Below it the button is there and says why it cannot be pressed.',
            'example' => '6: the level that opens the premium crate, so that the first transmutation comes after the first merges.',
            'range' => '1 – 100 (the level).',
        ],
        '/transmute/limit_per_day' => [
            'label' => 'Parts a driver can transmute in a day',
            'help' => 'How many parts a driver can transmute in the last 24 hours. It is a guard against scripts, not a limit for players: the price is what limits them.',
            'example' => '10: the eleventh part waits until the first one is a day old.',
            'range' => '1 – 1000 (parts).',
        ],
        '/transmute/coin_base/*' => [
            'label' => 'Coins of a step (part of the first tier)',
            'help' => 'The premium coins of the step (the first number is common to uncommon, the second uncommon to rare, the third rare to epic) for a part of the first tier. A part of a higher tier costs the coins of the step times the growth of the tier, once for every tier above the first.',
            'example' => '20: rare to epic for a part of the first tier; for the fifth tier 20 times 1.3 to the power of 4, which is 57.',
            'range' => '0 – 100000 (coins).',
            'tip' => 'A coin costs 0.08 to 0.10 zl in the packs: the command economy:transmute shows what a part costs in money.',
        ],
        '/transmute/coin_tier_growth_ppm' => [
            'label' => 'Growth of the coins with the tier',
            'help' => 'How many times more coins a part of the next tier costs: 130% is 1.3 times. The coins of a step are the base of the step times this, once for every tier above the first.',
            'example' => '130%: a part of the fifth tier costs 2.86 times the coins of the first.',
            'range' => '100 – 300% (a multiple).',
        ],
        '/transmute/cash_ppm/*' => [
            'label' => 'Cash of a step (multiple of the price)',
            'help' => 'The cash of the step (common to uncommon, uncommon to rare, rare to epic) as a multiple of the price of the model of the part: 100% is the price of the part. About half of what merging costs for the same step is the rule these numbers were set by.',
            'example' => '1400%: rare to epic costs fourteen times the price of the model, half of what merging costs for that step.',
            'range' => '0 – 100000% (a multiple).',
            'tip' => 'Check with the command economy:transmute: it fails when the cash of a step is below about half of what merging costs.',
        ],
    ],
];
