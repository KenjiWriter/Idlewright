<?php

return [
    'entries' => [
        'reason' => [
            'label' => 'Motif du refus',
            'help' => 'Pourquoi le plan ne convient pas ; l’assistant le lit dans plan_status et peut soumettre une version corrigée.',
            'example' => 'Hausse trop forte des prix des pièces de niveau 2 ; restons sur les seuils d’XP.',
        ],
        'note' => [
            'label' => 'Remarque pour l’assistant',
            'help' => 'Une remarque que l’assistant lit dans plan_status ; le plan reste dans le même état.',
            'example' => 'Calcule aussi un joueur qui revient toutes les 30 minutes.',
        ],
    ],
];
