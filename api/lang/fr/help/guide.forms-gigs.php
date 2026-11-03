<?php

return [
    'entries' => [
        'gigs' => [
            'label' => 'Comment fonctionne le formulaire des courses',
            'help' => 'Vous réglez ici les petits boulots du jeu : le tableau d’une ville, ce que paie une heure de course à chaque niveau, et les modèles dont les offres sont tirées. Ce que vous saisissez est enregistré comme brouillon des règles et parvient aux joueurs avec le prochain pack publié. La course de nuit, une occasion que la police peut arrêter, a son propre onglet.',
            'steps' => [
                'Onglet Tableau : activez les courses, choisissez l’étape du tutoriel après laquelle elles s’ouvrent, et réglez la durée d’une vague et le nombre d’offres de chaque moment de la journée.',
                'Onglet Paie : réglez les vitesses, les ancres de l’échelle et les tranches des niveaux. Ces chiffres décident de ce que vaut une heure de course face à une heure de tours.',
                'Onglets Types et Modèles d’offres : nommez les types, puis décrivez chaque modèle : ce que la voiture doit transporter, la longueur du trajet, ce qu’il paie et sa probabilité à chaque moment de la journée.',
                'Appuyez sur Calculer la paie sous le formulaire : il montre ce que paie chaque modèle sur chaque voiture du pack, face à l’échelle et face aux tours, et si le contrôle de la publication le laisserait passer.',
                'Enregistrez le brouillon et publiez un pack sur la page Publications. Le contrôle vérifie encore les mêmes chiffres.',
            ],
            'example' => 'Un jeu de bateaux : le type Bac, un modèle qui transporte 1 – 6 passagers entre deux ports à 100 %, un modèle de nuit à 125 % et un modèle de gros pour 1000 – 1800 kg tiré seulement de jour.',
        ],
    ],
];
