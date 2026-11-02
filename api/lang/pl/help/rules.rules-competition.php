<?php

return [
    'entries' => [
        '/competition/pi/reference_pi' => [
            'label' => 'PI auta odniesienia',
            'help' => 'Ile PI jest warte auto odniesienia (seryjne, bez części). To skala, na której stoją progi klas: zmiana tej liczby przesuwa PI wszystkich aut o ten sam procent, więc razem z nią trzeba przesunąć progi klas.',
            'example' => '300: seryjny Rdzak GT-4 ma PI 300, auto o 50% lepszych statystykach ma 450.',
            'range' => '1 – 100 000 (PI).',
        ],
        '/competition/pi/stat_cap_ppm' => [
            'label' => 'Sufit jednej statystyki w PI (% auta odniesienia)',
            'help' => 'Ile najwyżej liczy się jedna statystyka auta w PI, jako wielokrotność statystyki auta odniesienia (100% to tyle samo). Sufit nie pozwala, żeby jedna ogromna liczba kupiła klasę.',
            'example' => '400%: przyspieszenie cztery razy większe niż w aucie odniesienia liczy się jak cztery razy, nie więcej.',
            'range' => '100% – 10 000%.',
        ],
        '/competition/pi/weights_ppm/*' => [
            'label' => 'Waga statystyki w PI',
            'help' => 'Jak wiele liczy się ta statystyka w PI auta (waga w sumie 100%). Domyślnie wagi są średnią z tego, co wszystkie tory wymagają od auta, więc statystyki, na których tory polegają najbardziej (przyczepność, przyspieszenie), liczą się najmocniej. Wagi muszą dawać w sumie 100% i dotyczą statystyk modelu gry.',
            'example' => '23%: przyczepność stanowi prawie jedną czwartą PI.',
            'range' => '0% – 100%; razem 100%.',
        ],
        '/competition/pi/class_max_pi/*' => [
            'label' => 'Górny próg klasy (PI)',
            'help' => 'Najwyższe PI, jakie ma jeszcze auto tej klasy. Auto jest w pierwszej klasie, której próg ma nie wyższy od jego PI; auta powyżej ostatniego progu są w klasie najwyższej. Progi muszą rosnąć od najsłabszej klasy. Turniej klasy przyjmuje tylko auta tej klasy, więc dobry tuning daje przewagę w klasie, a lepsze auto otwiera następną.',
            'example' => '399: auto z PI 399 jest jeszcze w klasie C, a z PI 400 już w klasie B.',
            'range' => '1 – 1 000 000 (PI); rosnąco.',
        ],
        '/competition/rating/start' => [
            'label' => 'Respekt na starcie',
            'help' => 'Respekt nowego kierowcy oraz wartość, do której sezon ściąga rating przy miękkim resecie.',
            'example' => '1000: nowy kierowca zaczyna w dywizji Uliczny.',
            'range' => '1 – 100 000.',
        ],
        '/competition/rating/minimum' => [
            'label' => 'Najniższy Respekt',
            'help' => 'Poniżej tej liczby Respekt kierowcy nie spada, choćby przegrywał wszystko. Chroni przed spadaniem bez końca.',
            'example' => '600: kierowca, który przegrywa bez końca, zatrzymuje się na 600.',
            'range' => '0 – 100 000; nie więcej niż Respekt na starcie.',
        ],
        '/competition/rating/k_new' => [
            'label' => 'K dla nowego kierowcy',
            'help' => 'Jak szybko rusza się Respekt kierowcy, który rozegrał mniej meczów rankingowych niż mówi następna liczba. Przy równym rywalu wygrana daje połowę tej liczby, porażka tyle samo zabiera. Duże K szybko ustawia nowego gracza na jego miejscu.',
            'example' => '40: wygrana z równym rywalem to +20, porażka −20.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/new_matches' => [
            'label' => 'Ile meczów kierowca jest nowy',
            'help' => 'Przez tyle meczów rankingowych kierowca ma duże K. Potem Respekt rusza się wolniej.',
            'example' => '20: pierwsze 20 meczów ustawia gracza.',
            'range' => '0 – 1000 meczów.',
        ],
        '/competition/rating/k_established' => [
            'label' => 'K dla kierowcy z ugruntowaną pozycją',
            'help' => 'Jak szybko rusza się Respekt kierowcy, który nie jest już nowy i jest poniżej szczytu.',
            'example' => '24: wygrana z równym rywalem to +12, porażka −12.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/k_top' => [
            'label' => 'K dla kierowcy na szczycie',
            'help' => 'Jak szybko rusza się Respekt kierowcy na szczycie (od progu szczytu). Małe K sprawia, że szczyt jest stabilny.',
            'example' => '16: wygrana z równym rywalem to +8, porażka −8.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/top_from' => [
            'label' => 'Respekt od którego kierowca jest na szczycie',
            'help' => 'Od tego Respektu kierowca ma najmniejsze K. To osobna liczba od progu dywizji Elita, choć domyślnie obie są takie same.',
            'example' => '1700.',
            'range' => '1 – 100 000.',
        ],
        '/competition/rating/club_from' => [
            'label' => 'Początek dywizji Klubowy',
            'help' => 'Od tego Respektu kierowca jest w dywizji Klubowy (poniżej: Uliczny). Dywizje dobierają przeciwników w turniejach.',
            'example' => '1100.',
            'range' => '1 – 100 000; poniżej progu dywizji regionalnej.',
        ],
        '/competition/rating/regional_from' => [
            'label' => 'Początek dywizji Regionalny',
            'help' => 'Od tego Respektu kierowca jest w dywizji Regionalny.',
            'example' => '1400.',
            'range' => '1 – 100 000; między klubową a elitą.',
        ],
        '/competition/rating/elite_from' => [
            'label' => 'Początek dywizji Elita',
            'help' => 'Od tego Respektu kierowca jest w dywizji Elita, najwyższej.',
            'example' => '1700.',
            'range' => '1 – 100 000; powyżej progu dywizji regionalnej.',
        ],
        '/competition/rating/season_keep_ppm' => [
            'label' => 'Ile Respektu zostaje po sezonie (%)',
            'help' => 'Jaka część Respektu zostaje po miękkim resecie na początku sezonu; reszta wraca do wartości startowej. 100% nie zmienia niczego, 0% ustawia wszystkich na start.',
            'example' => '75%: kierowca z 1400 zaczyna sezon z 1300, kierowca z 600 z 700.',
            'range' => '0% – 100%.',
        ],
        '/competition/match/luck_sigma_ppm' => [
            'label' => 'Szerokość losu biegu (% czasu okrążenia)',
            'help' => 'O ile procent czasu okrążenia może zmienić się czas biegu z powodu losu: około dwa biegi na trzy mieszczą się w tej szerokości, a żaden nie wychodzi poza trzy i jedną trzecią razy tyle. Większy los sprawia, że słabsze auto częściej wygrywa; zero to brak losu. Jest losowany z ziarna, więc wynik meczu da się powtórzyć.',
            'example' => '2%: auto o 1% szybsze wygrywa pojedynczy bieg w 64%, a mecz do trzech biegów w 70%.',
            'range' => '0% – 10%.',
        ],
        '/competition/match/runs' => [
            'label' => 'Liczba biegów w meczu',
            'help' => 'Z ilu biegów składa się mecz; wygrywa strona, która wygra ich najwięcej, więc liczba musi być nieparzysta. Każdy bieg jest na innym torze (z nawierzchniami do wyboru), więc więcej biegów premiuje auto dobrane do wielu torów i zmniejsza wpływ losu.',
            'example' => '3: najlepszy z trzech biegów.',
            'range' => '1 – 9, nieparzysta.',
        ],
    ],
];
