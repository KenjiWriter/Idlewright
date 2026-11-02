<?php

return [

    'recovery' => [
        'invalid' => 'Dieser Wiederherstellungscode ist ungültig.',
        'locked' => 'Zu viele fehlgeschlagene Versuche. Versuche es später erneut.',
    ],

    'email' => [
        'sent' => 'Wir haben einen Link an :email gesendet. Er ist nur einmal und :minutes Minuten lang gültig.',
        'already_linked' => 'Dieses Konto hat bereits eine E-Mail-Adresse.',
    ],

    'login_link' => [
        'sent' => 'Wenn diese E-Mail-Adresse zu einer Garage gehört, ist ein Link unterwegs. Er ist nur einmal und :minutes Minuten lang gültig.',
    ],

    'oauth' => [
        'unavailable' => 'Diese Art der Anmeldung ist nicht verfügbar.',
        'ticket_invalid' => 'Diese Anmeldung ist abgelaufen oder wurde schon benutzt. Starte sie noch einmal.',
        'no_account' => 'Mit diesem :provider-Konto ist keine Garage verknüpft. Spiele zuerst als Gast und verknüpfe das Konto in deiner Garage.',
        'wrong_account' => 'Diese Anmeldung wurde für eine andere Garage gestartet. Starte sie noch einmal in der Garage, die du verknüpfen willst.',
    ],

    'delete' => [
        'name_mismatch' => 'Gib deinen Fahrernamen genau so ein, wie er geschrieben ist, um das Konto zu löschen.',
    ],

    'token' => [
        'invalid' => 'Dieser Link ist ungültig oder abgelaufen.',
    ],

    'identity' => [
        'taken' => 'Diese Anmeldung gehört bereits zu einer anderen Garage. Melde dich bei dieser Garage an oder nutze eine andere Anmeldung.',
        'provider_linked' => 'Dieses Konto hat bereits eine Anmeldung dieser Art.',
    ],

    'name' => [
        'attribute' => 'Fahrername',
        'taken' => 'Dieser Fahrername ist bereits vergeben.',
    ],

    'email_change' => [
        'sent' => 'Wir haben einen Link an :email gesendet. Die Änderung erfolgt erst, wenn er geöffnet wird (er funktioniert einmal und :minutes Minuten lang). Die alte Adresse wurde benachrichtigt.',
    ],

    'reauth' => [
        'sent' => 'Wir haben einen Link an die E-Mail-Adresse des Kontos gesendet. Er funktioniert einmal und :minutes Minuten lang. Komm nach dem Öffnen hierher zurück.',
    ],

    'refused' => [
        'identity_not_found' => 'Diese Garage hat diese Anmeldemethode nicht.',
        'last_method' => 'Das ist die letzte Anmeldemethode dieser Garage, sie kann also nicht entfernt werden. Füge zuerst eine andere hinzu.',
        'reauth_required' => 'Bestätige, dass du es bist, bevor du eine Anmeldemethode entfernst.',
        'reauth_mismatch' => 'Das ist nicht das Konto des Anbieters, das mit der Garage verknüpft ist. Melde dich mit dem verknüpften Konto an.',
        'reauth_no_method' => 'Diese Methode kann nicht zur Bestätigung dienen, weil die Garage sie nicht hat.',
        'reauth_unavailable' => 'Von diesem Gerät aus lässt sich nicht bestätigen, wer du bist. Melde dich neu an.',
        'no_email' => 'Diese Garage meldet sich nicht per E-Mail an. Füge eine E-Mail-Adresse hinzu, statt sie zu ändern.',
        'email_same' => 'Das ist bereits die E-Mail-Adresse dieser Garage.',
        'email_change_cooldown' => 'Die E-Mail-Adresse wurde vor Kurzem geändert. Die nächste Änderung ist ab :date möglich.',
        'consent_required' => 'Die Nutzungsbedingungen oder die Datenschutzerklärung haben sich geändert. Akzeptiere die neue Fassung, um weiterzuspielen.',
        'consent_version_changed' => 'Die Texte haben sich beim Lesen geändert. Lies die neue Fassung.',
    ],

];
