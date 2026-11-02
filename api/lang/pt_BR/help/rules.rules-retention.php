<?php

return [
    'entries' => [
        '/streaks/goal/daily_done' => [
            'label' => 'Missões do dia que formam a meta diária',
            'help' => 'Quantas missões do conjunto diário o jogador precisa cumprir (cumpridas, não necessariamente recebidas) para o dia contar na sequência. Um conjunto com menos missões exige todas. A sequência é contada a partir do conjunto diário, então não há tarefas extras.',
            'example' => '3: três das seis missões do dia, de modo que o jogador que entra uma vez por dia também mantém a sequência.',
            'range' => '1 – 20 (missões).',
            'tip' => 'Um número menor protege a sequência de quem entra pouco; um maior exige jogar de verdade todos os dias.',
        ],
        '/streaks/shield/every_days' => [
            'label' => 'Dias de sequência por escudo',
            'help' => 'O jogador ganha um escudo quando a sequência chega a um múltiplo desses dias. O escudo salva a sequência de um dia perdido: a sequência fica onde está, e esse dia não paga nada.',
            'example' => '7: um escudo no 7º, no 14º e no 21º dia de uma sequência.',
            'range' => '1 – 365 (dias).',
        ],
        '/streaks/shield/cap' => [
            'label' => 'Máximo de escudos que o jogador guarda',
            'help' => 'Quantos escudos o jogador pode ter ao mesmo tempo. Um escudo ganho acima desse número se perde. Com 0 o jogo não tem escudos, e um dia perdido encerra a sequência.',
            'example' => '2: depois de dois dias perdidos seguidos a sequência é salva duas vezes, e o terceiro a encerra.',
            'range' => '0 – 10 (escudos).',
            'tip' => 'Escudos se ganham jogando e não podem ser comprados, então um número alto é gentil, não uma forma de vender a sequência.',
        ],
        '/streaks/cycle/*/cash_ppm' => [
            'label' => 'Dinheiro de um dia do ciclo (parte de um tanque)',
            'help' => 'Quanto dinheiro um dia da sequência paga, como parte do ganho líquido de um tanque do melhor carro do jogador (100% é um tanque inteiro), então cresce com o jogador e não precisa ser ajustado. Cada entrada do ciclo é um dia, e depois da última o ciclo recomeça. Vem além dos ganhos dos carros e não conta na economia do jogo.',
            'example' => '2%: o primeiro dia do ciclo paga dois centésimos de um tanque; o último dia da semana costuma pagar quatro vezes mais.',
            'range' => '0 – 100% (uma parte de um tanque).',
            'tip' => 'Confira a soma de um mês com o comando streaks:budget: ela deve ficar em alguns por cento do que os carros ganham.',
        ],
        '/streaks/cycle/*/xp_ppm' => [
            'label' => 'XP de um dia do ciclo (parte de um tanque)',
            'help' => 'Quanto XP um dia da sequência paga, como parte do XP de um tanque do melhor carro do jogador (100% é um tanque inteiro). O nível do jogador é um portão do jogo, então mantenha o valor baixo.',
            'example' => '1%: um dia do ciclo paga um centésimo do XP de um tanque.',
            'range' => '0 – 100% (uma parte de um tanque).',
        ],
        '/streaks/milestones/*/day' => [
            'label' => 'Tamanho da sequência de um marco (dias)',
            'help' => 'O tamanho da sequência, em dias, em que o marco paga o que dá (as caixas e, se houver, dinheiro e XP), além do dia do ciclo. Cada tamanho é citado uma vez.',
            'example' => '30: uma sequência de trinta dias paga seu marco no trigésimo dia, uma vez.',
            'range' => '2 – 3650 (dias).',
        ],
    ],
];
