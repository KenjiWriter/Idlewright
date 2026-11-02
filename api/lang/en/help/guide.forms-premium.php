<?php

return [
    'entries' => [
        'premium' => [
            'label' => 'How the paid features form works',
            'help' => 'Here you set what the game sells for premium coins and for real money: the packs of coins, the speed-up, the auto-refill, the VIP account and the pass. What you type is saved as a draft of the rules and reaches the players with the next published pack.',
            'steps' => [
                'Coins tab: name the currency, choose the currency of the prices and enter the packs of coins with their prices; the first pack sets the price of a coin that the others are measured by.',
                'Auto-refill, VIP account and Pass tabs: switch the feature on and fill in its numbers; a feature that is off is not offered, but what players already have is kept.',
                'Read the lines under the form: they turn coins into money at the price of a coin of the first pack and show the bonus of each pack.',
                'Save the draft; the bar above the form says what changed. The same numbers are in the parameter studio, and both change the same draft.',
                'Publish a pack on the Publications page. The payment provider is a separate step that the game does not have yet.',
            ],
            'example' => 'A ships game: the currency Doubloon, three packs (50 for 4.99, 220 for 19.99, 600 for 49.99), auto-refill with a stock of 100, the VIP account for 30 days at 150 doubloons with +20% cash and XP, and the pass left off until a payment provider is connected.',
        ],
    ],
];
