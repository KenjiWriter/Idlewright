<?php

return [
    'entries' => [
        'tuning' => [
            'label' => 'Como funcionam os bônus das peças',
            'help' => 'Um piloto coloca bônus numa peça na oficina: cada tentativa custa uma parte do preço da peça e sorteia um valor de uma curva em sino em torno da média do bônus. Esta página mostra os bônus do jogo, e o estúdio de parâmetros muda os números deles.',
            'steps' => [
                'Leia a lista: cada bônus tem seu nome em cada idioma, o que faz, os slots em que serve, o peso no sorteio e o nível em que abre.',
                'Leia o sino de cada bônus: as barras são a chance de cada porcento inteiro que uma tentativa dá, e a tabela diz quantas tentativas são necessárias em média para chegar a 6, 8, 10 e 12 por cento.',
                'Para mudar a média, a dispersão, o mínimo ou o máximo de um bônus, o número de lugares ou o preço de uma tentativa, abra o estúdio de parâmetros, seção "Bônus de peças (ajuste)"; os limites (8% sem contexto, 12% com contexto) são verificados ao criar um pacote.',
                'Aperte "Calcular" no fim: ele mede o melhor conjunto de bônus nos carros de referência e diz quanto tira da volta. Acima dos 10% do projeto é só um aviso; acima do teto aprovado, a porta tuning:budget para a publicação do pacote.',
                'Publique um pacote na página Publicações; os bônus que os pilotos já têm são mantidos, e um bônus que um pacote retira deixa de valer até voltar.',
            ],
            'example' => 'Um bônus "Aderência no cascalho" com média de 5%, dispersão de 2,3%, de 1% a 12%: cerca de uma tentativa em três dá 6% ou mais, uma em mil dá 12%. Subir a média para 7% leva o melhor conjunto no cascalho acima do teto aprovado, e a porta para o pacote até os limites baixarem.',
        ],
    ],
];
