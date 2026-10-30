<?php

return [
    'entries' => [
        '/missions/reset_hour' => [
            'label' => 'Stunde, zu der der Missionstag beginnt',
            'help' => 'Die Stunde (in der Zeitzone des Spiels), zu der der Missionstag und mit ihm das neue Tages-Set beginnt. Die Woche beginnt montags zu dieser Stunde und der Monat am Ersten zu dieser Stunde. Dann endet auch der Zeitraum davor: Was ein Spieler geschafft, aber nicht abgeholt hat, holt der Server für ihn ab, der Rest verfällt.',
            'example' => '4: Wer abends und nach Mitternacht spielt, hat einen Missionstag und bekommt das neue Set um 4:00 Uhr morgens.',
            'range' => '0 – 23 (die Stunde).',
        ],
        '/missions/periods/daily/bonus_coins' => [
            'label' => 'Bonus für das ganze Set: Tag (Münzen)',
            'help' => 'Wie viele Premium-Münzen (RideCoin) ein Spieler dafür bekommt, alle Missionen des Tages-Sets zu schaffen. Es sind die einzigen kostenlosen Münzen der Missionen; die Belohnungen einzelner Missionen enthalten keine. Diese Zahl bestimmt also alle kostenlosen Münzen dieses Zeitraums, und das Schema erlaubt nicht mehr als 5.',
            'example' => '2 Münzen: Wer alle drei Zeiträume jedes Mal schafft, erhält 2, 8 und 25 Münzen, etwa 119 im Monat; das VIP-Konto kostet 150 Münzen und ein Paket mit 50 Münzen 4,99 zł.',
            'range' => '0 – 5 Münzen.',
            'tip' => 'Spieler empfinden einen niedrigeren Bonus als weggenommene Belohnung, also fang niedrig an: Ihn später zu erhöhen ist leicht.',
        ],
        '/missions/periods/weekly/bonus_coins' => [
            'label' => 'Bonus für das ganze Set: Woche (Münzen)',
            'help' => 'Wie viele Premium-Münzen (RideCoin) ein Spieler dafür bekommt, alle Missionen des Wochen-Sets zu schaffen. Es sind die einzigen kostenlosen Münzen der Missionen; die Belohnungen einzelner Missionen enthalten keine. Diese Zahl bestimmt also alle kostenlosen Münzen dieses Zeitraums, und das Schema erlaubt nicht mehr als 20.',
            'example' => '8 Münzen: Wer alle drei Zeiträume jedes Mal schafft, erhält 2, 8 und 25 Münzen, etwa 119 im Monat; das VIP-Konto kostet 150 Münzen und ein Paket mit 50 Münzen 4,99 zł.',
            'range' => '0 – 20 Münzen.',
            'tip' => 'Spieler empfinden einen niedrigeren Bonus als weggenommene Belohnung, also fang niedrig an: Ihn später zu erhöhen ist leicht.',
        ],
        '/missions/periods/monthly/bonus_coins' => [
            'label' => 'Bonus für das ganze Set: Monat (Münzen)',
            'help' => 'Wie viele Premium-Münzen (RideCoin) ein Spieler dafür bekommt, alle Missionen des Monats-Sets zu schaffen. Es sind die einzigen kostenlosen Münzen der Missionen; die Belohnungen einzelner Missionen enthalten keine. Diese Zahl bestimmt also alle kostenlosen Münzen dieses Zeitraums, und das Schema erlaubt nicht mehr als 60.',
            'example' => '25 Münzen: Wer alle drei Zeiträume jedes Mal schafft, erhält 2, 8 und 25 Münzen, etwa 119 im Monat; das VIP-Konto kostet 150 Münzen und ein Paket mit 50 Münzen 4,99 zł.',
            'range' => '0 – 60 Münzen.',
            'tip' => 'Spieler empfinden einen niedrigeren Bonus als weggenommene Belohnung, also fang niedrig an: Ihn später zu erhöhen ist leicht.',
        ],
        '/missions/periods/daily/cash_ppm' => [
            'label' => 'Geld für eine Mission: Tag (% ihres Wertes)',
            'help' => 'Der Teil des Wertes der Missionen des Tages-Sets, der in Geld gezahlt wird (1.000.000 ist alles). Der Wert einer Mission ist die Arbeit, die sie verlangt, in Tanks des besten Autos des Spielers: Runden, Verdienst, Kraftstoff, Kisten, Verbesserungen und der Rest haben im Missionsdokument ein Gewicht (effort), eine Mission, die viel verlangt, zahlt also viel. Das Geld sind so viele Tanks Verdienst nach dem Kraftstoff. Es kommt zum Verdienst der Autos hinzu und zählt nicht in der Spielwirtschaft: Die Schranken und der Simulator sehen es nicht.',
            'example' => '10 %: Eine Mission im Wert von 4 Tanks eines Autos, das 375 $ pro Tank verdient, zahlt etwa 150 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/weekly/cash_ppm' => [
            'label' => 'Geld für eine Mission: Woche (% ihres Wertes)',
            'help' => 'Der Teil des Wertes der Missionen des Wochen-Sets, der in Geld gezahlt wird (1.000.000 ist alles). Der Wert einer Mission ist die Arbeit, die sie verlangt, in Tanks des besten Autos des Spielers: Runden, Verdienst, Kraftstoff, Kisten, Verbesserungen und der Rest haben im Missionsdokument ein Gewicht (effort), eine Mission, die viel verlangt, zahlt also viel. Das Geld sind so viele Tanks Verdienst nach dem Kraftstoff. Es kommt zum Verdienst der Autos hinzu und zählt nicht in der Spielwirtschaft: Die Schranken und der Simulator sehen es nicht.',
            'example' => '10 %: Eine Mission im Wert von 4 Tanks eines Autos, das 375 $ pro Tank verdient, zahlt etwa 150 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/monthly/cash_ppm' => [
            'label' => 'Geld für eine Mission: Monat (% ihres Wertes)',
            'help' => 'Der Teil des Wertes der Missionen des Monats-Sets, der in Geld gezahlt wird (1.000.000 ist alles). Der Wert einer Mission ist die Arbeit, die sie verlangt, in Tanks des besten Autos des Spielers: Runden, Verdienst, Kraftstoff, Kisten, Verbesserungen und der Rest haben im Missionsdokument ein Gewicht (effort), eine Mission, die viel verlangt, zahlt also viel. Das Geld sind so viele Tanks Verdienst nach dem Kraftstoff. Es kommt zum Verdienst der Autos hinzu und zählt nicht in der Spielwirtschaft: Die Schranken und der Simulator sehen es nicht.',
            'example' => '10 %: Eine Mission im Wert von 4 Tanks eines Autos, das 375 $ pro Tank verdient, zahlt etwa 150 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/daily/xp_ppm' => [
            'label' => 'XP für eine Mission: Tag (% ihres Wertes)',
            'help' => 'Der Teil des Wertes der Missionen des Tages-Sets, der in XP des Spielers gezahlt wird (1.000.000 ist alles): so viele Tanks XP des besten Autos. Die Stufe des Spielers öffnet Strecken, Städte und Teile, und das Tutorial sagt, dass die Stufen die Arbeit der Runden sein sollen, also halte diese Zahl klein: Jeder Punkt verschiebt das Tempo des ganzen Spiels.',
            'example' => '6 %: Eine Mission im Wert von 4 Tanks eines Autos, dessen Tank 600 XP gibt, zahlt etwa 144 XP.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/weekly/xp_ppm' => [
            'label' => 'XP für eine Mission: Woche (% ihres Wertes)',
            'help' => 'Der Teil des Wertes der Missionen des Wochen-Sets, der in XP des Spielers gezahlt wird (1.000.000 ist alles): so viele Tanks XP des besten Autos. Die Stufe des Spielers öffnet Strecken, Städte und Teile, und das Tutorial sagt, dass die Stufen die Arbeit der Runden sein sollen, also halte diese Zahl klein: Jeder Punkt verschiebt das Tempo des ganzen Spiels.',
            'example' => '6 %: Eine Mission im Wert von 4 Tanks eines Autos, dessen Tank 600 XP gibt, zahlt etwa 144 XP.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/monthly/xp_ppm' => [
            'label' => 'XP für eine Mission: Monat (% ihres Wertes)',
            'help' => 'Der Teil des Wertes der Missionen des Monats-Sets, der in XP des Spielers gezahlt wird (1.000.000 ist alles): so viele Tanks XP des besten Autos. Die Stufe des Spielers öffnet Strecken, Städte und Teile, und das Tutorial sagt, dass die Stufen die Arbeit der Runden sein sollen, also halte diese Zahl klein: Jeder Punkt verschiebt das Tempo des ganzen Spiels.',
            'example' => '6 %: Eine Mission im Wert von 4 Tanks eines Autos, dessen Tank 600 XP gibt, zahlt etwa 144 XP.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/main/cash_ppm' => [
            'label' => 'Geld für eine Hauptmission (% ihres Wertes)',
            'help' => 'Der Teil des Wertes einer Hauptmission, der in Geld gezahlt wird, berechnet beim Öffnen (1.000.000 ist alles). Die Ziele über die ganze Zeit (Runden, Verdienst, Kraftstoff) werden in Tanks des besten Autos des Spielers in diesem Moment umgerechnet, ein besseres Auto macht daraus also keine geschenkte Belohnung; der Rest (Stufe, Autos, Garagen, Teile) hat im Missionsdokument ein Gewicht (effort). Diese Belohnungen werden einmal gezahlt.',
            'example' => '15 %: „Fahre 250 Runden“ sind etwa 8 Tanks eines Autos, das 375 $ pro Tank verdient, also etwa 469 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/main/xp_ppm' => [
            'label' => 'XP für eine Hauptmission (% ihres Wertes)',
            'help' => 'Der Teil des Wertes einer Hauptmission, der in XP des Spielers gezahlt wird, berechnet beim Öffnen (1.000.000 ist alles). Die Stufe des Spielers ist eine Schranke des Spiels, also halte diese Zahl klein; die XP des Autos werden getrennt gegeben (car_xp_laps der Mission).',
            'example' => '9 %: „Fahre 250 Runden“ sind etwa 8 Tanks eines Autos, dessen Tank 600 XP gibt, also etwa 450 XP.',
            'range' => '0 – 1000 %.',
        ],
    ],
];
