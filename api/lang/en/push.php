<?php

// The texts of the push messages. The server writes them in the language of the device, so the service worker needs none. `:game` is the name of the game, `:car` the name of the car.
// `digest` is one message for several things that happened while the driver was not told (the quiet hours, the gap between messages).

return [
    'test' => [
        'title' => ':game',
        'body' => 'Notifications are on. We will let you know when a car stops or arrives.',
    ],
    'car_stopped_fuel' => [
        'title' => ':car is out of fuel',
        'body' => 'It stands and earns nothing until you fill the tank.',
    ],
    'car_stopped_breakdown' => [
        'title' => ':car has broken down',
        'body' => 'It waits for a repair and earns nothing until then.',
    ],
    'car_arrived' => [
        'title' => ':car has arrived',
        'body' => 'Its journey is over.',
    ],
    'job_done' => [
        'title' => 'The workshop is done',
        'body' => 'A job in your workshop is finished.',
    ],
    'digest' => [
        'title' => ':game',
        'body' => '{1} One thing is waiting for you.|[2,*] :count things are waiting for you.',
    ],
];
