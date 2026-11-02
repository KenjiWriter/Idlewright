<?php

return [

    'account_link' => [
        'claim' => [
            'subject' => 'Confirmez votre adresse e-mail pour sécuriser votre garage',
            'intro' => 'Quelqu’un a demandé à sécuriser le garage du pilote :driver avec cette adresse e-mail. Si c’était vous, confirmez-la ci-dessous.',
            'action' => 'Confirmer l’adresse e-mail',
        ],
        'add' => [
            'subject' => 'Confirmez votre adresse e-mail pour l’ajouter à votre garage',
            'intro' => 'Quelqu’un a demandé à ajouter cette adresse e-mail comme moyen de connexion au garage du pilote :driver. Si c’était vous, confirmez-la ci-dessous.',
            'action' => 'Confirmer l’adresse e-mail',
        ],
        'login' => [
            'subject' => 'Votre lien de connexion à RideIdle',
            'intro' => 'Utilisez le bouton ci-dessous pour vous connecter au garage du pilote :driver.',
            'action' => 'Se connecter',
        ],
        'existing' => [
            'subject' => 'Cette adresse e-mail a déjà un garage',
            'intro' => 'Quelqu’un a essayé de sécuriser un nouveau garage avec cette adresse e-mail, mais elle est déjà associée au garage du pilote :driver. Si c’était vous et que vous voulez utiliser ce garage sur cet appareil, connectez-vous ci-dessous.',
            'action' => 'Se connecter',
        ],
        'change' => [
            'subject' => 'Confirmez la nouvelle adresse e-mail de votre garage',
            'intro' => 'Quelqu’un a demandé de changer l’adresse e-mail du garage du pilote :driver pour celle-ci. Si c’était vous, confirmez-le ci-dessous : rien ne change avant.',
            'action' => 'Confirmer la nouvelle adresse',
        ],
        'reauth' => [
            'subject' => 'Confirmez que c’est vous',
            'intro' => 'Retirer un moyen de connexion du garage du pilote :driver demande de confirmer que c’est vous. Si c’est le cas, confirmez ci-dessous.',
            'action' => 'Oui, c’est moi',
        ],
        'expires' => 'Le lien n’est utilisable qu’une fois et reste valable :minutes minutes.',
        'ignore' => 'Si ce n’était pas vous, ignorez ce message : rien ne change sans le lien.',
        'fallback' => 'Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :',
    ],

    'account_notice' => [
        'change_asked' => [
            'subject' => 'Quelqu’un demande de changer l’e-mail de votre garage',
            'intro' => 'Quelqu’un a demandé de changer l’adresse e-mail du garage du pilote :driver pour :detail. Rien ne change tant que personne n’ouvre le lien envoyé à la nouvelle adresse. Si ce n’était pas vous, sécurisez le garage : nous annulons le changement et déconnectons tous les appareils.',
            'action' => 'Ce n’était pas moi',
        ],
        'changed' => [
            'subject' => 'L’adresse e-mail de votre garage a été changée',
            'intro' => 'L’adresse e-mail du garage du pilote :driver a été changée pour :detail. Cette adresse ne sert plus à se connecter. Si ce n’était pas vous, vous pouvez annuler : nous remettons cette adresse et déconnectons tous les appareils.',
            'action' => 'Ce n’était pas moi, annuler le changement',
        ],
        'unlinked' => [
            'subject' => 'Un moyen de connexion a été retiré de votre garage',
            'intro' => 'Un moyen de connexion a été retiré du garage du pilote :driver : :detail. Si ce n’était pas vous, connectez-vous au garage et vérifiez vos moyens de connexion.',
        ],
        'methods' => [
            'email' => 'adresse e-mail',
            'google' => 'Google',
            'facebook' => 'Facebook',
            'apple' => 'Apple',
        ],
        'valid' => '{1} Le lien reste valable :count jour.|[2,*] Le lien reste valable :count jours.',
        'footer' => 'Ceci est une notification de sécurité de RideIdle. Vous n’avez pas besoin d’y répondre.',
    ],

];
