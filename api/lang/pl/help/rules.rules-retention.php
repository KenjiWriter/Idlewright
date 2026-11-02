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
        '/slots/base' => [
            'label' => 'Auta, które każdy gracz może mieć na drodze',
            'help' => 'Ile aut gracz może mieć aktywnych (jadących) naraz. Pozostałe auta stoją w magazynie w garażu: nie jeżdżą, nie palą paliwa, nic się nie zużywa i nic nie zarabiają, a miejsce w garażu zachowują. Auta, które gracz miał, zanim wprowadzono limit, zostają mu, dopóki nie odstawi któregoś do magazynu.',
            'example' => '2: gracz może mieć dwa auta na drodze; trzecie auto trafia do magazynu, gdy je dostanie.',
            'range' => '1 – 50 (aut).',
            'tip' => 'Symulator ekonomii zakłada ok. 1,7 auta na drodze dla zwykłego gracza, więc 2 nie zmienia tempa graczy, którzy nie płacą. Sprawdź to poleceniem economy:simulate po zmianie.',
        ],
        '/slots/vip' => [
            'label' => 'Więcej aut na drodze z kontem VIP',
            'help' => 'Ile aut więcej gracz z kontem VIP może mieć na drodze, dopóki konto trwa. Auta w tych miejscach przestają zarabiać w chwili końca konta i idą do magazynu; nic nie ginie.',
            'example' => '3: z kontem VIP gracz może mieć pięć aut na drodze (2 + 3), co razem z premią konta daje ok. trzy razy tyle, ile zarabia gracz z dwoma autami.',
            'range' => '0 – 50 (aut).',
            'tip' => 'To największa część wartości konta VIP: więcej aut zarabia więcej niż premia do gotówki i XP. Sprawdź przewagę w symulatorze ekonomii, zanim ją podniesiesz.',
        ],
        '/slots/swap_ms' => [
            'label' => 'Czas wyprowadzenia auta na drogę',
            'help' => 'Ile auto wyjęte z magazynu jest przygotowywane: jedzie od tej chwili, a wcześniej nic nie zarabia. Dzięki temu gracz nie zmienia aut przy każdym deszczu.',
            'example' => '120 s: dwie minuty.',
            'range' => '0 – 3600 s.',
        ],
        '/slots/swap_fee_ppm' => [
            'label' => 'Koszt zamiany (część baku)',
            'help' => 'Ile kosztuje wyprowadzenie auta z magazynu na drogę w miejsce auta, które jest na drodze, jako część zarobku netto jednego baku najlepszego auta gracza (100% to cały bak), w pełnych dolarach i co najmniej jeden. Postawienie auta na wolnym miejscu nic nie kosztuje.',
            'example' => '2%: dla auta, które zarabia $375 na baku, zamiana kosztuje $8.',
            'range' => '0 – 100% (część baku).',
        ],
    ],
];
