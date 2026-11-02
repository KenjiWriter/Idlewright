<?php

return [

    'recovery' => [
        'invalid' => 'This recovery code is not valid.',
        'locked' => 'Too many failed attempts. Try again later.',
    ],

    'email' => [
        'sent' => 'We sent a link to :email. It works once, for :minutes minutes.',
        'already_linked' => 'This account already has an e-mail.',
    ],

    'login_link' => [
        'sent' => 'If this e-mail belongs to a garage, a link is on its way. It works once, for :minutes minutes.',
    ],

    'oauth' => [
        'unavailable' => 'This way of signing in is not available.',
        'ticket_invalid' => 'This sign-in has expired or was already used. Start it again.',
        'no_account' => 'No garage is linked to this :provider account. Start playing as a guest and link the account in your garage.',
        'wrong_account' => 'This sign-in was started for another garage. Start it again from the garage you want to link.',
    ],

    'delete' => [
        'name_mismatch' => 'Type your driver name exactly as it is written to delete the account.',
    ],

    'token' => [
        'invalid' => 'This link is not valid or has expired.',
    ],

    'identity' => [
        'taken' => 'This sign-in already belongs to another garage. Sign in to that garage instead, or use another one.',
        'provider_linked' => 'This account already has a sign-in of this kind.',
    ],

    'name' => [
        'attribute' => 'driver name',
        'taken' => 'This driver name is already taken.',
    ],

    'email_change' => [
        'sent' => 'We sent a link to :email. The change is made only when it is opened (it works once, for :minutes minutes). The old address was notified.',
    ],

    'reauth' => [
        'sent' => 'We sent a link to the e-mail of the account. It works once, for :minutes minutes. Come back here after you open it.',
    ],

    'refused' => [
        'identity_not_found' => 'This garage does not have that sign-in.',
        'last_method' => 'This is the last sign-in of this garage, so it cannot be unlinked. Add another one first.',
        'reauth_required' => 'Confirm that it is you before you unlink a sign-in.',
        'reauth_mismatch' => 'This is not the provider account that is linked to the garage. Sign in with the linked one.',
        'reauth_no_method' => 'That sign-in cannot be used to confirm, because the garage does not have it.',
        'reauth_unavailable' => 'Who you are cannot be confirmed from this device. Sign in again.',
        'no_email' => 'This garage does not sign in with an e-mail. Add an e-mail instead of changing it.',
        'email_same' => 'That is already the e-mail of this garage.',
        'email_change_cooldown' => 'The e-mail was changed recently. The next change is possible from :date.',
        'consent_required' => 'The terms or the privacy policy have changed. Accept the new version to keep playing.',
        'consent_version_changed' => 'The texts changed while you were reading. Read the new version.',
    ],

];
