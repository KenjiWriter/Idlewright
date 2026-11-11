<?php

return [
    'entries' => [
        'reason' => [
            'label' => 'Grund der Ablehnung',
            'help' => 'Warum der Plan nicht passt; der Assistent liest es in plan_status und kann eine korrigierte Version einreichen.',
            'example' => 'Zu großer Preissprung bei Teilen der Stufe 2; bleiben wir bei den XP-Schwellen.',
        ],
        'note' => [
            'label' => 'Hinweis für den Assistenten',
            'help' => 'Ein Hinweis, den der Assistent in plan_status liest; der Plan bleibt im selben Stand.',
            'example' => 'Berechne auch einen Spieler, der alle 30 Minuten zurückkommt.',
        ],
    ],
];
