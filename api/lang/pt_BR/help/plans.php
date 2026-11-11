<?php

return [
    'entries' => [
        'reason' => [
            'label' => 'Motivo da rejeição',
            'help' => 'Por que o plano não serve; o assistente lê isso em plan_status e pode enviar uma versão corrigida.',
            'example' => 'Aumento grande demais nos preços das peças de nível 2; fiquemos nos limiares de XP.',
        ],
        'note' => [
            'label' => 'Observação para o assistente',
            'help' => 'Uma observação que o assistente lê em plan_status; o plano continua no mesmo estado.',
            'example' => 'Calcule também um jogador que volta a cada 30 minutos.',
        ],
    ],
];
