<?php

return [
    'entries' => [
        'gigs' => [
            'label' => 'Como funciona o formulário das corridas',
            'help' => 'Aqui você ajusta os trabalhos extras do jogo: o quadro de uma cidade, quanto paga uma hora de corrida em cada nível e os modelos de onde as ofertas são sorteadas. O que você digita é salvo como rascunho das regras e chega aos jogadores com o próximo pacote publicado. A corrida noturna, uma oportunidade que a polícia pode parar, tem uma aba própria.',
            'steps' => [
                'Aba Quadro: ligue as corridas, escolha o passo do tutorial depois do qual elas abrem e ajuste quanto dura uma onda e quantas ofertas cada período do dia tem.',
                'Aba Pagamento: ajuste as velocidades, as âncoras da escada e as faixas dos níveis. Esses números decidem quanto vale uma hora de corrida frente a uma hora de voltas.',
                'Abas Tipos e Modelos de ofertas: dê nome aos tipos e depois descreva cada modelo: o que o carro precisa levar, quão longa é a viagem, quanto paga e quão provável é em cada período do dia.',
                'Aperte Calcular o pagamento abaixo do formulário: ele mostra quanto cada modelo paga em cada carro do pacote, frente à escada e frente às voltas, e se a barreira da publicação o deixaria passar.',
                'Salve o rascunho e publique um pacote na página Publicações. A barreira confere os mesmos números de novo.',
            ],
            'example' => 'Um jogo de barcos: o tipo Balsa, um modelo que leva 1 – 6 passageiros entre dois portos a 100%, um modelo noturno a 125% e um modelo de atacado para 1000 – 1800 kg sorteado só de dia.',
        ],
    ],
];
