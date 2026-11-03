<?php

// Odczytuje z katalogu pomocy panelu to, co mają mówić legendy zrzutów podręcznika: dla każdego klucza pomocy etykietę, opis, przykład, zakres i wskazówkę (po polsku), a dla
// każdego klucza tłumaczenia jego tekst. Nic tu nie jest napisane osobno: legenda to te same zdania, które operator widzi pod polem formularza.
//
// Wejście: plik JSON z kluczami (`PODRECZNIK_LEGENDY_WEJSCIE`: {"help": [...], "lang": [...]}), wyjście: plik JSON (`PODRECZNIK_LEGENDY_WYJSCIE`). Uruchamia go `zrzuty.mjs` przez
// `php artisan tinker --execute="require '<ścieżka>';"`.

use App\Filament\Support\FieldHelp;

app()->setLocale('pl');

$wejscie = json_decode((string) file_get_contents((string) getenv('PODRECZNIK_LEGENDY_WEJSCIE')), true, 512, JSON_THROW_ON_ERROR);
$wyjscie = ['help' => [], 'lang' => []];

foreach ($wejscie['help'] ?? [] as $klucz) {
    $wpis = FieldHelp::entry($klucz, 'pl');
    $wyjscie['help'][$klucz] = $wpis === null ? null : array_intersect_key($wpis, array_flip(['label', 'help', 'example', 'range', 'tip', 'options']));
}

foreach ($wejscie['lang'] ?? [] as $klucz) {
    $tekst = __($klucz);
    $wyjscie['lang'][$klucz] = $tekst === $klucz ? null : $tekst;
}

file_put_contents((string) getenv('PODRECZNIK_LEGENDY_WYJSCIE'), json_encode($wyjscie, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
