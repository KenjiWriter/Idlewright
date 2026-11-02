<?php

return [
    'entries' => [
        '/competition/pi/reference_pi' => [
            'label' => 'PI des Referenzautos',
            'help' => 'Was das Referenzauto (Serie, ohne Teile) in PI wert ist. Auf dieser Skala stehen die Grenzen der Klassen: Ändert man sie, verschiebt sich das PI aller Autos um denselben Anteil, die Klassengrenzen müssen also mitgehen.',
            'example' => '300: ein Rdzak GT-4 in Serie hat 300 PI, ein Auto mit 50 % besseren Werten 450.',
            'range' => '1 – 100.000 (PI).',
        ],
        '/competition/pi/stat_cap_ppm' => [
            'label' => 'Obergrenze einer Eigenschaft im PI (% des Referenzautos)',
            'help' => 'Wie viel eine Eigenschaft eines Autos im PI höchstens zählt, als Vielfaches der Eigenschaft des Referenzautos (100 % ist gleich viel). Die Obergrenze verhindert, dass eine einzige riesige Zahl eine Klasse kauft.',
            'example' => '400 %: eine viermal so große Beschleunigung wie beim Referenzauto zählt viermal, nicht mehr.',
            'range' => '100 % – 10.000 %.',
        ],
        '/competition/pi/weights_ppm/*' => [
            'label' => 'Gewicht einer Eigenschaft im PI',
            'help' => 'Wie viel diese Eigenschaft im PI eines Autos zählt (die Gewichte ergeben zusammen 100 %). Standardmäßig sind die Gewichte der Mittelwert dessen, was alle Strecken von einem Auto verlangen, also zählen die Eigenschaften, auf die die Strecken am meisten setzen (Grip, Beschleunigung), am meisten. Die Gewichte müssen 100 % ergeben und gelten für die Eigenschaften des Spielmodells.',
            'example' => '23 %: Grip macht fast ein Viertel des PI aus.',
            'range' => '0 % – 100 %; zusammen 100 %.',
        ],
        '/competition/pi/class_max_pi/*' => [
            'label' => 'Obergrenze einer Klasse (PI)',
            'help' => 'Das höchste PI, das ein Auto dieser Klasse noch haben darf. Ein Auto gehört zur ersten Klasse, deren Grenze nicht unter seinem PI liegt; Autos über der letzten Grenze sind in der obersten Klasse. Die Grenzen müssen von der schwächsten Klasse an wachsen. Ein Turnier einer Klasse nimmt nur Autos dieser Klasse, ein gutes Tuning ist also ein Vorteil in der Klasse und ein besseres Auto öffnet die nächste.',
            'example' => '399: ein Auto mit 399 PI ist noch in Klasse C, eines mit 400 in Klasse B.',
            'range' => '1 – 1.000.000 (PI); aufsteigend.',
        ],
        '/competition/rating/start' => [
            'label' => 'Respekt zu Beginn',
            'help' => 'Der Respekt eines neuen Fahrers und der Wert, auf den eine Saison die Wertung beim weichen Zurücksetzen zurückzieht.',
            'example' => '1000: ein neuer Fahrer startet in der Straßendivision.',
            'range' => '1 – 100.000.',
        ],
        '/competition/rating/minimum' => [
            'label' => 'Niedrigster Respekt',
            'help' => 'Der Respekt eines Fahrers fällt nicht unter diesen Wert, egal wie viel er verliert. So gibt es keinen endlosen Absturz.',
            'example' => '600: ein Fahrer, der ständig verliert, bleibt bei 600 stehen.',
            'range' => '0 – 100.000; nicht über dem Start-Respekt.',
        ],
        '/competition/rating/k_new' => [
            'label' => 'K für neue Fahrer',
            'help' => 'Wie schnell sich der Respekt eines Fahrers bewegt, solange er weniger Wertungsmatches gespielt hat, als die nächste Zahl sagt. Gegen einen gleich starken Rivalen gibt ein Sieg die Hälfte davon, eine Niederlage nimmt ebenso viel. Ein großes K bringt einen neuen Spieler schnell an seinen Platz.',
            'example' => '40: ein Sieg gegen einen gleich starken Rivalen ist +20, eine Niederlage −20.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/new_matches' => [
            'label' => 'Matches, in denen ein Fahrer neu ist',
            'help' => 'So viele Wertungsmatches lang hat ein Fahrer das große K. Danach bewegt sich der Respekt langsamer.',
            'example' => '20: die ersten 20 Matches sortieren einen Spieler ein.',
            'range' => '0 – 1000 Matches.',
        ],
        '/competition/rating/k_established' => [
            'label' => 'K für etablierte Fahrer',
            'help' => 'Wie schnell sich der Respekt eines Fahrers bewegt, der nicht mehr neu und unterhalb der Spitze ist.',
            'example' => '24: ein Sieg gegen einen gleich starken Rivalen ist +12, eine Niederlage −12.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/k_top' => [
            'label' => 'K für Fahrer an der Spitze',
            'help' => 'Wie schnell sich der Respekt eines Fahrers an der Spitze (ab der Spitzengrenze) bewegt. Ein kleines K macht die Spitze stabil.',
            'example' => '16: ein Sieg gegen einen gleich starken Rivalen ist +8, eine Niederlage −8.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/top_from' => [
            'label' => 'Respekt, ab dem ein Fahrer an der Spitze ist',
            'help' => 'Ab diesem Respekt hat ein Fahrer das kleinste K. Es ist eine eigene Zahl neben der Grenze der Elitedivision, auch wenn beide standardmäßig gleich sind.',
            'example' => '1700.',
            'range' => '1 – 100.000.',
        ],
        '/competition/rating/club_from' => [
            'label' => 'Beginn der Clubdivision',
            'help' => 'Ab diesem Respekt ist ein Fahrer in der Clubdivision (darunter: Straße). Die Divisionen wählen die Gegner in Turnieren aus.',
            'example' => '1100.',
            'range' => '1 – 100.000; unter der Regionaldivision.',
        ],
        '/competition/rating/regional_from' => [
            'label' => 'Beginn der Regionaldivision',
            'help' => 'Ab diesem Respekt ist ein Fahrer in der Regionaldivision.',
            'example' => '1400.',
            'range' => '1 – 100.000; zwischen Club und Elite.',
        ],
        '/competition/rating/elite_from' => [
            'label' => 'Beginn der Elitedivision',
            'help' => 'Ab diesem Respekt ist ein Fahrer in der Elitedivision, der höchsten.',
            'example' => '1700.',
            'range' => '1 – 100.000; über der Regionaldivision.',
        ],
        '/competition/rating/season_keep_ppm' => [
            'label' => 'Wie viel Respekt nach einer Saison bleibt (%)',
            'help' => 'Der Teil des Respekts, der nach dem weichen Zurücksetzen zu Beginn einer Saison bleibt; der Rest geht auf den Startwert zurück. 100 % ändert nichts, 0 % setzt alle auf den Start.',
            'example' => '75 %: ein Fahrer mit 1400 startet die Saison mit 1300, einer mit 600 mit 700.',
            'range' => '0 % – 100 %.',
        ],
        '/competition/match/luck_sigma_ppm' => [
            'label' => 'Breite des Glücks eines Laufs (% der Rundenzeit)',
            'help' => 'Um wie viel Prozent der Rundenzeit sich die Zeit eines Laufs durch Glück ändern kann: etwa zwei von drei Läufen liegen innerhalb dieser Breite, keiner weiter als das Dreieinhalbfache davon. Mehr Glück lässt das schwächere Auto öfter gewinnen; null ist kein Glück. Es wird aus dem Seed gezogen, das Ergebnis eines Matches lässt sich also wiederholen.',
            'example' => '2 %: ein um 1 % schnelleres Auto gewinnt einen einzelnen Lauf in 64 % der Fälle, ein Match über drei Läufe in 70 %.',
            'range' => '0 % – 10 %.',
        ],
        '/competition/match/runs' => [
            'label' => 'Läufe in einem Match',
            'help' => 'Aus wie vielen Läufen ein Match besteht; die Seite, die die meisten gewinnt, gewinnt das Match, die Zahl muss also ungerade sein. Jeder Lauf findet auf einer anderen Strecke statt, mehr Läufe begünstigen also ein Auto, das zu vielen Strecken passt, und verringern den Anteil des Glücks.',
            'example' => '3: das Beste aus drei Läufen.',
            'range' => '1 – 9, ungerade.',
        ],
    ],
];
