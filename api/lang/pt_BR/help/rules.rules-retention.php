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
        '/slots/base' => [
            'label' => 'Carros que cada jogador pode ter na estrada',
            'help' => 'Quantos carros um jogador pode ter ativos (andando) ao mesmo tempo. Os outros carros ficam guardados na garagem: não andam, não gastam combustível, não se desgastam e não ganham nada, mas mantêm o lugar na garagem. Os carros que o jogador tinha antes do limite ficam com ele até que guarde um.',
            'example' => '2: o jogador pode ter dois carros na estrada; um terceiro carro vai para a garagem de guarda quando ele o recebe.',
            'range' => '1 – 50 (carros).',
            'tip' => 'O simulador de economia assume cerca de 1,7 carro na estrada para um jogador regular, então 2 não muda o ritmo de quem não paga. Confira com o comando economy:simulate depois de uma mudança.',
        ],
        '/slots/vip' => [
            'label' => 'Mais carros na estrada com a conta VIP',
            'help' => 'Quantos carros a mais um jogador com a conta VIP pode ter na estrada enquanto a conta durar. Os carros desses lugares param de ganhar no momento em que a conta acaba e vão para a guarda; nada se perde.',
            'example' => '3: com a conta VIP o jogador pode ter cinco carros na estrada (2 + 3), o que com o bônus da conta é cerca de três vezes o que ganha um jogador com dois carros.',
            'range' => '0 – 50 (carros).',
            'tip' => 'Essa é a maior parte do valor da conta VIP: mais carros ganham mais que o bônus de dinheiro e XP. Confira a vantagem no simulador de economia antes de aumentá-la.',
        ],
        '/slots/swap_ms' => [
            'label' => 'Tempo para pôr um carro na estrada',
            'help' => 'Por quanto tempo um carro tirado da guarda é preparado: ele anda a partir desse momento e não ganha nada antes. Isso impede o jogador de trocar de carro a cada chuva.',
            'example' => '120 s: dois minutos.',
            'range' => '0 – 3600 s.',
        ],
        '/slots/swap_fee_ppm' => [
            'label' => 'Custo de uma troca (parte de um tanque)',
            'help' => 'Quanto custa pôr um carro da guarda na estrada no lugar de um que está nela, como parte do ganho líquido de um tanque do melhor carro do jogador (100% é um tanque inteiro), em dólares inteiros e no mínimo um. Pôr um carro em um lugar livre não custa nada.',
            'example' => '2%: para um carro que ganha $375 por tanque, uma troca custa $8.',
            'range' => '0 – 100% (uma parte de um tanque).',
        ],
    ],
];
