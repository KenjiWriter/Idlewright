<?php

return [
    'entries' => [
        '/tracks/*/thread/return_ms' => [
            'label' => 'Rückweg eines Laufs (s)',
            'help' => 'Was der Rückweg zum Start jeder Runde einer Strecke hinzufügt, die als Lauf gefahren wird (Dragstrip, Sprint), in Sekunden, egal welches Auto. Ein Rundkurs hat keinen Rückweg.',
            'range' => 'Von 0 bis 600 s (aus dem Schema).',
            'tip' => 'Er bringt einen Lauf auf die gewünschte Runde, ohne die Abschnitte zu ändern; je länger er ist, desto weniger unterscheiden sich die Autos in ihren Runden. Er zählt nur, wenn die Runde auf dem Streckenverlauf gefahren wird.',
        ],
        '/tracks/*/thread/sections/*/length_m' => [
            'label' => 'Gerade: Länge (m)',
            'help' => 'Wie lang eine Gerade des Streckenverlaufs der Strecke ist, in Metern. Die Länge der Strecke ist die Summe der Abschnitte, jeder auf einen Meter gerundet, wobei eine Kurve als ihr Bogen zählt.',
            'range' => 'Von 5 bis 5000 m (aus dem Schema).',
            'tip' => 'Ändere die Länge einer Geraden, und die Länge der Strecke muss sich mit ändern: Der Prüfer meldet, wenn sie sich um mehr als einen Meter unterscheiden. Die Seite des Streckenverlaufs erledigt das für dich.',
        ],
        '/tracks/*/thread/sections/*/radius_m' => [
            'label' => 'Kurve: Radius (m)',
            'help' => 'Der Radius einer Kurve des Streckenverlaufs der Strecke, in Metern. Eine Kehre hat etwa 20 m, eine enge Kurve 35, eine weite Kurve über 100. Die Geschwindigkeit darin ergibt sich aus Haftung und Fahrverhalten des Autos.',
            'range' => 'Von 8 bis 1000 m (aus dem Schema).',
            'tip' => 'Die Geschwindigkeit in einer Kurve ist die Wurzel aus Querbeschleunigung mal Radius, eine doppelt so weite Kurve wird also etwa 40 % schneller genommen.',
        ],
        '/tracks/*/thread/sections/*/angle_deg' => [
            'label' => 'Kurve: Winkel (°)',
            'help' => 'Um wie viel eine Kurve des Streckenverlaufs das Auto lenkt, in Grad: 90 ist ein rechter Winkel, 180 eine Kehre.',
            'range' => 'Von 5 bis 360° (aus dem Schema).',
            'tip' => 'Die Kurven einer Schleife ergeben zusammen etwa 360°, Linkskurven zählen gegen Rechtskurven; der Prüfer warnt, wenn das nicht stimmt, weil sich die Linie nicht schließen würde.',
        ],
        '/tracks/*/thread/sections/*/rough' => [
            'label' => 'Abschnitt: Rauheit',
            'help' => 'Wie rau ein Abschnitt des Streckenverlaufs ist, auf der Skala des Wertes Bodenfreiheit: Ein Auto mit mindestens dieser Bodenfreiheit fährt mit dem Tempo für rauen Untergrund darüber, eines mit weniger langsamer. Glatte Abschnitte haben keine.',
            'range' => 'Von 0 bis 100 (aus dem Schema).',
            'tip' => 'Das Tempo auf rauem Untergrund und wie schnell ein tiefes Auto langsamer wird, stehen im Studio („Tempo auf einem rauen Abschnitt“, „Verlangsamung auf einem rauen Abschnitt“).',
        ],
    ],
];
