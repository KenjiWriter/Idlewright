<?php

return [
    'entries' => [
        'premium' => [
            'label' => 'Comment fonctionne le formulaire des fonctions payantes',
            'help' => 'Vous réglez ici ce que le jeu vend contre des pièces premium et de l’argent réel : les packs de pièces, l’accélération, le plein automatique, le compte VIP et le pass. Ce que vous saisissez est enregistré comme brouillon des règles et arrive chez les joueurs avec le prochain pack publié.',
            'steps' => [
                'Onglet Pièces : nommez la monnaie, choisissez la monnaie des prix et saisissez les packs de pièces avec leurs prix ; le premier pack fixe le prix d’une pièce auquel les autres sont mesurés.',
                'Onglets Plein automatique, Compte VIP et Pass : activez la fonction et remplissez ses chiffres ; une fonction désactivée n’est pas proposée, mais ce que les joueurs ont déjà est conservé.',
                'Lisez les lignes sous le formulaire : elles convertissent les pièces en argent au prix d’une pièce du premier pack et montrent le bonus de chaque pack.',
                'Enregistrez le brouillon ; la barre au-dessus du formulaire dit ce qui a changé. Les mêmes chiffres sont dans le studio des paramètres, et les deux modifient le même brouillon.',
                'Publiez un pack sur la page Publications. Le prestataire de paiement est une étape distincte que le jeu n’a pas encore.',
            ],
            'example' => 'Un jeu de bateaux : la monnaie Doubloon, trois packs (50 pour 4,99, 220 pour 19,99, 600 pour 49,99), plein automatique avec un stock de 100, compte VIP de 30 jours à 150 doublons avec +20 % d’argent et d’XP, et le pass laissé désactivé tant qu’aucun prestataire de paiement n’est connecté.',
        ],
    ],
];
