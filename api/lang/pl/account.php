<?php

return [

    'recovery' => [
        'invalid' => 'Ten kod odzyskiwania jest nieprawidłowy.',
        'locked' => 'Zbyt wiele nieudanych prób. Spróbuj ponownie później.',
    ],

    'email' => [
        'sent' => 'Wysłaliśmy link na :email. Działa jeden raz, przez :minutes minut.',
        'already_linked' => 'To konto ma już e-mail.',
    ],

    'login_link' => [
        'sent' => 'Jeśli ten e-mail należy do garażu, link jest w drodze. Działa jeden raz, przez :minutes minut.',
    ],

    'oauth' => [
        'unavailable' => 'Ten sposób logowania jest niedostępny.',
        'ticket_invalid' => 'To logowanie wygasło albo zostało już użyte. Zacznij je od nowa.',
        'no_account' => 'Żaden garaż nie jest połączony z tym kontem :provider. Zacznij grać jako gość i połącz konto w swoim garażu.',
        'wrong_account' => 'To logowanie zostało rozpoczęte dla innego garażu. Zacznij je od nowa z garażu, który chcesz połączyć.',
    ],

    'delete' => [
        'name_mismatch' => 'Wpisz imię kierowcy dokładnie tak, jak jest zapisane, żeby usunąć konto.',
    ],

    'token' => [
        'invalid' => 'Ten link jest nieprawidłowy albo wygasł.',
    ],

    'identity' => [
        'taken' => 'To logowanie należy już do innego garażu. Zaloguj się do tego garażu albo użyj innego sposobu.',
        'provider_linked' => 'To konto ma już logowanie tego rodzaju.',
    ],

    'name' => [
        'attribute' => 'imię kierowcy',
        'taken' => 'To imię kierowcy jest już zajęte.',
    ],

    'email_change' => [
        'sent' => 'Wysłaliśmy link na :email. Zmiana nastąpi dopiero po jego otwarciu (działa jeden raz, przez :minutes minut). Na dotychczasowy adres poszło powiadomienie.',
    ],

    'reauth' => [
        'sent' => 'Wysłaliśmy link na adres e-mail konta. Działa jeden raz, przez :minutes minut. Po jego otwarciu wróć tutaj.',
    ],

    'refused' => [
        'identity_not_found' => 'Ten garaż nie ma takiego sposobu logowania.',
        'last_method' => 'To ostatni sposób logowania tego garażu, więc nie można go odpiąć. Najpierw dodaj inny.',
        'reauth_required' => 'Potwierdź, że to Ty, zanim odepniesz sposób logowania.',
        'reauth_mismatch' => 'To inne konto u dostawcy niż to, które jest połączone z garażem. Zaloguj się połączonym kontem.',
        'reauth_no_method' => 'Tego sposobu nie da się użyć do potwierdzenia, bo garaż go nie ma.',
        'reauth_unavailable' => 'Z tego urządzenia nie da się potwierdzić tożsamości. Zaloguj się ponownie.',
        'no_email' => 'Ten garaż nie loguje się e-mailem. Dodaj e-mail zamiast go zmieniać.',
        'email_same' => 'To już jest e-mail tego garażu.',
        'email_change_cooldown' => 'E-mail zmieniono niedawno. Kolejna zmiana będzie możliwa od :date.',
        'consent_required' => 'Regulamin lub polityka prywatności się zmieniły. Zaakceptuj nową wersję, żeby grać dalej.',
        'consent_version_changed' => 'Teksty zmieniły się w trakcie czytania. Przeczytaj nową wersję.',
    ],

];
