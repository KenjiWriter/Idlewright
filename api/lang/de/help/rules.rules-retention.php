<?php

return [
    'entries' => [
        '/streaks/goal/daily_done' => [
            'label' => 'Missionen des Tages, die das Tagesziel ergeben',
            'help' => 'Wie viele Missionen des Tagessets ein Spieler schaffen muss (geschafft, nicht unbedingt abgeholt), damit der Tag für die Serie zählt. Ein Set mit weniger Missionen verlangt alle. Die Serie wird aus dem Tagesset gezählt, es gibt also keine zusätzlichen Aufgaben.',
            'example' => '3: drei der sechs Missionen des Tages, sodass auch ein Spieler, der einmal am Tag vorbeischaut, die Serie halten kann.',
            'range' => '1 – 20 (Missionen).',
            'tip' => 'Eine niedrigere Zahl schützt die Serie von Spielern, die selten reinschauen; eine höhere verlangt echtes Spielen an jedem Tag.',
        ],
        '/streaks/shield/every_days' => [
            'label' => 'Serientage pro Schild',
            'help' => 'Ein Spieler verdient einen Schild, wenn die Serie ein Vielfaches dieser Tage erreicht. Ein Schild rettet die Serie vor einem verpassten Tag: Die Serie bleibt, wo sie ist, und dieser Tag zahlt nichts.',
            'example' => '7: ein Schild am 7., 14. und 21. Tag einer Serie.',
            'range' => '1 – 365 (Tage).',
        ],
        '/streaks/shield/cap' => [
            'label' => 'Höchstzahl der Schilde, die ein Spieler behält',
            'help' => 'Wie viele Schilde ein Spieler gleichzeitig haben kann. Ein Schild, der darüber hinaus verdient wird, verfällt. Bei 0 hat das Spiel keine Schilde, und ein verpasster Tag beendet die Serie.',
            'example' => '2: Nach zwei verpassten Tagen in Folge wird die Serie zweimal gerettet, der dritte beendet sie.',
            'range' => '0 – 10 (Schilde).',
            'tip' => 'Schilde werden erspielt und nicht gekauft, eine hohe Zahl ist also mild und kein Weg, die Serie zu verkaufen.',
        ],
        '/streaks/cycle/*/cash_ppm' => [
            'label' => 'Geld eines Tages des Zyklus (Teil einer Tankfüllung)',
            'help' => 'Wie viel Geld ein Tag der Serie zahlt, als Teil der Nettoeinnahmen einer Tankfüllung des besten Autos des Spielers (100 % ist eine ganze Füllung); es wächst also mit dem Spieler und muss nicht angepasst werden. Jeder Eintrag des Zyklus ist ein Tag, nach dem letzten beginnt der Zyklus von vorn. Es kommt zu den Einnahmen der Autos hinzu und zählt nicht zur Wirtschaft des Spiels.',
            'example' => '2 %: Der erste Tag des Zyklus zahlt zwei Hundertstel einer Füllung; der letzte Tag einer Woche zahlt meist das Vierfache.',
            'range' => '0 – 100 % (ein Teil einer Füllung).',
            'tip' => 'Prüfe die Summe für einen Monat mit dem Befehl streaks:budget: Sie sollte bei wenigen Prozent dessen bleiben, was die Autos verdienen.',
        ],
        '/streaks/cycle/*/xp_ppm' => [
            'label' => 'XP eines Tages des Zyklus (Teil einer Tankfüllung)',
            'help' => 'Wie viel XP ein Tag der Serie zahlt, als Teil der XP einer Tankfüllung des besten Autos des Spielers (100 % ist eine ganze Füllung). Das Level eines Spielers ist ein Tor des Spiels, halte den Wert also klein.',
            'example' => '1 %: Ein Tag des Zyklus zahlt ein Hundertstel der XP einer Füllung.',
            'range' => '0 – 100 % (ein Teil einer Füllung).',
        ],
        '/streaks/milestones/*/day' => [
            'label' => 'Serienlänge eines Meilensteins (Tage)',
            'help' => 'Die Länge der Serie in Tagen, bei der der Meilenstein zahlt, was er gibt (die Kisten und, falls vorhanden, Geld und XP), zusätzlich zum Tag des Zyklus. Jede Länge wird nur einmal genannt.',
            'example' => '30: Eine Serie von dreißig Tagen zahlt ihren Meilenstein am dreißigsten Tag, einmal.',
            'range' => '2 – 3650 (Tage).',
        ],
    ],
];
