<?php

return [
    'entries' => [
        'variant' => [
            'label' => 'Na czym liczyć',
            'help' => 'Żywa paczka to gra, którą mają gracze; szkice to to, co trafiłoby do paczki zrobionej teraz.',
            'options' => [
                'live' => [
                    'label' => 'Żywa paczka',
                    'help' => 'Gra taka, jaką mają dziś gracze.',
                ],
                'drafts' => [
                    'label' => 'Szkice',
                    'help' => 'Gra z zapisanymi szkicami, jeszcze przed paczką.',
                ],
            ],
        ],
        'car' => [
            'label' => 'Auto na start',
            'help' => 'Auto, którym kierowca zaczyna. Puste: najtańsze auto startowe gry.',
            'example' => 'rdzak-gt-4',
        ],
        'style' => [
            'label' => 'Sposób gry',
            'help' => 'Jak często kierowca jest w grze. Gdy go nie ma, auto jeździ samo, dopóki starczy paliwa.',
            'options' => [
                'active' => [
                    'label' => 'Gra bez przerw',
                    'help' => 'Kierowca jest w grze cały czas: tankuje i kupuje od razu.',
                ],
                'every' => [
                    'label' => 'Wraca co N minut',
                    'help' => 'Kierowca wraca co tyle minut, ile podasz, i zostaje na tyle minut, ile podasz.',
                ],
                'A' => [
                    'label' => 'Gracz A',
                    'help' => 'Gracz A symulatora ekonomii: 3 wizyty dziennie.',
                ],
                'B' => [
                    'label' => 'Gracz B',
                    'help' => 'Gracz B symulatora ekonomii: 1 wizyta dziennie.',
                ],
                'C' => [
                    'label' => 'Gracz C',
                    'help' => 'Gracz C symulatora ekonomii: 8 wizyt dziennie.',
                ],
            ],
        ],
        'every_minutes' => [
            'label' => 'Co ile minut wraca',
            'help' => 'Minuty od początku jednej wizyty do początku następnej.',
            'example' => '60',
        ],
        'visit_minutes' => [
            'label' => 'Ile minut zostaje',
            'help' => 'Długość jednej wizyty w minutach; nie dłużej niż czas między wizytami.',
            'example' => '10',
        ],
        'minutes' => [
            'label' => 'Ile minut grać',
            'help' => 'Jak długo symulator gra, licząc od założenia konta; najwyżej 43 200 minut (30 dni).',
            'example' => '120 to dwie godziny, 1440 to doba.',
        ],
        'track' => [
            'label' => 'Tor',
            'help' => 'Kierowca jeździ tym torem, gdy tylko się otworzy. Puste: zawsze najlepszy otwarty tor.',
        ],
        'goal' => [
            'label' => 'Wybór toru według',
            'help' => 'Czym kierowca mierzy, który tor jest najlepszy.',
            'options' => [
                'profit' => [
                    'label' => 'Zysk na godzinę',
                    'help' => 'Wypłata minus paliwo, na godzinę jazdy.',
                ],
                'xp' => [
                    'label' => 'XP na godzinę',
                    'help' => 'Doświadczenie kierowcy na godzinę jazdy.',
                ],
            ],
        ],
        'buy' => [
            'label' => 'Zakupy',
            'help' => 'Co kierowca kupuje; zawsze zostawia gotówkę na pełny bak.',
            'options' => [
                'none' => [
                    'label' => 'Nic',
                    'help' => 'Kierowca jeździ tym, co dostał.',
                ],
                'list' => [
                    'label' => 'Lista w kolejności',
                    'help' => 'Kupuje części i auta z listy po kolei, gdy starczy gotówki i poziomu.',
                ],
                'best' => [
                    'label' => 'Najwięcej zysku za dolara',
                    'help' => 'Kupuje to, co najbardziej podnosi wynik godziny w stosunku do ceny.',
                ],
            ],
        ],
        'list' => [
            'label' => 'Lista zakupów',
            'help' => 'Slugi części i aut oddzielone przecinkami, w kolejności zakupu.',
            'example' => 'street-tires-t2, disc-brakes-t2',
        ],
        'step_seconds' => [
            'label' => 'Czas kroku samouczka (s)',
            'help' => 'Ile sekund gry zajmuje jeden krok samouczka. Prawdziwy czas zmierzymy później na danych graczy.',
            'example' => '60',
        ],
        'tutorial' => [
            'label' => 'Z samouczkiem',
            'help' => 'Kierowca najpierw przechodzi samouczek i dostaje jego nagrody.',
        ],
        'missions' => [
            'label' => 'Z misjami',
            'help' => 'Dolicza dzienne nagrody misji na koniec każdej doby gry.',
        ],
        'travel' => [
            'label' => 'Podróże',
            'help' => 'Kierowca jedzie do innego miasta, w którym ma garaż, gdy to się opłaca w czasie, który został.',
        ],
    ],
];
