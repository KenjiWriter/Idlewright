<?php

return [
    'entries' => [
        'reason' => [
            'label' => 'Powód odrzucenia',
            'help' => 'Dlaczego plan nie przechodzi; asystent przeczyta to w plan_status i może złożyć poprawioną wersję.',
            'example' => 'Za duży skok cen części tieru 2; zostańmy przy progach XP.',
        ],
        'note' => [
            'label' => 'Uwaga dla asystenta',
            'help' => 'Uwaga, którą asystent przeczyta w plan_status; plan zostaje w tym samym stanie.',
            'example' => 'Policz jeszcze gracza, który wraca co 30 minut.',
        ],
    ],
];
