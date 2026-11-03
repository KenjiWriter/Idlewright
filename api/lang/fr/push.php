<?php

// The texts of the push messages. The server writes them in the language of the device, so the service worker needs none. `:game` is the name of the game, `:car` the name of the car.
// `digest` is one message for several things that happened while the driver was not told (the quiet hours, the gap between messages).

return [
    'test' => [
        'title' => ':game',
        'body' => 'Les notifications sont activées. Nous vous préviendrons quand une voiture s’arrête ou arrive.',
    ],
    'car_stopped_fuel' => [
        'title' => ':car est en panne sèche',
        'body' => 'Elle est à l’arrêt et ne rapporte rien tant que vous n’avez pas fait le plein.',
    ],
    'car_stopped_breakdown' => [
        'title' => ':car est en panne',
        'body' => 'Elle attend une réparation et ne rapporte rien en attendant.',
    ],
    'car_arrived' => [
        'title' => ':car est arrivée',
        'body' => 'Son voyage est terminé.',
    ],
    'job_done' => [
        'title' => 'L’atelier a terminé',
        'body' => 'Un travail de votre atelier est fini.',
    ],
    'streak_risk' => [
        'title' => 'Gardez votre série',
        'body' => 'L’objectif du jour n’est pas encore atteint. Votre série : :days.',
    ],
    'digest' => [
        'title' => ':game',
        'body' => '{1} Une chose vous attend.|[2,*] :count choses vous attendent.',
    ],
];
