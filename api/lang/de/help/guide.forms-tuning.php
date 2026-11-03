<?php

return [
    'entries' => [
        'tuning' => [
            'label' => 'Wie die Boni der Teile funktionieren',
            'help' => 'Ein Fahrer legt in der Werkstatt Boni auf ein Teil: Jeder Versuch kostet einen Teil des Teilepreises und zieht einen Wert aus einer Glockenkurve um den Mittelwert des Bonus. Diese Seite zeigt die Boni des Spiels, und das Parameterstudio ändert ihre Zahlen.',
            'steps' => [
                'Lies die Liste: Jeder Bonus hat seinen Namen in jeder Sprache, eine Beschreibung, die passenden Slots, sein Gewicht bei der Auslosung und die Stufe, ab der er sich öffnet.',
                'Lies die Glocke jedes Bonus: Die Balken sind die Chance jedes ganzen Prozents, das ein Versuch gibt, und die Tabelle sagt, wie viele Versuche es im Schnitt bis 6, 8, 10 und 12 Prozent braucht.',
                'Um Mittelwert, Streuung, kleinsten oder größten Wert eines Bonus, die Zahl der Plätze oder den Preis eines Versuchs zu ändern, öffne das Parameterstudio, Abschnitt „Teile-Boni (Tuning)“; die Grenzen (8 % ohne Kontext, 12 % mit Kontext) werden beim Erstellen eines Pakets geprüft.',
                'Drücke unten auf „Berechnen“: Es misst das beste Set der Boni an den Referenzautos und sagt, wie viel es von der Runde abzieht. Über den 10 % des Entwurfs ist es nur eine Warnung; über der genehmigten Obergrenze stoppt das Tor tuning:budget die Veröffentlichung des Pakets.',
                'Veröffentliche ein Paket auf der Seite Veröffentlichungen; Boni, die Fahrer schon haben, bleiben erhalten, und ein Bonus, den ein Paket entfernt, wirkt nicht mehr, bis er zurück ist.',
            ],
            'example' => 'Ein Bonus „Grip auf Schotter“ mit Mittelwert 5 %, Streuung 2,3 %, von 1 % bis 12 %: Etwa jeder dritte Versuch gibt 6 % oder mehr, einer von tausend gibt 12 %. Hebst du den Mittelwert auf 7 %, geht das beste Set auf Schotter über die genehmigte Obergrenze, und das Tor stoppt das Paket, bis die Grenzen sinken.',
        ],
    ],
];
