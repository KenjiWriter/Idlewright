<?php

return [
    'entries' => [
        'track' => [
            'label' => 'Strecke',
            'help' => 'Die Strecke, deren Streckenverlauf du siehst und änderst. Die anderen Strecken bleiben, wie sie sind.',
            'tip' => 'Speichere, bevor du zu einer anderen Strecke wechselst: Die Änderungen an der, die du verlässt, werden nicht behalten.',
        ],
        'lap_from' => [
            'label' => 'Runde aus',
            'help' => 'Woher die Runde eines Autos auf dieser Strecke kommt: aus seinen Werten über die Anforderungen der Strecke (die Formel, die bisher jede Strecke hat) oder aus einer Runde, die auf dem Streckenverlauf gefahren wird. Bei der Formel zeichnet der Streckenverlauf die Strecke nur.',
            'tip' => 'Bevor du eine Strecke auf den Streckenverlauf umstellst, sieh dir die Tabelle der Autos unter der Linie an: Sie zeigt, wie stark sich jede Runde änderte. Die Runde des Referenzautos bleibt in beiden Fällen die Basisrunde der Strecke.',
            'options' => [
                'stats' => ['label' => 'Formel (Werte)', 'help' => 'Die Runde entsteht aus den Werten des Autos und den Anforderungen der Strecke, wie bisher. Der Streckenverlauf, falls es einen gibt, ist nur eine Zeichnung.'],
                'thread' => ['label' => 'Streckenverlauf', 'help' => 'Die Runde wird auf den Abschnitten des Streckenverlaufs gefahren: Das Auto fährt los, beschleunigt auf den Geraden, bremst vor den Kurven und hält den Belag.'],
            ],
        ],
        'mode' => [
            'label' => 'Wie gefahren wird',
            'help' => 'Ein Rundkurs ist eine Schleife, die fliegend gefahren wird: Das Auto überquert die Linie mit der Geschwindigkeit, mit der es sie verlassen hat. Ein Lauf wird jedes Mal aus dem Stand gefahren, wie eine Dragstrip oder ein Sprint.',
            'options' => [
                'circuit' => ['label' => 'Rundkurs', 'help' => 'Eine Schleife; das Auto hält an der Linie nicht an.'],
                'run' => ['label' => 'Lauf', 'help' => 'Jede Runde beginnt aus dem Stand, und der Rückweg zum Start dauert eine festgelegte Zeit.'],
            ],
        ],
        'return_s' => [
            'label' => 'Rückweg (s)',
            'help' => 'Was der Rückweg zum Start jeder Runde eines Laufs hinzufügt, in Sekunden, egal welches Auto. Ein Rundkurs hat keinen Rückweg.',
            'range' => 'Von 0 bis 600 s (aus dem Schema).',
            'tip' => 'Er bringt einen Dragstrip auf die gewünschte Runde, ohne die Abschnitte zu ändern; je länger er ist, desto weniger unterscheiden sich die Autos in ihren Runden.',
        ],
        'sections' => [
            'label' => 'Abschnitte',
            'help' => 'Der Streckenverlauf als Kette von Abschnitten in der Reihenfolge, in der sie gefahren werden: Geraden und Kurven. Ändere die Reihenfolge mit den Pfeilen; die Linie daneben wird daraus gezeichnet.',
        ],
        'type' => [
            'label' => 'Art des Abschnitts',
            'help' => 'Eine Gerade führt geradeaus; eine Kurve lenkt das Auto um einen Winkel auf einem Radius.',
            'options' => [
                'straight' => ['label' => 'Gerade', 'help' => 'Hat eine Länge.'],
                'corner' => ['label' => 'Kurve', 'help' => 'Hat einen Radius und einen Winkel.'],
            ],
        ],
        'length' => [
            'label' => 'Länge (m)',
            'help' => 'Wie lang die Gerade ist, in Metern.',
            'range' => 'Von 5 bis 5000 m (aus dem Schema).',
            'tip' => 'Die Länge der Strecke ist die Summe der Abschnitte und wird beim Speichern eingetragen.',
        ],
        'radius' => [
            'label' => 'Radius (m)',
            'help' => 'Der Radius der Kurve, in Metern. Eine Kehre hat etwa 20 m, eine enge Kurve 35, eine weite Kurve über 100. Die Geschwindigkeit darin ergibt sich aus Haftung und Fahrverhalten des Autos.',
            'range' => 'Von 8 bis 1000 m (aus dem Schema).',
        ],
        'angle' => [
            'label' => 'Winkel (°)',
            'help' => 'Um wie viel die Kurve das Auto lenkt, in Grad: 90 ist ein rechter Winkel, 180 eine Kehre.',
            'range' => 'Von 5 bis 360° (aus dem Schema).',
            'tip' => 'Die Kurven einer Schleife ergeben zusammen etwa 360° (Linkskurven zählen gegen Rechtskurven); sonst schließt sich die Linie in der Zeichnung nicht.',
        ],
        'turn' => [
            'label' => 'Biegt ab',
            'help' => 'In welche Richtung die Kurve abbiegt. Es zeichnet nur die Linie: Die Runde hängt nicht davon ab.',
            'options' => [
                'left' => ['label' => 'Links', 'help' => 'Die Linie biegt nach links.'],
                'right' => ['label' => 'Rechts', 'help' => 'Die Linie biegt nach rechts.'],
            ],
        ],
        'surface' => [
            'label' => 'Belag',
            'help' => 'Woraus der Abschnitt besteht, wenn es nicht das ist, woraus die Strecke besteht: ein Schotterstück in einer Rallye-Prüfung. Leer heißt der Belag der Strecke. Das Wetter macht ihn nass, schlammig oder verschneit, und die Reifen eines Autos haften auf jedem anders.',
            'options' => [
                'asphalt' => ['label' => 'Asphalt', 'help' => 'Trockener Asphalt; Regen macht ihn zu nassem Asphalt, Schnee bedeckt ihn.'],
                'gravel' => ['label' => 'Schotter', 'help' => 'Loser Schotter; Regen macht ihn zu Schlamm, Schnee bedeckt ihn. Er hält die Höchstgeschwindigkeit eines Autos zurück und verlangt die richtigen Reifen.'],
            ],
        ],
        'limit' => [
            'label' => 'Tempolimit (km/h)',
            'help' => 'Ein Tempo, schneller als das kein Auto in diesem Abschnitt fährt: eine Straße, eine Boxengasse, der Rückweg. Leer heißt keines.',
            'range' => 'Von 20 bis 400 km/h (aus dem Schema).',
        ],
        'rough' => [
            'label' => 'Rauheit',
            'help' => 'Wie rau der Abschnitt ist, auf der Skala des Wertes Bodenfreiheit: Ein Auto mit mindestens dieser Bodenfreiheit fährt mit dem Tempo für rauen Untergrund darüber, eines mit weniger langsamer. Leer oder 0 heißt glatt.',
            'range' => 'Von 0 bis 100 (aus dem Schema).',
            'tip' => 'Das Tempo auf rauem Untergrund und wie schnell ein tiefes Auto langsamer wird, stehen im Studio („Tempo auf einem rauen Abschnitt“, „Verlangsamung auf einem rauen Abschnitt“).',
        ],
    ],
];
