<?php

return [
    'entries' => [
        '/missions/reset_hour' => [
            'label' => 'Hour the day of missions starts',
            'help' => 'The hour (in the time zone of the game) at which the day of the missions starts, and with it the new daily set. The week starts on Monday at this hour and the month on the first at this hour. The period before also ends then: what a player finished and did not take, the server takes for them, and the rest is lost.',
            'example' => '4: a player who plays in the evening and after midnight has one day of missions, and gets the new set at 4:00 in the morning.',
            'range' => '0 – 23 (the hour).',
        ],
        '/missions/periods/daily/bonus_coins' => [
            'label' => 'Bonus for the whole set: day (coins)',
            'help' => 'How many premium coins (RideCoin) a player gets for doing every mission of the set of the day. They are the only free coins the missions give; the rewards of single missions have none. So this number is all the free coins of this period, and the schema does not allow more than 5.',
            'example' => '2 coins: all three periods done every time make 2, 8 and 25 coins, about 119 a month; the VIP account costs 150 coins and a pack of 50 coins 4.99 zł.',
            'range' => '0 – 5 coins.',
            'tip' => 'Players feel a lower bonus as a reward taken away, so start low: raising it later is easy.',
        ],
        '/missions/periods/weekly/bonus_coins' => [
            'label' => 'Bonus for the whole set: week (coins)',
            'help' => 'How many premium coins (RideCoin) a player gets for doing every mission of the set of the week. They are the only free coins the missions give; the rewards of single missions have none. So this number is all the free coins of this period, and the schema does not allow more than 20.',
            'example' => '8 coins: all three periods done every time make 2, 8 and 25 coins, about 119 a month; the VIP account costs 150 coins and a pack of 50 coins 4.99 zł.',
            'range' => '0 – 20 coins.',
            'tip' => 'Players feel a lower bonus as a reward taken away, so start low: raising it later is easy.',
        ],
        '/missions/periods/monthly/bonus_coins' => [
            'label' => 'Bonus for the whole set: month (coins)',
            'help' => 'How many premium coins (RideCoin) a player gets for doing every mission of the set of the month. They are the only free coins the missions give; the rewards of single missions have none. So this number is all the free coins of this period, and the schema does not allow more than 60.',
            'example' => '25 coins: all three periods done every time make 2, 8 and 25 coins, about 119 a month; the VIP account costs 150 coins and a pack of 50 coins 4.99 zł.',
            'range' => '0 – 60 coins.',
            'tip' => 'Players feel a lower bonus as a reward taken away, so start low: raising it later is easy.',
        ],
        '/missions/periods/daily/cash_ppm' => [
            'label' => 'Cash for a mission: day (% of its worth)',
            'help' => 'The part of the worth of the missions of the set of the day that is paid in cash (1 000 000 is all of it). The worth of a mission is the work it asks, in tanks of the best car of the player: laps, earnings, fuel, crates, upgrades and the rest have a weight in the missions document (effort), so a mission that asks a lot pays a lot. The cash is that many tanks of earnings after the fuel. It is on top of what the cars earn and does not count in the economy of the game: the gates and the simulator do not see it.',
            'example' => '10%: a mission worth 4 tanks of a car that earns $375 on a tank pays about $150.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/weekly/cash_ppm' => [
            'label' => 'Cash for a mission: week (% of its worth)',
            'help' => 'The part of the worth of the missions of the set of the week that is paid in cash (1 000 000 is all of it). The worth of a mission is the work it asks, in tanks of the best car of the player: laps, earnings, fuel, crates, upgrades and the rest have a weight in the missions document (effort), so a mission that asks a lot pays a lot. The cash is that many tanks of earnings after the fuel. It is on top of what the cars earn and does not count in the economy of the game: the gates and the simulator do not see it.',
            'example' => '10%: a mission worth 4 tanks of a car that earns $375 on a tank pays about $150.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/monthly/cash_ppm' => [
            'label' => 'Cash for a mission: month (% of its worth)',
            'help' => 'The part of the worth of the missions of the set of the month that is paid in cash (1 000 000 is all of it). The worth of a mission is the work it asks, in tanks of the best car of the player: laps, earnings, fuel, crates, upgrades and the rest have a weight in the missions document (effort), so a mission that asks a lot pays a lot. The cash is that many tanks of earnings after the fuel. It is on top of what the cars earn and does not count in the economy of the game: the gates and the simulator do not see it.',
            'example' => '10%: a mission worth 4 tanks of a car that earns $375 on a tank pays about $150.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/daily/xp_ppm' => [
            'label' => 'XP for a mission: day (% of its worth)',
            'help' => 'The part of the worth of the missions of the set of the day that is paid in XP of the player (1 000 000 is all of it): that many tanks of the XP of the best car. The level of the player opens tracks, cities and parts, and the tutorial says that levels are to be the work of the laps, so keep this number small: every point moves the pace of the whole game.',
            'example' => '6%: a mission worth 4 tanks of a car whose tank gives 600 XP pays about 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/weekly/xp_ppm' => [
            'label' => 'XP for a mission: week (% of its worth)',
            'help' => 'The part of the worth of the missions of the set of the week that is paid in XP of the player (1 000 000 is all of it): that many tanks of the XP of the best car. The level of the player opens tracks, cities and parts, and the tutorial says that levels are to be the work of the laps, so keep this number small: every point moves the pace of the whole game.',
            'example' => '6%: a mission worth 4 tanks of a car whose tank gives 600 XP pays about 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/monthly/xp_ppm' => [
            'label' => 'XP for a mission: month (% of its worth)',
            'help' => 'The part of the worth of the missions of the set of the month that is paid in XP of the player (1 000 000 is all of it): that many tanks of the XP of the best car. The level of the player opens tracks, cities and parts, and the tutorial says that levels are to be the work of the laps, so keep this number small: every point moves the pace of the whole game.',
            'example' => '6%: a mission worth 4 tanks of a car whose tank gives 600 XP pays about 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/main/cash_ppm' => [
            'label' => 'Cash for a main mission (% of its worth)',
            'help' => 'The part of the worth of a main mission that is paid in cash, worked out when it opens (1 000 000 is all of it). The goals over all the time (laps, earnings, fuel) are turned into tanks of the best car of the player at that moment, so a better car does not make them a free reward; the rest (level, cars, garages, parts) has a weight in the missions document (effort). These rewards are paid once.',
            'example' => '15%: “Drive 250 laps” is about 8 tanks of a car that earns $375 on a tank, so about $469.',
            'range' => '0 – 1000%.',
        ],
        '/missions/main/xp_ppm' => [
            'label' => 'XP for a main mission (% of its worth)',
            'help' => 'The part of the worth of a main mission that is paid in XP of the player, worked out when it opens (1 000 000 is all of it). The level of the player is a gate of the game, so keep this number small; the XP of the car is given separately (car_xp_laps of the mission).',
            'example' => '9%: “Drive 250 laps” is about 8 tanks of a car whose tank gives 600 XP, so about 450 XP.',
            'range' => '0 – 1000%.',
        ],
    ],
];
