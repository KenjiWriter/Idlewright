# RideIdle

Przeglądarkowa gra idle o wyścigach. Gracz odbudowuje wraki, kupuje i ulepsza auta, wysyła je na tory w pięciu miastach i wraca po wypłatę, bo serwer liczy postęp także wtedy, gdy gracza nie ma. Gra działa jako PWA; aplikacje mobilne (Capacitor) są w planach.

Pod spodem jest silnik sterowany danymi. Auta, części, tory, reguły, słowa świata gry, wygląd i samouczek to treść, którą operator zmienia w panelu i publikuje jako paczkę. Jedna instalacja obsługuje wiele gier, każdą na własnej domenie, z własną marką i treścią (przykład innego świata: motyw statków w [`content/themes/ships.json`](content/themes/ships.json)).

Projekt (założenia, systemy gry, katalogi, rozliczanie, architektura, roadmapa i plansze ekranów) jest na płótnie projektowym: https://claude.ai/artifact/Ag5ArvxSnEiuVvr4vUzecy. Ekrany aplikacji mają wyglądać jak plansze z płótna.

## Co jest w grze

Stan gałęzi `main` na 2026-11-02. Szczegóły są w dokumentach podanych przy każdym obszarze.

**Gracz i konto** ([`docs/konto.md`](docs/konto.md), [`docs/prawo.md`](docs/prawo.md))
- Wejście jako gość z imieniem kierowcy i kodem odzyskiwania, potem zabezpieczenie konta linkiem z e-maila albo przez Google, Facebooka lub Apple.
- Odpinanie sposobu logowania, zmiana e-maila, ponowna zgoda po zmianie regulaminu.
- Regulamin i polityka prywatności w pięciu językach, kopia danych i usunięcie konta.

**Bieg i rozliczanie** ([`docs/settlement.md`](docs/settlement.md), [`docs/api-runs.md`](docs/api-runs.md))
- Auto jeździ okrążenia toru bez udziału gracza, a wynik liczy czysta funkcja `settle` na liczbach całkowitych.
- Paliwo i tankowanie, awarie, naprawa i holowanie, siedem botów na torze i nagroda za pozycję.
- Zegar gry (trzy godziny to doba, a noc wydłuża okrążenie na torach bez świateł), pogoda, nawierzchnie i opony.
- Podsumowanie nieobecności dla całego konta.

**Tory** ([`content/README.md`](content/README.md), [`docs/nitka-toru.md`](docs/nitka-toru.md))
- 15 torów, po trzy w każdym z pięciu miast, w rodzajach sprint, standard i dystans.
- Długość toru wyznacza czas okrążenia, paliwo, wypłatę i XP. Każdy tor ma narysowaną nitkę, a okrążenie po niej może liczyć model punktu materialnego. Ekran wyścigu pokazuje nitkę z autem.
- Jednostki per gra: metryczne, morskie albo imperialne.

**Auta, części i warsztat** ([`docs/api-runs.md`](docs/api-runs.md), [`docs/web-run.md`](docs/web-run.md))
- Paczka `core` ma 23 auta i 74 części. Auto ma silnik, napęd (FWD, RWD albo AWD) i grupę silnika (turbosprężarki, kompresory, chłodzenie, skrzynie).
- Rzadkość części (od Common do Legendary), afiksy, bonusy części (druga warstwa statystyk, liczona w biegu), ulepszanie +N, łączenie dwóch części w lepszą, transmutacja rzadkości za gotówkę i monety.
- Przebieg, kondycja części, serwis i awarie, ekran „Stan techniczny”.
- Warsztat w każdym mieście: wyposażenie, stanowiska, mechanicy i kolejka zleceń. Inwentarz porównuje każdą część z tą, która jest założona.

**Świat i rynek** ([`docs/api-runs.md`](docs/api-runs.md), [`docs/web-run.md`](docs/web-run.md))
- Mapa z pięcioma miastami i drogami, podróż auta (także przyspieszona, z ryzykiem zatrzymania przez policję), garaże w miastach, flota.
- Limit aut aktywnych: dwa na drodze, trzy więcej z kontem VIP, reszta stoi w magazynie.
- Giełda, dealer, sklep ze skrzynkami części, sprzedaż aut za 70% wartości, magazyny do otwierania.

