<?php

return [
    'entries' => [
        'variant' => [
            'label' => 'Calcular sobre',
            'help' => 'O pacote ativo é o jogo dos jogadores; os rascunhos são o que um pacote feito agora teria.',
            'options' => [
                'live' => [
                    'label' => 'Pacote ativo',
                    'help' => 'O jogo como os jogadores o têm hoje.',
                ],
                'drafts' => [
                    'label' => 'Rascunhos',
                    'help' => 'O jogo com os rascunhos salvos, antes de um pacote.',
                ],
            ],
        ],
        'car' => [
            'label' => 'Carro inicial',
            'help' => 'O carro com que o piloto começa. Vazio: o carro inicial mais barato do jogo.',
            'example' => 'rdzak-gt-4',
        ],
        'style' => [
            'label' => 'Forma de jogar',
            'help' => 'Com que frequência o piloto está no jogo. Quando não está, o carro anda sozinho enquanto durar o combustível.',
            'options' => [
                'active' => [
                    'label' => 'Joga sem pausas',
                    'help' => 'O piloto está no jogo o tempo todo: abastece e compra na hora.',
                ],
                'every' => [
                    'label' => 'Volta a cada N minutos',
                    'help' => 'O piloto volta a cada tantos minutos e fica tantos minutos.',
                ],
                'A' => [
                    'label' => 'Jogador A',
                    'help' => 'O jogador A do simulador de economia: 3 visitas por dia.',
                ],
                'B' => [
                    'label' => 'Jogador B',
                    'help' => 'O jogador B do simulador de economia: 1 visita por dia.',
                ],
                'C' => [
                    'label' => 'Jogador C',
                    'help' => 'O jogador C do simulador de economia: 8 visitas por dia.',
                ],
            ],
        ],
        'every_minutes' => [
            'label' => 'Volta a cada (minutos)',
            'help' => 'Os minutos do início de uma visita ao início da seguinte.',
            'example' => '60',
        ],
        'visit_minutes' => [
            'label' => 'Fica (minutos)',
            'help' => 'A duração de uma visita em minutos; não mais que o tempo entre as visitas.',
            'example' => '10',
        ],
        'minutes' => [
            'label' => 'Minutos de jogo',
            'help' => 'Quanto tempo o simulador joga desde a criação da conta; no máximo 43.200 minutos (30 dias).',
            'example' => '120 são duas horas, 1440 um dia.',
        ],
        'track' => [
            'label' => 'Pista',
            'help' => 'O piloto anda nesta pista assim que ela abre. Vazio: sempre a melhor pista aberta.',
        ],
        'goal' => [
            'label' => 'Escolher a pista por',
            'help' => 'O que o piloto mede para escolher a melhor pista.',
            'options' => [
                'profit' => [
                    'label' => 'Lucro por hora',
                    'help' => 'O pagamento menos o combustível, por hora de direção.',
                ],
                'xp' => [
                    'label' => 'XP por hora',
                    'help' => 'A experiência do piloto por hora de direção.',
                ],
            ],
        ],
        'buy' => [
            'label' => 'Compras',
            'help' => 'O que o piloto compra; ele sempre guarda o dinheiro de um tanque cheio.',
            'options' => [
                'none' => [
                    'label' => 'Nada',
                    'help' => 'O piloto anda com o que recebeu.',
                ],
                'list' => [
                    'label' => 'Uma lista, em ordem',
                    'help' => 'Compra as peças e carros da lista um a um, quando o dinheiro e o nível permitem.',
                ],
                'best' => [
                    'label' => 'O maior lucro por dólar',
                    'help' => 'Compra o que mais aumenta o resultado de uma hora em relação ao preço.',
                ],
            ],
        ],
        'list' => [
            'label' => 'Lista de compras',
            'help' => 'Slugs de peças e carros separados por vírgulas, na ordem de compra.',
            'example' => 'street-tires-t2, disc-brakes-t2',
        ],
        'step_seconds' => [
            'label' => 'Uma etapa do tutorial (s)',
            'help' => 'Quantos segundos de jogo leva uma etapa do tutorial. O tempo real será medido depois com os dados dos jogadores.',
            'example' => '60',
        ],
        'tutorial' => [
            'label' => 'Com tutorial',
            'help' => 'O piloto passa primeiro pelo tutorial e recebe suas recompensas.',
        ],
        'missions' => [
            'label' => 'Com missões',
            'help' => 'Soma as recompensas diárias das missões ao fim de cada dia de jogo.',
        ],
        'travel' => [
            'label' => 'Viagens',
            'help' => 'O piloto vai para outra cidade onde tem garagem, quando isso compensa no tempo que resta.',
        ],
    ],
];
