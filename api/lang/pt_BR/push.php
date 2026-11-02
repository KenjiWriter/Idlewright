<?php

// The texts of the push messages. The server writes them in the language of the device, so the service worker needs none. `:game` is the name of the game, `:car` the name of the car.
// `digest` is one message for several things that happened while the driver was not told (the quiet hours, the gap between messages).

return [
    'test' => [
        'title' => ':game',
        'body' => 'As notificações estão ativas. Avisaremos quando um carro parar ou chegar.',
    ],
    'car_stopped_fuel' => [
        'title' => ':car ficou sem combustível',
        'body' => 'Está parado e não ganha nada até você abastecer.',
    ],
    'car_stopped_breakdown' => [
        'title' => ':car quebrou',
        'body' => 'Espera por um reparo e não ganha nada até lá.',
    ],
    'car_arrived' => [
        'title' => ':car chegou',
        'body' => 'A viagem terminou.',
    ],
    'job_done' => [
        'title' => 'A oficina terminou',
        'body' => 'Um trabalho da sua oficina está pronto.',
    ],
    'digest' => [
        'title' => ':game',
        'body' => '{1} Uma coisa espera por você.|[2,*] :count coisas esperam por você.',
    ],
];
