<?php

return [
    'entries' => [
        '/dynamics/power_ppm' => [
            'label' => 'Potência na pista',
            'help' => 'A parte da potência por massa de um carro (sua estatística de aceleração, em kW por tonelada) que chega à pista como aceleração quando uma volta é feita em um traçado. 60% significa que um carro com 100 kW por tonelada acelera como um ponto material com 60 W por kg de tração.',
            'range' => 'De 10% a 200% (do esquema).',
            'tip' => 'Quanto maior, mais a aceleração decide a volta e mais curta é cada volta em um traçado. Isso não mexe na volta do carro de referência da pista, que é definida pelo tempo base da pista, só em quanto os outros carros são mais rápidos ou mais lentos que ele.',
        ],
        '/dynamics/lateral_g_ppm' => [
            'label' => 'Aderência na curva (g)',
            'help' => 'O que uma aderência e uma dirigibilidade de 100 dão para o lado em uma curva, em g, mostrado como porcentagem: 95% é 0,95 g. A curva é feita na velocidade em que a aceleração lateral é esse valor vezes a aderência e a dirigibilidade do carro divididas por 100.',
            'range' => 'De 30% a 250% (do esquema).',
            'tip' => 'Isso muda o quanto as curvas pesam diante das retas: aumente e os carros que as curvas seguram ganham dos que a potência segura.',
        ],
        '/dynamics/brake_g_ppm' => [
            'label' => 'Frenagem (g)',
            'help' => 'O que uma estatística de frenagem de 100 dá nos freios, em g, mostrado como porcentagem: 100% é 1 g. Ela define o quanto um carro pode frear tarde antes de uma curva: a distância para reduzir de uma velocidade é a velocidade ao quadrado dividida pelo dobro da desaceleração.',
            'range' => 'De 30% a 300% (do esquema).',
            'tip' => 'Aumente e todos os carros freiam mais tarde, então uma volta é decidida menos pela frenagem e mais pela potência e pelas curvas.',
        ],
        '/dynamics/launch_g_ppm' => [
            'label' => 'Aderência na largada (g)',
            'help' => 'O que uma aderência de 100 dá à aceleração na largada e na saída de curvas lentas, em g, mostrado como porcentagem. Até a velocidade em que o motor não consegue mais dar, o carro acelera esse valor vezes a sua aderência dividida por 100; acima disso, quem decide é a potência.',
            'range' => 'De 30% a 250% (do esquema).',
            'tip' => 'Aumente e carros potentes com pneus ruins ou em piso ruim perdem menos na largada. No cascalho e na lama os pneus multiplicam a aderência, então o limite ali é menor.',
        ],
        '/dynamics/agility/min_ppm' => [
            'label' => 'Parcela da dirigibilidade: curva aberta',
            'help' => 'Quanto da velocidade em uma curva aberta depende da dirigibilidade e quanto da aderência, mostrado como porcentagem: 25% é um quarto de dirigibilidade e três quartos de aderência. Uma curva aberta tem o raio do campo “Raio da curva aberta” ou mais.',
            'range' => 'De 0% a 100% (do esquema).',
            'tip' => 'A parcela em uma curva de raio entre os dois valores cai em linha reta da do cotovelo até esta.',
        ],
        '/dynamics/agility/max_ppm' => [
            'label' => 'Parcela da dirigibilidade: cotovelo',
            'help' => 'Quanto da velocidade em um cotovelo (raio perto de zero) depende da dirigibilidade e quanto da aderência, mostrado como porcentagem: 75% é três quartos de dirigibilidade.',
            'range' => 'De 0% a 100% (do esquema).',
            'tip' => 'Um carro pesado, de dirigibilidade fraca, perde mais nos cotovelos; aumente para que eles pesem mais para esses carros.',
        ],
        '/dynamics/agility/radius_m' => [
            'label' => 'Raio da curva aberta (m)',
            'help' => 'A partir deste raio, em metros, uma curva é aberta e só a menor parcela da dirigibilidade conta nela; abaixo dele a parcela sobe em linha reta até a maior, a do cotovelo.',
            'range' => 'De 20 a 2000 m (do esquema).',
            'tip' => 'Um raio pequeno faz da maioria das curvas curvas abertas, em que a aderência decide; um grande faz a dirigibilidade contar em mais delas.',
        ],
        '/dynamics/top_speed_ppm/asphalt_dry' => [
            'label' => 'Velocidade máxima: asfalto seco',
            'help' => 'O que sobra da velocidade máxima de um carro neste piso (asfalto seco), mostrado como porcentagem: 100% não a segura em nada, menos que isso a segura. Há um número para cada piso que o clima pode fazer: asfalto seco, asfalto molhado, cascalho seco, lama e neve.',
            'range' => 'De 10% a 100% (do esquema).',
            'tip' => 'Um carro nunca anda mais rápido em um piso do que a sua velocidade máxima vezes este número. Reduza a lama e a neve para que uma pista com mau tempo seja um lugar para carros lentos.',
        ],
        '/dynamics/top_speed_ppm/asphalt_wet' => [
            'label' => 'Velocidade máxima: asfalto molhado',
            'help' => 'O que sobra da velocidade máxima de um carro neste piso (asfalto molhado), mostrado como porcentagem: 100% não a segura em nada, menos que isso a segura. Há um número para cada piso que o clima pode fazer: asfalto seco, asfalto molhado, cascalho seco, lama e neve.',
            'range' => 'De 10% a 100% (do esquema).',
            'tip' => 'Um carro nunca anda mais rápido em um piso do que a sua velocidade máxima vezes este número. Reduza a lama e a neve para que uma pista com mau tempo seja um lugar para carros lentos.',
        ],
        '/dynamics/top_speed_ppm/gravel_dry' => [
            'label' => 'Velocidade máxima: cascalho seco',
            'help' => 'O que sobra da velocidade máxima de um carro neste piso (cascalho seco), mostrado como porcentagem: 100% não a segura em nada, menos que isso a segura. Há um número para cada piso que o clima pode fazer: asfalto seco, asfalto molhado, cascalho seco, lama e neve.',
            'range' => 'De 10% a 100% (do esquema).',
            'tip' => 'Um carro nunca anda mais rápido em um piso do que a sua velocidade máxima vezes este número. Reduza a lama e a neve para que uma pista com mau tempo seja um lugar para carros lentos.',
        ],
        '/dynamics/top_speed_ppm/mud' => [
            'label' => 'Velocidade máxima: lama',
            'help' => 'O que sobra da velocidade máxima de um carro neste piso (lama), mostrado como porcentagem: 100% não a segura em nada, menos que isso a segura. Há um número para cada piso que o clima pode fazer: asfalto seco, asfalto molhado, cascalho seco, lama e neve.',
            'range' => 'De 10% a 100% (do esquema).',
            'tip' => 'Um carro nunca anda mais rápido em um piso do que a sua velocidade máxima vezes este número. Reduza a lama e a neve para que uma pista com mau tempo seja um lugar para carros lentos.',
        ],
        '/dynamics/top_speed_ppm/snow' => [
            'label' => 'Velocidade máxima: neve',
            'help' => 'O que sobra da velocidade máxima de um carro neste piso (neve), mostrado como porcentagem: 100% não a segura em nada, menos que isso a segura. Há um número para cada piso que o clima pode fazer: asfalto seco, asfalto molhado, cascalho seco, lama e neve.',
            'range' => 'De 10% a 100% (do esquema).',
            'tip' => 'Um carro nunca anda mais rápido em um piso do que a sua velocidade máxima vezes este número. Reduza a lama e a neve para que uma pista com mau tempo seja um lugar para carros lentos.',
        ],
        '/dynamics/rough/speed_kmh' => [
            'label' => 'Velocidade em um trecho irregular (km/h)',
            'help' => 'A velocidade, em km/h, com que um carro que tem toda a altura do solo que um trecho irregular pede passa por ele. Nenhum carro anda mais rápido em um trecho marcado como irregular, por melhor que seja; um carro com menos altura do solo anda mais devagar.',
            'range' => 'De 20 a 400 km/h (do esquema).',
            'tip' => 'A irregularidade de um trecho é definida no trecho do traçado de uma pista, na escala da estatística altura do solo.',
        ],
        '/dynamics/rough/exponent_ppm' => [
            'label' => 'Redução de velocidade em um trecho irregular',
            'help' => 'O quanto um carro com pouca altura do solo reduz a velocidade em um trecho irregular, mostrado como porcentagem de um expoente: a sua velocidade no trecho é a velocidade de cima vezes (a altura do solo dele dividida pelo que o trecho pede) elevada a essa potência. 50% é a raiz quadrada: um carro com um quarto da altura do solo anda pela metade.',
            'range' => 'De 5% a 300% (do esquema).',
            'tip' => 'Aumente para punir mais os carros baixos, reduza para deixá-los passar quase tão rápido quanto os altos.',
        ],
    ],
];
