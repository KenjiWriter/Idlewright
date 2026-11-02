<?php

return [
    'entries' => [
        '/competition/pi/reference_pi' => [
            'label' => 'PI de la voiture de référence',
            'help' => 'Ce que vaut en PI la voiture de référence (d’origine, sans pièces). C’est l’échelle sur laquelle reposent les limites des classes : la modifier déplace le PI de toutes les voitures dans la même proportion, donc les limites doivent suivre.',
            'example' => '300 : une Rdzak GT-4 d’origine vaut 300 PI, une voiture aux statistiques 50 % meilleures en vaut 450.',
            'range' => '1 – 100 000 (PI).',
        ],
        '/competition/pi/stat_cap_ppm' => [
            'label' => 'Plafond d’une statistique dans le PI (% de la voiture de référence)',
            'help' => 'Le maximum que compte une statistique d’une voiture dans le PI, en multiple de la statistique de la voiture de référence (100 % est identique). Le plafond empêche un seul chiffre énorme d’acheter une classe.',
            'example' => '400 % : une accélération quatre fois celle de la voiture de référence compte quatre fois, pas plus.',
            'range' => '100 % – 10 000 %.',
        ],
        '/competition/pi/weights_ppm/*' => [
            'label' => 'Poids d’une statistique dans le PI',
            'help' => 'Combien cette statistique compte dans le PI d’une voiture (les poids font 100 % au total). Par défaut, les poids sont la moyenne de ce que tous les circuits demandent à une voiture, donc les statistiques sur lesquelles les circuits s’appuient le plus (adhérence, accélération) comptent le plus. Les poids doivent faire 100 % et portent sur les statistiques du modèle du jeu.',
            'example' => '23 % : l’adhérence fait presque un quart du PI.',
            'range' => '0 % – 100 % ; 100 % au total.',
        ],
        '/competition/pi/class_max_pi/*' => [
            'label' => 'Limite haute d’une classe (PI)',
            'help' => 'Le PI le plus élevé que peut encore avoir une voiture de cette classe. Une voiture est dans la première classe dont la limite n’est pas inférieure à son PI ; au-dessus de la dernière limite, c’est la classe supérieure. Les limites doivent croître depuis la classe la plus faible. Un tournoi de classe n’accepte que les voitures de cette classe : un bon réglage donne l’avantage dans la classe, une meilleure voiture ouvre la suivante.',
            'example' => '399 : une voiture à 399 PI est encore en classe C, à 400 elle est en classe B.',
            'range' => '1 – 1 000 000 (PI) ; croissant.',
        ],
        '/competition/rating/start' => [
            'label' => 'Respekt de départ',
            'help' => 'Le Respekt d’un nouveau pilote, et la valeur vers laquelle une saison ramène le rating lors de la remise à zéro douce.',
            'example' => '1000 : un nouveau pilote débute en division rue.',
            'range' => '1 – 100 000.',
        ],
        '/competition/rating/minimum' => [
            'label' => 'Respekt minimal',
            'help' => 'Le Respekt d’un pilote ne descend pas sous ce seuil, quelles que soient ses défaites. Cela évite une chute sans fin.',
            'example' => '600 : un pilote qui perd sans cesse s’arrête à 600.',
            'range' => '0 – 100 000 ; pas plus que le Respekt de départ.',
        ],
        '/competition/rating/k_new' => [
            'label' => 'K d’un nouveau pilote',
            'help' => 'La vitesse à laquelle bouge le Respekt d’un pilote qui a joué moins de matchs classés que ne le dit le nombre suivant. Face à un rival égal, une victoire donne la moitié de ce nombre et une défaite en retire autant. Un K élevé place vite un nouveau joueur à son niveau.',
            'example' => '40 : une victoire sur un rival égal fait +20, une défaite −20.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/new_matches' => [
            'label' => 'Matchs pendant lesquels un pilote est nouveau',
            'help' => 'Pendant ce nombre de matchs classés, un pilote a le grand K. Ensuite le Respekt bouge plus lentement.',
            'example' => '20 : les 20 premiers matchs placent un joueur.',
            'range' => '0 – 1000 matchs.',
        ],
        '/competition/rating/k_established' => [
            'label' => 'K d’un pilote établi',
            'help' => 'La vitesse à laquelle bouge le Respekt d’un pilote qui n’est plus nouveau et reste sous le sommet.',
            'example' => '24 : une victoire sur un rival égal fait +12, une défaite −12.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/k_top' => [
            'label' => 'K d’un pilote au sommet',
            'help' => 'La vitesse à laquelle bouge le Respekt d’un pilote au sommet (à partir du seuil du sommet). Un petit K rend le sommet stable.',
            'example' => '16 : une victoire sur un rival égal fait +8, une défaite −8.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/top_from' => [
            'label' => 'Respekt à partir duquel un pilote est au sommet',
            'help' => 'À partir de ce Respekt, un pilote a le plus petit K. C’est un nombre à part du seuil de la division élite, même si par défaut les deux sont identiques.',
            'example' => '1700.',
            'range' => '1 – 100 000.',
        ],
        '/competition/rating/club_from' => [
            'label' => 'Début de la division club',
            'help' => 'À partir de ce Respekt, un pilote est en division club (en dessous : rue). Les divisions choisissent les adversaires dans les tournois.',
            'example' => '1100.',
            'range' => '1 – 100 000 ; sous la division régionale.',
        ],
        '/competition/rating/regional_from' => [
            'label' => 'Début de la division régionale',
            'help' => 'À partir de ce Respekt, un pilote est en division régionale.',
            'example' => '1400.',
            'range' => '1 – 100 000 ; entre club et élite.',
        ],
        '/competition/rating/elite_from' => [
            'label' => 'Début de la division élite',
            'help' => 'À partir de ce Respekt, un pilote est en division élite, la plus haute.',
            'example' => '1700.',
            'range' => '1 – 100 000 ; au-dessus de la division régionale.',
        ],
        '/competition/rating/season_keep_ppm' => [
            'label' => 'Part du Respekt conservée après une saison (%)',
            'help' => 'La part du Respekt qui reste après la remise à zéro douce au début d’une saison ; le reste revient à la valeur de départ. 100 % ne change rien, 0 % remet tout le monde au départ.',
            'example' => '75 % : un pilote à 1400 commence la saison à 1300, un pilote à 600 à 700.',
            'range' => '0 % – 100 %.',
        ],
        '/competition/match/luck_sigma_ppm' => [
            'label' => 'Largeur de la chance d’une manche (% du temps au tour)',
            'help' => 'De combien de pour cent du temps au tour le temps d’une manche peut changer à cause de la chance : environ deux manches sur trois sont dans cette largeur, et aucune ne dépasse trois fois et un tiers cette valeur. Plus de chance fait gagner plus souvent la voiture la plus faible ; zéro, c’est aucune chance. Elle est tirée de la graine, donc le résultat d’un match peut être rejoué.',
            'example' => '2 % : une voiture 1 % plus rapide gagne une manche 64 % du temps, et un match de trois manches 70 %.',
            'range' => '0 % – 10 %.',
        ],
        '/competition/match/runs' => [
            'label' => 'Nombre de manches d’un match',
            'help' => 'De combien de manches se compose un match ; le côté qui en gagne le plus gagne le match, donc le nombre doit être impair. Chaque manche se court sur un autre circuit, donc plus de manches favorisent une voiture adaptée à de nombreux circuits et réduisent la part de chance.',
            'example' => '3 : le meilleur de trois manches.',
            'range' => '1 – 9, impair.',
        ],
    ],
];
