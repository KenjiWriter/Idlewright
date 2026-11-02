<?php

return [
    'entries' => [
        '/competition/pi/reference_pi' => [
            'label' => 'PI do carro de referência',
            'help' => 'Quanto vale em PI o carro de referência (original, sem peças). É a escala em que ficam os limites das classes: mudar este número desloca o PI de todos os carros na mesma proporção, então os limites das classes têm de acompanhar.',
            'example' => '300: um Rdzak GT-4 original vale 300 PI, um carro com estatísticas 50% melhores vale 450.',
            'range' => '1 – 100.000 (PI).',
        ],
        '/competition/pi/stat_cap_ppm' => [
            'label' => 'Teto de uma estatística no PI (% do carro de referência)',
            'help' => 'O máximo que uma estatística de um carro conta no PI, como múltiplo da estatística do carro de referência (100% é igual). O teto impede que um único número enorme compre uma classe.',
            'example' => '400%: uma aceleração quatro vezes a do carro de referência conta quatro vezes, não mais.',
            'range' => '100% – 10.000%.',
        ],
        '/competition/pi/weights_ppm/*' => [
            'label' => 'Peso de uma estatística no PI',
            'help' => 'Quanto esta estatística conta no PI de um carro (os pesos somam 100%). Por padrão os pesos são a média do que todas as pistas pedem de um carro, então as estatísticas em que as pistas mais se apoiam (aderência, aceleração) contam mais. Os pesos têm de somar 100% e valem para as estatísticas do modelo do jogo.',
            'example' => '23%: a aderência é quase um quarto do PI.',
            'range' => '0% – 100%; 100% no total.',
        ],
        '/competition/pi/class_max_pi/*' => [
            'label' => 'Limite superior de uma classe (PI)',
            'help' => 'O maior PI que um carro desta classe ainda pode ter. Um carro está na primeira classe cujo limite não é menor que o seu PI; os carros acima do último limite estão na classe mais alta. Os limites têm de crescer a partir da classe mais fraca. Um torneio de uma classe aceita só carros dessa classe, então um bom acerto dá vantagem na classe e um carro melhor abre a seguinte.',
            'example' => '399: um carro com 399 PI ainda está na classe C, com 400 está na classe B.',
            'range' => '1 – 1.000.000 (PI); crescente.',
        ],
        '/competition/rating/start' => [
            'label' => 'Respekt inicial',
            'help' => 'O Respekt de um piloto novo e o valor para o qual uma temporada puxa a pontuação no reinício suave.',
            'example' => '1000: um piloto novo começa na divisão de rua.',
            'range' => '1 – 100.000.',
        ],
        '/competition/rating/minimum' => [
            'label' => 'Respekt mínimo',
            'help' => 'O Respekt de um piloto não cai abaixo deste valor, por mais que perca. Evita uma queda sem fim.',
            'example' => '600: um piloto que só perde para em 600.',
            'range' => '0 – 100.000; não acima do Respekt inicial.',
        ],
        '/competition/rating/k_new' => [
            'label' => 'K de um piloto novo',
            'help' => 'Com que rapidez o Respekt de um piloto se move enquanto ele jogou menos partidas ranqueadas do que o número seguinte diz. Contra um rival igual, uma vitória dá metade disso e uma derrota tira o mesmo. Um K grande põe logo um jogador novo no seu lugar.',
            'example' => '40: uma vitória sobre um rival igual dá +20, uma derrota −20.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/new_matches' => [
            'label' => 'Partidas em que um piloto é novo',
            'help' => 'Durante este número de partidas ranqueadas um piloto tem o K grande. Depois disso o Respekt se move mais devagar.',
            'example' => '20: as primeiras 20 partidas colocam o jogador no seu lugar.',
            'range' => '0 – 1000 partidas.',
        ],
        '/competition/rating/k_established' => [
            'label' => 'K de um piloto estabelecido',
            'help' => 'Com que rapidez o Respekt se move para um piloto que já não é novo e está abaixo do topo.',
            'example' => '24: uma vitória sobre um rival igual dá +12, uma derrota −12.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/k_top' => [
            'label' => 'K de um piloto no topo',
            'help' => 'Com que rapidez o Respekt se move para um piloto no topo (a partir do limite do topo). Um K pequeno deixa o topo estável.',
            'example' => '16: uma vitória sobre um rival igual dá +8, uma derrota −8.',
            'range' => '1 – 400.',
        ],
        '/competition/rating/top_from' => [
            'label' => 'Respekt a partir do qual um piloto está no topo',
            'help' => 'A partir deste Respekt um piloto tem o menor K. É um número próprio, separado do limite da divisão elite, embora por padrão os dois sejam iguais.',
            'example' => '1700.',
            'range' => '1 – 100.000.',
        ],
        '/competition/rating/club_from' => [
            'label' => 'Início da divisão clube',
            'help' => 'A partir deste Respekt um piloto está na divisão clube (abaixo: rua). As divisões escolhem os rivais nos torneios.',
            'example' => '1100.',
            'range' => '1 – 100.000; abaixo da divisão regional.',
        ],
        '/competition/rating/regional_from' => [
            'label' => 'Início da divisão regional',
            'help' => 'A partir deste Respekt um piloto está na divisão regional.',
            'example' => '1400.',
            'range' => '1 – 100.000; entre clube e elite.',
        ],
        '/competition/rating/elite_from' => [
            'label' => 'Início da divisão elite',
            'help' => 'A partir deste Respekt um piloto está na divisão elite, a mais alta.',
            'example' => '1700.',
            'range' => '1 – 100.000; acima da divisão regional.',
        ],
        '/competition/rating/season_keep_ppm' => [
            'label' => 'Quanto do Respekt permanece após uma temporada (%)',
            'help' => 'A parte do Respekt que permanece após o reinício suave no começo de uma temporada; o resto volta ao valor inicial. 100% não muda nada, 0% põe todos no início.',
            'example' => '75%: um piloto com 1400 começa a temporada com 1300, um com 600 com 700.',
            'range' => '0% – 100%.',
        ],
        '/competition/match/luck_sigma_ppm' => [
            'label' => 'Largura da sorte de uma corrida (% do tempo de volta)',
            'help' => 'Em quantos por cento do tempo de volta o tempo de uma corrida pode mudar por causa da sorte: cerca de duas corridas em três ficam dentro dessa largura e nenhuma passa de três vezes e um terço dela. Mais sorte faz o carro mais fraco vencer com mais frequência; zero é sem sorte. Ela é sorteada a partir da semente, então o resultado de uma partida pode ser repetido.',
            'example' => '2%: um carro 1% mais rápido vence uma corrida isolada em 64% das vezes e uma partida de três corridas em 70%.',
            'range' => '0% – 10%.',
        ],
        '/competition/match/runs' => [
            'label' => 'Corridas em uma partida',
            'help' => 'De quantas corridas uma partida é feita; o lado que vencer mais delas vence a partida, então o número tem de ser ímpar. Cada corrida é em outra pista, então mais corridas favorecem um carro que serve para muitas pistas e reduzem a parte da sorte.',
            'example' => '3: a melhor de três corridas.',
            'range' => '1 – 9, ímpar.',
        ],
    ],
];
