<?php

return [
    'entries' => [
        'include' => [
            'label' => 'Quoi inclure',
            'help' => 'Ce que l’assistant doit savoir du jeu : les voitures, les pièces et les chiffres des règles. Une réponse ne pourra changer que ce que vous cochez ici.',
            'example' => 'Seulement les pièces, pour changer leurs prix : décochez les voitures et les règles.',
            'tip' => 'Moins vous cochez, plus l’instruction est courte et plus la conversation est précise.',
            'options' => [
                'vehicles' => ['label' => 'Voitures', 'help' => 'Un tableau des voitures avec leurs chiffres et les champs qui permettent de les choisir (transmission, classe).'],
                'parts' => ['label' => 'Pièces', 'help' => 'Un tableau par emplacement (moteur, pneus, …), avec les chiffres des pièces.'],
                'rules' => ['label' => 'Chiffres des règles', 'help' => 'Les chiffres du Studio avec leurs limites et une description de chacun.'],
            ],
        ],
        'simple' => [
            'label' => 'Seulement les chiffres les plus importants',
            'help' => 'Activé : seuls les chiffres du mode simple du Studio (prix, récompenses, durées, niveaux) vont dans l’instruction. Désactivé : tous les chiffres des sections choisies.',
            'tip' => 'Une instruction avec tous les chiffres est plusieurs fois plus longue.',
        ],
        'locale' => [
            'label' => 'Langue des descriptions',
            'help' => 'La langue des libellés et des descriptions des chiffres dans l’instruction. Les règles de la conversation et le format du bloc sont toujours en anglais, et l’assistant répond dans la langue dans laquelle vous lui écrivez.',
            'options' => [
                'pl' => ['label' => 'Polski', 'help' => 'Descriptions en polonais.'],
                'en' => ['label' => 'English', 'help' => 'Descriptions en anglais.'],
                'fr' => ['label' => 'Français', 'help' => 'Descriptions en français.'],
                'de' => ['label' => 'Deutsch', 'help' => 'Descriptions en allemand.'],
                'pt-BR' => ['label' => 'Português (Brasil)', 'help' => 'Descriptions en portugais du Brésil.'],
            ],
        ],
        'sections' => [
            'label' => 'Sections des règles',
            'help' => 'Quelles parties des chiffres des règles inclure (les mêmes que dans le Studio). Aucune cochée : toutes.',
            'example' => 'Seulement « Fonctions payantes » (premium), quand vous parlez du prix du VIP.',
            'tip' => 'Une réponse ne peut changer que les sections que vous cochez.',
        ],
        'texts' => [
            'label' => 'Inclure les textes',
            'help' => 'Activé : les noms et descriptions des voitures et des pièces, et les textes des sections des règles choisies, vont dans l’instruction dans toutes les langues (quelques milliers de jetons de plus). Sans cela l’assistant ne voit pas les textes, il ne peut donc en changer ni en traduire aucun.',
            'tip' => 'Activez-le pour renommer ou traduire.',
        ],
    ],
];
