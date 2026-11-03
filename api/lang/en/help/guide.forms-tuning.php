<?php

return [
    'entries' => [
        'tuning' => [
            'label' => 'How the bonuses of the parts work',
            'help' => 'A driver puts bonuses on a part in the workshop: each try costs a share of the price of the part and draws an amount from a bell curve around the mean of the bonus. This page shows the bonuses of the game, and the parameter studio changes their numbers.',
            'steps' => [
                'Read the list: every bonus has its name in every language, what it does, the slots it fits, its weight in a draw and the level it opens at.',
                'Read the bell of each bonus: the bars are the chance of each whole percent that one try gives, and the table says how many tries it takes on average to reach 6, 8, 10 and 12 percent.',
                'To change the mean, the spread, the least or the most of a bonus, the number of places or the price of a try, open the parameter studio, section "Part bonuses (tuning)"; the limits (8% without a context, 12% with one) are checked when a pack is made.',
                'Press "Work it out" at the bottom: it measures the best set of the bonuses on the reference cars and says how much it takes off the lap. Over the 10% of the design it is only a warning; over the approved ceiling the publication of the pack is stopped by the gate tuning:budget.',
                'Publish a pack on the Publications page; the bonuses that drivers already hold are kept, and a bonus that a pack takes away stops working until it is back.',
            ],
            'example' => 'A bonus "Gravel grip" with a mean of 5%, a spread of 2.3%, from 1% to 12%: one try in about three gives 6% or more, one in a thousand gives 12%. Raise the mean to 7% and the best set on gravel goes past the approved ceiling, so the gate stops the pack until the limits come down.',
        ],
    ],
];
