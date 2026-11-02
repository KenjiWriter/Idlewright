<?php

return [
    'entries' => [
        '/tuning/slots' => [
            'label' => 'Miejsca na bonusy',
            'help' => 'Ile miejsc na bonusy ma część, o ile jej model nie mówi inaczej. Gracz wypełnia je i zmienia w warsztacie.',
            'example' => '3: każda część ma trzy miejsca, na początku puste.',
        ],
        '/tuning/unlock_level' => [
            'label' => 'Poziom, od którego warsztat dostraja',
            'help' => 'Od jakiego poziomu gracza warsztat dostraja części, czyli ustawia na nich bonusy.',
            'example' => '3: od trzeciego poziomu.',
        ],
        '/tuning/cost_random_ppm' => [
            'label' => 'Cena próby z losowym bonusem',
            'help' => 'Jaką częścią ceny modelu części płaci się za jedną próbę, gdy bonus losuje serwer. 30 000 to 3%.',
            'example' => '30 000: część za 570 $ kosztuje 17,10 $ za próbę.',
        ],
        '/tuning/cost_pick_ppm' => [
            'label' => 'Cena próby z wybranym bonusem',
            'help' => 'Jaką częścią ceny modelu płaci się za próbę, gdy gracz sam wybiera bonus (drożej niż losowy). 100 000 to 10%.',
            'example' => '100 000: część za 570 $ kosztuje 57 $ za próbę.',
        ],
        '/tuning/cost_min_cents' => [
            'label' => 'Najmniejsza cena próby',
            'help' => 'Tyle najmniej kosztuje próba, także dla tanich części, w centach.',
            'example' => '500: nigdy mniej niż 5 $.',
        ],
        '/tuning/time_ppm_of_mount' => [
            'label' => 'Czas próby',
            'help' => 'Ile trwa próba w stosunku do czasu założenia tej części. 500 000 to połowa.',
            'example' => '500 000: założenie trwa 2 minuty, próba 1 minutę.',
        ],
        '/tuning/floor_sigma_ppm' => [
            'label' => 'Dolna granica nowego bonusu',
            'help' => 'Pierwsza wartość bonusu, który dopiero wchodzi na miejsce, nie jest niższa niż tyle poniżej średniej, w milionowych sigmy. Dzięki temu zmiana bonusu pod inną trasę nie jest karą.',
            'example' => '1 000 000: nie niżej niż jedna sigma pod średnią.',
        ],
        '/tuning/pity_step_ppm' => [
            'label' => 'Krok ochrony przed pechem',
            'help' => 'O ile (w milionowych sigmy) rośnie średnia po każdej próbie na tym miejscu, która niczego nie poprawiła. 0 wyłącza ochronę.',
            'example' => '0: bez ochrony; 50 000: po 10 próbach bez poprawy średnia rośnie o pół sigmy.',
        ],
        '/tuning/pity_max_ppm' => [
            'label' => 'Największy skok ochrony',
            'help' => 'O ile najwyżej ochrona przed pechem przesuwa średnią, w milionowych sigmy.',
            'example' => '1 000 000: najwyżej o jedną sigmę.',
        ],
        '/tuning/car_cap_ppm' => [
            'label' => 'Sufit bonusów na aucie',
            'help' => 'Ile najwyżej bonusy wszystkich części na aucie mogą dodać do jednej statystyki w jednym kontekście, w ppm.',
            'example' => '250 000: nie więcej niż +25%.',
        ],
        '/tuning/max_ppm_plain' => [
            'label' => 'Największy bonus bez kontekstu',
            'help' => 'Najwięcej, ile może dawać w najlepszym przypadku bonus, który działa na każdym torze. Paczka z bonusem, który może dać więcej, jest odrzucana.',
            'example' => '80 000: najwyżej +8%.',
        ],
        '/tuning/max_ppm_context' => [
            'label' => 'Największy bonus z kontekstem',
            'help' => 'Najwięcej, ile może dawać w najlepszym przypadku bonus, który działa tylko na pewnym rodzaju toru, nawierzchni albo w pewnej pogodzie. Wąski kontekst pozwala na więcej.',
            'example' => '120 000: najwyżej +12%.',
        ],
        '/tuning/definitions/*/weight' => [
            'label' => 'Waga bonusu',
            'help' => 'Jak często bonus jest losowany spośród tych, które pasują do slotu części, gdy gracz nie wybiera sam.',
            'example' => '3 przy innych o wadze 3 i 2: wypada w 3 przypadkach na 8.',
        ],
        '/tuning/definitions/*/from_level' => [
            'label' => 'Poziom, od którego bonus jest w ofercie',
            'help' => 'Od jakiego poziomu gracza bonus pojawia się w warsztacie. Poniżej tego poziomu nie da się go ani wylosować, ani wybrać.',
            'example' => '8: bonus na śnieg dla graczy od poziomu 8.',
        ],
        '/tuning/definitions/*/mean_ppm' => [
            'label' => 'Najczęstsza wartość bonusu',
            'help' => 'Środek rozkładu: wartość, wokół której losuje się bonus. 50 000 to 5%.',
            'example' => '50 000: najczęściej wypada około 5%.',
        ],
        '/tuning/definitions/*/sigma_ppm' => [
            'label' => 'Rozrzut bonusu (sigma)',
            'help' => 'Jak szeroko wartości rozkładają się wokół średniej: im większa sigma, tym częściej wypadają wartości dalekie od średniej. Wartość o dwie sigmy ponad średnią trafia się raz na 70 prób.',
            'example' => '23 000: rozrzut 2,3%, więc 10% wypada raz na 70 prób.',
        ],
        '/tuning/definitions/*/min_ppm' => [
            'label' => 'Najmniejsza wartość bonusu',
            'help' => 'Najmniej, ile może dać bonus. Wylosowana wartość niższa niż ta jest podnoszona do niej.',
            'example' => '10 000: nie mniej niż 1%.',
        ],
        '/tuning/definitions/*/max_ppm' => [
            'label' => 'Największa wartość bonusu',
            'help' => 'Najwięcej, ile może dać bonus. Wartość tuż pod tą granicą jest bardzo rzadka. Nie może przekraczać limitu bonusu z kontekstem albo bez niego z ustawień ogólnych.',
            'example' => '120 000: najwyżej 12%, a trafić je można raz na ponad tysiąc prób.',
        ],
    ],
];