**Postęp i powroty** ([`docs/api-runs.md`](docs/api-runs.md), [`docs/web-run.md`](docs/web-run.md))
- XP i poziomy aut, poziom gracza.
- Samouczek prowadzony SMS-ami od Kuby: 23 kroki od pierwszego wraku po misje główne, z paskiem celu pod paskiem gracza i strzałką do celu, który jest poza ekranem.
- Misje: główne łańcuchy (Kierowca, Mechanik, Kolekcjoner) oraz misje dzienne, tygodniowe i miesięczne z bonusem RideCoin za komplet. Seria dni z osłonami.

**Płatne funkcje** ([`docs/api-runs.md`](docs/api-runs.md), [`docs/settlement.md`](docs/settlement.md))
- Waluta premium RideCoin (tylko wewnątrz gry), kończenie zleceń i podróży od razu, prawnik po zatrzymaniu przez policję, automatyczne tankowanie, konto VIP i przepustka nieskończonego tankowania.
- Rdzeń płatności prawdziwymi pieniędzmi: zamówienie, podpisany webhook, zwroty i spory. Działa na dostawcy testowym i sprawdza podpis Stripe, ale prawdziwy Stripe nie jest jeszcze podłączony.

**Wygląd** ([`docs/web-run.md`](docs/web-run.md))
- Scena z autem, karta danych, pasek u góry (paliwo, RideCoin, auta), części jako punkty na aucie, warsztat i sklep na scenie. Kolory i czcionki pochodzą z dokumentu marki gry.
- PWA: manifest i ikony każdej gry, service worker, który trzyma pliki aplikacji i nigdy nie dotyka `/api/`.

**Panel operatora** (Filament 5 pod `/admin`; [`docs/api-runs.md`](docs/api-runs.md), [`deploy/README.md`](deploy/README.md))
- Administratorzy z 2FA, role `editor` i `publisher`, dziennik działań.
- Treść z plików albo z bazy (`CONTENT_SOURCE`), szkice aut i części, publikacja paczki przez bramki symulatorów, cofnięcie do wcześniejszej paczki.
- Strony: Studio (wszystkie liczby reguł z suwakami, także w trybie prostym), Słownik, Model gry (sloty i statystyki jako dane), Świat (edytor mapy), Samouczek, Nitka toru, Misje, Płatne funkcje, Marka i strona, obrazki trzymane w bazie.
- Sieć gier: kreator nowej gry, domeny i SSL, lista graczy gry z plikiem CSV, pasek zmian z podglądem gry, katalog pomocy każdego pola w pięciu językach.

**Pomiary**
- `metrics:report` (retencja D1–D7, sesje), symulatory ekonomii, torów, misji i serii dni, test obciążeniowy w [`deploy/loadtest/`](deploy/loadtest).

## Stack

| Warstwa | Technologia |
|---|---|
| Backend | PHP 8.4, Laravel 13 (API-first), Sanctum, Socialite |
| Panel | Filament 5 (osobny host na produkcji) |
| Frontend | Vue 3, TypeScript, Vite, Pinia, Vue Router, Tailwind CSS 4, vue-i18n |
| Baza | lokalnie MariaDB lub MySQL, testy domyślnie na SQLite w pamięci, produkcja PostgreSQL |
| Cache, kolejki, blokady | lokalnie baza; na produkcji liczniki limitów w Redisie, a kolejka w bazie (żeby `accounts:delete` mogło usunąć czekające maile) |
| Serwer | Ubuntu, nginx, PHP-FPM, jedna domena na grę ([`deploy/`](deploy)) |
| Testy | Pest 4 (także testy architektury), Vitest |
| CI | GitHub Actions: API na SQLite, MariaDB i PostgreSQL, web z typami, testami i buildem |

## Struktura repozytorium

