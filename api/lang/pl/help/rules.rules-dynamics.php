<?php

return [
    'entries' => [
        '/dynamics/power_ppm' => [
            'label' => 'Moc na drodze',
            'help' => 'Jaka część mocy na masę auta (jego statystyki przyspieszenia, w kW na tonę) dochodzi do drogi jako przyspieszenie, gdy okrążenie jedzie się po nitce. 60% znaczy, że auto o 100 kW na tonę przyspiesza jak punkt materialny z napędem 60 W na kg.',
            'range' => 'Od 10% do 200% (ze schematu).',
            'tip' => 'Im więcej, tym bardziej o okrążeniu decyduje przyspieszanie i tym krótsze jest każde okrążenie po nitce. Nie rusza okrążenia auta wzorcowego toru, które wyznacza czas bazowy toru, tylko to, o ile szybsze lub wolniejsze od niego są pozostałe auta.',
        ],
        '/dynamics/lateral_g_ppm' => [
            'label' => 'Przyczepność w zakręcie (g)',
            'help' => 'Ile daje przyczepność i prowadzenie równe 100 w bok w zakręcie, w g, pokazane jako procent: 95% to 0,95 g. Zakręt jedzie się z prędkością, przy której przyspieszenie w bok to tyle razy przyczepność i prowadzenie auta podzielone przez 100.',
            'range' => 'Od 30% do 250% (ze schematu).',
            'tip' => 'Zmienia to, ile znaczą zakręty wobec prostych: podnieś, a auta, które hamują zakręty, zyskają na tych, które hamuje moc.',
        ],
        '/dynamics/brake_g_ppm' => [
            'label' => 'Hamowanie (g)',
            'help' => 'Ile daje na hamulcach statystyka hamowania równa 100, w g, pokazane jako procent: 100% to 1 g. Od tego zależy, jak późno auto może hamować przed zakrętem: droga do wyhamowania z prędkości to prędkość do kwadratu przez dwukrotność opóźnienia.',
            'range' => 'Od 30% do 300% (ze schematu).',
            'tip' => 'Podnieś, a każde auto hamuje później, więc o okrążeniu decyduje mniej hamowanie, a bardziej moc i zakręty.',
        ],
        '/dynamics/launch_g_ppm' => [
            'label' => 'Przyczepność przy ruszaniu (g)',
            'help' => 'Ile przyczepność równa 100 daje przyspieszeniu przy ruszaniu i po wyjściu z wolnych zakrętów, w g, pokazane jako procent. Do prędkości, przy której silnik nie daje już więcej, auto przyspiesza tyle razy swoja przyczepność podzielona przez 100; powyżej decyduje moc.',
            'range' => 'Od 30% do 250% (ze schematu).',
            'tip' => 'Podnieś, a mocne auta na słabych oponach albo na słabej nawierzchni tracą mniej przy ruszaniu. Na żwirze i błocie opony mnożą przyczepność, więc granica jest tam niższa.',
        ],
        '/dynamics/agility/min_ppm' => [
            'label' => 'Udział prowadzenia: szeroki łuk',
            'help' => 'Jaka część prędkości w szerokim łuku zależy od prowadzenia, a jaka od przyczepności, pokazana jako procent: 25% to jedna czwarta prowadzenia i trzy czwarte przyczepności. Szeroki łuk to taki o promieniu z pola „Promień szerokiego łuku” lub większym.',
            'range' => 'Od 0% do 100% (ze schematu).',
            'tip' => 'Udział w zakręcie o promieniu pomiędzy obiema wartościami maleje po prostej od tego dla agrafki do tego.',
        ],
        '/dynamics/agility/max_ppm' => [
            'label' => 'Udział prowadzenia: agrafka',
            'help' => 'Jaka część prędkości w agrafce (promień bliski zeru) zależy od prowadzenia, a jaka od przyczepności, pokazana jako procent: 75% to trzy czwarte prowadzenia.',
            'range' => 'Od 0% do 100% (ze schematu).',
            'tip' => 'Ciężkie auto, ze słabym prowadzeniem, traci najwięcej w agrafkach; podnieś, żeby agrafki bardziej liczyły się dla takich aut.',
        ],
        '/dynamics/agility/radius_m' => [
            'label' => 'Promień szerokiego łuku (m)',
            'help' => 'Od tego promienia, w metrach, zakręt jest szerokim łukiem i liczy się w nim tylko najmniejszy udział prowadzenia; poniżej udział rośnie po prostej do największego w agrafce.',
            'range' => 'Od 20 do 2000 m (ze schematu).',
            'tip' => 'Mały promień sprawia, że większość zakrętów to szerokie łuki, w których decyduje przyczepność; duży sprawia, że prowadzenie liczy się w większej ich liczbie.',
        ],
        '/dynamics/top_speed_ppm/asphalt_dry' => [
            'label' => 'Prędkość maksymalna: suchy asfalt',
            'help' => 'Ile zostaje z prędkości maksymalnej auta na nawierzchni: suchy asfalt, pokazane jako procent: 100% nie hamuje jej wcale, mniej hamuje. Jest jedna liczba na każdą nawierzchnię, jaką może zrobić pogoda: suchy asfalt, mokry asfalt, suchy żwir, błoto i śnieg.',
            'range' => 'Od 10% do 100% (ze schematu).',
            'tip' => 'Auto nigdy nie jedzie na nawierzchni szybciej niż jego prędkość maksymalna razy ta liczba. Obniż błoto i śnieg, żeby tor w złą pogodę był miejscem dla wolnych aut.',
        ],
        '/dynamics/top_speed_ppm/asphalt_wet' => [
            'label' => 'Prędkość maksymalna: mokry asfalt',
            'help' => 'Ile zostaje z prędkości maksymalnej auta na nawierzchni: mokry asfalt, pokazane jako procent: 100% nie hamuje jej wcale, mniej hamuje. Jest jedna liczba na każdą nawierzchnię, jaką może zrobić pogoda: suchy asfalt, mokry asfalt, suchy żwir, błoto i śnieg.',
            'range' => 'Od 10% do 100% (ze schematu).',
            'tip' => 'Auto nigdy nie jedzie na nawierzchni szybciej niż jego prędkość maksymalna razy ta liczba. Obniż błoto i śnieg, żeby tor w złą pogodę był miejscem dla wolnych aut.',
        ],
        '/dynamics/top_speed_ppm/gravel_dry' => [
            'label' => 'Prędkość maksymalna: suchy żwir',
            'help' => 'Ile zostaje z prędkości maksymalnej auta na nawierzchni: suchy żwir, pokazane jako procent: 100% nie hamuje jej wcale, mniej hamuje. Jest jedna liczba na każdą nawierzchnię, jaką może zrobić pogoda: suchy asfalt, mokry asfalt, suchy żwir, błoto i śnieg.',
            'range' => 'Od 10% do 100% (ze schematu).',
            'tip' => 'Auto nigdy nie jedzie na nawierzchni szybciej niż jego prędkość maksymalna razy ta liczba. Obniż błoto i śnieg, żeby tor w złą pogodę był miejscem dla wolnych aut.',
        ],
        '/dynamics/top_speed_ppm/mud' => [
            'label' => 'Prędkość maksymalna: błoto',
            'help' => 'Ile zostaje z prędkości maksymalnej auta na nawierzchni: błoto, pokazane jako procent: 100% nie hamuje jej wcale, mniej hamuje. Jest jedna liczba na każdą nawierzchnię, jaką może zrobić pogoda: suchy asfalt, mokry asfalt, suchy żwir, błoto i śnieg.',
            'range' => 'Od 10% do 100% (ze schematu).',
            'tip' => 'Auto nigdy nie jedzie na nawierzchni szybciej niż jego prędkość maksymalna razy ta liczba. Obniż błoto i śnieg, żeby tor w złą pogodę był miejscem dla wolnych aut.',
        ],
        '/dynamics/top_speed_ppm/snow' => [
            'label' => 'Prędkość maksymalna: śnieg',
            'help' => 'Ile zostaje z prędkości maksymalnej auta na nawierzchni: śnieg, pokazane jako procent: 100% nie hamuje jej wcale, mniej hamuje. Jest jedna liczba na każdą nawierzchnię, jaką może zrobić pogoda: suchy asfalt, mokry asfalt, suchy żwir, błoto i śnieg.',
            'range' => 'Od 10% do 100% (ze schematu).',
            'tip' => 'Auto nigdy nie jedzie na nawierzchni szybciej niż jego prędkość maksymalna razy ta liczba. Obniż błoto i śnieg, żeby tor w złą pogodę był miejscem dla wolnych aut.',
        ],
        '/dynamics/rough/speed_kmh' => [
            'label' => 'Prędkość po nierównym odcinku (km/h)',
            'help' => 'Prędkość, w km/h, z jaką auto mające cały prześwit, jakiego wymaga nierówny odcinek, po nim jedzie. Żadne auto nie jedzie szybciej po odcinku oznaczonym jako nierówny, choćby było najlepsze; auto z mniejszym prześwitem jedzie wolniej.',
            'range' => 'Od 20 do 400 km/h (ze schematu).',
            'tip' => 'Nierówność odcinka ustawia się przy odcinku nitki toru, na skali statystyki prześwitu.',
        ],
        '/dynamics/rough/exponent_ppm' => [
            'label' => 'Zwalnianie na nierównym odcinku',
            'help' => 'Jak szybko zwalnia auto ze zbyt małym prześwitem na nierównym odcinku, pokazane jako procent wykładnika: jego prędkość po odcinku to prędkość z góry razy (jego prześwit przez to, czego wymaga odcinek) do tej potęgi. 50% to pierwiastek: auto z ćwiartką prześwitu jedzie o połowę wolniej.',
            'range' => 'Od 5% do 300% (ze schematu).',
            'tip' => 'Podnieś, żeby mocniej karać niskie auta, obniż, żeby przepuścić je prawie tak szybko jak wysokie.',
        ],
    ],
];
