<?php

return [
    'entries' => [
        'track' => [
            'label' => 'Circuit',
            'help' => 'Le circuit dont vous voyez et modifiez le tracé. Les autres circuits restent tels quels.',
            'tip' => 'Enregistrez avant de passer à un autre circuit : les modifications de celui que vous quittez ne sont pas conservées.',
        ],
        'lap_from' => [
            'label' => 'Tour d’après',
            'help' => 'D’où vient le tour d’une voiture sur ce circuit : de ses statistiques à travers les exigences du circuit (la formule de tous les circuits jusqu’ici), ou d’un tour roulé sur le tracé du circuit. Avec la formule, le tracé ne fait que dessiner le circuit.',
            'tip' => 'Avant de passer un circuit sur le tracé, regardez le tableau des voitures sous la ligne : il montre de combien chaque tour changerait. Le tour de la voiture de référence reste le temps de base du circuit dans les deux cas.',
            'options' => [
                'stats' => ['label' => 'Formule (statistiques)', 'help' => 'Le tour est fait des statistiques de la voiture et des exigences du circuit, comme avant. Le tracé, s’il y en a un, n’est qu’un dessin.'],
                'thread' => ['label' => 'Tracé', 'help' => 'Le tour est roulé sur les sections du tracé : la voiture démarre, accélère sur les droites, freine avant les virages et tient la surface.'],
            ],
        ],
        'mode' => [
            'label' => 'Manière de rouler',
            'help' => 'Un circuit est une boucle roulée lancée : la voiture franchit la ligne à la vitesse à laquelle elle l’a quittée. Un parcours est roulé à chaque fois départ arrêté, comme une piste de drag ou un sprint.',
            'options' => [
                'circuit' => ['label' => 'Circuit', 'help' => 'Une boucle ; la voiture ne s’arrête pas à la ligne.'],
                'run' => ['label' => 'Parcours', 'help' => 'Chaque tour part de l’arrêt, et le retour au départ prend un temps fixé.'],
            ],
        ],
        'return_s' => [
            'label' => 'Retour au départ (s)',
            'help' => 'Ce que le retour au départ ajoute à chaque tour d’un parcours, en secondes, quelle que soit la voiture. Un circuit n’a pas de retour.',
            'range' => 'De 0 à 600 s (d’après le schéma).',
            'tip' => 'Il ramène une piste de drag au tour voulu sans changer les sections ; plus il est long, moins les voitures diffèrent par leurs tours.',
        ],
        'sections' => [
            'label' => 'Sections',
            'help' => 'Le tracé comme une chaîne de sections dans l’ordre où elles sont roulées : droites et virages. Changez l’ordre avec les flèches ; la ligne à côté en est dessinée.',
        ],
        'type' => [
            'label' => 'Type de section',
            'help' => 'Une droite continue tout droit ; un virage fait tourner la voiture d’un angle sur un rayon.',
            'options' => [
                'straight' => ['label' => 'Ligne droite', 'help' => 'A une longueur.'],
                'corner' => ['label' => 'Virage', 'help' => 'A un rayon et un angle.'],
            ],
        ],
        'length' => [
            'label' => 'Longueur (m)',
            'help' => 'La longueur de la droite, en mètres.',
            'range' => 'De 5 à 5000 m (d’après le schéma).',
            'tip' => 'La longueur du circuit est la somme des sections, et elle est inscrite à l’enregistrement.',
        ],
        'radius' => [
            'label' => 'Rayon (m)',
            'help' => 'Le rayon du virage, en mètres. Une épingle fait environ 20 m, un virage serré 35, un grand virage plus de 100. La vitesse dedans vient de l’adhérence et de la tenue de route de la voiture.',
            'range' => 'De 8 à 1000 m (d’après le schéma).',
        ],
        'angle' => [
            'label' => 'Angle (°)',
            'help' => 'De combien le virage fait tourner la voiture, en degrés : 90 est un angle droit, 180 une épingle.',
            'range' => 'De 5 à 360° (d’après le schéma).',
            'tip' => 'Les virages d’une boucle font environ 360° en tout (les virages à gauche comptent contre ceux à droite) ; sinon la ligne ne se referme pas sur le dessin.',
        ],
        'turn' => [
            'label' => 'Tourne',
            'help' => 'De quel côté tourne le virage. Cela ne fait que dessiner la ligne : le tour n’en dépend pas.',
            'options' => [
                'left' => ['label' => 'À gauche', 'help' => 'La ligne tourne à gauche.'],
                'right' => ['label' => 'À droite', 'help' => 'La ligne tourne à droite.'],
            ],
        ],
        'surface' => [
            'label' => 'Surface',
            'help' => 'De quoi est faite la section quand ce n’est pas ce dont est fait le circuit : un tronçon de gravier dans une spéciale de rallye. Vide signifie la surface du circuit. La météo la rend mouillée, boueuse ou enneigée, et les pneus d’une voiture adhèrent différemment à chacune.',
            'options' => [
                'asphalt' => ['label' => 'Asphalte', 'help' => 'Asphalte sec ; la pluie le rend mouillé, la neige le recouvre.'],
                'gravel' => ['label' => 'Gravier', 'help' => 'Gravier meuble ; la pluie le transforme en boue, la neige le recouvre. Il retient la vitesse maximale d’une voiture et demande les bons pneus.'],
            ],
        ],
        'limit' => [
            'label' => 'Limite de vitesse (km/h)',
            'help' => 'Une vitesse au-delà de laquelle aucune voiture ne va dans cette section : une rue, une voie des stands, la route du retour. Vide signifie aucune.',
            'range' => 'De 20 à 400 km/h (d’après le schéma).',
        ],
        'rough' => [
            'label' => 'Irrégularité',
            'help' => 'À quel point la section est accidentée, sur l’échelle de la statistique de garde au sol : une voiture avec au moins cette garde au sol la franchit à la vitesse du terrain accidenté, une voiture avec moins va plus lentement. Vide ou 0 signifie lisse.',
            'range' => 'De 0 à 100 (d’après le schéma).',
            'tip' => 'La vitesse sur terrain accidenté et la rapidité avec laquelle une voiture basse ralentit sont dans le studio (« Vitesse sur un tronçon accidenté », « Ralentissement sur un tronçon accidenté »).',
        ],
    ],
];
