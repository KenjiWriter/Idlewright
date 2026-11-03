<?php

return [
    'entries' => [
        'tuning' => [
            'label' => 'Jak działają bonusy części',
            'help' => 'Kierowca wkłada bonusy w części w warsztacie: każda próba kosztuje część ceny części i losuje wartość z dzwonu wokół średniej bonusu. Ta strona pokazuje bonusy gry, a liczby zmienia studio parametrów.',
            'steps' => [
                'Przeczytaj listę: każdy bonus ma nazwę w każdym języku, opis tego, co robi, sloty, do których pasuje, wagę w losowaniu i poziom, od którego się otwiera.',
                'Przeczytaj dzwon każdego bonusu: słupki to szansa każdego pełnego procenta, który da jedna próba, a tabela mówi, ile prób średnio trzeba do 6, 8, 10 i 12 procent.',
                'Żeby zmienić średnią, rozrzut, najmniejszą lub największą wartość bonusu, liczbę miejsc albo cenę próby, otwórz studio parametrów, sekcja „Bonusy części (dostrajanie)”; granice (8% bez kontekstu, 12% z kontekstem) są sprawdzane przy tworzeniu paczki.',
                'Naciśnij „Policz” na dole: mierzy najlepszy zestaw bonusów na autach wzorcowych i mówi, ile zdejmuje z okrążenia. Powyżej 10% z projektu to tylko ostrzeżenie; powyżej zatwierdzonego pułapu publikację paczki zatrzymuje bramka tuning:budget.',
                'Opublikuj paczkę na stronie Publikacje; bonusy, które kierowcy już mają, zostają, a bonus zabrany przez paczkę przestaje działać, dopóki nie wróci.',
            ],
            'example' => 'Bonus „Przyczepność na żwirze” ze średnią 5%, rozrzutem 2,3%, od 1% do 12%: mniej więcej co trzecia próba daje 6% lub więcej, jedna na tysiąc daje 12%. Podniesienie średniej do 7% wypycha najlepszy zestaw na żwirze ponad zatwierdzony pułap, więc bramka zatrzyma paczkę, aż granice spadną.',
        ],
    ],
];
