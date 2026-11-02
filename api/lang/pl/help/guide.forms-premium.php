<?php

return [
    'entries' => [
        'premium' => [
            'label' => 'Jak działa formularz płatnych funkcji',
            'help' => 'Tu ustawiasz, co gra sprzedaje za monety premium i za prawdziwe pieniądze: paczki monet, przyspieszenie, automatyczne tankowanie, konto VIP i przepustkę. To, co wpiszesz, zapisuje się jako szkic reguł i trafia do graczy z następną opublikowaną paczką.',
            'steps' => [
                'Zakładka Monety: nazwij walutę, wybierz walutę cen i wpisz paczki monet z cenami; pierwsza paczka ustala cenę monety, którą mierzy się pozostałe.',
                'Zakładki Automatyczne tankowanie, Konto VIP i Przepustka: włącz funkcję i wpisz jej liczby; funkcja wyłączona nie jest oferowana, ale to, co gracze już mają, zostaje.',
                'Przeczytaj wiersze pod formularzem: przeliczają monety na pieniądze po cenie monety z pierwszej paczki i pokazują premię każdej paczki.',
                'Zapisz szkic; pasek nad formularzem mówi, co się zmieniło. Te same liczby są w Studiu parametrów i obie strony zmieniają ten sam szkic.',
                'Opublikuj paczkę na stronie Publikacje. Dostawca płatności to osobny krok, którego gra jeszcze nie ma.',
            ],
            'example' => 'Gra o statkach: waluta Doubloon, trzy paczki (50 za 4,99, 220 za 19,99, 600 za 49,99), automatyczne tankowanie z zapasem 100, konto VIP na 30 dni za 150 dublonów z premią +20% do gotówki i XP, a przepustka wyłączona, dopóki nie podłączy się dostawcy płatności.',
        ],
    ],
];
