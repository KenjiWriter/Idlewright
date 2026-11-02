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
        '/slots/base' => [
            'label' => 'Autos, die jeder Spieler auf der Straße haben kann',
            'help' => 'Wie viele Autos ein Spieler gleichzeitig aktiv (fahrend) haben kann. Die übrigen Autos stehen eingelagert in der Garage: Sie fahren nicht, verbrauchen keinen Treibstoff, verschleißen nicht und verdienen nichts, behalten aber ihren Platz in der Garage. Die Autos, die ein Spieler vor der Grenze hatte, bleiben ihm, bis er eines einlagert.',
            'example' => '2: Ein Spieler kann zwei Autos auf der Straße haben; ein drittes Auto wird eingelagert, wenn er es bekommt.',
            'range' => '1 – 50 (Autos).',
            'tip' => 'Der Wirtschaftssimulator rechnet bei einem regelmäßigen Spieler mit etwa 1,7 Autos auf der Straße, 2 ändert also nichts am Tempo von Spielern, die nicht zahlen. Prüfe das nach einer Änderung mit dem Befehl economy:simulate.',
        ],
        '/slots/vip' => [
            'label' => 'Mehr Autos auf der Straße mit dem VIP-Konto',
            'help' => 'Wie viele Autos mehr ein Spieler mit dem VIP-Konto auf der Straße haben kann, solange das Konto läuft. Die Autos auf diesen Plätzen verdienen im Moment des Kontoendes nichts mehr und werden eingelagert; nichts geht verloren.',
            'example' => '3: Mit dem VIP-Konto kann ein Spieler fünf Autos auf der Straße haben (2 + 3), was mit dem Bonus des Kontos etwa das Dreifache dessen ist, was ein Spieler mit zwei Autos verdient.',
            'range' => '0 – 50 (Autos).',
            'tip' => 'Das ist der größte Teil des Wertes des VIP-Kontos: Mehr Autos verdienen mehr als der Bonus auf Geld und XP. Prüfe den Vorteil mit dem Wirtschaftssimulator, bevor du ihn erhöhst.',
        ],
        '/slots/swap_ms' => [
            'label' => 'Zeit, ein Auto auf die Straße zu bringen',
            'help' => 'Wie lange ein aus der Einlagerung geholtes Auto vorbereitet wird: Es fährt ab diesem Moment und verdient vorher nichts. So wechselt ein Spieler nicht bei jedem Schauer das Auto.',
            'example' => '120 s: zwei Minuten.',
            'range' => '0 – 3600 s.',
        ],
        '/slots/swap_fee_ppm' => [
            'label' => 'Kosten eines Tauschs (Teil einer Tankfüllung)',
            'help' => 'Was es kostet, ein Auto aus der Einlagerung anstelle eines Autos auf der Straße zu bringen, als Teil der Nettoeinnahmen einer Tankfüllung des besten Autos des Spielers (100 % ist eine ganze Füllung), in ganzen Dollar und mindestens einem. Ein Auto auf einen freien Platz zu stellen kostet nichts.',
            'example' => '2 %: Bei einem Auto, das 375 $ pro Füllung verdient, kostet ein Tausch 8 $.',
            'range' => '0 – 100 % (ein Teil einer Füllung).',
        ],
    ],
];
