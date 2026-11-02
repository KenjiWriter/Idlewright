<?php

return [
    'entries' => [
        'include' => [
            'label' => 'Co dołączyć',
            'help' => 'Co asystent ma wiedzieć o grze: auta, części i liczby reguł. Odpowiedź będzie mogła zmienić tylko to, co tu zaznaczysz.',
            'example' => 'Same części, gdy chcesz zmienić ich ceny: odznacz auta i reguły.',
            'tip' => 'Im mniej zaznaczysz, tym krótsza instrukcja i tym dokładniejsza rozmowa.',
            'options' => [
                'vehicles' => ['label' => 'Auta', 'help' => 'Tabela aut z liczbami i polami, po których można wybierać (napęd, klasa).'],
                'parts' => ['label' => 'Części', 'help' => 'Po jednej tabeli na slot (silnik, opony, …), z liczbami części.'],
                'rules' => ['label' => 'Liczby reguł', 'help' => 'Liczby ze Studia z granicami i opisem każdej.'],
            ],
        ],
        'simple' => [
            'label' => 'Tylko najważniejsze liczby',
            'help' => 'Włączone: z reguł trafiają do instrukcji tylko liczby trybu prostego Studia (ceny, nagrody, czasy, poziomy). Wyłączone: wszystkie liczby wybranych sekcji.',
            'tip' => 'Instrukcja ze wszystkimi liczbami jest kilka razy dłuższa.',
        ],
        'locale' => [
            'label' => 'Język opisów',
            'help' => 'W jakim języku są etykiety i opisy liczb w instrukcji. Zasady rozmowy i format bloku są zawsze po angielsku, a asystent odpowiada w języku, w którym do niego piszesz.',
            'options' => [
                'pl' => ['label' => 'Polski', 'help' => 'Opisy po polsku.'],
                'en' => ['label' => 'English', 'help' => 'Opisy po angielsku.'],
                'fr' => ['label' => 'Français', 'help' => 'Opisy po francusku.'],
                'de' => ['label' => 'Deutsch', 'help' => 'Opisy po niemiecku.'],
                'pt-BR' => ['label' => 'Português (Brasil)', 'help' => 'Opisy po portugalsku (Brazylia).'],
            ],
        ],
        'sections' => [
            'label' => 'Sekcje reguł',
            'help' => 'Które działy liczb reguł dołączyć (te same co w Studiu). Bez zaznaczenia: wszystkie.',
            'example' => 'Tylko „Płatne funkcje” (premium), gdy rozmawiasz o cenie VIP.',
            'tip' => 'Odpowiedź może zmienić tylko zaznaczone sekcje.',
        ],
        'texts' => [
            'label' => 'Dołącz teksty',
            'help' => 'Włączone: do instrukcji trafiają nazwy i opisy aut i części oraz teksty wybranych sekcji reguł, we wszystkich językach (to kilka tysięcy tokenów więcej). Bez tego asystent nie widzi tekstów, więc nie zmieni ani nie przetłumaczy żadnego.',
            'tip' => 'Włącz, gdy chcesz zmieniać nazwy albo tłumaczyć.',
        ],
        'design' => [
            'label' => 'Zaprojektuj tę grę od nowa',
            'help' => 'Włączone: asystent nie zmienia pojedynczych liczb, tylko prowadzi z Tobą projekt całej gry etapami (słowa, auta, części, liczby, tory i produkty), a każdy etap kończy jednym blokiem. Teksty są w instrukcji bez względu na przełącznik „Dołącz teksty”.',
            'tip' => 'Użyj w nowej grze założonej z szablonu kreatorem. Instrukcja jest wtedy dłuższa.',
        ],
    ],
];
