<?php

return [
    'entries' => [
        'variant' => [
            'label' => 'Berechnen auf',
            'help' => 'Das Live-Paket ist das Spiel der Spieler; die Entwürfe sind das, was ein jetzt erstelltes Paket enthielte.',
            'options' => [
                'live' => [
                    'label' => 'Live-Paket',
                    'help' => 'Das Spiel, wie die Spieler es heute haben.',
                ],
                'drafts' => [
                    'label' => 'Entwürfe',
                    'help' => 'Das Spiel mit den gespeicherten Entwürfen, vor einem Paket.',
                ],
            ],
        ],
        'car' => [
            'label' => 'Startauto',
            'help' => 'Das Auto, mit dem der Fahrer beginnt. Leer: das günstigste Startauto des Spiels.',
            'example' => 'rdzak-gt-4',
        ],
        'style' => [
            'label' => 'Spielweise',
            'help' => 'Wie oft der Fahrer im Spiel ist. Wenn nicht, fährt das Auto allein, solange der Treibstoff reicht.',
            'options' => [
                'active' => [
                    'label' => 'Spielt ohne Pause',
                    'help' => 'Der Fahrer ist die ganze Zeit im Spiel: Er tankt und kauft sofort.',
                ],
                'every' => [
                    'label' => 'Kommt alle N Minuten zurück',
                    'help' => 'Der Fahrer kommt alle so viele Minuten zurück und bleibt so viele Minuten.',
                ],
                'A' => [
                    'label' => 'Spieler A',
                    'help' => 'Spieler A des Wirtschaftssimulators: 3 Besuche am Tag.',
                ],
                'B' => [
                    'label' => 'Spieler B',
                    'help' => 'Spieler B des Wirtschaftssimulators: 1 Besuch am Tag.',
                ],
                'C' => [
                    'label' => 'Spieler C',
                    'help' => 'Spieler C des Wirtschaftssimulators: 8 Besuche am Tag.',
                ],
            ],
        ],
        'every_minutes' => [
            'label' => 'Kommt zurück alle (Minuten)',
            'help' => 'Die Minuten vom Beginn eines Besuchs bis zum Beginn des nächsten.',
            'example' => '60',
        ],
        'visit_minutes' => [
            'label' => 'Bleibt (Minuten)',
            'help' => 'Die Dauer eines Besuchs in Minuten; nicht länger als die Zeit zwischen den Besuchen.',
            'example' => '10',
        ],
        'minutes' => [
            'label' => 'Spielminuten',
            'help' => 'Wie lange der Simulator ab dem neuen Konto spielt; höchstens 43.200 Minuten (30 Tage).',
            'example' => '120 sind zwei Stunden, 1440 ein Tag.',
        ],
        'track' => [
            'label' => 'Strecke',
            'help' => 'Der Fahrer fährt diese Strecke, sobald sie offen ist. Leer: immer die beste offene Strecke.',
        ],
        'goal' => [
            'label' => 'Strecke wählen nach',
            'help' => 'Woran der Fahrer die beste Strecke misst.',
            'options' => [
                'profit' => [
                    'label' => 'Gewinn pro Stunde',
                    'help' => 'Die Auszahlung abzüglich Treibstoff, für eine Stunde Fahrt.',
                ],
                'xp' => [
                    'label' => 'XP pro Stunde',
                    'help' => 'Die Erfahrung des Fahrers für eine Stunde Fahrt.',
                ],
            ],
        ],
        'buy' => [
            'label' => 'Käufe',
            'help' => 'Was der Fahrer kauft; er behält immer das Geld für eine volle Tankfüllung.',
            'options' => [
                'none' => [
                    'label' => 'Nichts',
                    'help' => 'Der Fahrer fährt mit dem, was er bekommen hat.',
                ],
                'list' => [
                    'label' => 'Eine Liste, der Reihe nach',
                    'help' => 'Er kauft die Teile und Autos der Liste nacheinander, wenn Geld und Level reichen.',
                ],
                'best' => [
                    'label' => 'Der meiste Gewinn pro Dollar',
                    'help' => 'Er kauft, was das Ergebnis einer Stunde im Verhältnis zum Preis am meisten hebt.',
                ],
            ],
        ],
        'list' => [
            'label' => 'Einkaufsliste',
            'help' => 'Slugs von Teilen und Autos, durch Kommas getrennt, in der Reihenfolge des Kaufs.',
            'example' => 'street-tires-t2, disc-brakes-t2',
        ],
        'step_seconds' => [
            'label' => 'Ein Schritt des Tutorials (s)',
            'help' => 'Wie viele Sekunden Spielzeit ein Schritt des Tutorials dauert. Die echte Zeit messen wir später an den Daten der Spieler.',
            'example' => '60',
        ],
        'tutorial' => [
            'label' => 'Mit Tutorial',
            'help' => 'Der Fahrer durchläuft zuerst das Tutorial und bekommt seine Belohnungen.',
        ],
        'missions' => [
            'label' => 'Mit Missionen',
            'help' => 'Addiert die täglichen Belohnungen der Missionen am Ende jedes Spieltags.',
        ],
        'travel' => [
            'label' => 'Reisen',
            'help' => 'Der Fahrer fährt in eine andere Stadt, in der er eine Garage hat, wenn es sich in der verbleibenden Zeit lohnt.',
        ],
    ],
];
