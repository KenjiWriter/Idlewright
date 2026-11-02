<?php

return [

    'account_link' => [
        'claim' => [
            'subject' => 'Confirme seu e-mail para salvar sua garagem',
            'intro' => 'Alguém pediu para salvar a garagem de :driver com este e-mail. Se foi você, confirme abaixo.',
            'action' => 'Confirmar e-mail',
        ],
        'add' => [
            'subject' => 'Confirme seu e-mail para adicioná-lo à sua garagem',
            'intro' => 'Alguém pediu para adicionar este e-mail como login da garagem de :driver. Se foi você, confirme abaixo.',
            'action' => 'Confirmar e-mail',
        ],
        'login' => [
            'subject' => 'Seu link para entrar no RideIdle',
            'intro' => 'Use o botão abaixo para entrar na garagem de :driver.',
            'action' => 'Entrar',
        ],
        'existing' => [
            'subject' => 'Este e-mail já tem uma garagem',
            'intro' => 'Alguém tentou salvar uma nova garagem com este e-mail, mas ele já pertence à garagem de :driver. Se foi você e quer usar essa garagem neste aparelho, use o botão abaixo para entrar.',
            'action' => 'Entrar',
        ],
        'change' => [
            'subject' => 'Confirme o novo e-mail da sua garagem',
            'intro' => 'Alguém pediu para trocar o e-mail da garagem do piloto :driver para este endereço. Se foi você, confirme abaixo: até lá nada muda.',
            'action' => 'Confirmar o novo endereço',
        ],
        'reauth' => [
            'subject' => 'Confirme que é você',
            'intro' => 'Desvincular um login da garagem do piloto :driver exige confirmar que é você. Se for, confirme abaixo.',
            'action' => 'Sim, sou eu',
        ],
        'expires' => 'O link só funciona uma vez e vale por :minutes minutos.',
        'ignore' => 'Se não foi você, ignore esta mensagem: nada muda sem o link.',
        'fallback' => 'Se o botão não funcionar, copie e cole este endereço no navegador:',
    ],

    'account_notice' => [
        'change_asked' => [
            'subject' => 'Alguém pediu para trocar o e-mail da sua garagem',
            'intro' => 'Alguém pediu para trocar o e-mail da garagem do piloto :driver para :detail. Nada muda até que alguém abra o link enviado ao novo endereço. Se não foi você, proteja a garagem: cancelamos a troca e desconectamos todos os dispositivos.',
            'action' => 'Não fui eu',
        ],
        'changed' => [
            'subject' => 'O e-mail da sua garagem foi trocado',
            'intro' => 'O e-mail da garagem do piloto :driver foi trocado para :detail. Este endereço não serve mais para entrar. Se não foi você, é possível desfazer: devolvemos este endereço e desconectamos todos os dispositivos.',
            'action' => 'Não fui eu, desfazer a troca',
        ],
        'unlinked' => [
            'subject' => 'Um login foi desvinculado da sua garagem',
            'intro' => 'Um login foi desvinculado da garagem do piloto :driver: :detail. Se não foi você, entre na garagem e confira os seus logins.',
        ],
        'methods' => [
            'email' => 'e-mail',
            'google' => 'Google',
            'facebook' => 'Facebook',
            'apple' => 'Apple',
        ],
        'valid' => '{1} O link vale por :count dia.|[2,*] O link vale por :count dias.',
        'footer' => 'Este é um aviso de segurança do RideIdle. Você não precisa responder.',
    ],

];
