<?php

return [

    'account_link' => [
        'claim' => [
            'subject' => 'Bestätige deine E-Mail, um deine Garage zu sichern',
            'intro' => 'Jemand hat darum gebeten, die Garage von :driver mit dieser E-Mail-Adresse zu sichern. Wenn du das warst, bestätige es unten.',
            'action' => 'E-Mail bestätigen',
        ],
        'add' => [
            'subject' => 'Bestätige deine E-Mail, um sie deiner Garage hinzuzufügen',
            'intro' => 'Jemand hat darum gebeten, diese E-Mail-Adresse als Anmeldemethode für die Garage von :driver hinzuzufügen. Wenn du das warst, bestätige es unten.',
            'action' => 'E-Mail bestätigen',
        ],
        'login' => [
            'subject' => 'Dein Link zur Anmeldung bei RideIdle',
            'intro' => 'Nutze die Schaltfläche unten, um dich bei der Garage von :driver anzumelden.',
            'action' => 'Anmelden',
        ],
        'existing' => [
            'subject' => 'Diese E-Mail-Adresse hat schon eine Garage',
            'intro' => 'Jemand wollte mit dieser E-Mail-Adresse eine neue Garage sichern, aber diese E-Mail-Adresse gehört bereits zur Garage von :driver. Wenn du das warst und diese Garage auf diesem Gerät nutzen willst, melde dich unten an.',
            'action' => 'Anmelden',
        ],
        'change' => [
            'subject' => 'Bestätige die neue E-Mail-Adresse deiner Garage',
            'intro' => 'Jemand hat darum gebeten, die E-Mail-Adresse der Garage von Fahrer :driver auf diese Adresse zu ändern. Wenn du das warst, bestätige es unten: Bis dahin ändert sich nichts.',
            'action' => 'Neue Adresse bestätigen',
        ],
        'reauth' => [
            'subject' => 'Bestätige, dass du es bist',
            'intro' => 'Um eine Anmeldemethode von der Garage von Fahrer :driver zu entfernen, musst du bestätigen, dass du es bist. Wenn du es bist, bestätige unten.',
            'action' => 'Ja, ich bin es',
        ],
        'expires' => 'Der Link ist nur einmal und :minutes Minuten lang gültig.',
        'ignore' => 'Wenn du das nicht warst, ignoriere diese Nachricht: Ohne den Link ändert sich nichts.',
        'fallback' => 'Wenn die Schaltfläche nicht funktioniert, kopiere diese Adresse in deinen Browser:',
    ],

    'account_notice' => [
        'change_asked' => [
            'subject' => 'Jemand möchte die E-Mail-Adresse deiner Garage ändern',
            'intro' => 'Jemand hat darum gebeten, die E-Mail-Adresse der Garage von Fahrer :driver auf :detail zu ändern. Es ändert sich nichts, bis jemand den Link öffnet, der an die neue Adresse gesendet wurde. Wenn du das nicht warst, sichere die Garage: Wir brechen die Änderung ab und melden alle Geräte ab.',
            'action' => 'Das war ich nicht',
        ],
        'changed' => [
            'subject' => 'Die E-Mail-Adresse deiner Garage wurde geändert',
            'intro' => 'Die E-Mail-Adresse der Garage von Fahrer :driver wurde auf :detail geändert. Diese Adresse dient nicht mehr zur Anmeldung. Wenn du das nicht warst, kannst du es rückgängig machen: Wir setzen diese Adresse wieder ein und melden alle Geräte ab.',
            'action' => 'Das war ich nicht, Änderung rückgängig machen',
        ],
        'unlinked' => [
            'subject' => 'Eine Anmeldemethode wurde von deiner Garage entfernt',
            'intro' => 'Von der Garage von Fahrer :driver wurde eine Anmeldemethode entfernt: :detail. Wenn du das nicht warst, melde dich bei der Garage an und prüfe deine Anmeldemethoden.',
        ],
        'methods' => [
            'email' => 'E-Mail',
            'google' => 'Google',
            'facebook' => 'Facebook',
            'apple' => 'Apple',
        ],
        'valid' => '{1} Der Link ist :count Tag gültig.|[2,*] Der Link ist :count Tage gültig.',
        'footer' => 'Das ist eine Sicherheitsmitteilung von RideIdle. Du musst nicht antworten.',
    ],

];
