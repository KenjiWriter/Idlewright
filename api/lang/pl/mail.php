<?php

return [

    'account_link' => [
        'claim' => [
            'subject' => 'Potwierdź e-mail, żeby zabezpieczyć garaż',
            'intro' => 'Ktoś poprosił o zabezpieczenie garażu kierowcy :driver tym adresem e-mail. Jeśli to Ty, potwierdź to poniżej.',
            'action' => 'Potwierdź e-mail',
        ],
        'add' => [
            'subject' => 'Potwierdź e-mail, żeby dodać go do garażu',
            'intro' => 'Ktoś poprosił o dodanie tego adresu e-mail jako sposobu logowania do garażu kierowcy :driver. Jeśli to Ty, potwierdź to poniżej.',
            'action' => 'Potwierdź e-mail',
        ],
        'login' => [
            'subject' => 'Twój link do logowania w RideIdle',
            'intro' => 'Użyj przycisku poniżej, żeby zalogować się do garażu kierowcy :driver.',
            'action' => 'Zaloguj się',
        ],
        'existing' => [
            'subject' => 'Ten e-mail ma już garaż',
            'intro' => 'Ktoś próbował zabezpieczyć nowy garaż tym adresem e-mail, ale należy on już do garażu kierowcy :driver. Jeśli to Ty i chcesz używać tego garażu na tym urządzeniu, zaloguj się poniżej.',
            'action' => 'Zaloguj się',
        ],
        'change' => [
            'subject' => 'Potwierdź nowy adres e-mail garażu',
            'intro' => 'Ktoś poprosił o zmianę e-maila garażu kierowcy :driver na ten adres. Jeśli to Ty, potwierdź to poniżej: do tej chwili nic się nie zmienia.',
            'action' => 'Potwierdź nowy adres',
        ],
        'reauth' => [
            'subject' => 'Potwierdź, że to Ty',
            'intro' => 'Odpięcie sposobu logowania od garażu kierowcy :driver wymaga potwierdzenia, że to Ty. Jeśli to Ty, potwierdź poniżej.',
            'action' => 'To ja, potwierdzam',
        ],
        'expires' => 'Link działa jeden raz i przez :minutes minut.',
        'ignore' => 'Jeśli to nie Ty, zignoruj tę wiadomość: bez linku nic się nie zmieni.',
        'fallback' => 'Jeśli przycisk nie działa, skopiuj ten adres do przeglądarki:',
    ],

    'account_notice' => [
        'change_asked' => [
            'subject' => 'Ktoś prosi o zmianę e-maila Twojego garażu',
            'intro' => 'Ktoś poprosił o zmianę e-maila garażu kierowcy :driver na :detail. Nic się nie zmieni, dopóki ktoś nie otworzy linku wysłanego na nowy adres. Jeśli to nie Ty, zabezpiecz garaż: anulujemy zmianę i wylogujemy wszystkie urządzenia.',
            'action' => 'To nie ja',
        ],
        'changed' => [
            'subject' => 'E-mail Twojego garażu został zmieniony',
            'intro' => 'E-mail garażu kierowcy :driver zmieniono na :detail. Ten adres już nie służy do logowania. Jeśli to nie Ty, możesz to cofnąć: przywrócimy ten adres i wylogujemy wszystkie urządzenia.',
            'action' => 'To nie ja, cofnij zmianę',
        ],
        'unlinked' => [
            'subject' => 'Odpięto sposób logowania z Twojego garażu',
            'intro' => 'Z garażu kierowcy :driver odpięto sposób logowania: :detail. Jeśli to nie Ty, zaloguj się do garażu i sprawdź swoje sposoby logowania.',
        ],
        'methods' => [
            'email' => 'e-mail',
            'google' => 'Google',
            'facebook' => 'Facebook',
            'apple' => 'Apple',
        ],
        'valid' => '{1} Link działa przez :count dzień.|[2,*] Link działa przez :count dni.',
        'footer' => 'To powiadomienie bezpieczeństwa z RideIdle. Nie musisz na nie odpowiadać.',
    ],

];
