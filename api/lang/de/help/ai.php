<?php

return [
    'entries' => [
        'include' => [
            'label' => 'Was enthalten sein soll',
            'help' => 'Was der Assistent über das Spiel wissen soll: die Autos, die Teile und die Zahlen der Regeln. Eine Antwort darf nur ändern, was du hier ankreuzt.',
            'example' => 'Nur die Teile, wenn du ihre Preise ändern willst: Hake die Autos und die Regeln ab.',
            'tip' => 'Je weniger du ankreuzt, desto kürzer die Anweisung und desto genauer das Gespräch.',
            'options' => [
                'vehicles' => ['label' => 'Autos', 'help' => 'Eine Tabelle der Autos mit ihren Zahlen und den Feldern, nach denen man sie auswählen kann (Antrieb, Klasse).'],
                'parts' => ['label' => 'Teile', 'help' => 'Eine Tabelle je Platz (Motor, Reifen, …), mit den Zahlen der Teile.'],
                'rules' => ['label' => 'Zahlen der Regeln', 'help' => 'Die Zahlen des Studios mit ihren Grenzen und einer Beschreibung jeder Zahl.'],
            ],
        ],
        'simple' => [
            'label' => 'Nur die wichtigsten Zahlen',
            'help' => 'Ein: Nur die Zahlen des einfachen Modus des Studios (Preise, Belohnungen, Zeiten, Stufen) kommen in die Anweisung. Aus: alle Zahlen der gewählten Abschnitte.',
            'tip' => 'Eine Anweisung mit allen Zahlen ist um ein Mehrfaches länger.',
        ],
        'locale' => [
            'label' => 'Sprache der Beschreibungen',
            'help' => 'Die Sprache der Bezeichnungen und Beschreibungen der Zahlen in der Anweisung. Die Regeln des Gesprächs und das Format des Blocks sind immer englisch, und der Assistent antwortet in der Sprache, in der du ihm schreibst.',
            'options' => [
                'pl' => ['label' => 'Polski', 'help' => 'Beschreibungen auf Polnisch.'],
                'en' => ['label' => 'English', 'help' => 'Beschreibungen auf Englisch.'],
                'fr' => ['label' => 'Français', 'help' => 'Beschreibungen auf Französisch.'],
                'de' => ['label' => 'Deutsch', 'help' => 'Beschreibungen auf Deutsch.'],
                'pt-BR' => ['label' => 'Português (Brasil)', 'help' => 'Beschreibungen auf brasilianischem Portugiesisch.'],
            ],
        ],
        'sections' => [
            'label' => 'Abschnitte der Regeln',
            'help' => 'Welche Teile der Zahlen der Regeln enthalten sein sollen (dieselben wie im Studio). Nichts angekreuzt: alle.',
            'example' => 'Nur „Bezahlfunktionen“ (premium), wenn du über den Preis des VIP sprichst.',
            'tip' => 'Eine Antwort darf nur die angekreuzten Abschnitte ändern.',
        ],
        'texts' => [
            'label' => 'Texte einbeziehen',
            'help' => 'Ein: Die Namen und Beschreibungen der Autos und Teile sowie die Texte der gewählten Abschnitte der Regeln kommen in allen Sprachen in die Anweisung (einige tausend Tokens mehr). Ohne das sieht der Assistent die Texte nicht und kann keinen ändern oder übersetzen.',
            'tip' => 'Schalte es ein, wenn du umbenennen oder übersetzen willst.',
        ],
    ],
];
