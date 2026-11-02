<?php

return [
    'entries' => [
        'missions' => [
            'label' => 'Comment fonctionne le formulaire des missions',
            'help' => 'Vous réglez ici les missions du jeu : quand commencent les lots du jour, de la semaine et du mois et quelle part de la valeur de ses missions chacun paie, les missions parmi lesquelles les lots tirent, les chaînes de missions principales et ce que vaut une unité de chaque sorte de travail. Ce que vous saisissez est enregistré ensemble comme brouillon des règles et du document des missions, et arrive chez les joueurs avec le prochain pack publié.',
            'steps' => [
                'Onglet Récompenses et périodes : activez les missions, choisissez le fuseau horaire et l’heure de début du jour, et réglez quelle part de la valeur de ses missions chaque lot et les missions principales paient (argent et XP en pourcentage), avec les pièces du lot complet.',
                'Onglet Valeur du travail : dites ce que vaut une unité de chaque sorte de travail en réservoirs de la meilleure voiture ; une mission paie une part du travail qu’elle demande, donc une mission qui demande plus paie plus.',
                'Appuyez sur Calculer le budget : il montre ce que valent un jour, une semaine et un mois en argent, XP et pièces pour une voiture sur une piste, et si le contrôle de la publication laisserait passer.',
                'Onglet Lots : pour le lot quotidien, hebdomadaire et mensuel, ajoutez des emplacements et les missions que chacun peut tirer, avec leurs objectifs, poids et multiplicateurs.',
                'Onglet Missions principales : ajoutez des chaînes et leurs missions dans l’ordre d’ouverture, avec leurs objectifs, leurs textes dans les langues et les objets qu’elles donnent.',
                'Enregistrez le brouillon : les deux documents sont gardés ensemble, ou aucun si l’un est refusé. Publiez un pack sur la page Publications.',
            ],
            'example' => 'Un jeu de bateaux : les lots paient 10 % de la valeur en argent et 6 % en XP avec 2, 8 et 25 pièces, les missions principales 15 % et 9 % ; « naviguer 50 tours » vaut 50 tours d’un réservoir de 30 tours fois 1 réservoir de travail, soit environ 1,7 réservoir, et paie 17 % du gain net d’un réservoir dans un lot du jour.',
        ],
    ],
];
