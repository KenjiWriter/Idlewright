<?php

return [
    'entries' => [
        '/tuning/slots' => [
            'label' => 'Espaços para bônus',
            'help' => 'Quantos espaços para bônus uma peça tem, a menos que o modelo dela diga outra coisa. O jogador os preenche e troca na oficina.',
            'example' => '3: toda peça tem três espaços, vazios no início.',
        ],
        '/tuning/unlock_level' => [
            'label' => 'Nível a partir do qual a oficina ajusta',
            'help' => 'A partir de que nível de jogador a oficina ajusta peças, ou seja, coloca bônus nelas.',
            'example' => '3: a partir do nível três.',
        ],
        '/tuning/cost_random_ppm' => [
            'label' => 'Preço de uma tentativa com bônus aleatório',
            'help' => 'Que parte do preço do modelo da peça custa uma tentativa quando o servidor sorteia o bônus. 30.000 são 3%.',
            'example' => '30.000: uma peça de $570 custa $17,10 por tentativa.',
        ],
        '/tuning/cost_pick_ppm' => [
            'label' => 'Preço de uma tentativa com bônus escolhido',
            'help' => 'Que parte do preço do modelo custa uma tentativa quando o jogador escolhe o bônus (mais caro que um aleatório). 100.000 são 10%.',
            'example' => '100.000: uma peça de $570 custa $57 por tentativa.',
        ],
        '/tuning/cost_min_cents' => [
            'label' => 'Menor preço de uma tentativa',
            'help' => 'O mínimo que uma tentativa custa, mesmo para peças baratas, em centavos.',
            'example' => '500: nunca menos que $5.',
        ],
        '/tuning/time_ppm_of_mount' => [
            'label' => 'Duração de uma tentativa',
            'help' => 'Quanto dura uma tentativa em relação ao tempo de montagem da peça. 500.000 é a metade.',
            'example' => '500.000: montar leva 2 minutos, uma tentativa 1 minuto.',
        ],
        '/tuning/floor_sigma_ppm' => [
            'label' => 'Piso de um novo bônus',
            'help' => 'O primeiro valor de um bônus que acaba de entrar no espaço não fica mais abaixo da média do que isto, em milionésimos de sigma. Assim trocar de bônus para outra pista não é castigo.',
            'example' => '1.000.000: nunca abaixo de um sigma sob a média.',
        ],
        '/tuning/pity_step_ppm' => [
            'label' => 'Passo da proteção contra azar',
            'help' => 'Quanto (em milionésimos de sigma) a média sobe após cada tentativa nesse espaço que não melhorou nada. 0 desliga a proteção.',
            'example' => '0: sem proteção; 50.000: após 10 tentativas sem ganho a média sobe meio sigma.',
        ],
        '/tuning/pity_max_ppm' => [
            'label' => 'Máximo da proteção',
            'help' => 'O máximo que a proteção contra azar desloca a média, em milionésimos de sigma.',
            'example' => '1.000.000: no máximo um sigma.',
        ],
        '/tuning/car_cap_ppm' => [
            'label' => 'Teto de bônus no carro',
            'help' => 'O máximo que os bônus de todas as peças de um carro podem somar a uma estatística em um contexto, em ppm.',
            'example' => '250.000: no máximo +25%.',
        ],
        '/tuning/max_ppm_plain' => [
            'label' => 'Maior bônus sem contexto',
            'help' => 'O máximo que um bônus que vale em toda pista pode dar no melhor caso. Um pacote com um bônus que pode dar mais é recusado.',
            'example' => '80.000: no máximo +8%.',
        ],
        '/tuning/max_ppm_context' => [
            'label' => 'Maior bônus com contexto',
            'help' => 'O máximo que um bônus que vale só em um tipo de pista, piso ou clima pode dar no melhor caso. Um contexto estreito permite mais.',
            'example' => '120.000: no máximo +12%.',
        ],
        '/tuning/definitions/*/weight' => [
            'label' => 'Peso do bônus',
            'help' => 'Com que frequência o bônus é sorteado entre os que servem ao slot da peça, quando o jogador não escolhe.',
            'example' => '3 ao lado de outros de peso 3 e 2: sai em 3 de 8 casos.',
        ],
        '/tuning/definitions/*/from_level' => [
            'label' => 'Nível a partir do qual o bônus é oferecido',
            'help' => 'A partir de que nível de jogador o bônus aparece na oficina. Abaixo dele o bônus não pode ser sorteado nem escolhido.',
            'example' => '8: o bônus de neve para jogadores a partir do nível 8.',
        ],
        '/tuning/definitions/*/mean_ppm' => [
            'label' => 'Valor mais comum do bônus',
            'help' => 'O meio da curva: o valor em torno do qual o bônus é sorteado. 50.000 são 5%.',
            'example' => '50.000: sai mais vezes perto de 5%.',
        ],
        '/tuning/definitions/*/sigma_ppm' => [
            'label' => 'Dispersão do bônus (sigma)',
            'help' => 'O quanto os valores se espalham em torno da média: quanto maior o sigma, mais vezes saem valores longe da média. Um valor dois sigmas acima da média sai uma vez em 70 tentativas.',
            'example' => '23.000: dispersão de 2,3%, então 10% sai uma vez em 70 tentativas.',
        ],
        '/tuning/definitions/*/min_ppm' => [
            'label' => 'Menor valor do bônus',
            'help' => 'O mínimo que um bônus pode dar. Um valor sorteado abaixo dele é elevado a ele.',
            'example' => '10.000: nunca abaixo de 1%.',
        ],
        '/tuning/definitions/*/max_ppm' => [
            'label' => 'Maior valor do bônus',
            'help' => 'O máximo que um bônus pode dar. Um valor logo abaixo dele é muito raro. Não pode passar do limite de um bônus com ou sem contexto das configurações gerais.',
            'example' => '120.000: no máximo 12%, e sai uma vez em mais de mil tentativas.',
        ],
    ],
];
