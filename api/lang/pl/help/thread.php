<?php

return [
    'entries' => [
        'track' => [
            'label' => 'Tor',
            'help' => 'Tor, którego nitkę widzisz i zmieniasz. Pozostałe tory zostają, jakie są.',
            'tip' => 'Zapisz przed przejściem do innego toru: zmiany toru, który opuszczasz, nie są zachowywane.',
        ],
        'lap_from' => [
            'label' => 'Czas okrążenia z',
            'help' => 'Skąd bierze się czas okrążenia auta na tym torze: z jego statystyk przez wymagania toru (wzór, jaki mają wszystkie tory do tej pory) albo z okrążenia przejechanego po nitce toru. Przy wzorze nitka tylko rysuje tor.',
            'tip' => 'Zanim przestawisz tor na nitkę, spójrz na tabelę aut pod linią: pokazuje, o ile zmieniłoby się każde okrążenie. Okrążenie auta wzorcowego zostaje czasem bazowym toru tak czy inaczej.',
            'options' => [
                'stats' => ['label' => 'Wzór (statystyki)', 'help' => 'Okrążenie powstaje ze statystyk auta i wymagań toru, jak dotąd. Nitka, jeśli tor ją ma, jest tylko rysunkiem.'],
                'thread' => ['label' => 'Nitka', 'help' => 'Okrążenie jedzie się po odcinkach nitki: auto rusza, przyspiesza na prostych, hamuje przed zakrętami i trzyma nawierzchnię.'],
            ],
        ],
        'mode' => [
            'label' => 'Jak się jedzie',
            'help' => 'Pętla to obwód jeżdżony „lotnie”: auto mija linię z prędkością, z którą ją opuściło. Przejazd jedzie się za każdym razem ze startu stojącego, jak dragster albo sprint.',
            'options' => [
                'circuit' => ['label' => 'Pętla', 'help' => 'Obwód; auto nie zatrzymuje się na linii.'],
                'run' => ['label' => 'Przejazd', 'help' => 'Każde okrążenie zaczyna się ze startu stojącego, a powrót na start trwa ustalony czas.'],
            ],
        ],
        'return_s' => [
            'label' => 'Powrót na start (s)',
            'help' => 'Ile dodaje do każdego okrążenia przejazdu droga powrotna na start, w sekundach, bez względu na auto. Pętla nie ma powrotu.',
            'range' => 'Od 0 do 600 s (ze schematu).',
            'tip' => 'Pozwala doprowadzić dragster do okrążenia, jakie chcesz, bez zmiany odcinków; im dłuższy, tym mniej auta różnią się czasami.',
        ],
        'sections' => [
            'label' => 'Odcinki',
            'help' => 'Nitka jako łańcuch odcinków w kolejności jazdy: proste i zakręty. Kolejność zmieniasz strzałkami; linia obok jest z nich rysowana.',
        ],
        'type' => [
            'label' => 'Rodzaj odcinka',
            'help' => 'Prosta idzie dalej; zakręt skręca auto o kąt na promieniu.',
            'options' => [
                'straight' => ['label' => 'Prosta', 'help' => 'Ma długość.'],
                'corner' => ['label' => 'Zakręt', 'help' => 'Ma promień i kąt.'],
            ],
        ],
        'length' => [
            'label' => 'Długość (m)',
            'help' => 'Jak długa jest prosta, w metrach.',
            'range' => 'Od 5 do 5000 m (ze schematu).',
            'tip' => 'Długość toru to suma odcinków i jest wpisywana przy zapisie.',
        ],
        'radius' => [
            'label' => 'Promień (m)',
            'help' => 'Promień zakrętu, w metrach. Agrafka ma około 20 m, ciasny zakręt 35, szeroki łuk ponad 100. Prędkość w nim wynika z przyczepności i prowadzenia auta.',
            'range' => 'Od 8 do 1000 m (ze schematu).',
        ],
        'angle' => [
            'label' => 'Kąt (°)',
            'help' => 'O ile zakręt skręca auto, w stopniach: 90 to kąt prosty, 180 agrafka.',
            'range' => 'Od 5 do 360° (ze schematu).',
            'tip' => 'Zakręty pętli sumują się do około 360° (lewe liczą się przeciw prawym); inaczej linia na rysunku nie zamyka się.',
        ],
        'turn' => [
            'label' => 'Skręca',
            'help' => 'W którą stronę skręca zakręt. Tylko rysuje linię: okrążenie od tego nie zależy.',
            'options' => [
                'left' => ['label' => 'W lewo', 'help' => 'Linia zakręca w lewo.'],
                'right' => ['label' => 'W prawo', 'help' => 'Linia zakręca w prawo.'],
            ],
        ],
        'surface' => [
            'label' => 'Nawierzchnia',
            'help' => 'Z czego jest odcinek, gdy nie jest to to, z czego jest tor: odcinek żwiru na odcinku specjalnym rajdu. Puste znaczy nawierzchnia toru. Pogoda robi z niej mokrą, błotnistą albo śnieżną, a opony auta trzymają się każdej z nich inaczej.',
            'options' => [
                'asphalt' => ['label' => 'Asfalt', 'help' => 'Suchy asfalt; deszcz robi z niego mokry asfalt, śnieg go przykrywa.'],
                'gravel' => ['label' => 'Żwir', 'help' => 'Luźny żwir; deszcz robi z niego błoto, śnieg go przykrywa. Trzyma prędkość maksymalną auta i wymaga właściwych opon.'],
            ],
        ],
        'limit' => [
            'label' => 'Limit prędkości (km/h)',
            'help' => 'Prędkość, szybciej niż którą żadne auto nie jedzie na tym odcinku: ulica, pit lane, droga powrotna. Puste znaczy brak limitu.',
            'range' => 'Od 20 do 400 km/h (ze schematu).',
        ],
        'rough' => [
            'label' => 'Nierówność',
            'help' => 'Jak nierówny jest odcinek, na skali statystyki prześwitu: auto z co najmniej takim prześwitem jedzie po nim z prędkością po nierównym, z mniejszym wolniej. Puste albo 0 znaczy gładki.',
            'range' => 'Od 0 do 100 (ze schematu).',
            'tip' => 'Prędkość po nierównym i to, jak szybko zwalnia niskie auto, są w studiu („Prędkość po nierównym odcinku”, „Zwalnianie na nierównym odcinku”).',
        ],
    ],
];
