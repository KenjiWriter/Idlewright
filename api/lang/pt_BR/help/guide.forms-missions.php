<?php

return [
    'entries' => [
        'missions' => [
            'label' => 'Como funciona o formulário das missões',
            'help' => 'Aqui você define as missões do jogo: quando começam os conjuntos do dia, da semana e do mês e que parte do valor das suas missões cada um paga, as missões entre as quais os conjuntos sorteiam, as cadeias de missões principais e quanto vale uma unidade de cada tipo de trabalho. O que você digita é salvo junto como rascunho das regras e do documento das missões e chega aos jogadores com o próximo pacote publicado.',
            'steps' => [
                'Aba Recompensas e períodos: ligue as missões, escolha o fuso horário e a hora em que o dia começa e defina que parte do valor das suas missões cada conjunto e as missões principais pagam (dinheiro e XP em percentual), com as moedas pelo conjunto completo.',
                'Aba Valor do trabalho: diga quanto vale uma unidade de cada tipo de trabalho em tanques do melhor carro; uma missão paga uma parte do trabalho que pede, então uma missão que pede mais paga mais.',
                'Aperte Calcular o orçamento: ele mostra quanto valem um dia, uma semana e um mês em dinheiro, XP e moedas para um carro numa pista, e se a barreira da publicação deixaria passar.',
                'Aba Conjuntos: para o conjunto diário, semanal e mensal, adicione espaços e as missões que cada espaço pode sortear, com suas metas, pesos e multiplicadores.',
                'Aba Missões principais: adicione cadeias e suas missões na ordem em que abrem, com metas, textos nos idiomas e os itens que dão.',
                'Salve o rascunho: os dois documentos são guardados juntos, ou nenhum se um for recusado. Publique um pacote na página Publicações.',
            ],
            'example' => 'Um jogo de navios: os conjuntos pagam 10% do valor em dinheiro e 6% em XP com 2, 8 e 25 moedas, as missões principais 15% e 9%; “navegar 50 voltas” vale 50 voltas de um tanque de 30 voltas vezes 1 tanque de trabalho, cerca de 1,7 tanque, e num conjunto do dia paga 17% do ganho líquido de um tanque.',
        ],
    ],
];
