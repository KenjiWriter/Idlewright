<?php

return [
    'entries' => [
        '/tuning/slots' => [
            'label' => 'Plätze für Boni',
            'help' => 'Wie viele Plätze für Boni ein Teil hat, sofern sein Modell nichts anderes sagt. Der Spieler füllt und wechselt sie in der Werkstatt.',
            'example' => '3: jedes Teil hat drei Plätze, am Anfang leer.',
        ],
        '/tuning/unlock_level' => [
            'label' => 'Stufe, ab der die Werkstatt abstimmt',
            'help' => 'Ab welcher Spielerstufe die Werkstatt Teile abstimmt, also Boni auf ihnen setzt.',
            'example' => '3: ab Stufe drei.',
        ],
        '/tuning/cost_random_ppm' => [
            'label' => 'Preis eines Versuchs mit zufälligem Bonus',
            'help' => 'Welchen Anteil des Preises des Teilemodells ein Versuch kostet, wenn der Server den Bonus zieht. 30.000 sind 3 %.',
            'example' => '30.000: ein Teil für 570 $ kostet 17,10 $ pro Versuch.',
        ],
        '/tuning/cost_pick_ppm' => [
            'label' => 'Preis eines Versuchs mit gewähltem Bonus',
            'help' => 'Welchen Anteil des Modellpreises ein Versuch kostet, wenn der Spieler den Bonus wählt (teurer als ein zufälliger). 100.000 sind 10 %.',
            'example' => '100.000: ein Teil für 570 $ kostet 57 $ pro Versuch.',
        ],
        '/tuning/cost_min_cents' => [
            'label' => 'Mindestpreis eines Versuchs',
            'help' => 'Das Mindeste, was ein Versuch kostet, auch für billige Teile, in Cent.',
            'example' => '500: nie weniger als 5 $.',
        ],
        '/tuning/time_ppm_of_mount' => [
            'label' => 'Dauer eines Versuchs',
            'help' => 'Wie lange ein Versuch im Verhältnis zur Montagezeit des Teils dauert. 500.000 ist die Hälfte.',
            'example' => '500.000: die Montage dauert 2 Minuten, ein Versuch 1 Minute.',
        ],
        '/tuning/floor_sigma_ppm' => [
            'label' => 'Untergrenze eines neuen Bonus',
            'help' => 'Der erste Wert eines neu eingesetzten Bonus liegt höchstens so weit unter seinem Mittelwert, in Millionstel Sigma. So ist der Wechsel für eine andere Strecke keine Strafe.',
            'example' => '1.000.000: nie tiefer als ein Sigma unter dem Mittelwert.',
        ],
        '/tuning/pity_step_ppm' => [
            'label' => 'Schritt des Pechschutzes',
            'help' => 'Um wie viel (in Millionstel Sigma) der Mittelwert nach jedem Versuch an diesem Platz steigt, der nichts verbessert hat. 0 schaltet den Schutz aus.',
            'example' => '0: kein Schutz; 50.000: nach 10 Versuchen ohne Gewinn steigt der Mittelwert um ein halbes Sigma.',
        ],
        '/tuning/pity_max_ppm' => [
            'label' => 'Höchstwert des Schutzes',
            'help' => 'Um wie viel der Pechschutz den Mittelwert höchstens verschiebt, in Millionstel Sigma.',
            'example' => '1.000.000: höchstens um ein Sigma.',
        ],
        '/tuning/car_cap_ppm' => [
            'label' => 'Bonusdeckel pro Auto',
            'help' => 'Wie viel die Boni aller Teile eines Autos höchstens zu einem Wert in einem Kontext addieren dürfen, in ppm.',
            'example' => '250.000: nicht mehr als +25 %.',
        ],
        '/tuning/max_ppm_plain' => [
            'label' => 'Höchster Bonus ohne Kontext',
            'help' => 'Das Meiste, was ein Bonus, der auf jeder Strecke wirkt, im besten Fall geben darf. Ein Paket mit einem Bonus, der mehr geben kann, wird abgelehnt.',
            'example' => '80.000: höchstens +8 %.',
        ],
        '/tuning/max_ppm_context' => [
            'label' => 'Höchster Bonus mit Kontext',
            'help' => 'Das Meiste, was ein Bonus, der nur auf einer Streckenart, einem Untergrund oder bei einem Wetter wirkt, im besten Fall geben darf. Ein enger Kontext erlaubt mehr.',
            'example' => '120.000: höchstens +12 %.',
        ],
        '/tuning/definitions/*/weight' => [
            'label' => 'Gewicht des Bonus',
            'help' => 'Wie oft der Bonus unter denen gezogen wird, die zum Slot des Teils passen, wenn der Spieler nicht selbst wählt.',
            'example' => '3 neben anderen mit Gewicht 3 und 2: er wird in 3 von 8 Fällen gezogen.',
        ],
        '/tuning/definitions/*/from_level' => [
            'label' => 'Stufe, ab der der Bonus angeboten wird',
            'help' => 'Ab welcher Spielerstufe der Bonus in der Werkstatt erscheint. Darunter lässt er sich weder ziehen noch wählen.',
            'example' => '8: der Schneebonus für Spieler ab Stufe 8.',
        ],
        '/tuning/definitions/*/mean_ppm' => [
            'label' => 'Häufigster Wert des Bonus',
            'help' => 'Die Mitte der Kurve: der Wert, um den der Bonus gezogen wird. 50.000 sind 5 %.',
            'example' => '50.000: er kommt am häufigsten um 5 % heraus.',
        ],
        '/tuning/definitions/*/sigma_ppm' => [
            'label' => 'Streuung des Bonus (Sigma)',
            'help' => 'Wie breit sich die Werte um den Mittelwert verteilen: je größer das Sigma, desto öfter kommen Werte weit vom Mittelwert. Ein Wert zwei Sigma über dem Mittelwert kommt einmal in 70 Versuchen.',
            'example' => '23.000: eine Streuung von 2,3 %, also kommen 10 % einmal in 70 Versuchen.',
        ],
        '/tuning/definitions/*/min_ppm' => [
            'label' => 'Kleinster Wert des Bonus',
            'help' => 'Das Mindeste, was ein Bonus geben kann. Ein gezogener Wert darunter wird auf ihn angehoben.',
            'example' => '10.000: nie unter 1 %.',
        ],
        '/tuning/definitions/*/max_ppm' => [
            'label' => 'Größter Wert des Bonus',
            'help' => 'Das Meiste, was ein Bonus geben kann. Ein Wert knapp darunter ist sehr selten. Er darf die Grenze für einen Bonus mit oder ohne Kontext aus den allgemeinen Einstellungen nicht überschreiten.',
            'example' => '120.000: höchstens 12 %, und er kommt einmal in über tausend Versuchen.',
        ],
    ],
];
