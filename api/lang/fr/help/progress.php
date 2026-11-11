<?php

return [
    'entries' => [
        'variant' => [
            'label' => 'Calculer sur',
            'help' => 'Le pack en ligne est le jeu des joueurs ; les brouillons sont ce que contiendrait un pack fait maintenant.',
            'options' => [
                'live' => [
                    'label' => 'Pack en ligne',
                    'help' => 'Le jeu tel que les joueurs l’ont aujourd’hui.',
                ],
                'drafts' => [
                    'label' => 'Brouillons',
                    'help' => 'Le jeu avec les brouillons enregistrés, avant un pack.',
                ],
            ],
        ],
        'car' => [
            'label' => 'Voiture de départ',
            'help' => 'La voiture avec laquelle le pilote commence. Vide : la voiture de départ la moins chère du jeu.',
            'example' => 'rdzak-gt-4',
        ],
        'style' => [
            'label' => 'Façon de jouer',
            'help' => 'À quelle fréquence le pilote est dans le jeu. Quand il n’y est pas, la voiture roule seule tant que le carburant dure.',
            'options' => [
                'active' => [
                    'label' => 'Joue sans pause',
                    'help' => 'Le pilote est toujours dans le jeu : il fait le plein et achète tout de suite.',
                ],
                'every' => [
                    'label' => 'Revient toutes les N minutes',
                    'help' => 'Le pilote revient toutes les tant de minutes et reste tant de minutes.',
                ],
                'A' => [
                    'label' => 'Joueur A',
                    'help' => 'Le joueur A du simulateur d’économie : 3 visites par jour.',
                ],
                'B' => [
                    'label' => 'Joueur B',
                    'help' => 'Le joueur B du simulateur d’économie : 1 visite par jour.',
                ],
                'C' => [
                    'label' => 'Joueur C',
                    'help' => 'Le joueur C du simulateur d’économie : 8 visites par jour.',
                ],
            ],
        ],
        'every_minutes' => [
            'label' => 'Revient toutes les (minutes)',
            'help' => 'Les minutes entre le début d’une visite et le début de la suivante.',
            'example' => '60',
        ],
        'visit_minutes' => [
            'label' => 'Reste (minutes)',
            'help' => 'La durée d’une visite en minutes ; pas plus que le temps entre deux visites.',
            'example' => '10',
        ],
        'minutes' => [
            'label' => 'Minutes de jeu',
            'help' => 'Combien de temps le simulateur joue depuis la création du compte ; au plus 43 200 minutes (30 jours).',
            'example' => '120 font deux heures, 1440 une journée.',
        ],
        'track' => [
            'label' => 'Circuit',
            'help' => 'Le pilote roule sur ce circuit dès qu’il est ouvert. Vide : toujours le meilleur circuit ouvert.',
        ],
        'goal' => [
            'label' => 'Choisir un circuit selon',
            'help' => 'Ce que le pilote mesure pour choisir le meilleur circuit.',
            'options' => [
                'profit' => [
                    'label' => 'Bénéfice par heure',
                    'help' => 'Le gain moins le carburant, pour une heure de conduite.',
                ],
                'xp' => [
                    'label' => 'XP par heure',
                    'help' => 'L’expérience du pilote pour une heure de conduite.',
                ],
            ],
        ],
        'buy' => [
            'label' => 'Achats',
            'help' => 'Ce que le pilote achète ; il garde toujours l’argent d’un plein.',
            'options' => [
                'none' => [
                    'label' => 'Rien',
                    'help' => 'Le pilote roule avec ce qu’il a reçu.',
                ],
                'list' => [
                    'label' => 'Une liste, dans l’ordre',
                    'help' => 'Il achète les pièces et voitures de la liste une à une, quand l’argent et le niveau le permettent.',
                ],
                'best' => [
                    'label' => 'Le plus de bénéfice par dollar',
                    'help' => 'Il achète ce qui augmente le plus le résultat d’une heure par rapport au prix.',
                ],
            ],
        ],
        'list' => [
            'label' => 'Liste d’achats',
            'help' => 'Les slugs des pièces et des voitures séparés par des virgules, dans l’ordre d’achat.',
            'example' => 'street-tires-t2, disc-brakes-t2',
        ],
        'step_seconds' => [
            'label' => 'Une étape du tutoriel (s)',
            'help' => 'Combien de secondes de jeu prend une étape du tutoriel. Le vrai temps sera mesuré plus tard sur les données des joueurs.',
            'example' => '60',
        ],
        'tutorial' => [
            'label' => 'Avec le tutoriel',
            'help' => 'Le pilote passe d’abord le tutoriel et reçoit ses récompenses.',
        ],
        'missions' => [
            'label' => 'Avec les missions',
            'help' => 'Ajoute les récompenses quotidiennes des missions à la fin de chaque jour de jeu.',
        ],
        'travel' => [
            'label' => 'Voyages',
            'help' => 'Le pilote part dans une autre ville où il a un garage, quand cela rapporte dans le temps qui reste.',
        ],
    ],
];
