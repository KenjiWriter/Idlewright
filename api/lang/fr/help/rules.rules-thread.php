<?php

return [
    'entries' => [
        '/tracks/*/thread/return_ms' => [
            'label' => 'Retour au départ d’un parcours (s)',
            'help' => 'Ce que le retour au départ ajoute à chaque tour d’un circuit roulé en parcours (une piste de drag, un sprint), en secondes, quelle que soit la voiture. Un circuit n’a pas de retour.',
            'range' => 'De 0 à 600 s (d’après le schéma).',
            'tip' => 'Il ramène un parcours au tour voulu sans changer ses sections ; plus il est long, moins les voitures diffèrent par leurs tours. Il ne compte que si le tour est roulé sur le tracé.',
        ],
        '/tracks/*/thread/sections/*/length_m' => [
            'label' => 'Ligne droite : longueur (m)',
            'help' => 'La longueur d’une ligne droite du tracé du circuit, en mètres. La longueur du circuit est la somme des sections, chacune arrondie au mètre, un virage comptant pour son arc.',
            'range' => 'De 5 à 5000 m (d’après le schéma).',
            'tip' => 'Changez la longueur d’une droite et la longueur du circuit doit changer avec elle : le validateur signale un écart de plus d’un mètre. La page du tracé le fait pour vous.',
        ],
        '/tracks/*/thread/sections/*/radius_m' => [
            'label' => 'Virage : rayon (m)',
            'help' => 'Le rayon d’un virage du tracé du circuit, en mètres. Une épingle fait environ 20 m, un virage serré 35, un grand virage plus de 100. La vitesse dedans vient de l’adhérence et de la tenue de route de la voiture.',
            'range' => 'De 8 à 1000 m (d’après le schéma).',
            'tip' => 'La vitesse dans un virage est la racine carrée de l’accélération latérale fois le rayon, donc un virage deux fois plus large se prend environ 40 % plus vite.',
        ],
        '/tracks/*/thread/sections/*/angle_deg' => [
            'label' => 'Virage : angle (°)',
            'help' => 'De combien un virage du tracé fait tourner la voiture, en degrés : 90 est un angle droit, 180 une épingle.',
            'range' => 'De 5 à 360° (d’après le schéma).',
            'tip' => 'Les virages d’une boucle font environ 360° en tout, les virages à gauche comptant contre ceux à droite ; le validateur prévient quand ce n’est pas le cas, car la ligne ne se refermerait pas.',
        ],
        '/tracks/*/thread/sections/*/rough' => [
            'label' => 'Section : irrégularité',
            'help' => 'À quel point une section du tracé est accidentée, sur l’échelle de la statistique de garde au sol : une voiture avec au moins cette garde au sol la franchit à la vitesse du terrain accidenté, une voiture avec moins va plus lentement. Les sections lisses n’en ont pas.',
            'range' => 'De 0 à 100 (d’après le schéma).',
            'tip' => 'La vitesse sur terrain accidenté et la rapidité avec laquelle une voiture basse ralentit sont dans le studio (« Vitesse sur un tronçon accidenté », « Ralentissement sur un tronçon accidenté »).',
        ],
    ],
];
