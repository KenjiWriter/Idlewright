<?php

return [
    'entries' => [
        '/missions/reset_hour' => [
            'label' => 'Heure de début du jour des missions',
            'help' => 'L’heure (dans le fuseau horaire du jeu) à laquelle commence le jour des missions, et avec lui la nouvelle série quotidienne. La semaine commence le lundi à cette heure et le mois le premier à cette heure. La période précédente se termine alors aussi : ce que le joueur a terminé sans le récupérer, le serveur le récupère pour lui, et le reste est perdu.',
            'example' => '4 : un joueur qui joue le soir et après minuit a un seul jour de missions, et reçoit la nouvelle série à 4 h du matin.',
            'range' => '0 – 23 (l’heure).',
        ],
        '/missions/periods/daily/bonus_coins' => [
            'label' => 'Bonus pour la série complète : jour (pièces)',
            'help' => 'Le nombre de pièces premium (RideCoin) que le joueur reçoit pour avoir terminé toutes les missions de la série du jour. Ce sont les seules pièces gratuites que donnent les missions ; les récompenses des missions seules n’en contiennent pas. Ce nombre fixe donc toutes les pièces gratuites de cette période, et le schéma n’autorise pas plus de 5.',
            'example' => '2 pièces : avec les trois périodes réussies à chaque fois, cela fait 2, 8 et 25 pièces, environ 119 par mois ; le compte VIP coûte 150 pièces et un lot de 50 pièces 4,99 zł.',
            'range' => '0 – 5 pièces.',
            'tip' => 'Les joueurs ressentent une baisse du bonus comme une récompense retirée, alors commencez bas : l’augmenter plus tard est facile.',
        ],
        '/missions/periods/weekly/bonus_coins' => [
            'label' => 'Bonus pour la série complète : semaine (pièces)',
            'help' => 'Le nombre de pièces premium (RideCoin) que le joueur reçoit pour avoir terminé toutes les missions de la série de la semaine. Ce sont les seules pièces gratuites que donnent les missions ; les récompenses des missions seules n’en contiennent pas. Ce nombre fixe donc toutes les pièces gratuites de cette période, et le schéma n’autorise pas plus de 20.',
            'example' => '8 pièces : avec les trois périodes réussies à chaque fois, cela fait 2, 8 et 25 pièces, environ 119 par mois ; le compte VIP coûte 150 pièces et un lot de 50 pièces 4,99 zł.',
            'range' => '0 – 20 pièces.',
            'tip' => 'Les joueurs ressentent une baisse du bonus comme une récompense retirée, alors commencez bas : l’augmenter plus tard est facile.',
        ],
        '/missions/periods/monthly/bonus_coins' => [
            'label' => 'Bonus pour la série complète : mois (pièces)',
            'help' => 'Le nombre de pièces premium (RideCoin) que le joueur reçoit pour avoir terminé toutes les missions de la série du mois. Ce sont les seules pièces gratuites que donnent les missions ; les récompenses des missions seules n’en contiennent pas. Ce nombre fixe donc toutes les pièces gratuites de cette période, et le schéma n’autorise pas plus de 60.',
            'example' => '25 pièces : avec les trois périodes réussies à chaque fois, cela fait 2, 8 et 25 pièces, environ 119 par mois ; le compte VIP coûte 150 pièces et un lot de 50 pièces 4,99 zł.',
            'range' => '0 – 60 pièces.',
            'tip' => 'Les joueurs ressentent une baisse du bonus comme une récompense retirée, alors commencez bas : l’augmenter plus tard est facile.',
        ],
        '/missions/periods/daily/cash_ppm' => [
            'label' => 'Argent pour une mission : jour ( % de sa valeur)',
            'help' => 'La part de la valeur des missions la série du jour qui est payée en argent (1 000 000 est le tout). La valeur d’une mission est le travail qu’elle demande, en réservoirs de la meilleure voiture du joueur : tours, gains, carburant, caisses, améliorations et le reste ont un poids dans le document des missions (effort), donc une mission qui demande beaucoup paie beaucoup. L’argent correspond à autant de réservoirs de gains après le carburant. C’est un supplément aux gains des voitures, qui ne compte pas dans l’économie du jeu : les barrières et le simulateur ne le voient pas.',
            'example' => '10 % : une mission qui vaut 4 réservoirs d’une voiture qui gagne 375 $ par réservoir paie environ 150 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/weekly/cash_ppm' => [
            'label' => 'Argent pour une mission : semaine ( % de sa valeur)',
            'help' => 'La part de la valeur des missions la série de la semaine qui est payée en argent (1 000 000 est le tout). La valeur d’une mission est le travail qu’elle demande, en réservoirs de la meilleure voiture du joueur : tours, gains, carburant, caisses, améliorations et le reste ont un poids dans le document des missions (effort), donc une mission qui demande beaucoup paie beaucoup. L’argent correspond à autant de réservoirs de gains après le carburant. C’est un supplément aux gains des voitures, qui ne compte pas dans l’économie du jeu : les barrières et le simulateur ne le voient pas.',
            'example' => '10 % : une mission qui vaut 4 réservoirs d’une voiture qui gagne 375 $ par réservoir paie environ 150 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/monthly/cash_ppm' => [
            'label' => 'Argent pour une mission : mois ( % de sa valeur)',
            'help' => 'La part de la valeur des missions la série du mois qui est payée en argent (1 000 000 est le tout). La valeur d’une mission est le travail qu’elle demande, en réservoirs de la meilleure voiture du joueur : tours, gains, carburant, caisses, améliorations et le reste ont un poids dans le document des missions (effort), donc une mission qui demande beaucoup paie beaucoup. L’argent correspond à autant de réservoirs de gains après le carburant. C’est un supplément aux gains des voitures, qui ne compte pas dans l’économie du jeu : les barrières et le simulateur ne le voient pas.',
            'example' => '10 % : une mission qui vaut 4 réservoirs d’une voiture qui gagne 375 $ par réservoir paie environ 150 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/daily/xp_ppm' => [
            'label' => 'XP pour une mission : jour ( % de sa valeur)',
            'help' => 'La part de la valeur des missions la série du jour qui est payée en XP du joueur (1 000 000 est le tout) : autant de réservoirs d’XP de la meilleure voiture. Le niveau du joueur ouvre pistes, villes et pièces, et le tutoriel dit que les niveaux doivent venir des tours, alors gardez ce nombre petit : chaque point décale le rythme de tout le jeu.',
            'example' => '6 % : une mission qui vaut 4 réservoirs d’une voiture dont le réservoir donne 600 XP paie environ 144 XP.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/weekly/xp_ppm' => [
            'label' => 'XP pour une mission : semaine ( % de sa valeur)',
            'help' => 'La part de la valeur des missions la série de la semaine qui est payée en XP du joueur (1 000 000 est le tout) : autant de réservoirs d’XP de la meilleure voiture. Le niveau du joueur ouvre pistes, villes et pièces, et le tutoriel dit que les niveaux doivent venir des tours, alors gardez ce nombre petit : chaque point décale le rythme de tout le jeu.',
            'example' => '6 % : une mission qui vaut 4 réservoirs d’une voiture dont le réservoir donne 600 XP paie environ 144 XP.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/periods/monthly/xp_ppm' => [
            'label' => 'XP pour une mission : mois ( % de sa valeur)',
            'help' => 'La part de la valeur des missions la série du mois qui est payée en XP du joueur (1 000 000 est le tout) : autant de réservoirs d’XP de la meilleure voiture. Le niveau du joueur ouvre pistes, villes et pièces, et le tutoriel dit que les niveaux doivent venir des tours, alors gardez ce nombre petit : chaque point décale le rythme de tout le jeu.',
            'example' => '6 % : une mission qui vaut 4 réservoirs d’une voiture dont le réservoir donne 600 XP paie environ 144 XP.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/main/cash_ppm' => [
            'label' => 'Argent pour une mission principale ( % de sa valeur)',
            'help' => 'La part de la valeur d’une mission principale qui est payée en argent, calculée à son ouverture (1 000 000 est le tout). Les objectifs sur toute la durée (tours, gains, carburant) sont convertis en réservoirs de la meilleure voiture du joueur à ce moment-là, une meilleure voiture n’en fait donc pas une récompense gratuite ; le reste (niveau, voitures, garages, pièces) a un poids dans le document des missions (effort). Ces récompenses sont versées une seule fois.',
            'example' => '15 % : « Bouclez 250 tours » vaut environ 8 réservoirs d’une voiture qui gagne 375 $ par réservoir, soit environ 469 $.',
            'range' => '0 – 1000 %.',
        ],
        '/missions/main/xp_ppm' => [
            'label' => 'XP pour une mission principale ( % de sa valeur)',
            'help' => 'La part de la valeur d’une mission principale qui est payée en XP du joueur, calculée à son ouverture (1 000 000 est le tout). Le niveau du joueur est une barrière du jeu, alors gardez ce nombre petit ; l’XP de la voiture est donnée à part (car_xp_laps de la mission).',
            'example' => '9 % : « Bouclez 250 tours » vaut environ 8 réservoirs d’une voiture dont le réservoir donne 600 XP, soit environ 450 XP.',
            'range' => '0 – 1000 %.',
        ],
    ],
];
