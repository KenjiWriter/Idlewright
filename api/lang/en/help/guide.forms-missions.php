<?php

return [
    'entries' => [
        'missions' => [
            'label' => 'How the missions form works',
            'help' => 'Here you set the missions of the game: when the sets of a day, a week and a month start and what part of the worth of its missions each pays, the missions that the sets draw from, the chains of main missions, and what a unit of every kind of work is worth. What you type is saved as a draft of the rules and of the document of the missions, together, and reaches the players with the next published pack.',
            'steps' => [
                'Rewards and periods tab: switch the missions on, choose the time zone and the hour the day starts, and set what part of the worth of its missions each set pays and the main missions pay (cash and XP in percent), with the coins for a whole set.',
                'Worth of work tab: say what one unit of every kind of work is worth in tanks of the best car; a mission pays a part of the work it asks, so a mission that asks more pays more.',
                'Press Work out the budget: it shows what a day, a week and a month come to in cash, XP and coins for a car on a track, and whether the gate of the publication would let it through.',
                'Sets tab: for the daily, weekly and monthly set, add slots and the missions each slot can draw, with their goals, weights and multipliers.',
                'Main missions tab: add chains and their missions in the order in which they open, with their goals, texts in the languages and the items they give.',
                'Save the draft: both documents are kept together, or none when one of them is refused. Publish a pack on the Publications page.',
            ],
            'example' => 'A ships game: the sets pay 10% of the worth in cash and 6% in XP with 2, 8 and 25 coins, the main missions 15% and 9%; "sail 50 laps" is worth 50 laps of a 30-lap tank times 1 tank of work, about 1.7 tanks, and pays 17% of the net earnings of a tank in a daily set.',
        ],
    ],
];
