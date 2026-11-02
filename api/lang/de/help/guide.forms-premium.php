<?php

return [
    'entries' => [
        'premium' => [
            'label' => 'So funktioniert das Formular der Bezahlfunktionen',
            'help' => 'Hier legst du fest, was das Spiel für Premiummünzen und für echtes Geld verkauft: die Münzpakete, die Beschleunigung, das automatische Tanken, das VIP-Konto und den Pass. Was du eingibst, wird als Entwurf der Regeln gespeichert und erreicht die Spieler mit dem nächsten veröffentlichten Paket.',
            'steps' => [
                'Reiter Münzen: Benenne die Währung, wähle die Währung der Preise und trage die Münzpakete mit ihren Preisen ein; das erste Paket legt den Preis einer Münze fest, an dem die anderen gemessen werden.',
                'Reiter Automatisches Tanken, VIP-Konto und Pass: Schalte die Funktion ein und fülle ihre Zahlen aus; eine ausgeschaltete Funktion wird nicht angeboten, aber was Spieler schon haben, bleibt.',
                'Lies die Zeilen unter dem Formular: Sie rechnen Münzen zum Preis einer Münze des ersten Pakets in Geld um und zeigen den Bonus jedes Pakets.',
                'Speichere den Entwurf; die Leiste über dem Formular sagt, was sich geändert hat. Dieselben Zahlen stehen im Parameter-Studio, und beide ändern denselben Entwurf.',
                'Veröffentliche ein Paket auf der Seite Veröffentlichungen. Der Zahlungsanbieter ist ein eigener Schritt, den das Spiel noch nicht hat.',
            ],
            'example' => 'Ein Schiffsspiel: die Währung Doubloon, drei Pakete (50 für 4,99, 220 für 19,99, 600 für 49,99), automatisches Tanken mit einem Vorrat von 100, das VIP-Konto für 30 Tage zu 150 Dublonen mit +20 % Bargeld und XP und der Pass ausgeschaltet, bis ein Zahlungsanbieter verbunden ist.',
        ],
    ],
];
