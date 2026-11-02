<?php

return [
    'entries' => [
        '/streaks/goal/daily_done' => [
            'label' => 'Missions du jour qui font l’objectif',
            'help' => 'Combien de missions de la série du jour un joueur doit accomplir (accomplies, pas forcément récupérées) pour que la journée compte dans la série de jours. Une série qui a moins de missions les demande toutes. La série de jours est comptée à partir de la série du jour, il n’y a donc pas de tâches en plus.',
            'example' => '3 : trois des six missions du jour, si bien qu’un joueur qui passe une fois par jour peut garder sa série.',
            'range' => '1 – 20 (missions).',
            'tip' => 'Un nombre plus bas protège la série des joueurs qui passent rarement  un nombre plus haut demande de vraiment jouer chaque jour.',
        ],
        '/streaks/shield/every_days' => [
            'label' => 'Jours de série par bouclier',
            'help' => 'Un joueur gagne un bouclier quand sa série atteint un multiple de ce nombre de jours. Un bouclier sauve la série d’un jour manqué : la série reste où elle est, et ce jour ne rapporte rien.',
            'example' => '7 : un bouclier au 7e, au 14e et au 21e jour d’une série.',
            'range' => '1 – 365 (jours).',
        ],
        '/streaks/shield/cap' => [
            'label' => 'Nombre maximal de boucliers gardés',
            'help' => 'Combien de boucliers un joueur peut garder en même temps. Un bouclier gagné au-delà est perdu. Avec 0, le jeu n’a pas de boucliers et un jour manqué met fin à la série.',
            'example' => '2 : après deux jours manqués de suite la série est sauvée deux fois, et le troisième y met fin.',
            'range' => '0 – 10 (boucliers).',
            'tip' => 'Les boucliers se gagnent en jouant et ne s’achètent pas : un grand nombre est donc doux, et ne sert pas à vendre la série.',
        ],
        '/streaks/cycle/*/cash_ppm' => [
            'label' => 'Argent d’un jour du cycle (part d’un plein)',
            'help' => 'L’argent que rapporte un jour de série, en part des gains nets d’un plein de la meilleure voiture du joueur (100 % est un plein entier) : il grandit donc avec le joueur et n’a pas besoin d’être retouché. Chaque entrée du cycle est un jour, et après la dernière le cycle recommence. C’est en plus des gains des voitures et cela ne compte pas dans l’économie du jeu.',
            'example' => '2 % : le premier jour du cycle rapporte deux centièmes de plein  le dernier jour d’une semaine rapporte en général quatre fois plus.',
            'range' => '0 – 100 % (une part d’un plein).',
            'tip' => 'Vérifiez la somme d’un mois avec la commande streaks:budget : elle doit rester à quelques pour cent de ce que rapportent les voitures.',
        ],
        '/streaks/cycle/*/xp_ppm' => [
            'label' => 'XP d’un jour du cycle (part d’un plein)',
            'help' => 'Les XP que rapporte un jour de série, en part des XP d’un plein de la meilleure voiture du joueur (100 % est un plein entier). Le niveau d’un joueur est une porte du jeu : gardez cette valeur basse.',
            'example' => '1 % : un jour du cycle rapporte un centième des XP d’un plein.',
            'range' => '0 – 100 % (une part d’un plein).',
        ],
        '/streaks/milestones/*/day' => [
            'label' => 'Longueur de série d’une étape (jours)',
            'help' => 'La longueur de la série, en jours, à laquelle l’étape donne ce qu’elle offre (les caisses, et l’argent et les XP s’il y en a) en plus du jour du cycle. Chaque longueur n’est nommée qu’une fois.',
            'example' => '30 : une série de trente jours donne son étape le trentième jour, une seule fois.',
            'range' => '2 – 3650 (jours).',
        ],
    ],
];
