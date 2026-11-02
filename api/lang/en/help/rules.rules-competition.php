<?php

return [
    'entries' => [
        '/competition/pi/reference_pi' => [
            'label' => 'PI of the reference car',
            'help' => 'What the reference car (stock, with no parts) is worth in PI. It is the scale that the limits of the classes stand on: changing it moves the PI of every car by the same share, so the limits of the classes have to move with it.',
            'example' => '300: a stock Rdzak GT-4 is 300 PI, a car with 50% better statistics is 450.',
            'range' => '1 – 100,000 (PI).',
        ],
        '/competition/pi/stat_cap_ppm' => [
            'label' => 'Cap of one statistic in the PI (% of the reference car)',
            'help' => 'The most that one statistic of a car counts for in the PI, as a multiple of the statistic of the reference car (100% is the same). The cap stops one huge number from buying a class.',
            'example' => '400%: an acceleration four times that of the reference car counts four times, no more.',
            'range' => '100% – 10,000%.',
        ],
        '/competition/pi/max_pi' => [
            'label' => 'Highest PI shown',
            'help' => 'The highest PI a car can have: a stronger car still has this much. The top class ends here (a car is shown as a class and a PI, from F0 to SS999). It has to be above the limit of the last class that has a limit.',
            'example' => '999: the strongest car is SS 999.',
            'range' => '1 – 100,000 (PI).',
        ],
        '/competition/pi/weights_ppm/*' => [
            'label' => 'Weight of a statistic in the PI',
            'help' => 'How much this statistic counts for in the PI of a car (the weights add up to 100%). By default the weights are the mean of what all the tracks ask of a car, so the statistics that the tracks rely on most (grip, acceleration) count the most. The weights have to add up to 100% and are for the statistics of the model of the game.',
            'example' => '23%: grip is almost a quarter of the PI.',
            'range' => '0% – 100%; 100% together.',
        ],
        '/competition/pi/class_max_pi/*' => [
            'label' => 'Upper limit of a class (PI)',
            'help' => 'The highest PI that a car of this class can still have. A car is in the first class whose limit is not below its PI; cars above the last limit are in the top class. The limits have to grow from the weakest class. A tournament of a class takes the cars of that class only, so a good tune is an advantage in the class and a better car opens the next one.',
            'example' => '399: a car with 399 PI is still in class D, one with 400 is in class C.',
            'range' => '1 – 1,000,000 (PI); ascending.',
        ],
        '/competition/rating/start' => [
            'label' => 'Respekt at the start',
            'help' => 'The Respekt of a new driver, and the value that a season pulls the rating back towards in the soft reset.',
            'example' => '1000: a new driver starts in the street division.',
            'range' => '1 – 100,000.',
        ],
        '/competition/rating/minimum' => [
            'label' => 'Lowest Respekt',
            'help' => 'The Respekt of a driver does not fall below this, however much they lose. It stops a fall without end.',
            'example' => '600: a driver who keeps losing stops at 600.',
            'range' => '0 – 100,000; not above the Respekt at the start.',
        ],
        '/competition/rating/k_new' => [
            'label' => 'K for a new driver',
            'help' => 'How fast the Respekt of a driver moves while they have played fewer rated matches than the next number says. Against an equal rival a win gives half of this and a loss takes as much. A big K quickly puts a new player at their place.',
            'example' => '40: a win over an equal rival is +20, a loss −20.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/new_matches' => [
            'label' => 'Matches a driver is new for',
            'help' => 'For this many rated matches a driver has the big K. After that the Respekt moves more slowly.',
            'example' => '20: the first 20 matches settle a player.',
            'range' => '0 – 1000 matches.',
        ],
        '/competition/rating/k_established' => [
            'label' => 'K for an established driver',
            'help' => 'How fast the Respekt moves for a driver who is not new any more and is below the top.',
            'example' => '24: a win over an equal rival is +12, a loss −12.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/k_top' => [
            'label' => 'K for a driver at the top',
            'help' => 'How fast the Respekt moves for a driver at the top (from the limit of the top). A small K makes the top steady.',
            'example' => '16: a win over an equal rival is +8, a loss −8.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/top_from' => [
            'label' => 'Respekt from which a driver is at the top',
            'help' => 'From this Respekt a driver has the smallest K. It is a number of its own, apart from the limit of the elite division, though by default both are the same.',
            'example' => '1700.',
            'range' => '1 – 100,000.',
        ],
        '/competition/rating/club_from' => [
            'label' => 'Start of the club division',
            'help' => 'From this Respekt a driver is in the club division (below it: street). The divisions pick the rivals in tournaments.',
            'example' => '1100.',
            'range' => '1 – 100,000; below the regional division.',
        ],
        '/competition/rating/regional_from' => [
            'label' => 'Start of the regional division',
            'help' => 'From this Respekt a driver is in the regional division.',
            'example' => '1400.',
            'range' => '1 – 100,000; between club and elite.',
        ],
        '/competition/rating/elite_from' => [
            'label' => 'Start of the elite division',
            'help' => 'From this Respekt a driver is in the elite division, the highest.',
            'example' => '1700.',
            'range' => '1 – 100,000; above the regional division.',
        ],
        '/competition/rating/season_keep_ppm' => [
            'label' => 'How much Respekt stays after a season (%)',
            'help' => 'The part of the Respekt that stays after the soft reset at the start of a season; the rest goes back to the start value. 100% changes nothing, 0% puts everybody at the start.',
            'example' => '75%: a driver on 1400 starts the season on 1300, one on 600 on 700.',
            'range' => '0% – 100%.',
        ],
        '/competition/match/luck_sigma_ppm' => [
            'label' => 'Width of the luck of a run (% of the lap time)',
            'help' => 'By how many percent of the lap time the time of a run can change because of luck: about two runs in three are within this width, and none goes further than three and a third times as much. More luck makes the weaker car win more often; zero is no luck. It is drawn from the seed, so the result of a match can be repeated.',
            'example' => '2%: a car 1% quicker wins a single run 64% of the time, and a match of three runs 70%.',
            'range' => '0% – 10%.',
        ],
        '/competition/match/runs' => [
            'label' => 'Runs in a match',
            'help' => 'How many runs a match is made of; the side that wins most of them wins the match, so the number has to be odd. Each run is on another track (with surfaces to choose from), so more runs favour a car that suits many tracks and cut the part of luck.',
            'example' => '3: the best of three runs.',
            'range' => '1 – 9, odd.',
        ],
    ],
];
