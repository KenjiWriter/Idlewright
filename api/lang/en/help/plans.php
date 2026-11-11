<?php

return [
    'entries' => [
        'reason' => [
            'label' => 'Reason for rejecting',
            'help' => 'Why the plan does not pass; the assistant reads it in plan_status and may submit a corrected version.',
            'example' => 'Too big a jump in the prices of tier 2 parts; keep to the XP thresholds.',
        ],
        'note' => [
            'label' => 'Note for the assistant',
            'help' => 'A note the assistant reads in plan_status; the plan stays as it is.',
            'example' => 'Also count a player who comes back every 30 minutes.',
        ],
    ],
];
