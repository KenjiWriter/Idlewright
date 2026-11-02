<?php

return [
    'entries' => [
        'premium' => [
            'label' => 'Como funciona o formulário dos recursos pagos',
            'help' => 'Aqui você define o que o jogo vende por moedas premium e por dinheiro real: os pacotes de moedas, a aceleração, o abastecimento automático, a conta VIP e o passe. O que você digita é salvo como rascunho das regras e chega aos jogadores com o próximo pacote publicado.',
            'steps' => [
                'Aba Moedas: dê nome à moeda, escolha a moeda dos preços e digite os pacotes de moedas com seus preços; o primeiro pacote define o preço de uma moeda pelo qual os outros são medidos.',
                'Abas Abastecimento automático, Conta VIP e Passe: ligue o recurso e preencha seus números; um recurso desligado não é oferecido, mas o que os jogadores já têm fica.',
                'Leia as linhas abaixo do formulário: elas convertem moedas em dinheiro ao preço de uma moeda do primeiro pacote e mostram o bônus de cada pacote.',
                'Salve o rascunho; a barra acima do formulário diz o que mudou. Os mesmos números estão no estúdio de parâmetros, e os dois mudam o mesmo rascunho.',
                'Publique um pacote na página Publicações. O provedor de pagamento é uma etapa separada que o jogo ainda não tem.',
            ],
            'example' => 'Um jogo de navios: a moeda Doubloon, três pacotes (50 por 4,99, 220 por 19,99, 600 por 49,99), abastecimento automático com estoque de 100, conta VIP de 30 dias por 150 dobrões com +20% de dinheiro e XP, e o passe desligado até um provedor de pagamento ser conectado.',
        ],
    ],
];
