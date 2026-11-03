<?php

return [
    'entries' => [
        'track' => [
            'label' => 'Pista',
            'help' => 'A pista cujo traçado você vê e altera. As outras pistas ficam como estão.',
            'tip' => 'Salve antes de passar para outra pista: as alterações da que você deixa não são mantidas.',
        ],
        'lap_from' => [
            'label' => 'Volta a partir de',
            'help' => 'De onde vem a volta de um carro nesta pista: das suas estatísticas pelas exigências da pista (a fórmula que todas as pistas tiveram até agora) ou de uma volta feita no traçado da pista. Com a fórmula, o traçado só desenha a pista.',
            'tip' => 'Antes de passar uma pista para o traçado, olhe a tabela de carros abaixo da linha: ela mostra o quanto cada volta mudaria. A volta do carro de referência continua sendo a volta base da pista de qualquer jeito.',
            'options' => [
                'stats' => ['label' => 'Fórmula (estatísticas)', 'help' => 'A volta é feita das estatísticas do carro e das exigências da pista, como antes. O traçado, se a pista tiver um, é só um desenho.'],
                'thread' => ['label' => 'Traçado', 'help' => 'A volta é feita nos trechos do traçado: o carro sai, acelera nas retas, freia antes das curvas e segura o piso.'],
            ],
        ],
        'mode' => [
            'label' => 'Como se anda',
            'help' => 'Um circuito é um laço percorrido lançado: o carro cruza a linha com a velocidade com que a deixou. Uma prova é percorrida sempre de largada parada, como uma arrancada ou um sprint.',
            'options' => [
                'circuit' => ['label' => 'Circuito', 'help' => 'Um laço; o carro não para na linha.'],
                'run' => ['label' => 'Prova', 'help' => 'Cada volta começa parada, e a volta ao início leva um tempo fixo.'],
            ],
        ],
        'return_s' => [
            'label' => 'Volta ao início (s)',
            'help' => 'O que a volta ao início acrescenta a cada volta de uma prova, em segundos, seja qual for o carro. Um circuito não tem volta.',
            'range' => 'De 0 a 600 s (do esquema).',
            'tip' => 'Ela leva uma arrancada à volta que você quer sem mudar os trechos; quanto mais longa, menos os carros diferem nas voltas.',
        ],
        'sections' => [
            'label' => 'Trechos',
            'help' => 'O traçado como uma cadeia de trechos na ordem em que são percorridos: retas e curvas. Mude a ordem com as setas; a linha ao lado é desenhada a partir deles.',
        ],
        'type' => [
            'label' => 'Tipo de trecho',
            'help' => 'Uma reta segue em frente; uma curva vira o carro por um ângulo em um raio.',
            'options' => [
                'straight' => ['label' => 'Reta', 'help' => 'Tem um comprimento.'],
                'corner' => ['label' => 'Curva', 'help' => 'Tem um raio e um ângulo.'],
            ],
        ],
        'length' => [
            'label' => 'Comprimento (m)',
            'help' => 'O comprimento da reta, em metros.',
            'range' => 'De 5 a 5000 m (do esquema).',
            'tip' => 'O comprimento da pista é a soma dos trechos e é escrito ao salvar.',
        ],
        'radius' => [
            'label' => 'Raio (m)',
            'help' => 'O raio da curva, em metros. Um cotovelo tem cerca de 20 m, uma curva fechada 35, uma curva aberta mais de 100. A velocidade nela vem da aderência e da dirigibilidade do carro.',
            'range' => 'De 8 a 1000 m (do esquema).',
        ],
        'angle' => [
            'label' => 'Ângulo (°)',
            'help' => 'O quanto a curva vira o carro, em graus: 90 é um ângulo reto, 180 um cotovelo.',
            'range' => 'De 5 a 360° (do esquema).',
            'tip' => 'As curvas de um laço somam cerca de 360° (as da esquerda contam contra as da direita); senão a linha não se fecha no desenho.',
        ],
        'turn' => [
            'label' => 'Vira',
            'help' => 'Para que lado a curva vira. Isso só desenha a linha: a volta não depende disso.',
            'options' => [
                'left' => ['label' => 'Para a esquerda', 'help' => 'A linha vira para a esquerda.'],
                'right' => ['label' => 'Para a direita', 'help' => 'A linha vira para a direita.'],
            ],
        ],
        'surface' => [
            'label' => 'Piso',
            'help' => 'De que é feito o trecho quando não é do que a pista é feita: um trecho de cascalho em uma especial de rali. Vazio significa o piso da pista. O clima o deixa molhado, enlameado ou com neve, e os pneus de um carro aderem a cada um de forma diferente.',
            'options' => [
                'asphalt' => ['label' => 'Asfalto', 'help' => 'Asfalto seco; a chuva o deixa molhado, a neve o cobre.'],
                'gravel' => ['label' => 'Cascalho', 'help' => 'Cascalho solto; a chuva o transforma em lama, a neve o cobre. Ele segura a velocidade máxima do carro e pede os pneus certos.'],
            ],
        ],
        'limit' => [
            'label' => 'Limite de velocidade (km/h)',
            'help' => 'Uma velocidade acima da qual nenhum carro anda neste trecho: uma rua, o pit lane, o caminho de volta. Vazio significa nenhum.',
            'range' => 'De 20 a 400 km/h (do esquema).',
        ],
        'rough' => [
            'label' => 'Irregularidade',
            'help' => 'O quanto o trecho é irregular, na escala da estatística altura do solo: um carro com pelo menos essa altura passa na velocidade do terreno irregular, um com menos passa mais devagar. Vazio ou 0 significa liso.',
            'range' => 'De 0 a 100 (do esquema).',
            'tip' => 'A velocidade em terreno irregular e o quão rápido um carro baixo reduz estão no estúdio (“Velocidade em um trecho irregular”, “Redução de velocidade em um trecho irregular”).',
        ],
        'scale_factor' => [
            'label' => 'Fator',
            'help' => 'O número pelo qual cada raio é multiplicado: 1,2 deixa as curvas 20% mais abertas, 0,8 mais fechadas.',
            'range' => 'De 0,2 a 5.',
            'tip' => 'Depois de escalar os raios, o circuito costuma deixar de fechar: feche-o de novo.',
        ],
        'stretch_length' => [
            'label' => 'Comprimento (m)',
            'help' => 'O comprimento, em metros, que a reta mais longa do traçado recebe.',
            'range' => 'De 5 a 5000 m (do esquema).',
            'tip' => 'Uma reta mais longa deixa a pista mais rápida e dá mais peso à potência e à velocidade máxima.',
        ],
    ],
];
