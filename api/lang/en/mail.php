<?php

return [

    'account_link' => [
        'claim' => [
            'subject' => 'Confirm your e-mail to secure your garage',
            'intro' => 'Someone asked to secure the garage of :driver with this e-mail. If it was you, confirm it below.',
            'action' => 'Confirm e-mail',
        ],
        'add' => [
            'subject' => 'Confirm your e-mail to add it to your garage',
            'intro' => 'Someone asked to add this e-mail as a way to sign in to the garage of :driver. If it was you, confirm it below.',
            'action' => 'Confirm e-mail',
        ],
        'login' => [
            'subject' => 'Your link to sign in to RideIdle',
            'intro' => 'Use the button below to sign in to the garage of :driver.',
            'action' => 'Sign in',
        ],
        'existing' => [
            'subject' => 'This e-mail already has a garage',
            'intro' => 'Someone tried to secure a new garage with this e-mail, but it already belongs to the garage of :driver. If it was you and you want to use that garage on this device, sign in below.',
            'action' => 'Sign in',
        ],
        'change' => [
            'subject' => 'Confirm the new e-mail of your garage',
            'intro' => 'Someone asked to change the e-mail of the garage of driver :driver to this address. If it was you, confirm it below: nothing changes until then.',
            'action' => 'Confirm the new address',
        ],
        'reauth' => [
            'subject' => 'Confirm that it is you',
            'intro' => 'Unlinking a sign-in from the garage of driver :driver needs you to confirm that it is you. If it is, confirm below.',
            'action' => 'Yes, it is me',
        ],
        'expires' => 'The link works once and for :minutes minutes.',
        'ignore' => 'If it was not you, ignore this message: nothing changes without the link.',
        'fallback' => 'If the button does not work, copy this address into your browser:',
    ],

    'account_notice' => [
        'change_asked' => [
            'subject' => 'Someone asked to change the e-mail of your garage',
            'intro' => 'Someone asked to change the e-mail of the garage of driver :driver to :detail. Nothing changes until someone opens the link sent to the new address. If it was not you, secure the garage: we cancel the change and sign out every device.',
            'action' => 'This was not me',
        ],
        'changed' => [
            'subject' => 'The e-mail of your garage was changed',
            'intro' => 'The e-mail of the garage of driver :driver was changed to :detail. This address no longer signs in. If it was not you, you can undo it: we put this address back and sign out every device.',
            'action' => 'This was not me, undo the change',
        ],
        'unlinked' => [
            'subject' => 'A sign-in was removed from your garage',
            'intro' => 'A sign-in was removed from the garage of driver :driver: :detail. If it was not you, sign in to the garage and check your sign-ins.',
        ],
        'methods' => [
            'email' => 'e-mail',
            'google' => 'Google',
            'facebook' => 'Facebook',
            'apple' => 'Apple',
        ],
        'valid' => '{1} The link works for :count day.|[2,*] The link works for :count days.',
        'footer' => 'This is a security notice from RideIdle. You do not need to reply.',
    ],

];
