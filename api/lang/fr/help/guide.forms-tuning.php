<?php

return [
    'entries' => [
        'tuning' => [
            'label' => 'Comment fonctionnent les bonus des pièces',
            'help' => 'Un pilote met des bonus sur une pièce à l’atelier. Chaque essai coûte une part du prix de la pièce et tire une valeur d’une courbe en cloche autour de la moyenne du bonus. Cette page montre les bonus du jeu, et le studio des paramètres change leurs chiffres.',
            'steps' => [
                'Lisez la liste. Chaque bonus a son nom dans chaque langue, ce qu’il fait, les emplacements qui lui conviennent, son poids dans un tirage et le niveau auquel il s’ouvre.',
                'Lisez la cloche de chaque bonus. Les barres sont la chance de chaque pourcent entier que donne un essai, et le tableau dit combien d’essais il faut en moyenne pour atteindre 6, 8, 10 et 12 pourcents.',
                'Pour changer la moyenne, l’écart, le minimum ou le maximum d’un bonus, le nombre d’emplacements ou le prix d’un essai, ouvrez le studio des paramètres, section « Bonus de pièces (réglage) ». Les limites (8 % sans contexte, 12 % avec) sont vérifiées à la création d’un pack.',
                'Appuyez sur « Calculer » en bas. Il mesure le meilleur ensemble de bonus sur les voitures de référence et dit combien il retire du tour. Au-dessus des 10 % du projet ce n’est qu’un avertissement. Au-dessus du plafond approuvé, la porte tuning:budget arrête la publication du pack.',
                'Publiez un pack sur la page des publications. Les bonus que les pilotes ont déjà sont conservés, et un bonus qu’un pack retire cesse d’agir jusqu’à son retour.',
            ],
            'example' => 'Un bonus « Adhérence sur gravier » avec une moyenne de 5 %, un écart de 2,3 %, de 1 % à 12 % : environ un essai sur trois donne 6 % ou plus, un sur mille donne 12 %. Si vous montez la moyenne à 7 %, le meilleur ensemble sur gravier dépasse le plafond approuvé, et la porte arrête le pack tant que les limites ne baissent pas.',
        ],
    ],
];
