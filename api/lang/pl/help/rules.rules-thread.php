<?php

return [
    'entries' => [
        '/tracks/*/thread/return_ms' => [
            'label' => 'Powrót na start przejazdu (s)',
            'help' => 'Ile dodaje do każdego okrążenia toru jeżdżonego jako przejazd (dragster, sprint) droga powrotna na start, w sekundach, bez względu na auto. Pętla nie ma powrotu.',
            'range' => 'Od 0 do 600 s (ze schematu).',
            'tip' => 'Pozwala doprowadzić przejazd do okrążenia, jakie chcesz, bez zmiany odcinków; im dłuższy, tym mniej auta różnią się czasami. Ma znaczenie tylko, gdy okrążenie jedzie się po nitce.',
        ],
        '/tracks/*/thread/sections/*/length_m' => [
            'label' => 'Prosta: długość (m)',
            'help' => 'Jak długa jest prosta nitki toru, w metrach. Długość toru to suma odcinków, każdy zaokrąglony do metra, a zakręt liczy się jako jego łuk.',
            'range' => 'Od 5 do 5000 m (ze schematu).',
            'tip' => 'Zmień długość prostej, a długość toru musi się zmienić razem z nią: walidator mówi, gdy różnią się o więcej niż metr. Strona nitki robi to za Ciebie.',
        ],
        '/tracks/*/thread/sections/*/radius_m' => [
            'label' => 'Zakręt: promień (m)',
            'help' => 'Promień zakrętu nitki toru, w metrach. Agrafka ma około 20 m, ciasny zakręt 35, szeroki łuk ponad 100. Prędkość w nim wynika z przyczepności i prowadzenia auta.',
            'range' => 'Od 8 do 1000 m (ze schematu).',
            'tip' => 'Prędkość w zakręcie to pierwiastek z przyspieszenia w bok razy promień, więc zakręt dwa razy szerszy pokonuje się o ok. 40% szybciej.',
        ],
        '/tracks/*/thread/sections/*/angle_deg' => [
            'label' => 'Zakręt: kąt (°)',
            'help' => 'O ile zakręt nitki skręca auto, w stopniach: 90 to kąt prosty, 180 agrafka.',
            'range' => 'Od 5 do 360° (ze schematu).',
            'tip' => 'Zakręty pętli sumują się do około 360° (lewe liczą się przeciw prawym); walidator ostrzega, gdy tak nie jest, bo linia nie zamknęłaby się.',
        ],
        '/tracks/*/thread/sections/*/rough' => [
            'label' => 'Odcinek: nierówność',
            'help' => 'Jak nierówny jest odcinek nitki, na skali statystyki prześwitu: auto z co najmniej takim prześwitem jedzie po nim z prędkością po nierównym, z mniejszym wolniej. Gładkie odcinki jej nie mają.',
            'range' => 'Od 0 do 100 (ze schematu).',
            'tip' => 'Prędkość po nierównym i to, jak szybko zwalnia niskie auto, są w studiu („Prędkość po nierównym odcinku”, „Zwalnianie na nierównym odcinku”).',
        ],
    ],
];
