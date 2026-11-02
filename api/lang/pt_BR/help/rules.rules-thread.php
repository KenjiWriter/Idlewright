<?php

return [
    'entries' => [
        '/tracks/*/thread/return_ms' => [
            'label' => 'Volta ao início de uma prova (s)',
            'help' => 'O que a volta ao início acrescenta a cada volta de uma pista percorrida como prova (arrancada, sprint), em segundos, seja qual for o carro. Um circuito não tem volta.',
            'range' => 'De 0 a 600 s (do esquema).',
            'tip' => 'Ela leva uma prova à volta que você quer sem mudar os trechos; quanto mais longa, menos os carros diferem nas voltas. Só importa quando a volta é feita no traçado.',
        ],
        '/tracks/*/thread/sections/*/length_m' => [
            'label' => 'Reta: comprimento (m)',
            'help' => 'O comprimento de uma reta do traçado da pista, em metros. O comprimento da pista é a soma dos trechos, cada um arredondado ao metro, e uma curva conta como o seu arco.',
            'range' => 'De 5 a 5000 m (do esquema).',
            'tip' => 'Mude o comprimento de uma reta e o comprimento da pista tem de mudar junto: o validador avisa quando diferem em mais de um metro. A página do traçado faz isso por você.',
        ],
        '/tracks/*/thread/sections/*/radius_m' => [
            'label' => 'Curva: raio (m)',
            'help' => 'O raio de uma curva do traçado da pista, em metros. Um cotovelo tem cerca de 20 m, uma curva fechada 35, uma curva aberta mais de 100. A velocidade nela vem da aderência e da dirigibilidade do carro.',
            'range' => 'De 8 a 1000 m (do esquema).',
            'tip' => 'A velocidade numa curva é a raiz quadrada da aceleração lateral vezes o raio, então uma curva com o dobro do raio é feita cerca de 40% mais rápido.',
        ],
        '/tracks/*/thread/sections/*/angle_deg' => [
            'label' => 'Curva: ângulo (°)',
            'help' => 'O quanto uma curva do traçado vira o carro, em graus: 90 é um ângulo reto, 180 um cotovelo.',
            'range' => 'De 5 a 360° (do esquema).',
            'tip' => 'As curvas de um laço somam cerca de 360°, as da esquerda contando contra as da direita; o validador avisa quando não é assim, porque a linha não se fecharia.',
        ],
        '/tracks/*/thread/sections/*/rough' => [
            'label' => 'Trecho: irregularidade',
            'help' => 'O quanto um trecho do traçado é irregular, na escala da estatística altura do solo: um carro com pelo menos essa altura passa na velocidade do terreno irregular, um com menos passa mais devagar. Trechos lisos não têm.',
            'range' => 'De 0 a 100 (do esquema).',
            'tip' => 'A velocidade em terreno irregular e o quão rápido um carro baixo reduz estão no estúdio (“Velocidade em um trecho irregular”, “Redução de velocidade em um trecho irregular”).',
        ],
    ],
];
