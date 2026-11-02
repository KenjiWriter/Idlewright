<?php

return [
    'entries' => [
        '/tuning/slots' => [
            'label' => 'Places for bonuses',
            'help' => 'How many places for bonuses a part has, unless its model says otherwise. The player fills and changes them in the workshop.',
            'example' => '3: every part has three places, empty at first.',
        ],
        '/tuning/unlock_level' => [
            'label' => 'Level from which the workshop tunes',
            'help' => 'From which player level the workshop tunes parts, that is, sets bonuses on them.',
            'example' => '3: from level three.',
        ],
        '/tuning/cost_random_ppm' => [
            'label' => 'Price of a try with a random bonus',
            'help' => 'What share of the price of the part’s model one try costs when the server draws the bonus. 30,000 is 3%.',
            'example' => '30,000: a part worth $570 costs $17.10 a try.',
        ],
        '/tuning/cost_pick_ppm' => [
            'label' => 'Price of a try with a picked bonus',
            'help' => 'What share of the model price a try costs when the player picks the bonus (dearer than a random one). 100,000 is 10%.',
            'example' => '100,000: a part worth $570 costs $57 a try.',
        ],
        '/tuning/cost_min_cents' => [
            'label' => 'Least price of a try',
            'help' => 'The least a try costs, also for cheap parts, in cents.',
            'example' => '500: never less than $5.',
        ],
        '/tuning/time_ppm_of_mount' => [
            'label' => 'Time of a try',
            'help' => 'How long a try takes as a share of the time of putting the part on. 500,000 is half.',
            'example' => '500,000: putting it on takes 2 minutes, a try 1 minute.',
        ],
        '/tuning/floor_sigma_ppm' => [
            'label' => 'Floor of a new bonus',
            'help' => 'The first amount of a bonus that is new at a place is not lower than this much below its mean, in millionths of sigma. So changing a bonus for another track is not a punishment.',
            'example' => '1,000,000: never lower than one sigma below the mean.',
        ],
        '/tuning/pity_step_ppm' => [
            'label' => 'Step of the bad-luck protection',
            'help' => 'By how much (in millionths of sigma) the mean grows after every try at the place that did not make it better. 0 turns the protection off.',
            'example' => '0: no protection; 50,000: after 10 tries with no gain the mean grows by half a sigma.',
        ],
        '/tuning/pity_max_ppm' => [
            'label' => 'Most the protection adds',
            'help' => 'The most that the bad-luck protection moves the mean, in millionths of sigma.',
            'example' => '1,000,000: by one sigma at most.',
        ],
        '/tuning/car_cap_ppm' => [
            'label' => 'Bonus ceiling on a car',
            'help' => 'The most that the bonuses of all the parts on a car can add to one statistic in one context, in ppm.',
            'example' => '250,000: no more than +25%.',
        ],
        '/tuning/max_ppm_plain' => [
            'label' => 'Highest bonus with no context',
            'help' => 'The most a bonus that works on every track may give at its best. A pack with a bonus that may give more is refused.',
            'example' => '80,000: +8% at the most.',
        ],
        '/tuning/max_ppm_context' => [
            'label' => 'Highest bonus with a context',
            'help' => 'The most a bonus that works only on one kind of track, surface or weather may give at its best. A narrow context allows more.',
            'example' => '120,000: +12% at the most.',
        ],
        '/tuning/definitions/*/weight' => [
            'label' => 'Weight of the bonus',
            'help' => 'How often the bonus is drawn among those that fit the slot of the part, when the player does not pick.',
            'example' => '3 beside others of weight 3 and 2: it is drawn in 3 cases of 8.',
        ],
        '/tuning/definitions/*/from_level' => [
            'label' => 'Level from which the bonus is on offer',
            'help' => 'From which player level the bonus appears in the workshop. Below it the bonus can be neither drawn nor picked.',
            'example' => '8: the snow bonus for players from level 8.',
        ],
        '/tuning/definitions/*/mean_ppm' => [
            'label' => 'Most common amount of the bonus',
            'help' => 'The middle of the curve: the amount that the bonus is drawn around. 50,000 is 5%.',
            'example' => '50,000: it most often comes out near 5%.',
        ],
        '/tuning/definitions/*/sigma_ppm' => [
            'label' => 'Spread of the bonus (sigma)',
            'help' => 'How widely the amounts spread round the mean: the larger the sigma, the more often amounts far from the mean come out. An amount two sigmas above the mean comes once in 70 tries.',
            'example' => '23,000: a spread of 2.3%, so 10% comes out once in 70 tries.',
        ],
        '/tuning/definitions/*/min_ppm' => [
            'label' => 'Least amount of the bonus',
            'help' => 'The least a bonus can give. A drawn amount below it is lifted to it.',
            'example' => '10,000: never below 1%.',
        ],
        '/tuning/definitions/*/max_ppm' => [
            'label' => 'Highest amount of the bonus',
            'help' => 'The most a bonus can give. An amount just under it is very rare. It cannot go over the limit for a bonus with or without a context in the general settings.',
            'example' => '120,000: 12% at the most, and it comes out once in over a thousand tries.',
        ],
    ],
];
