<?php

// The texts of the push messages. The server writes them in the language of the device, so the service worker needs none. `:game` is the name of the game, `:car` the name of the car.
// `digest` is one message for several things that happened while the driver was not told (the quiet hours, the gap between messages).

return [
    'test' => [
        'title' => ':game',
        'body' => 'Powiadomienia działają. Damy znać, gdy auto stanie albo dojedzie.',
    ],
    'car_stopped_fuel' => [
        'title' => ':car został bez paliwa',
        'body' => 'Stoi i nic nie zarabia, dopóki nie zatankujesz.',
    ],
    'car_stopped_breakdown' => [
        'title' => ':car się zepsuł',
        'body' => 'Czeka na naprawę i do tej pory nic nie zarabia.',
    ],
    'car_arrived' => [
        'title' => ':car dojechał',
        'body' => 'Jego podróż się skończyła.',
    ],
    'job_done' => [
        'title' => 'Warsztat skończył pracę',
        'body' => 'Zlecenie w Twoim warsztacie jest gotowe.',
    ],
    'digest' => [
        'title' => ':game',
        'body' => '{1} Czeka na Ciebie jedna sprawa.|[2,4] Czekają na Ciebie :count sprawy.|[5,*] Czeka na Ciebie :count spraw.',
    ],
];
