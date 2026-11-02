<?php

return [

    'recovery' => [
        'invalid' => 'Este código de recuperação não é válido.',
        'locked' => 'Muitas tentativas malsucedidas. Tente novamente mais tarde.',
    ],

    'email' => [
        'sent' => 'Enviamos um link para :email. Ele só funciona uma vez e vale por :minutes minutos.',
        'already_linked' => 'Esta conta já tem um e-mail.',
    ],

    'login_link' => [
        'sent' => 'Se este e-mail pertence a uma garagem, um link está a caminho. Ele só funciona uma vez e vale por :minutes minutos.',
    ],

    'oauth' => [
        'unavailable' => 'Este tipo de login não está disponível.',
        'ticket_invalid' => 'Este login expirou ou já foi usado. Comece de novo.',
        'no_account' => 'Nenhuma garagem está vinculada a esta conta :provider. Comece a jogar como convidado e vincule a conta na sua garagem.',
        'wrong_account' => 'Este login foi iniciado para outra garagem. Comece de novo na garagem que você quer vincular.',
    ],

    'delete' => [
        'name_mismatch' => 'Digite o nome do seu piloto exatamente como está escrito para excluir a conta.',
    ],

    'token' => [
        'invalid' => 'Este link não é válido ou expirou.',
    ],

    'identity' => [
        'taken' => 'Este login já pertence a outra garagem. Entre nessa garagem ou use outro.',
        'provider_linked' => 'Esta conta já tem um login desse tipo.',
    ],

    'name' => [
        'attribute' => 'nome do piloto',
        'taken' => 'Este nome de piloto já está em uso.',
    ],

    'email_change' => [
        'sent' => 'Enviamos um link para :email. A troca só acontece quando ele for aberto (funciona uma vez, por :minutes minutos). O endereço antigo foi avisado.',
    ],

    'reauth' => [
        'sent' => 'Enviamos um link para o e-mail da conta. Ele funciona uma vez, por :minutes minutos. Volte aqui depois de abri-lo.',
    ],

    'refused' => [
        'identity_not_found' => 'Esta garagem não tem esse login.',
        'last_method' => 'Este é o último login desta garagem, então não pode ser desvinculado. Adicione outro antes.',
        'reauth_required' => 'Confirme que é você antes de desvincular um login.',
        'reauth_mismatch' => 'Esta não é a conta do provedor vinculada à garagem. Entre com a conta vinculada.',
        'reauth_no_method' => 'Esse login não pode ser usado para confirmar, porque a garagem não o tem.',
        'reauth_unavailable' => 'Não é possível confirmar quem você é a partir deste dispositivo. Entre novamente.',
        'no_email' => 'Esta garagem não entra com e-mail. Adicione um e-mail em vez de trocá-lo.',
        'email_same' => 'Esse já é o e-mail desta garagem.',
        'email_change_cooldown' => 'O e-mail foi trocado há pouco. A próxima troca será possível a partir de :date.',
        'consent_required' => 'Os termos ou a política de privacidade mudaram. Aceite a nova versão para continuar jogando.',
        'consent_version_changed' => 'Os textos mudaram enquanto você lia. Leia a nova versão.',
    ],

];
