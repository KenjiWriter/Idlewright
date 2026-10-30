<?php

return [
    'entries' => [
        '/missions/reset_hour' => [
            'label' => 'Hora em que o dia de missões começa',
            'help' => 'A hora (no fuso horário do jogo) em que o dia de missões começa, e com ele o novo conjunto diário. A semana começa na segunda-feira a essa hora e o mês no dia primeiro a essa hora. O período anterior também termina então: o que o jogador cumpriu e não recolheu, o servidor recolhe por ele, e o resto se perde.',
            'example' => '4: quem joga à noite e depois da meia-noite tem um único dia de missões e recebe o novo conjunto às 4h da manhã.',
            'range' => '0 – 23 (a hora).',
        ],
        '/missions/periods/daily/bonus_coins' => [
            'label' => 'Bônus pelo conjunto completo: dia (moedas)',
            'help' => 'Quantas moedas premium (RideCoin) o jogador ganha por cumprir todas as missões do conjunto do dia. São as únicas moedas grátis que as missões dão; as recompensas das missões avulsas não têm moedas. Este número define, portanto, todas as moedas grátis deste período, e o esquema não permite mais de 5.',
            'example' => '2 moedas: cumprindo os três períodos sempre, são 2, 8 e 25 moedas, cerca de 119 por mês; a conta VIP custa 150 moedas e um pacote de 50 moedas custa 4,99 zł.',
            'range' => '0 – 5 moedas.',
            'tip' => 'Os jogadores sentem um bônus menor como uma recompensa tirada, então comece baixo: aumentar depois é fácil.',
        ],
        '/missions/periods/weekly/bonus_coins' => [
            'label' => 'Bônus pelo conjunto completo: semana (moedas)',
            'help' => 'Quantas moedas premium (RideCoin) o jogador ganha por cumprir todas as missões do conjunto da semana. São as únicas moedas grátis que as missões dão; as recompensas das missões avulsas não têm moedas. Este número define, portanto, todas as moedas grátis deste período, e o esquema não permite mais de 20.',
            'example' => '8 moedas: cumprindo os três períodos sempre, são 2, 8 e 25 moedas, cerca de 119 por mês; a conta VIP custa 150 moedas e um pacote de 50 moedas custa 4,99 zł.',
            'range' => '0 – 20 moedas.',
            'tip' => 'Os jogadores sentem um bônus menor como uma recompensa tirada, então comece baixo: aumentar depois é fácil.',
        ],
        '/missions/periods/monthly/bonus_coins' => [
            'label' => 'Bônus pelo conjunto completo: mês (moedas)',
            'help' => 'Quantas moedas premium (RideCoin) o jogador ganha por cumprir todas as missões do conjunto do mês. São as únicas moedas grátis que as missões dão; as recompensas das missões avulsas não têm moedas. Este número define, portanto, todas as moedas grátis deste período, e o esquema não permite mais de 60.',
            'example' => '25 moedas: cumprindo os três períodos sempre, são 2, 8 e 25 moedas, cerca de 119 por mês; a conta VIP custa 150 moedas e um pacote de 50 moedas custa 4,99 zł.',
            'range' => '0 – 60 moedas.',
            'tip' => 'Os jogadores sentem um bônus menor como uma recompensa tirada, então comece baixo: aumentar depois é fácil.',
        ],
        '/missions/periods/daily/cash_ppm' => [
            'label' => 'Dinheiro por missão: dia (% do valor)',
            'help' => 'A parte do valor das missões do conjunto do dia que é paga em dinheiro (1.000.000 é tudo). O valor de uma missão é o trabalho que ela pede, em tanques do melhor carro do jogador: voltas, ganhos, combustível, caixas, aprimoramentos e o resto têm um peso no documento das missões (effort), então uma missão que pede muito paga muito. O dinheiro são tantos tanques de ganho depois do combustível. É um extra sobre o ganho dos carros e não conta na economia do jogo: as barreiras e o simulador não o veem.',
            'example' => '10%: uma missão que vale 4 tanques de um carro que ganha $ 375 por tanque paga cerca de $ 150.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/weekly/cash_ppm' => [
            'label' => 'Dinheiro por missão: semana (% do valor)',
            'help' => 'A parte do valor das missões do conjunto da semana que é paga em dinheiro (1.000.000 é tudo). O valor de uma missão é o trabalho que ela pede, em tanques do melhor carro do jogador: voltas, ganhos, combustível, caixas, aprimoramentos e o resto têm um peso no documento das missões (effort), então uma missão que pede muito paga muito. O dinheiro são tantos tanques de ganho depois do combustível. É um extra sobre o ganho dos carros e não conta na economia do jogo: as barreiras e o simulador não o veem.',
            'example' => '10%: uma missão que vale 4 tanques de um carro que ganha $ 375 por tanque paga cerca de $ 150.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/monthly/cash_ppm' => [
            'label' => 'Dinheiro por missão: mês (% do valor)',
            'help' => 'A parte do valor das missões do conjunto do mês que é paga em dinheiro (1.000.000 é tudo). O valor de uma missão é o trabalho que ela pede, em tanques do melhor carro do jogador: voltas, ganhos, combustível, caixas, aprimoramentos e o resto têm um peso no documento das missões (effort), então uma missão que pede muito paga muito. O dinheiro são tantos tanques de ganho depois do combustível. É um extra sobre o ganho dos carros e não conta na economia do jogo: as barreiras e o simulador não o veem.',
            'example' => '10%: uma missão que vale 4 tanques de um carro que ganha $ 375 por tanque paga cerca de $ 150.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/daily/xp_ppm' => [
            'label' => 'XP por missão: dia (% do valor)',
            'help' => 'A parte do valor das missões do conjunto do dia que é paga em XP do jogador (1.000.000 é tudo): tantos tanques de XP do melhor carro. O nível do jogador abre pistas, cidades e peças, e o tutorial diz que os níveis devem ser trabalho das voltas, então mantenha este número pequeno: cada ponto muda o ritmo do jogo todo.',
            'example' => '6%: uma missão que vale 4 tanques de um carro cujo tanque dá 600 XP paga cerca de 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/weekly/xp_ppm' => [
            'label' => 'XP por missão: semana (% do valor)',
            'help' => 'A parte do valor das missões do conjunto da semana que é paga em XP do jogador (1.000.000 é tudo): tantos tanques de XP do melhor carro. O nível do jogador abre pistas, cidades e peças, e o tutorial diz que os níveis devem ser trabalho das voltas, então mantenha este número pequeno: cada ponto muda o ritmo do jogo todo.',
            'example' => '6%: uma missão que vale 4 tanques de um carro cujo tanque dá 600 XP paga cerca de 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/periods/monthly/xp_ppm' => [
            'label' => 'XP por missão: mês (% do valor)',
            'help' => 'A parte do valor das missões do conjunto do mês que é paga em XP do jogador (1.000.000 é tudo): tantos tanques de XP do melhor carro. O nível do jogador abre pistas, cidades e peças, e o tutorial diz que os níveis devem ser trabalho das voltas, então mantenha este número pequeno: cada ponto muda o ritmo do jogo todo.',
            'example' => '6%: uma missão que vale 4 tanques de um carro cujo tanque dá 600 XP paga cerca de 144 XP.',
            'range' => '0 – 1000%.',
        ],
        '/missions/main/cash_ppm' => [
            'label' => 'Dinheiro por missão principal (% do valor)',
            'help' => 'A parte do valor de uma missão principal que é paga em dinheiro, calculada quando ela abre (1.000.000 é tudo). Os objetivos de todo o tempo (voltas, ganhos, combustível) são convertidos em tanques do melhor carro do jogador nesse momento, então um carro melhor não os transforma em recompensa grátis; o resto (nível, carros, garagens, peças) tem um peso no documento das missões (effort). Essas recompensas são pagas uma vez.',
            'example' => '15%: “Dê 250 voltas” são cerca de 8 tanques de um carro que ganha $ 375 por tanque, então cerca de $ 469.',
            'range' => '0 – 1000%.',
        ],
        '/missions/main/xp_ppm' => [
            'label' => 'XP por missão principal (% do valor)',
            'help' => 'A parte do valor de uma missão principal que é paga em XP do jogador, calculada quando ela abre (1.000.000 é tudo). O nível do jogador é uma barreira do jogo, então mantenha este número pequeno; o XP do carro é dado à parte (car_xp_laps da missão).',
            'example' => '9%: “Dê 250 voltas” são cerca de 8 tanques de um carro cujo tanque dá 600 XP, então cerca de 450 XP.',
            'range' => '0 – 1000%.',
        ],
    ],
];
