<?php

return [
    'entries' => [
        '/tuning/slots' => [
            'label' => 'Emplacements de bonus',
            'help' => 'Combien d’emplacements de bonus a une pièce, sauf si son modèle dit autre chose. Le joueur les remplit et les change à l’atelier.',
            'example' => '3 : chaque pièce a trois emplacements, vides au départ.',
        ],
        '/tuning/unlock_level' => [
            'label' => 'Niveau à partir duquel l’atelier règle',
            'help' => 'À partir de quel niveau de joueur l’atelier règle les pièces, c’est-à-dire y pose des bonus.',
            'example' => '3 : à partir du niveau trois.',
        ],
        '/tuning/cost_random_ppm' => [
            'label' => 'Prix d’un essai avec un bonus aléatoire',
            'help' => 'Quelle part du prix du modèle de la pièce coûte un essai quand le serveur tire le bonus. 30 000 font 3 %.',
            'example' => '30 000 : une pièce à 570 $ coûte 17,10 $ l’essai.',
        ],
        '/tuning/cost_pick_ppm' => [
            'label' => 'Prix d’un essai avec un bonus choisi',
            'help' => 'Quelle part du prix du modèle coûte un essai quand le joueur choisit le bonus (plus cher qu’un bonus aléatoire). 100 000 font 10 %.',
            'example' => '100 000 : une pièce à 570 $ coûte 57 $ l’essai.',
        ],
        '/tuning/cost_min_cents' => [
            'label' => 'Prix minimal d’un essai',
            'help' => 'Ce qu’un essai coûte au minimum, même pour les pièces bon marché, en cents.',
            'example' => '500 : jamais moins de 5 $.',
        ],
        '/tuning/time_ppm_of_mount' => [
            'label' => 'Durée d’un essai',
            'help' => 'Combien dure un essai par rapport au temps de montage de la pièce. 500 000 font la moitié.',
            'example' => '500 000 : le montage dure 2 minutes, un essai 1 minute.',
        ],
        '/tuning/floor_sigma_ppm' => [
            'label' => 'Plancher d’un nouveau bonus',
            'help' => 'La première valeur d’un bonus qui prend la place n’est pas plus basse que cela sous sa moyenne, en millionièmes de sigma. Changer de bonus pour une autre piste n’est donc pas une punition.',
            'example' => '1 000 000 : jamais plus bas qu’une sigma sous la moyenne.',
        ],
        '/tuning/pity_step_ppm' => [
            'label' => 'Pas de la protection contre la malchance',
            'help' => 'De combien (en millionièmes de sigma) la moyenne monte après chaque essai à cet emplacement qui n’a rien amélioré. 0 désactive la protection.',
            'example' => '0 : sans protection ; 50 000 : après 10 essais sans gain la moyenne monte d’une demi-sigma.',
        ],
        '/tuning/pity_max_ppm' => [
            'label' => 'Maximum de la protection',
            'help' => 'De combien au plus la protection contre la malchance déplace la moyenne, en millionièmes de sigma.',
            'example' => '1 000 000 : d’une sigma au plus.',
        ],
        '/tuning/car_cap_ppm' => [
            'label' => 'Plafond des bonus sur une voiture',
            'help' => 'Combien au plus les bonus de toutes les pièces d’une voiture peuvent ajouter à une statistique dans un contexte, en ppm.',
            'example' => '250 000 : pas plus de +25 %.',
        ],
        '/tuning/max_ppm_plain' => [
            'label' => 'Bonus maximal sans contexte',
            'help' => 'Le maximum qu’un bonus valable sur toutes les pistes peut donner au mieux. Un pack avec un bonus qui peut donner plus est refusé.',
            'example' => '80 000 : +8 % au plus.',
        ],
        '/tuning/max_ppm_context' => [
            'label' => 'Bonus maximal avec contexte',
            'help' => 'Le maximum qu’un bonus qui ne vaut que sur un type de piste, une surface ou une météo peut donner au mieux. Un contexte étroit permet plus.',
            'example' => '120 000 : +12 % au plus.',
        ],
        '/tuning/definitions/*/weight' => [
            'label' => 'Poids du bonus',
            'help' => 'À quelle fréquence le bonus est tiré parmi ceux qui conviennent à l’emplacement de la pièce, quand le joueur ne choisit pas.',
            'example' => '3 à côté d’autres de poids 3 et 2 : il sort 3 fois sur 8.',
        ],
        '/tuning/definitions/*/from_level' => [
            'label' => 'Niveau à partir duquel le bonus est proposé',
            'help' => 'À partir de quel niveau de joueur le bonus apparaît à l’atelier. En dessous, il ne peut être ni tiré ni choisi.',
            'example' => '8 : le bonus de neige pour les joueurs dès le niveau 8.',
        ],
        '/tuning/definitions/*/mean_ppm' => [
            'label' => 'Valeur la plus fréquente du bonus',
            'help' => 'Le milieu de la courbe : la valeur autour de laquelle le bonus est tiré. 50 000 font 5 %.',
            'example' => '50 000 : il sort le plus souvent autour de 5 %.',
        ],
        '/tuning/definitions/*/sigma_ppm' => [
            'label' => 'Dispersion du bonus (sigma)',
            'help' => 'À quel point les valeurs se répartissent autour de la moyenne : plus la sigma est grande, plus souvent sortent des valeurs éloignées de la moyenne. Une valeur à deux sigmas au-dessus de la moyenne sort une fois sur 70 essais.',
            'example' => '23 000 : une dispersion de 2,3 %, donc 10 % sort une fois sur 70 essais.',
        ],
        '/tuning/definitions/*/min_ppm' => [
            'label' => 'Valeur minimale du bonus',
            'help' => 'Le minimum que peut donner un bonus. Une valeur tirée plus basse est relevée jusqu’à lui.',
            'example' => '10 000 : jamais en dessous de 1 %.',
        ],
        '/tuning/definitions/*/max_ppm' => [
            'label' => 'Valeur maximale du bonus',
            'help' => 'Le maximum que peut donner un bonus. Une valeur juste en dessous est très rare. Elle ne peut pas dépasser la limite d’un bonus avec ou sans contexte des réglages généraux.',
            'example' => '120 000 : 12 % au plus, et il sort une fois sur plus de mille essais.',
        ],
    ],
];
