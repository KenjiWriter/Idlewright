<?php

return [
    'entries' => [
        'gigs' => [
            'label' => 'So funktioniert das Formular der Aufträge',
            'help' => 'Hier stellst du die Nebenaufträge des Spiels ein: die Tafel einer Stadt, was eine Stunde Auftrag auf jeder Stufe zahlt, und die Vorlagen, aus denen die Angebote ausgelost werden. Was du eingibst, wird als Entwurf der Regeln gespeichert und erreicht die Spieler mit dem nächsten veröffentlichten Paket.',
            'steps' => [
                'Reiter Tafel: Schalte die Aufträge an, wähle den Tutorial-Schritt, nach dem sie sich öffnen, und stelle ein, wie lange eine Welle dauert und wie viele Angebote jede Tageszeit hat.',
                'Reiter Lohn: Stelle die Geschwindigkeiten, die Anker der Leiter und die Bänder der Stufen ein. Diese Zahlen entscheiden, was eine Stunde Auftrag gegenüber einer Stunde Runden wert ist.',
                'Reiter Arten und Angebotsvorlagen: Benenne die Arten, dann beschreibe jede Vorlage: was das Auto befördern muss, wie lang die Fahrt ist, was sie zahlt und wie wahrscheinlich sie zu jeder Tageszeit ist.',
                'Drücke unter dem Formular auf Lohn berechnen: Es zeigt, was jede Vorlage mit jedem Auto des Pakets zahlt, gegen die Leiter und gegen die Runden, und ob die Schranke der Veröffentlichung es durchließe.',
                'Speichere den Entwurf und veröffentliche ein Paket auf der Seite Veröffentlichungen. Die Schranke prüft dieselben Zahlen noch einmal.',
            ],
            'example' => 'Ein Schiffsspiel: die Art Fähre, eine Vorlage, die 1 – 6 Passagiere zwischen zwei Häfen zu 100 % befördert, eine Nachtvorlage zu 125 % und eine Großvorlage für 1000 – 1800 kg, die nur am Tag ausgelost wird.',
        ],
    ],
];
