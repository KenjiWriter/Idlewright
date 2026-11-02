<?php

return [
    'entries' => [
        'missions' => [
            'label' => 'So funktioniert das Missionsformular',
            'help' => 'Hier legst du die Missionen des Spiels fest: wann die Sets von Tag, Woche und Monat beginnen und welchen Teil des Werts seiner Missionen jedes zahlt, die Missionen, aus denen die Sets ziehen, die Ketten der Hauptmissionen und was eine Einheit jeder Art von Arbeit wert ist. Was du eingibst, wird zusammen als Entwurf der Regeln und des Missionsdokuments gespeichert und erreicht die Spieler mit dem nächsten veröffentlichten Paket.',
            'steps' => [
                'Reiter Belohnungen und Zeiträume: Schalte die Missionen ein, wähle die Zeitzone und die Stunde des Tagesbeginns und lege fest, welchen Teil des Werts seiner Missionen jedes Set und die Hauptmissionen zahlen (Bargeld und XP in Prozent), mit den Münzen für das ganze Set.',
                'Reiter Wert der Arbeit: Sage, was eine Einheit jeder Art von Arbeit in Tanks des besten Autos wert ist; eine Mission zahlt einen Teil der Arbeit, die sie verlangt, eine Mission, die mehr verlangt, zahlt also mehr.',
                'Drücke Budget berechnen: Es zeigt, was ein Tag, eine Woche und ein Monat in Bargeld, XP und Münzen für ein Auto auf einer Strecke ausmachen und ob die Schranke der Veröffentlichung es durchließe.',
                'Reiter Sets: Füge für das tägliche, wöchentliche und monatliche Set Slots und die Missionen hinzu, die jeder Slot ziehen kann, mit ihren Zielen, Gewichten und Multiplikatoren.',
                'Reiter Hauptmissionen: Füge Ketten und ihre Missionen in der Reihenfolge der Öffnung hinzu, mit ihren Zielen, Texten in den Sprachen und den Gegenständen, die sie geben.',
                'Speichere den Entwurf: Beide Dokumente werden zusammen gespeichert, oder keines, wenn eines abgelehnt wird. Ein Paket veröffentlichst du auf der Seite Veröffentlichungen.',
            ],
            'example' => 'Ein Schiffsspiel: Die Sets zahlen 10 % des Werts in Bargeld und 6 % in XP mit 2, 8 und 25 Münzen, die Hauptmissionen 15 % und 9 %; „50 Runden segeln“ ist 50 Runden eines Tanks mit 30 Runden mal 1 Tank Arbeit wert, etwa 1,7 Tanks, und zahlt in einem Tagesset 17 % des Nettoverdiensts eines Tanks.',
        ],
    ],
];
