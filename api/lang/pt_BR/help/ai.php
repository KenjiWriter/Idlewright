<?php

return [
    'entries' => [
        'include' => [
            'label' => 'O que incluir',
            'help' => 'O que o assistente deve saber sobre o jogo: os carros, as peças e os números das regras. Uma resposta só poderá mudar o que você marcar aqui.',
            'example' => 'Só as peças, quando quiser mudar os preços delas: desmarque os carros e as regras.',
            'tip' => 'Quanto menos você marcar, mais curta é a instrução e mais exata é a conversa.',
            'options' => [
                'vehicles' => ['label' => 'Carros', 'help' => 'Uma tabela dos carros com seus números e os campos pelos quais podem ser escolhidos (tração, classe).'],
                'parts' => ['label' => 'Peças', 'help' => 'Uma tabela por encaixe (motor, pneus, …), com os números das peças.'],
                'rules' => ['label' => 'Números das regras', 'help' => 'Os números do Studio com seus limites e uma descrição de cada um.'],
            ],
        ],
        'simple' => [
            'label' => 'Só os números mais importantes',
            'help' => 'Ligado: só os números do modo simples do Studio (preços, recompensas, tempos, níveis) vão para a instrução. Desligado: todos os números das seções escolhidas.',
            'tip' => 'Uma instrução com todos os números é várias vezes mais longa.',
        ],
        'locale' => [
            'label' => 'Idioma das descrições',
            'help' => 'O idioma dos rótulos e das descrições dos números na instrução. As regras da conversa e o formato do bloco são sempre em inglês, e o assistente responde no idioma em que você escreve para ele.',
            'options' => [
                'pl' => ['label' => 'Polski', 'help' => 'Descrições em polonês.'],
                'en' => ['label' => 'English', 'help' => 'Descrições em inglês.'],
                'fr' => ['label' => 'Français', 'help' => 'Descrições em francês.'],
                'de' => ['label' => 'Deutsch', 'help' => 'Descrições em alemão.'],
                'pt-BR' => ['label' => 'Português (Brasil)', 'help' => 'Descrições em português do Brasil.'],
            ],
        ],
        'sections' => [
            'label' => 'Seções das regras',
            'help' => 'Quais partes dos números das regras incluir (as mesmas do Studio). Nenhuma marcada: todas.',
            'example' => 'Só “Recursos pagos” (premium), quando você fala do preço do VIP.',
            'tip' => 'Uma resposta só pode mudar as seções que você marcar.',
        ],
        'texts' => [
            'label' => 'Incluir os textos',
            'help' => 'Ligado: os nomes e as descrições dos carros e das peças, e os textos das seções das regras escolhidas, entram na instrução em todos os idiomas (alguns milhares de tokens a mais). Sem isso o assistente não vê os textos e não pode mudar nem traduzir nenhum.',
            'tip' => 'Ligue quando quiser renomear ou traduzir.',
        ],
        'design' => [
            'label' => 'Projetar este jogo do zero',
            'help' => 'Ligado: o assistente não muda números soltos, e sim projeta com você o jogo inteiro em etapas (palavras, carros, peças, números, pistas e produtos), e cada etapa termina em um bloco. Os textos entram na instrução seja qual for a chave “Incluir os textos”.',
            'tip' => 'Use em um jogo novo que o assistente de criação fez a partir do modelo. A instrução fica mais longa.',
        ],
    ],
];
