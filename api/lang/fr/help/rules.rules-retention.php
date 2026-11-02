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
        '/slots/base' => [
            'label' => 'Voitures que chaque joueur peut avoir sur la route',
            'help' => 'Combien de voitures un joueur peut avoir actives (qui roulent) en même temps. Les autres voitures restent en réserve dans le garage : elles ne roulent pas, ne brûlent pas de carburant, ne s’usent pas et ne rapportent rien, et elles gardent leur place au garage. Les voitures qu’un joueur avait avant la limite lui restent tant qu’il n’en met pas une en réserve.',
            'example' => '2 : un joueur peut avoir deux voitures sur la route ; une troisième va en réserve quand il l’obtient.',
            'range' => '1 – 50 (voitures).',
            'tip' => 'Le simulateur d’économie suppose environ 1,7 voiture sur la route pour un joueur régulier : 2 ne change donc pas le rythme des joueurs qui ne paient pas. Vérifiez-le avec la commande economy:simulate après un changement.',
        ],
        '/slots/vip' => [
            'label' => 'Voitures de plus sur la route avec le compte VIP',
            'help' => 'Combien de voitures de plus un joueur avec le compte VIP peut avoir sur la route, tant que le compte dure. Les voitures de ces places cessent de rapporter à la fin du compte et vont en réserve ; rien n’est perdu.',
            'example' => '3 : avec le compte VIP un joueur peut avoir cinq voitures sur la route (2 + 3), soit avec le bonus du compte environ trois fois ce que gagne un joueur avec deux voitures.',
            'range' => '0 – 50 (voitures).',
            'tip' => 'C’est la plus grande part de la valeur du compte VIP : plus de voitures rapportent plus que le bonus d’argent et d’XP. Vérifiez l’avantage avec le simulateur d’économie avant de l’augmenter.',
        ],
        '/slots/swap_ms' => [
            'label' => 'Temps pour mettre une voiture sur la route',
            'help' => 'Combien de temps une voiture sortie de la réserve est préparée : elle roule à partir de ce moment et ne rapporte rien avant. Cela évite qu’un joueur change de voiture à chaque averse.',
            'example' => '120 s : deux minutes.',
            'range' => '0 – 3600 s.',
        ],
        '/slots/swap_fee_ppm' => [
            'label' => 'Coût d’un échange (part d’un plein)',
            'help' => 'Ce que coûte la mise sur la route d’une voiture de la réserve à la place d’une voiture qui y est, en part des gains nets d’un plein de la meilleure voiture du joueur (100 % est un plein entier), en dollars entiers et au moins un. Mettre une voiture sur une place libre ne coûte rien.',
            'example' => '2 % : pour une voiture qui gagne 375 $ par plein, un échange coûte 8 $.',
            'range' => '0 – 100 % (une part d’un plein).',
        ],
    ],
];
