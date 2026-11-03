# Zrzuty podręcznika panelu: jak je odtworzyć

Zrzuty w [`docs/podrecznik-panelu.md`](../../podrecznik-panelu.md) (pliki `docs/img/podrecznik-*.png`) i listy pod nimi robi jedno polecenie. Po zmianie wyglądu panelu albo opisów pól uruchom je jeszcze raz i podręcznik znów zgadza się z panelem.

```bash
node docs/podrecznik/zrzuty/zrzuty.mjs
```

## Co robi

1. **Przygotowuje bazę** (`przygotuj.php`): pusta baza sqlite z `migrate:fresh`, treść gry z repozytorium jako paczka na żywo, administrator `boss@example.com` (wydawca, z drugim składnikiem logowania: sekret i kod odzyskiwania są tylko do zrzutów), druga gra „Statki”, czternastu graczy, dwa szkice (cena silnika i cena VIP), paczka zbudowana z tych szkiców, instrukcja dla AI i gotowa odpowiedź do wklejenia na stronie „Most AI”.
2. **Uruchamia serwer panelu** (`php -S` z `api/public`, wyłączone `PAO_DISABLE`, sesje w plikach, treść z bazy) i przeglądarkę (Edge albo Chrome, bez okna) sterowaną przez protokół DevTools.
3. **Loguje się** z kodem z aplikacji (TOTP liczony w skrypcie z sekretu przygotowanej bazy).
4. **Dla każdego ekranu z `strony.json`** wchodzi na stronę, wykonuje kroki (kliknięcie zakładki, rozwinięcie sekcji, wklejenie tekstu), wstrzykuje w stronę **numerowane czerwone ramki** przy opisanych polach (JS tuż przed zrzutem) i robi zrzut całej strony w szerokości 1440 px.
5. **Wpisuje legendy** do podręcznika: dla każdego ekranu tekst zastępuje to, co leży między `<!-- legenda:nazwa -->` i `<!-- /legenda -->`. Legendy czyta `legendy.php` z katalogu pomocy panelu (`FieldHelp`): etykieta, opis, zakres, przykład i wskazówka pola, czyli te same zdania, które panel pokazuje pod polem. Nic nie jest napisane osobno.

Skrypt kończy się kodem 1 i wypisuje listę, gdy któryś znacznik nie znalazł elementu (strona się zmieniła): wtedy popraw `strony.json`.

Jedno uruchomienie loguje się raz. Kilka uruchomień w ciągu minuty (na przykład przy poprawianiu jednego ekranu) może zatrzymać logowanie zabezpieczeniem panelu przed zgadywaniem haseł: skrypt kończy się wtedy błędem „Nie udało się zalogować”. Poczekaj minutę i uruchom go jeszcze raz.

## Wymagania

- Node.js 22 lub nowszy (ma `WebSocket` i `fetch`), skrypt nie ma zależności z npm.
- PHP z zainstalowanymi zależnościami w `api/` (`composer install`).
- Edge albo Chrome zainstalowane w systemie (skrypt szuka sam; można podać `--przegladarka=ścieżka` albo zmienną `PODRECZNIK_PRZEGLADARKA`).
- Pełny przebieg trwa kilka minut: bramki symulatorów przy budowaniu przykładowej paczki i wolne strony (Studio, formularze) to większość czasu.

## Opcje

| Opcja | Znaczenie |
|---|---|
| `--tylko=a,b` | tylko te ekrany (`slug` z `strony.json`); legendy też tylko dla nich |
| `--bez-bazy` | nie zakładaj bazy od nowa, użyj istniejącej (`--baza=plik`) |
| `--bez-serwera` | nie uruchamiaj serwera (panel już działa pod `--port`) |
| `--bez-legend` | tylko zrzuty, bez wpisywania legend do podręcznika |
| `--sonda=admin/x,admin/y` | nic nie zapisuje; wypisuje pola (klucze pomocy), przyciski, zakładki i nagłówki strony: pomoc przy pisaniu nowego wpisu w `strony.json` (adres **bez** początkowego ukośnika, bo Git Bash zamienia go na ścieżkę dysku) |
| `--port=8009` | port serwera panelu |
| `--baza=plik` | plik bazy sqlite |
| `--przegladarka=exe` | Edge lub Chrome |

Zmienna `PODRECZNIK_BEZ_PACZKI=1` pomija budowanie przykładowej paczki (przyspiesza przebieg, ale strony paczek i publikacji będą puste).

## Jak dopisać zrzut nowej strony

1. `node docs/podrecznik/zrzuty/zrzuty.mjs --sonda=admin/nowa-strona --bez-bazy --baza=plik.sqlite`: wypisze klucze pomocy pól (`data-help`), przyciski i zakładki.
2. Dopisz wpis do `strony.json`:

```json
{
  "slug": "nazwa-zrzutu",
  "url": "/admin/nowa-strona",
  "czekaj": "form",
  "kroki": [{ "kliknij": "[role=tab]", "zawiera": "Tory" }],
  "maks_wysokosc": 2400,
  "znaczniki": [
    { "pomoc": "world.length_m" },
    { "selektor": "button", "zawiera": "Policz", "nazwa": "Policz", "opis": "co robi przycisk" }
  ]
}
```

- `pomoc`: klucz z katalogu pomocy; znacznik znajduje pole po atrybucie `data-help`, a legenda bierze jego opis z katalogu (`FieldHelp`).
- `selektor` (+ `zawiera`, `ktory`): dla przycisków i zakładek, które nie mają wpisu w katalogu; legenda bierze wtedy `nazwa` i `opis` z manifestu.
- `tlumaczenie`: klucz tekstu panelu (`lang`), gdy opisem ma być zdanie z tłumaczeń.
- Kroki (każdy może mieć `pauza_po` w ms, domyślnie 500): `kliknij` (selektor, opcjonalnie `zawiera`: tekst elementu), `wpisz` (`{selektor, tekst}`: klawisze wpisywane w pole), `ustaw` (`{selektor, tekst}`: ustawia wartość pola naraz; tekst `@odpowiedz` i `@odpowiedz_z_bledem` bierze odpowiedź AI z `.dane/seed.json`), `czekaj` (selektor, na który trzeba poczekać), `pauza` (ms), `przewin` (selektor do przewinięcia) i `js` (kod do wykonania, na przykład rozwinięcie sekcji).

3. W podręczniku dodaj w odpowiednim miejscu `![opis](img/podrecznik-nazwa-zrzutu.png)` i pod nim `<!-- legenda:nazwa-zrzutu -->` oraz `<!-- /legenda -->`, a potem uruchom skrypt z `--tylko=nazwa-zrzutu`.

## Katalog `.dane/`

Skrypt zapisuje tu to, co przekazuje między krokami (`seed.json` z sekretem logowania, tekstem odpowiedzi AI i kodem instrukcji, pliki wejścia i wyjścia legend). Katalog nie jest w repozytorium (`.gitignore`).

## Czego ta wersja nie sprawdza

Zrzuty są robione na danych demonstracyjnych, w jednym rozmiarze okna (1440 px szerokości). Nie obejmują okien dialogowych (na przykład „Zrób paczkę”, „Uruchom” i potwierdzenia) ani widoku w telefonie.
