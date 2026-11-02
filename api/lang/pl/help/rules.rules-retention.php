<?php

return [
    'entries' => [
        '/streaks/goal/daily_done' => [
            'label' => 'Misje dnia, które dają cel dzienny',
            'help' => 'Ile misji zestawu dziennego gracz musi wykonać (wykonane, niekoniecznie odebrane), żeby dzień liczył się do serii. Zestaw z mniejszą liczbą misji wymaga wszystkich. Seria liczy się z zestawu dziennego, więc nie ma dodatkowych zadań.',
            'example' => '3: trzy z sześciu misji dnia, więc gracz, który zagląda raz dziennie, też utrzyma serię.',
            'range' => '1 – 20 (misji).',
            'tip' => 'Niższa liczba chroni serię graczy, którzy zaglądają rzadko; wyższa wymaga prawdziwej gry każdego dnia.',
        ],
        '/streaks/shield/every_days' => [
            'label' => 'Dni serii na jedną tarczę',
            'help' => 'Gracz zdobywa tarczę, gdy seria osiągnie wielokrotność tylu dni. Tarcza ratuje serię przed jednym opuszczonym dniem: seria stoi w miejscu, a ten dzień nic nie płaci.',
            'example' => '7: tarcza na 7., 14. i 21. dzień serii.',
            'range' => '1 – 365 (dni).',
        ],
        '/streaks/shield/cap' => [
            'label' => 'Najwięcej tarcz, jakie gracz trzyma',
            'help' => 'Ile tarcz gracz może mieć naraz. Tarcza zdobyta ponad tę liczbę przepada. Przy 0 gra nie ma tarcz, a opuszczony dzień kończy serię.',
            'example' => '2: po dwóch opuszczonych dniach z rzędu seria zostaje uratowana dwa razy, a trzeci ją kończy.',
            'range' => '0 – 10 (tarcz).',
            'tip' => 'Tarcze zdobywa się grą i nie można ich kupić, więc duża liczba jest łagodna, a nie sposobem na sprzedaż serii.',
        ],
        '/streaks/cycle/*/cash_ppm' => [
            'label' => 'Gotówka dnia cyklu (część baku)',
            'help' => 'Ile gotówki płaci dzień serii, jako część zarobku netto jednego baku najlepszego auta gracza (100% to cały bak), więc rośnie razem z graczem i nie trzeba jej poprawiać. Każdy wpis cyklu to jeden dzień, a po ostatnim cykl zaczyna się od nowa. To jest ponad zarobkiem aut i nie liczy się do ekonomii gry.',
            'example' => '2%: pierwszy dzień cyklu płaci dwie setne baku; ostatni dzień tygodnia płaci zwykle cztery razy tyle.',
            'range' => '0 – 100% (część baku).',
            'tip' => 'Sprawdź sumę za miesiąc poleceniem streaks:budget: powinna być kilkoma procentami tego, co zarabiają auta.',
        ],
        '/streaks/cycle/*/xp_ppm' => [
            'label' => 'XP dnia cyklu (część baku)',
            'help' => 'Ile XP płaci dzień serii, jako część XP jednego baku najlepszego auta gracza (100% to cały bak). Poziom gracza jest bramką gry, więc trzymaj to nisko.',
            'example' => '1%: dzień cyklu płaci jedną setną XP z baku.',
            'range' => '0 – 100% (część baku).',
        ],
        '/streaks/milestones/*/day' => [
            'label' => 'Długość serii dla kamienia milowego (dni)',
            'help' => 'Długość serii, w dniach, w której kamień milowy płaci to, co daje (skrzynki oraz gotówkę i XP, jeśli je ma), ponad dniem cyklu. Każda długość występuje raz.',
            'example' => '30: seria trzydziestu dni płaci swój kamień milowy trzydziestego dnia, jeden raz.',
            'range' => '2 – 3650 (dni).',
        ],
    ],
];
