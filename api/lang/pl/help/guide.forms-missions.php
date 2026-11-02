<?php

return [
    'entries' => [
        'missions' => [
            'label' => 'Jak działa formularz misji',
            'help' => 'Tu ustawiasz misje gry: kiedy zaczynają się zestawy dnia, tygodnia i miesiąca i jaką część wartości swoich misji każdy płaci, misje, spośród których zestawy losują, łańcuchy misji głównych i to, ile jest warta jednostka każdego rodzaju pracy. To, co wpiszesz, zapisuje się razem jako szkic reguł i dokumentu misji i trafia do graczy z następną opublikowaną paczką.',
            'steps' => [
                'Zakładka Nagrody i okresy: włącz misje, wybierz strefę czasową i godzinę początku dnia oraz ustaw, jaką część wartości swoich misji płaci każdy zestaw i misje główne (gotówka i XP w procentach), oraz monety za komplet.',
                'Zakładka Wartość pracy: powiedz, ile jest warta jedna jednostka każdego rodzaju pracy w bakach najlepszego auta; misja płaci część pracy, której żąda, więc misja, która żąda więcej, płaci więcej.',
                'Naciśnij Przelicz budżet: pokaże, ile wychodzą dzień, tydzień i miesiąc w gotówce, XP i monetach dla auta na torze, i czy bramka publikacji by to przepuściła.',
                'Zakładka Zestawy: dla zestawu dziennego, tygodniowego i miesięcznego dodaj sloty i misje, które każdy slot może wylosować, z celami, wagami i mnożnikami.',
                'Zakładka Misje główne: dodaj łańcuchy i ich misje w kolejności otwierania, z celami, tekstami w językach i przedmiotami, które dają.',
                'Zapisz szkic: oba dokumenty zapisują się razem albo żaden, gdy jeden zostanie odrzucony. Paczkę opublikujesz na stronie Publikacje.',
            ],
            'example' => 'Gra o statkach: zestawy płacą 10% wartości w gotówce i 6% w XP oraz 2, 8 i 25 monet, misje główne 15% i 9%; „przepłyń 50 okrążeń” jest warte 50 okrążeń baku o 30 okrążeniach razy 1 bak pracy, czyli ok. 1,7 baku, i w zestawie dziennym płaci 17% zarobku netto baku.',
        ],
    ],
];
