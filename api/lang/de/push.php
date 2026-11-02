<?php

// The texts of the push messages. The server writes them in the language of the device, so the service worker needs none. `:game` is the name of the game, `:car` the name of the car.
// `digest` is one message for several things that happened while the driver was not told (the quiet hours, the gap between messages).

return [
    'test' => [
        'title' => ':game',
        'body' => 'Benachrichtigungen sind an. Wir sagen Bescheid, wenn ein Auto stehen bleibt oder ankommt.',
    ],
    'car_stopped_fuel' => [
        'title' => ':car hat keinen Sprit mehr',
        'body' => 'Es steht und verdient nichts, bis du tankst.',
    ],
    'car_stopped_breakdown' => [
        'title' => ':car hat eine Panne',
        'body' => 'Es wartet auf eine Reparatur und verdient bis dahin nichts.',
    ],
    'car_arrived' => [
        'title' => ':car ist angekommen',
        'body' => 'Die Reise ist zu Ende.',
    ],
    'job_done' => [
        'title' => 'Die Werkstatt ist fertig',
        'body' => 'Ein Auftrag in deiner Werkstatt ist erledigt.',
    ],
    'digest' => [
        'title' => ':game',
        'body' => '{1} Eine Sache wartet auf dich.|[2,*] :count Dinge warten auf dich.',
    ],
];
