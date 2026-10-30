<?php

return [
    'entries' => [
        '/missions/reset_hour' => [
            'label' => 'Godzina początku dnia misji',
            'help' => 'O której godzinie (w strefie czasowej gry) zaczyna się dzień misji, a z nim nowy zestaw dzienny. Tydzień zaczyna się w poniedziałek o tej godzinie, a miesiąc pierwszego dnia o tej godzinie. O tej porze kończy się też poprzedni okres: to, co gracz ukończył i nie odebrał, serwer odbiera za niego, a reszta przepada.',
            'example' => '4: gracz, który gra wieczorem i po północy, ma jeden dzień misji, a nowy zestaw dostaje o 4:00 rano.',
            'range' => '0 – 23 (godzina).',
        ],
        '/missions/periods/daily/bonus_coins' => [
            'label' => 'Premia za komplet: dzień (monety)',
            'help' => 'Ile monet premium (RideCoin) dostaje gracz za wykonanie wszystkich misji dziennego zestawu. To jedyne darmowe monety, jakie dają misje; nagrody za pojedyncze misje nie zawierają monet. Ta liczba rządzi więc całym darmowym dopływem monet z tego okresu, a schemat nie pozwala ustawić więcej niż 5.',
            'example' => '2 monet: trzy okresy zaliczane za każdym razem dają razem 2, 8 i 25 monet, czyli ok. 119 w miesiącu; VIP kosztuje 150 monet, a paczka 50 monet 4,99 zł.',
            'range' => '0 – 5 monet.',
            'tip' => 'Obniżenie premii gracze odczują jak utratę nagrody, więc zaczynaj nisko: podniesienie jej później jest proste.',
        ],
        '/missions/periods/weekly/bonus_coins' => [
            'label' => 'Premia za komplet: tydzień (monety)',
            'help' => 'Ile monet premium (RideCoin) dostaje gracz za wykonanie wszystkich misji tygodniowego zestawu. To jedyne darmowe monety, jakie dają misje; nagrody za pojedyncze misje nie zawierają monet. Ta liczba rządzi więc całym darmowym dopływem monet z tego okresu, a schemat nie pozwala ustawić więcej niż 20.',
            'example' => '8 monet: trzy okresy zaliczane za każdym razem dają razem 2, 8 i 25 monet, czyli ok. 119 w miesiącu; VIP kosztuje 150 monet, a paczka 50 monet 4,99 zł.',
            'range' => '0 – 20 monet.',
            'tip' => 'Obniżenie premii gracze odczują jak utratę nagrody, więc zaczynaj nisko: podniesienie jej później jest proste.',
        ],
        '/missions/periods/monthly/bonus_coins' => [
            'label' => 'Premia za komplet: miesiąc (monety)',
            'help' => 'Ile monet premium (RideCoin) dostaje gracz za wykonanie wszystkich misji miesięcznego zestawu. To jedyne darmowe monety, jakie dają misje; nagrody za pojedyncze misje nie zawierają monet. Ta liczba rządzi więc całym darmowym dopływem monet z tego okresu, a schemat nie pozwala ustawić więcej niż 60.',
            'example' => '25 monet: trzy okresy zaliczane za każdym razem dają razem 2, 8 i 25 monet, czyli ok. 119 w miesiącu; VIP kosztuje 150 monet, a paczka 50 monet 4,99 zł.',
            'range' => '0 – 60 monet.',
            'tip' => 'Obniżenie premii gracze odczują jak utratę nagrody, więc zaczynaj nisko: podniesienie jej później jest proste.',
        ],
        '/missions/periods/daily/cash_ppm' => [
            'label' => 'Gotówka za misję: dzień (% wartości)',
            'help' => 'Jaką część wartości misji dziennego zestawu płaci się w gotówce (1 000 000 to całość). Wartość misji to praca, której chce, w bakach najlepszego auta gracza: okrążenia, zarobek, paliwo, skrzynki, ulepszenia i reszta mają w dokumencie misji swoją wagę (effort), więc misja, która wymaga dużo, płaci dużo. Gotówka to tyle baków zarobku po paliwie. To dodatek do zarobku aut i nie liczy się w ekonomii gry: bramki i symulator go nie widzą.',
            'example' => '10%: misja warta 4 baki auta, które na baku zarabia 375 $, płaci ok. 150 $.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/weekly/cash_ppm' => [
            'label' => 'Gotówka za misję: tydzień (% wartości)',
            'help' => 'Jaką część wartości misji tygodniowego zestawu płaci się w gotówce (1 000 000 to całość). Wartość misji to praca, której chce, w bakach najlepszego auta gracza: okrążenia, zarobek, paliwo, skrzynki, ulepszenia i reszta mają w dokumencie misji swoją wagę (effort), więc misja, która wymaga dużo, płaci dużo. Gotówka to tyle baków zarobku po paliwie. To dodatek do zarobku aut i nie liczy się w ekonomii gry: bramki i symulator go nie widzą.',
            'example' => '10%: misja warta 4 baki auta, które na baku zarabia 375 $, płaci ok. 150 $.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/monthly/cash_ppm' => [
            'label' => 'Gotówka za misję: miesiąc (% wartości)',
            'help' => 'Jaką część wartości misji miesięcznego zestawu płaci się w gotówce (1 000 000 to całość). Wartość misji to praca, której chce, w bakach najlepszego auta gracza: okrążenia, zarobek, paliwo, skrzynki, ulepszenia i reszta mają w dokumencie misji swoją wagę (effort), więc misja, która wymaga dużo, płaci dużo. Gotówka to tyle baków zarobku po paliwie. To dodatek do zarobku aut i nie liczy się w ekonomii gry: bramki i symulator go nie widzą.',
            'example' => '10%: misja warta 4 baki auta, które na baku zarabia 375 $, płaci ok. 150 $.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/daily/xp_ppm' => [
            'label' => 'XP za misję: dzień (% wartości)',
            'help' => 'Jaką część wartości misji dziennego zestawu płaci się w XP gracza (1 000 000 to całość): tyle baków XP najlepszego auta. Poziom gracza otwiera tory, miasta i części, a samouczek mówi, że poziomy mają być pracą okrążeń, więc trzymaj tę liczbę małą: każdy punkt przesuwa tempo całej gry.',
            'example' => '6%: misja warta 4 baki auta, którego bak daje 600 XP, płaci ok. 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/weekly/xp_ppm' => [
            'label' => 'XP za misję: tydzień (% wartości)',
            'help' => 'Jaką część wartości misji tygodniowego zestawu płaci się w XP gracza (1 000 000 to całość): tyle baków XP najlepszego auta. Poziom gracza otwiera tory, miasta i części, a samouczek mówi, że poziomy mają być pracą okrążeń, więc trzymaj tę liczbę małą: każdy punkt przesuwa tempo całej gry.',
            'example' => '6%: misja warta 4 baki auta, którego bak daje 600 XP, płaci ok. 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/monthly/xp_ppm' => [
            'label' => 'XP za misję: miesiąc (% wartości)',
            'help' => 'Jaką część wartości misji miesięcznego zestawu płaci się w XP gracza (1 000 000 to całość): tyle baków XP najlepszego auta. Poziom gracza otwiera tory, miasta i części, a samouczek mówi, że poziomy mają być pracą okrążeń, więc trzymaj tę liczbę małą: każdy punkt przesuwa tempo całej gry.',
            'example' => '6%: misja warta 4 baki auta, którego bak daje 600 XP, płaci ok. 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/main/cash_ppm' => [
            'label' => 'Gotówka za misję główną (% wartości)',
            'help' => 'Jaką część wartości misji głównej płaci się w gotówce, liczoną w chwili jej otwarcia (1 000 000 to całość). Cele całożyciowe (okrążenia, zarobek, paliwo) przelicza się na baki najlepszego auta gracza w tej chwili, więc lepsze auto nie robi z nich darmowej nagrody; reszta (poziom, auta, garaże, części) ma wagę w dokumencie misji (effort). To nagrody jednorazowe.',
            'example' => '15%: „Przejedź 250 okrążeń” to ok. 8 baków auta, które na baku zarabia 375 $, więc ok. 469 $.',
            'range' => '0 – 1000%.',
        ],
        '/missions/main/xp_ppm' => [
            'label' => 'XP za misję główną (% wartości)',
            'help' => 'Jaką część wartości misji głównej płaci się w XP gracza, liczoną w chwili jej otwarcia (1 000 000 to całość). Poziom gracza jest bramką gry, więc trzymaj tę liczbę małą; XP auta daje się osobno (car_xp_laps misji).',
            'example' => '9%: „Przejedź 250 okrążeń” to ok. 8 baków auta, którego bak daje 600 XP, więc ok. 450 XP.',
            'range' => '0 – 1000%.',
        ],
    ],
];