```
api/                Laravel: logika gry, rozliczanie, API, panel
  app/Domain/       czyste funkcje bez frameworka, bazy i zegara: rozliczanie, bieg, ekonomia, rynek, warsztat
  app/Services/     operacje na bazie (transakcje, księga, misje, warsztat, rynek, konta)
  app/Filament/     panel operatora: strony i zasoby
  app/Games/        wiele gier: gra z hosta żądania, zakres zapytań
  app/Payments/     płatności: dostawca testowy, Stripe, webhook
  lang/<język>/     teksty API i katalog pomocy panelu (help/)
web/                Vue 3: aplikacja gracza (PWA)
  src/views/        ekrany (garaż, warsztat, sklep, mapa, wyścig, misje, premium...)
  src/features/     logika ekranów pogrupowana według obszarów gry
  src/i18n/locales/ teksty aplikacji w pięciu językach
content/            treść w JSON
  packs/core/       paczka: auta i części
  rules/            reguły: bieg i świat (starter), ekonomia, misje, model, samouczek, słownik
  schema/           schematy JSON paczek i reguł
  tables/           tabele liczone przez komendy (profile botów, tabela Elo, tempo rzadkości, zwroty jednostek)
  themes/           motywy innych światów
deploy/             wdrożenie na Ubuntu: skrypty, nginx, systemd, kopie bazy, test obciążeniowy
docker/             usługi pomocnicze do pracy lokalnej
docs/               specyfikacje i decyzje przeniesione z płótna
```

## Zasady, które obowiązują od pierwszego dnia

- Serwer jest jedynym źródłem czasu i wyników. Klient wysyła intencje, nigdy wyniki.
- Rozliczanie jest funkcją czystą na liczbach całkowitych (czas w ms, gotówka w centach), z testami wektorowymi i testem własności (`settle(t1)` potem `settle(t2)` daje to samo co `settle(t2)`).
- Każda zmiana salda lub stanu idzie w transakcji z `lockForUpdate()` i kluczem idempotencji.
- Definicje (treść z paczek) i instancje (rzeczy graczy) są rozdzielone. Slug definicji jest niezmienny.
- Kod pyta o rolę slotu z modelu gry (`rules/model`), nie o jego nazwę, więc gra z innymi nazwami slotów jeździ tak samo.
- Treść i konta należą do gry (`game_id`), a grę wybiera host żądania.
- Kod działa na MariaDB i PostgreSQL. Różnice wychodzą w CI, nie na produkcji.

## Języki

Gra od pierwszego dnia działa w pięciu językach: `pl`, `en`, `fr`, `de`, `pt-BR` (skrót `br` to alias brazylijskiego portugalskiego). Angielski jest językiem zapasowym.

- **API:** język odpowiedzi wybiera middleware `SetLocale` w kolejności: nagłówek `X-Locale`, język zapisany na koncie (`users.locale`), nagłówek `Accept-Language`, język domyślny. Odpowiedź zawiera `Content-Language`. Komunikaty gry są w `api/lang/<język>/game.php`, pomoc pól panelu w `api/lang/<język>/help/`, a teksty z paczek treści (nazwy, opisy) w polach JSON z tłumaczeniami (`I18nText`).
- **Web:** `vue-i18n`, pliki w `web/src/i18n/locales/<język>.json`, ładowane leniwie (angielski od razu). Liczby i waluta idą przez `Intl`, więc wyglądają zgodnie ze zwyczajem języka. Słowa świata gry (na przykład „auto”, „tor”) mogą pochodzić ze słownika paczki zamiast z plików aplikacji.
- **Testy pilnują zgodności:** te same klucze i te same symbole zastępcze we wszystkich językach (API, web i katalog pomocy), a lista języków w `web` musi zgadzać się z `api/config/ride.php`.

Nowy tekst dodajemy od razu we wszystkich pięciu językach, bo test zgodności inaczej zgłosi błąd. Teksty francuskie, niemieckie i brazylijskie nie przeszły jeszcze przeglądu osoby, dla której to język ojczysty.

## Uruchomienie lokalne

Wymagania: PHP 8.4 z Composerem, Node 24, baza MariaDB lub MySQL (np. z Laragona). Docker jest opcjonalny.

