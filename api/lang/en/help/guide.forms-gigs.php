<?php

return [
    'entries' => [
        'gigs' => [
            'label' => 'How the gigs form works',
            'help' => 'Here you set the side jobs of the game: the board of a city, what an hour of a gig pays at each level, and the templates that the offers are drawn from. What you type is saved as a draft of the rules and reaches the players with the next published pack.',
            'steps' => [
                'Board tab: switch the gigs on, choose the tutorial step after which they open, and set how long a wave lasts and how many offers each time of the day has.',
                'Pay tab: set the speeds, the anchors of the ladder and the bands of the levels. These numbers decide what an hour of a gig is worth against an hour of laps.',
                'Kinds and Offer templates tabs: name the kinds, then describe each template: what the car must carry, how long the trip is, what it pays and how likely it is at each time of the day.',
                'Press Work out the pay under the form: it shows what every template pays on every car of the pack, against the ladder and against the laps, and whether the gate of the publication would let it through.',
                'Save the draft and publish a pack on the Publications page. The gate checks the same numbers again.',
            ],
            'example' => 'A ships game: the kind Ferry, a template that carries 1 – 6 passengers between two ports at 100%, a night template at 125%, and a wholesale template for 1000 – 1800 kg that is drawn only by day.',
        ],
    ],
];
