<?php

return [
    'entries' => [
        'gigs' => [
            'label' => 'Jak działa formularz kursów',
            'help' => 'Tutaj ustawiasz zlecenia poboczne gry: tablicę miasta, ile płaci godzina kursu na każdym poziomie i szablony, z których losowane są oferty. To, co wpiszesz, zapisuje się jako szkic reguł i trafia do graczy z najbliższą opublikowaną paczką.',
            'steps' => [
                'Zakładka Tablica: włącz kursy, wybierz krok samouczka, po którym się otwierają, i ustaw, jak długo trwa fala i ile ofert ma każda pora dnia.',
                'Zakładka Wypłata: ustaw prędkości, kotwice drabiny i przedziały poziomów. Te liczby decydują, ile warta jest godzina kursu wobec godziny okrążeń.',
                'Zakładki Rodzaje i Szablony ofert: nazwij rodzaje, potem opisz każdy szablon: co musi wieźć auto, jak długa jest trasa, ile płaci i jak jest prawdopodobny o każdej porze dnia.',
                'Naciśnij Policz wypłatę pod formularzem: pokaże, ile płaci każdy szablon na każdym aucie paczki, wobec drabiny i wobec okrążeń, i czy bramka publikacji by to przepuściła.',
                'Zapisz szkic i opublikuj paczkę na stronie Publikacje. Bramka sprawdza te same liczby jeszcze raz.',
            ],
            'example' => 'Gra o statkach: rodzaj Prom, szablon wożący 1 – 6 pasażerów między dwoma portami za 100%, szablon nocny za 125% i szablon hurtowy na 1000 – 1800 kg losowany tylko w dzień.',
        ],
    ],
];