```sh
# API
cd api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve              # http://127.0.0.1:8000, panel pod /admin
php artisan queue:work         # maile i publikacja paczek z panelu

# Web
cd web
npm install
npm run dev                    # http://localhost:5173, /api idzie do DEV_API_TARGET (domyślnie port 8000)

# Usługi pomocnicze w Dockerze: MariaDB (3307), PostgreSQL (5432), Redis (6379), Mailpit (SMTP 1025, podgląd 8025)
docker compose -f docker/compose.yaml up -d
```

- Treść gra czyta domyślnie z plików repozytorium (`CONTENT_SOURCE=files`), więc nie trzeba niczego importować.
- Pierwszy administrator panelu: `php artisan admin:create ty@example.com --role=publisher` (hasło jest pokazane raz, przy pierwszym logowaniu panel wymaga aplikacji uwierzytelniającej).
- Maile z `.env.example` idą na `127.0.0.1:1025`, czyli do Mailpita z Dockera.
- Jeśli port 8000 zajmuje inny projekt, uruchom API na innym (`php artisan serve --port=8011`) i wskaż go w `web/.env.local`: `DEV_API_TARGET=http://127.0.0.1:8011`.

## Testy

```sh
cd api && vendor/bin/pest                                  # domyślnie SQLite w pamięci
cd web && npm test && npm run typecheck && npm run build
```

- Testy API na konkretnej bazie: `DB_CONNECTION=mariadb DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=ride_idle DB_USERNAME=root DB_PASSWORD=secret vendor/bin/pest`. CI uruchamia je na SQLite, MariaDB i PostgreSQL.
- Nie uruchamiaj dwóch pełnych zestawów Pest naraz na jednej maszynie: dzielą katalog tymczasowy Livewire i jeden potrafi zepsuć drugiemu testy obrazków.
- `php artisan content:validate` sprawdza paczkę ze schematem, a `content:check` (uruchamia go też wydanie), czy treść, którą czyta gra, jest cała i pasuje do kodu.
- Testy pilnują między innymi czystości `app/Domain` (testy architektury), zgodności języków i kompletności katalogu pomocy.

## Przydatne komendy

Wszystkie uruchamia się w `api/` przez `php artisan <komenda>`, a `--help` pokazuje opcje. Komendy treści, kont, premium i część symulatorów przyjmują `--game`, gdy chodzi o grę inną niż domyślna.

| Obszar | Komendy |
|---|---|
| Treść | `content:validate`, `content:check`, `content:status`, `content:import`, `content:export`, `content:activate`, `content:convert-tracks`, `content:elo-table` |
| Balans i symulatory | `economy:simulate`, `economy:fleet`, `economy:preview`, `economy:rarity-pace`, `economy:unit-returns`, `economy:bot-profiles`, `economy:transmute`, `tracks:simulate`, `missions:budget`, `streaks:budget`, `parts:roll-affixes` |
| Konta i dane | `accounts:export`, `accounts:delete`, `accounts:prune-guests`, `accounts:prune-tokens` |
| Panel i domeny | `admin:create`, `admin:update`, `domains:claim`, `domains:report` |
| Premium i płatności | `premium:grant`, `premium:pass`, `payments:status`, `payments:switch`, `payments:reprocess`, `payments:prune`, `purchases:prune` |
| Pomiary i sprzątanie | `metrics:report`, `missions:prune`, `loadtest:accounts`, `loadtest:cleanup` |

## Wdrożenie

[`deploy/`](deploy) stawia grę na jednym serwerze Ubuntu, docelowo pod `raceidle.cerasusdigital.pl` (aplikacja web pod `/`, API pod `/api`):

- `deploy/upload.sh bootstrap root@SERWER` i `provision.sh` raz przygotowują serwer: PHP, PostgreSQL, Redis, nginx, certyfikat Let's Encrypt, kolejka, harmonogram, kopie bazy ze sprawdzaniem, zapora.
- `deploy/upload.sh release deploy@DOMENA` wydaje zatwierdzony stan repozytorium, a gdy sprawdzenie po wydaniu się nie uda, wraca do poprzedniego.
- `deploy/admin-site.sh` wystawia panel na osobnym hoście (`admin.raceidle.cerasusdigital.pl`).
- Kolejna gra na własnej domenie: `deploy/game-site.sh` albo z panelu (panel zapisuje zlecenie, a worker na serwerze co minutę stawia domenę i certyfikat).

