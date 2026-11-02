<?php

return [

    'recovery' => [
        'invalid' => 'Ce code de récupération n’est pas valide.',
        'locked' => 'Trop de tentatives échouées. Réessayez plus tard.',
    ],

    'email' => [
        'sent' => 'Nous avons envoyé un lien à :email. Il n’est utilisable qu’une fois et reste valable :minutes minutes.',
        'already_linked' => 'Ce compte a déjà une adresse e-mail.',
    ],

    'login_link' => [
        'sent' => 'Si cette adresse e-mail est associée à un garage, un lien vous a été envoyé. Il n’est utilisable qu’une fois et reste valable :minutes minutes.',
    ],

    'oauth' => [
        'unavailable' => 'Ce mode de connexion n’est pas disponible.',
        'ticket_invalid' => 'Cette connexion a expiré ou a déjà été utilisée. Recommencez.',
        'no_account' => 'Aucun garage n’est lié à ce compte :provider. Commencez à jouer en invité et liez le compte depuis votre garage.',
        'wrong_account' => 'Cette connexion a été démarrée pour un autre garage. Recommencez depuis le garage que vous voulez lier.',
    ],

    'delete' => [
        'name_mismatch' => 'Saisissez le nom de votre pilote exactement comme il est écrit pour supprimer le compte.',
    ],

    'token' => [
        'invalid' => 'Ce lien n’est pas valide ou a expiré.',
    ],

    'identity' => [
        'taken' => 'Ce moyen de connexion est déjà associé à un autre garage. Connectez-vous à ce garage ou utilisez-en un autre.',
        'provider_linked' => 'Ce compte a déjà un moyen de connexion de ce type.',
    ],

    'name' => [
        'attribute' => 'nom du pilote',
        'taken' => 'Ce nom de pilote est déjà pris.',
    ],

    'email_change' => [
        'sent' => 'Nous avons envoyé un lien à :email. Le changement n’a lieu qu’une fois le lien ouvert (il n’est utilisable qu’une fois et reste valable :minutes minutes). L’ancienne adresse a été prévenue.',
    ],

    'reauth' => [
        'sent' => 'Nous avons envoyé un lien à l’adresse e-mail du compte. Il n’est utilisable qu’une fois et reste valable :minutes minutes. Revenez ici après l’avoir ouvert.',
    ],

    'refused' => [
        'identity_not_found' => 'Ce garage n’a pas ce moyen de connexion.',
        'last_method' => 'C’est le dernier moyen de connexion de ce garage, il ne peut donc pas être retiré. Ajoutez-en d’abord un autre.',
        'reauth_required' => 'Confirmez que c’est vous avant de retirer un moyen de connexion.',
        'reauth_mismatch' => 'Ce n’est pas le compte du fournisseur lié au garage. Connectez-vous avec le compte lié.',
        'reauth_no_method' => 'Ce moyen ne peut pas servir à confirmer, car le garage ne l’a pas.',
        'reauth_unavailable' => 'Votre identité ne peut pas être confirmée depuis cet appareil. Reconnectez-vous.',
        'no_email' => 'Ce garage ne se connecte pas par e-mail. Ajoutez une adresse e-mail au lieu d’en changer.',
        'email_same' => 'C’est déjà l’adresse e-mail de ce garage.',
        'email_change_cooldown' => 'L’adresse e-mail a été changée récemment. Le prochain changement sera possible à partir du :date.',
        'consent_required' => 'Les conditions d’utilisation ou la politique de confidentialité ont changé. Acceptez la nouvelle version pour continuer à jouer.',
        'consent_version_changed' => 'Les textes ont changé pendant votre lecture. Lisez la nouvelle version.',
    ],

];
