<?php

return [
    'entries' => [
        '/dynamics/power_ppm' => [
            'label' => 'Puissance à la route',
            'help' => 'La part de la puissance par masse d’une voiture (sa statistique d’accélération, en kW par tonne) qui arrive à la route sous forme d’accélération quand un tour est roulé sur un tracé. 60 % signifie qu’une voiture de 100 kW par tonne accélère comme un point matériel avec 60 W par kg de motricité.',
            'range' => 'De 10 % à 200 % (d’après le schéma).',
            'tip' => 'Plus elle est élevée, plus l’accélération décide du tour, et plus chaque tour sur un tracé est court. Elle ne déplace pas le tour de la voiture de référence d’un circuit, fixé par le temps de base du circuit, seulement l’écart des autres voitures avec elle.',
        ],
        '/dynamics/lateral_g_ppm' => [
            'label' => 'Adhérence en virage (g)',
            'help' => 'Ce que donnent une adhérence et une tenue de route de 100 sur le côté dans un virage, en g, affiché en pourcentage : 95 % font 0,95 g. Un virage se roule à la vitesse où l’accélération latérale vaut cette valeur multipliée par l’adhérence et la tenue de route de la voiture divisées par 100.',
            'range' => 'De 30 % à 250 % (d’après le schéma).',
            'tip' => 'Cela change l’importance des virages face aux lignes droites : augmentez-la et les voitures freinées par les virages gagnent sur celles freinées par la puissance.',
        ],
        '/dynamics/brake_g_ppm' => [
            'label' => 'Freinage (g)',
            'help' => 'Ce que donne une statistique de freinage de 100 sur les freins, en g, affiché en pourcentage : 100 % font 1 g. Elle fixe à quel point une voiture peut freiner tard avant un virage : la distance pour ralentir depuis une vitesse est la vitesse au carré divisée par deux fois la décélération.',
            'range' => 'De 30 % à 300 % (d’après le schéma).',
            'tip' => 'Augmentez-la et toutes les voitures freinent plus tard, de sorte qu’un tour se joue moins au freinage et plus à la puissance et aux virages.',
        ],
        '/dynamics/launch_g_ppm' => [
            'label' => 'Adhérence au départ (g)',
            'help' => 'Ce qu’une adhérence de 100 donne à l’accélération au départ et en sortie de virages lents, en g, affiché en pourcentage. Jusqu’à la vitesse où le moteur ne peut plus donner davantage, la voiture accélère avec cette valeur multipliée par son adhérence divisée par 100 ; au-delà, c’est la puissance qui décide.',
            'range' => 'De 30 % à 250 % (d’après le schéma).',
            'tip' => 'Augmentez-la et les voitures puissantes avec de mauvais pneus ou sur une mauvaise surface perdent moins au départ. Sur le gravier et la boue, les pneus multiplient l’adhérence, la limite y est donc plus basse.',
        ],
        '/dynamics/agility/min_ppm' => [
            'label' => 'Part de la tenue de route : grand virage',
            'help' => 'La part de la vitesse dans un grand virage qui dépend de la tenue de route et celle qui dépend de l’adhérence, affichée en pourcentage : 25 % font un quart de tenue de route et trois quarts d’adhérence. Un grand virage a le rayon du champ « Rayon du grand virage » ou plus.',
            'range' => 'De 0 % à 100 % (d’après le schéma).',
            'tip' => 'La part dans un virage d’un rayon entre les deux valeurs diminue en ligne droite de celle de l’épingle à celle-ci.',
        ],
        '/dynamics/agility/max_ppm' => [
            'label' => 'Part de la tenue de route : épingle',
            'help' => 'La part de la vitesse dans une épingle (un rayon proche de zéro) qui dépend de la tenue de route et celle qui dépend de l’adhérence, affichée en pourcentage : 75 % font trois quarts de tenue de route.',
            'range' => 'De 0 % à 100 % (d’après le schéma).',
            'tip' => 'Une voiture lourde, à la tenue de route médiocre, perd le plus dans les épingles ; augmentez-la pour que les épingles comptent davantage pour ces voitures.',
        ],
        '/dynamics/agility/radius_m' => [
            'label' => 'Rayon du grand virage (m)',
            'help' => 'À partir de ce rayon, en mètres, un virage est un grand virage et seule la plus petite part de la tenue de route y compte ; en dessous, la part monte en ligne droite jusqu’à la plus grande, celle de l’épingle.',
            'range' => 'De 20 à 2000 m (d’après le schéma).',
            'tip' => 'Un petit rayon fait de la plupart des virages de grands virages, où l’adhérence décide ; un grand rayon fait compter la tenue de route dans davantage d’entre eux.',
        ],
        '/dynamics/top_speed_ppm/asphalt_dry' => [
            'label' => 'Vitesse maximale : asphalte sec',
            'help' => 'Ce qui reste de la vitesse maximale d’une voiture sur cette surface (asphalte sec), affiché en pourcentage : 100 % ne la retient pas du tout, moins la retient. Il y a un nombre pour chaque surface que la météo peut produire : asphalte sec, asphalte mouillé, gravier sec, boue et neige.',
            'range' => 'De 10 % à 100 % (d’après le schéma).',
            'tip' => 'Une voiture ne va jamais plus vite sur une surface que sa vitesse maximale multipliée par ce nombre. Abaissez la boue et la neige pour qu’un circuit par mauvais temps soit un lieu pour voitures lentes.',
        ],
        '/dynamics/top_speed_ppm/asphalt_wet' => [
            'label' => 'Vitesse maximale : asphalte mouillé',
            'help' => 'Ce qui reste de la vitesse maximale d’une voiture sur cette surface (asphalte mouillé), affiché en pourcentage : 100 % ne la retient pas du tout, moins la retient. Il y a un nombre pour chaque surface que la météo peut produire : asphalte sec, asphalte mouillé, gravier sec, boue et neige.',
            'range' => 'De 10 % à 100 % (d’après le schéma).',
            'tip' => 'Une voiture ne va jamais plus vite sur une surface que sa vitesse maximale multipliée par ce nombre. Abaissez la boue et la neige pour qu’un circuit par mauvais temps soit un lieu pour voitures lentes.',
        ],
        '/dynamics/top_speed_ppm/gravel_dry' => [
            'label' => 'Vitesse maximale : gravier sec',
            'help' => 'Ce qui reste de la vitesse maximale d’une voiture sur cette surface (gravier sec), affiché en pourcentage : 100 % ne la retient pas du tout, moins la retient. Il y a un nombre pour chaque surface que la météo peut produire : asphalte sec, asphalte mouillé, gravier sec, boue et neige.',
            'range' => 'De 10 % à 100 % (d’après le schéma).',
            'tip' => 'Une voiture ne va jamais plus vite sur une surface que sa vitesse maximale multipliée par ce nombre. Abaissez la boue et la neige pour qu’un circuit par mauvais temps soit un lieu pour voitures lentes.',
        ],
        '/dynamics/top_speed_ppm/mud' => [
            'label' => 'Vitesse maximale : boue',
            'help' => 'Ce qui reste de la vitesse maximale d’une voiture sur cette surface (boue), affiché en pourcentage : 100 % ne la retient pas du tout, moins la retient. Il y a un nombre pour chaque surface que la météo peut produire : asphalte sec, asphalte mouillé, gravier sec, boue et neige.',
            'range' => 'De 10 % à 100 % (d’après le schéma).',
            'tip' => 'Une voiture ne va jamais plus vite sur une surface que sa vitesse maximale multipliée par ce nombre. Abaissez la boue et la neige pour qu’un circuit par mauvais temps soit un lieu pour voitures lentes.',
        ],
        '/dynamics/top_speed_ppm/snow' => [
            'label' => 'Vitesse maximale : neige',
            'help' => 'Ce qui reste de la vitesse maximale d’une voiture sur cette surface (neige), affiché en pourcentage : 100 % ne la retient pas du tout, moins la retient. Il y a un nombre pour chaque surface que la météo peut produire : asphalte sec, asphalte mouillé, gravier sec, boue et neige.',
            'range' => 'De 10 % à 100 % (d’après le schéma).',
            'tip' => 'Une voiture ne va jamais plus vite sur une surface que sa vitesse maximale multipliée par ce nombre. Abaissez la boue et la neige pour qu’un circuit par mauvais temps soit un lieu pour voitures lentes.',
        ],
        '/dynamics/rough/speed_kmh' => [
            'label' => 'Vitesse sur un tronçon accidenté (km/h)',
            'help' => 'La vitesse, en km/h, à laquelle une voiture ayant toute la garde au sol qu’exige un tronçon accidenté le franchit. Aucune voiture ne va plus vite sur un tronçon marqué comme accidenté, si bonne soit-elle ; une voiture avec moins de garde au sol va plus lentement.',
            'range' => 'De 20 à 400 km/h (d’après le schéma).',
            'tip' => 'Le caractère accidenté d’un tronçon se fixe sur le tronçon du tracé d’un circuit, sur l’échelle de la statistique de garde au sol.',
        ],
        '/dynamics/rough/exponent_ppm' => [
            'label' => 'Ralentissement sur un tronçon accidenté',
            'help' => 'À quelle vitesse une voiture qui manque de garde au sol ralentit sur un tronçon accidenté, affiché en pourcentage d’un exposant : sa vitesse sur le tronçon est la vitesse ci-dessus multipliée par (sa garde au sol divisée par ce qu’exige le tronçon) à cette puissance. 50 % est la racine carrée : une voiture avec le quart de la garde au sol va deux fois moins vite.',
            'range' => 'De 5 % à 300 % (d’après le schéma).',
            'tip' => 'Augmentez-le pour pénaliser davantage les voitures basses, diminuez-le pour les laisser passer presque aussi vite que les hautes.',
        ],
    ],
];