Kolejność kroków, DNS, poczta, adresy dla Google, Facebooka i Apple, kopie, wycofanie i lista tego, co sprawdzono: [`deploy/README.md`](deploy/README.md). Zestaw nie był jeszcze uruchomiony na prawdziwym serwerze Ubuntu.

## Dokumentacja

| Plik | Co opisuje |
|---|---|
| [`docs/settlement.md`](docs/settlement.md) | rozliczanie: klasy, wektory testowe, testy własności, zamrażanie konfiguracji biegu, zapis w bazie |
| [`docs/api-runs.md`](docs/api-runs.md) | API gry: punkty końcowe, garaż, warsztat, świat, rynek, płatne funkcje, misje, panel, samouczek |
| [`docs/web-run.md`](docs/web-run.md) | ekrany aplikacji web, wygląd, PWA |
| [`docs/konto.md`](docs/konto.md) | konto i logowanie, e-mail, dostawcy, kopia i usunięcie danych |
| [`docs/prawo.md`](docs/prawo.md) | regulamin, polityka prywatności, pytania do przeglądu prawnego |
| [`docs/nitka-toru.md`](docs/nitka-toru.md) | nitka toru i model okrążenia |
| [`docs/panel-pomoc-uwagi.md`](docs/panel-pomoc-uwagi.md) | uwagi z pisania katalogu pomocy panelu i co z nimi zrobiono |
| [`docs/panel-podrecznik-plan.md`](docs/panel-podrecznik-plan.md) | plan podręcznika operatora panelu |
| [`docs/pomoc-notatki/`](docs/pomoc-notatki) | notatki do plików katalogu pomocy (źródła, generatory) |
| [`docs/dziennik-zmian.md`](docs/dziennik-zmian.md) | archiwum dawnej sekcji „Stan” z README (do 2026-10-29) |
| [`content/README.md`](content/README.md) | paczki treści, reguły, tory, ekonomia, symulatory, wersje balansu |
| [`deploy/README.md`](deploy/README.md) | wdrożenie krok po kroku |

## Stan i co dalej

Roadmapa na płótnie ma fazy F0–F9.

- **Zrobione:** F0 (szkielet), F1 (pionowy wycinek gry), F2 (tory, zegar, mapa, flota, rynek, skrzynki, boty), F3 (warsztat i części), F4 (treść w bazie i panel) oraz F5 (pomiary, podsumowanie nieobecności, PWA, kopie, test obciążeniowy). Poza roadmapą: silnik jako narzędzie (Studio, słownik, model gry, wiele gier, marka), panel sieci gier, misje, model toru i nitki torów.
- **W toku (F6):** w `main` są seria dni, limit aut aktywnych, transmutacja rzadkości i bonusy części (etapy B0–B2). Na gałęziach, jeszcze poza `main`: silnik rywalizacji (`competition-c0`), zlecenia poboczne (`jobs-j1`), zlecenie dostrajania części (`tuning-b3a`) i most AI w panelu (`ai-bridge-a4`). Powiadomienia web push i podręcznik operatora panelu nie są zaczęte.

Czeka na właściciela projektu albo na prawdziwe usługi:

- klucze testowe Stripe, przegląd prawny (regulamin, RideCoin, próg wieku) i odpowiedź Stripe w sprawie RideCoin, zanim ruszy sprzedaż monet,
- sprawdzenie logowania przez prawdziwe Google, Facebooka i Apple,
- pierwsze uruchomienie wdrożenia na prawdziwym serwerze Ubuntu,
- przegląd tekstów francuskich, niemieckich i brazylijskich,
- wycinanki pozostałych aut na jasną scenę (mają je trzy auta z paczki).

## Zasady pracy

- Każda zmiana to osobny commit. Komunikaty commitów piszemy po angielsku, w stylu `feat(api): ...`, `fix(web): ...`, `docs: ...`.
- Dokumentacja (ten plik, `docs/`) jest po polsku. Teksty w grze powstają od razu w pięciu językach.
