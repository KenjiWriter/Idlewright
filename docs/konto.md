# Konto i logowanie

Kierowca zaczyna jako **gość**: konto powstaje od razu, bez e-maila, a urządzenie dostaje token. Gość, który nie zapisał kodu odzyskiwania, traci garaż razem z urządzeniem albo danymi przeglądarki. Dlatego po pierwszym zarobku gra proponuje **połączyć konto**: e-mailem (link jednorazowy), przez Google, Facebooka albo Apple. Kod odzyskiwania zostaje jako wyjście dla tych, którzy nie chcą podawać niczego.

## Zasady

- **Tożsamości.** Sposób logowania to wiersz w `account_identities`: dostawca (`email`, `google`, `facebook`, `apple`), `subject` (adres zapisany małymi literami albo stały identyfikator nadany przez dostawcę) i adres do wyświetlenia. Jedna tożsamość należy do jednego konta (`unique(provider, subject)`), a konto ma najwyżej jedną tożsamość każdego dostawcy (`unique(user_id, provider)`).
- **Połączenie pierwszej tożsamości zabezpiecza gościa** (`claimed_at`), od tego momentu konto nie wygasa. Zgody (regulamin, wiek) zapisują się wtedy jako `terms_accepted_at` i `age_confirmed_at`. **Zgody składa się raz**: konto, które ma obie na koncie, nie jest pytane o nie przy dodawaniu drugiego sposobu logowania, a zapisane momenty zostają bez zmian. Konto, które ma tylko jedną z nich, jest pytane nadal. Adres konta (`users.email`) ustawia tylko tożsamość e-mail, bo tylko ją gracz udowodnił linkiem.
- **Nigdy nie szukamy konta po adresie, który podaje dostawca.** Cudze konto Google, które twierdzi, że ma adres właściciela, nie wpuszcza do jego garażu. Tożsamość znajdujemy po `(dostawca, subject)` i tylko tak.
- **Dwóch kont nie łączymy.** Jeśli tożsamość należy już do innego garażu, odpowiedź to 409 `identity_taken` i nic się nie zmienia; gracz wybiera, na którym gra. Łączenie walut i aut z dwóch kont otworzyłoby drogę do nadużyć.
- **Logowanie nieznanym dostawcą nie zakłada konta.** Odpowiedź to 404 `no_account`. Konto powstaje tylko przy „Utwórz kierowcę”.
- **Sekrety jednorazowe** (`account_tokens`): 32 losowe bajty, w bazie tylko skrót HMAC (klucz aplikacji), ważność, jedno użycie wymuszone jednym `UPDATE … WHERE nieużyty AND niewygasły AND cel`. Zły cel albo zły weryfikator nie zużywa sekretu.

## Okno „Zapisz swój garaż” (web)

- Otwiera się w garażu, gdy kierowca jest gościem i ma co najmniej jedno okrążenie. Czeka, aż kierowca zatwierdzi podsumowanie nieobecności, żeby nic się nie nakładało.
- To okno dialogowe: fokus przechodzi do środka i tam zostaje, `Esc` i kliknięcie poza oknem odkładają sprawę, fokus wraca tam, skąd przyszedł.
- Zawiera dwie zgody, przyciski dostawców, którzy są włączeni na serwerze, i pole e-mail. Bez obu zgód nic się nie wysyła. Pierwsza zgoda nazywa regulamin i politykę prywatności i linkuje do `/terms` i `/privacy` (w nowej karcie, żeby okno zostało, jak było); druga mówi, ile lat trzeba mieć, a liczbę podaje serwer (`LEGAL_MIN_AGE`). Gdy serwera nie da się zapytać o wiek, pole mówi to ogólnie, a serwer i tak o zgody pyta.
- „Później” zapamiętuje to w `localStorage` (`ride.linkPrompt`) i zostawia cichy baner, dopóki konto jest gościem. „Wolę kod odzyskiwania” zamyka okno i przewija do panelu z kodem. Wylogowanie i zalogowanie kogoś innego kasuje zapamiętane „później”.
- Kierowca, który ma już konto, widzi w garażu kartę „Twoje sposoby logowania” (adres zamaskowany: `t***@example.com`) z przyciskiem „Dodaj kolejny sposób logowania”. Przycisk rozwija to, co zostało do dodania: dostawców włączonych na serwerze, których konto jeszcze nie ma, i e-mail, jeśli konta go nie ma. Bez pytania o zgody, bo złożono je przy pierwszym sposobie. Gdy konto ma już wszystko, przycisku nie ma. Dzięki temu utrata jednego sposobu (np. konta Google) nie zamyka drogi do garażu.

## Ekran „Mam już konto” (`/recover`)

Na górze logowanie kontem (dostawcy i link e-mailem, bez zgód, bo konto już istnieje), niżej kod odzyskiwania. Kod działa także wtedy, gdy nie da się wczytać listy dostawców.

## E-mail

| Punkt końcowy | Opis |
|---|---|
| `POST /account/email` (token) | Prosi o link, który zabezpiecza konto albo dodaje do niego e-mail. Wymaga `email`, a od konta, które nie złożyło jeszcze zgód, także `accept_terms` i `confirm_age`. |
| `POST /account/login-link` | Prosi o link, który loguje na nowym urządzeniu. Tylko `email`. |
| `POST /account/email/confirm` | Używa linku (`token`). Link zabezpieczający nie loguje nikogo, link logujący zwraca token urządzenia. |

- Odpowiedź na prośbę o link to zawsze 202 i ten sam tekst, niezależnie od tego, czy adres ma już garaż. Nikt nie sprawdzi w ten sposób, kto gra. Gdy adres ma garaż, link dostaje jego właściciel (do jego garażu).
- Link jest ważny 30 minut (`ride.account.link_ttl_seconds`) i działa raz. Wygląda tak: `{WEB_URL}/account/confirm?token=…`. Strona od razu usuwa sekret z adresu.
- Limity na godzinę: prośba o zabezpieczenie 5 na konto, 3 na adres (skrót SHA-1, adresu nie przechowujemy) i 30 na IP; link logujący 3 na adres i 10 na IP; użycie linku 30 na IP.
- Maile idą kolejką (`Mail::queue`), w języku konta, w pięciu językach (`api/lang/<język>/mail.php`). Są cztery rodzaje: `claim` (zabezpiecza gościa), `add` (dodaje adres do konta, które jest już zabezpieczone, więc nie mówi, że garaż „będzie zabezpieczony”), `login` i `existing`. Link działa tak samo w `claim` i `add`, różni się tylko treść.

## Google, Facebook, Apple

Socialite (`laravel/socialite`) i `socialiteproviders/apple`. Przepływ jest rozbity na trzy kroki, bo samo „callback łączy konto” byłoby podatne na **login CSRF**: napastnik zaczyna własne logowanie Google, podsuwa ofierze adres powrotu, a konto Google ofiary łączy się z garażem napastnika.

1. **Start** (`POST /account/identities/{dostawca}/start` z tokenem, żeby połączyć albo dodać kolejnego dostawcę, albo `POST /auth/{dostawca}/start` bez konta, żeby się zalogować). Serwer zapisuje stan (`oauth`, 10 minut) z kontem, zgodą i **skrótem weryfikatora**, a przeglądarce oddaje adres dostawcy i sam **weryfikator**. Przeglądarka zapamiętuje go w `localStorage` (`ride.oauth`, do 15 minut) i idzie do dostawcy. Apple dostaje też nonce wyliczony ze stanu.
2. **Callback** (`GET` albo `POST` na `/auth/{dostawca}/callback`; Apple wraca formularzem). Serwer zużywa stan (raz, i tylko dla dostawcy, dla którego powstał), pyta dostawcę, kim jest osoba, i zapisuje **bilet** (`oauth_result`, 5 minut). **Niczego nie zmienia na koncie.** Przeglądarka dostaje przekierowanie na `{WEB_URL}/auth/callback#result=ready&provider=…&ticket=…`. Wynik jest we fragmencie adresu, którego nie widzi żaden serwer ani log; `cancelled` oznacza „nie” u dostawcy, `error` każdą inną porażkę.
3. **Zakończenie** (`POST /auth/complete` z `ticket` i `verifier`). Bilet można odebrać tylko z weryfikatorem tej przeglądarki, która zaczęła; zły weryfikator nie zużywa biletu. Intencja `link` wymaga zalogowanego kierowcy, **tego samego**, dla którego zaczęto (inaczej 403 `wrong_account`), i dodaje dostawcę do konta. Intencja `login` zwraca token urządzenia albo 404 `no_account`.

Strona `/auth/callback` czyta fragment, od razu go usuwa, pokazuje serwerowi weryfikator z `localStorage` i mówi kierowcy, co się stało. Jeśli przeglądarka nie zaczęła tego logowania, nie pyta serwera wcale. Weryfikator zostaje skasowany po każdej odpowiedzi serwera (logowanie kończy się raz) i zostaje, gdy serwera nie dało się dosięgnąć.

`GET /auth/providers` zwraca włączonych dostawców, w kolejności Google, Facebook, Apple. Dostawca jest włączony tylko wtedy, gdy ma komplet kluczy; połowicznie skonfigurowany nie pojawia się na ekranie.

Limity na godzinę: start 20 na konto i 60 na IP; zakończenie 30 na IP.

## Twoje dane (kopia i usunięcie konta)

W garażu każdy kierowca, także gość, ma sekcję „Twoje dane”. „Pobierz moje dane” pobiera plik JSON (`GET /account/export`), a „Usuń moje konto” (`DELETE /account` z `confirm_name`) usuwa konto, gdy kierowca wpisze swoje imię. Po usunięciu urządzenie zapomina konto, a strona startowa raz mówi, że konto usunięto. Operator ma te same możliwości jako polecenia `accounts:export` i `accounts:delete`. Szczegóły i ograniczenia w `docs/prawo.md`.

## Odpinanie logowania, zmiana e-maila, ponowna zgoda (projekt, 2026-11-02)

Zamyka trzy luki z „Czego jeszcze nie ma”. Decyzje poniżej są **domyślnymi propozycjami**: kod je wykonuje, a ich liczby leżą w `config/ride.php`, więc zmiana to jedna linia. Rzeczy prawne i produktowe są na końcu jako pytania do właściciela.

**D1. Odpinanie.** `DELETE /account/identities/{dostawca}` (`email`, `google`, `facebook`, `apple`), z tokenem urządzenia. Odpinamy jedną tożsamość z `account_identities`; konta nie ruszamy.

**D2. Ostatniego sposobu nie wolno odpiąć.** Odpowiedź to 422 `last_method`. Gość nie ma tożsamości, więc nie ma czego odpinać: 404 `identity_not_found` (to samo dla dostawcy, którego konto nie ma). Kolejność sprawdzeń: czy jest → czy to nie ostatni → czy jest świeży dowód. Kod odzyskiwania nie jest sposobem logowania w tym sensie i nie liczy się do „ostatniego”.

**D3. Odpinanie wymaga świeżego dowodu tożsamości (10 minut).** Bez niego 403 `reauth_required`. Dowód jest przypisany do **urządzenia** (token Sanctum), które o niego poprosiło, więc zgubiony albo ukradziony token na innym urządzeniu go nie dostaje, nawet gdy właściciel w tym czasie potwierdza coś u siebie. Dwa sposoby: (a) **link na e-mail konta** (`POST /account/reauth/email`, link otwiera się w dowolnej przeglądarce i oznacza urządzenie, które o niego poprosiło), (b) **świeże logowanie dostawcą, którego konto ma** (`POST /account/reauth/{dostawca}/start`, ten sam przepływ co łączenie, z weryfikatorem; bilet musi dotyczyć **tej samej** tożsamości, którą konto ma u dostawcy, inaczej 403 `reauth_mismatch`). `GET /account/reauth` mówi, czy dowód jest ważny i którymi sposobami można go zdobyć. Dowodem może być każda tożsamość konta, także ta odpinana. Stan dowodu leży w cache (`reauth:{id tokenu}`), więc jego utrata tylko każe dowieść jeszcze raz.

**D4. Skutki odpięcia.** W jednej transakcji z blokadą wiersza konta (dwa odpięcia naraz nie zostawią konta bez żadnego sposobu). Odpięcie e-maila czyści `users.email` i `email_verified_at` oraz unieważnia czekające linki wysłane na ten adres (adres jest od tej chwili wolny dla każdego konta). **Urządzenia zalogowane tym sposobem** (token `<dostawca>-login`, `email-login`) są wylogowane; urządzenie, z którego odpinamy, i pozostałe zostają. Na adres e-mail konta idzie krótka informacja „odpięto sposób logowania” (bez linku: tego nie da się cofnąć jednym kliknięciem, bo odpiąć może tylko ktoś, kto dowiódł tożsamości). **Dane u dostawcy zostają u dostawcy**: nie kasujemy konta Google/Facebook/Apple, a zgodę, którą gra tam ma, odbiera się w ustawieniach tego dostawcy; ekran to mówi.

**D5. Zmiana e-maila konta, które ma logowanie e-mailem.** `POST /account/email/change` z `email`. Konto bez e-maila go **dodaje** (istniejące `POST /account/email`), a ta ścieżka daje 409 `no_email`. Link potwierdzający idzie na **nowy** adres (30 minut, jedno użycie, ten sam adres strony `/account/confirm`) i **do jego użycia nic się nie zmienia**. Nowa prośba unieważnia poprzednią, więc działa najwyżej jeden link. Ten sam adres co obecny to 422. **Przerwa między zmianami: 7 dni** (`ride.account.email_change_cooldown_days`, liczona od ostatniej *dokonanej* zmiany, `users.email_changed_at`); w jej trakcie 422 `email_change_cooldown` z datą. **Adres, który ma już inny garaż, jest odmówiony tak jak dziś przy zabezpieczaniu**: odpowiedź to zawsze 202 z tym samym tekstem, a właściciel adresu dostaje zwykły mail „ten e-mail ma już garaż” (link logujący do jego garażu). Przy użyciu linku adres jest sprawdzany jeszcze raz (409 `identity_taken` dla kogoś, kto dowiódł, że ma skrzynkę).

**D6. Powiadomienie na stary adres i „to nie ja”.** Dwa maile na **stary** adres: przy prośbie („ktoś prosi o zmianę adresu na n***@…”) i po zmianie („adres zmieniony na n***@…”). Oba mają link „To nie ja” (`/account/secure?token=…`, **ważny 7 dni**, `ride.account.email_undo_days`). Strona nie robi nic sama, tylko pyta przyciskiem (skanery linków w pocztach otwierają adresy, a to jest akcja nieodwracalnie wylogowująca). `POST /account/email/revoke` (otwarty, limit jak potwierdzanie linków): anuluje czekającą zmianę; jeśli zmiana już zaszła, **przywraca stary adres** (o ile jest wolny, inaczej tylko reszta); wylogowuje **wszystkie** urządzenia; kasuje kod odzyskiwania (mógł go wystawić ktoś, kto miał token); unieważnia czekające linki. Wrócić można linkiem logującym na przywrócony adres.

**D7. Sesje.** Zmiana e-maila **zostawia** urządzenia (to samo konto, nowy adres udowodniony). „To nie ja” wylogowuje wszystkie. Odpięcie wylogowuje tylko urządzenia, które weszły tym sposobem.

**D8. Ponowna zgoda.**
- *Kogo pytamy:* konta, które już się zgodziły (`terms_accepted_at`), a ich `terms_version` jest starsza niż **`ride.legal.consent_version`** albo jej nie ma (zgoda sprzed wersjonowania). Gość nic nie akceptował, więc go nie pytamy (patrz pytania).
- *Co zmiana wersji znaczy:* `LEGAL_VERSION` to dzień ostatniej zmiany tekstów i pokazuje się na stronach; **`LEGAL_CONSENT_VERSION`** to dzień ostatniej zmiany, która wymaga nowej zgody. Domyślnie jest równa `LEGAL_VERSION` (każda zmiana pyta); drobną poprawkę podnosi się tylko w `LEGAL_VERSION`.
- *API:* każdy punkt końcowy za logowaniem, poza wyjątkami, odpowiada 403 `consent_required` (z `accepted_version` i `current_version`), dopóki konto nie przyjmie bieżącej wersji, więc stary klient nie pójdzie dalej po cichu. Wyjątki: `GET /me`, `PATCH /me/locale`, `POST /account/consent`, `GET /account/export`, `DELETE /account` (kto się nie zgadza, ma móc zabrać dane i odejść). `GET /me` dostaje blok `consent`.
- *Przyjęcie:* `POST /account/consent` z `accept_terms`, `confirm_age` i `version` (wersja, którą ekran pokazał; inna niż bieżąca to 409 `consent_version_changed`). Wiek pytamy znowu, bo próg mógł się zmienić razem z tekstami. Zapisujemy `terms_version`, a **każde przyjęcie** (także pierwsze) trafia do tabeli `consent_records` (konto, wersja, moment, źródło `link` albo `reconsent`), która wchodzi do eksportu danych. `terms_accepted_at` i `age_confirmed_at` nadal znaczą pierwszą zgodę.
- *Web:* ekran `/consent` blokuje grę (strażnik trasy i reakcja na 403), pokazuje, co się zmieniło (lista zmian per wersja w `web/src/legal/changes.ts`; bez wpisu ogólne zdanie z linkami do `/terms` i `/privacy`), dwa pola wyboru jak w oknie „Zapisz swój garaż”, a poza nimi trzy wyjścia: wyloguj, pobierz dane, usuń konto. Ja **nie piszę treści zmian ani przepisów**: wpis do `changes.ts` robi ten, kto zmienia teksty.

**D9. Limity na godzinę.** Odpinanie 10 na konto; dowód linkiem 5 na konto i 20 na IP; dowód dostawcą jak start dostawcy; prośba o zmianę e-maila 5 na konto, 3 na nowy adres (SHA-1) i 30 na IP; „to nie ja” i potwierdzanie linków 30 na IP; przyjęcie zgody 20 na konto. Tokeny (`email_change`, `email_revoke`, `reauth`) żyją w `account_tokens` jak reszta: skrót HMAC, wygasają, działają raz.

**D10. Czego tu nie robię.** Nie zmieniam treści regulaminu i polityki. Nie dodaję cofania odpięcia (odpięty sposób dodaje się od nowa). Nie dodaję wylogowania innych urządzeń na żądanie (poza „to nie ja”).

**Pytania do właściciela:** (1) czy drobne zmiany tekstów mają pytać o zgodę (domyślnie każda zmiana pyta; `LEGAL_CONSENT_VERSION` pozwala to rozdzielić); (2) czy konta ze zgodą sprzed wersjonowania (`terms_version` puste) mają być pytane (domyślnie tak); (3) czy gość, który nic nie akceptował, ma być zatrzymany przy zmianie wersji (domyślnie nie, bo jego zgoda przychodzi przy zabezpieczaniu konta; to pytanie 2 z `docs/prawo.md`); (4) próg wieku, bo ekran pyta o wiek ponownie, a liczbę podaje serwer; (5) czy przerwa 7 dni i okno cofnięcia 7 dni są właściwe; (6) czy zmiana e-maila ma wymagać także dowodu przy starym adresie (nie ma, bo wtedy nie da się zmienić adresu, do którego straciło się dostęp).

## Konfiguracja

Klucze zakłada się u dostawców, w swoich kontach deweloperskich; w repozytorium ich nie ma. W każdym trzeba zarejestrować **adres powrotu** `APP_URL/api/v1/auth/{google|facebook|apple}/callback`.

| Zmienna | Znaczenie |
|---|---|
| `WEB_URL` | Adres aplikacji web (domyślnie `http://localhost:5173`). Tam prowadzą linki z maili i powroty od dostawców. |
| `LEGAL_MIN_AGE`, `LEGAL_VERSION`, `LEGAL_OPERATOR_NAME`, `LEGAL_OPERATOR_ADDRESS`, `LEGAL_CONTACT_EMAIL` | Regulamin i polityka prywatności: wiek, wersja tekstów i dane operatora. Opis w `docs/prawo.md`. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google Cloud Console → API i usługi → Dane logowania → Identyfikator klienta OAuth, typ „Aplikacja internetowa”. W „Autoryzowanych identyfikatorach URI przekierowania” adres powrotu. |
| `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET` | Meta for Developers → aplikacja → Logowanie przez Facebooka → „Prawidłowe identyfikatory URI przekierowania OAuth”. Identyfikator i sekret aplikacji są w ustawieniach podstawowych. |
| `APPLE_CLIENT_ID` | Services ID (nie App ID) z włączonym „Sign in with Apple”; domena i adres powrotu są w jego konfiguracji. |
| `APPLE_CLIENT_SECRET` | Gotowy podpisany token (ważny najwyżej sześć miesięcy)… |
| `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY` | …albo identyfikator zespołu, identyfikator klucza i bezwzględna ścieżka pliku `.p8` (klucz z włączonym „Sign in with Apple”), z których serwer sam robi sekret. |
| `APPLE_REDIRECT_URI`, `GOOGLE_REDIRECT_URI`, `FACEBOOK_REDIRECT_URI` | Tylko gdy adres powrotu ma być inny niż `APP_URL/api/v1/auth/{dostawca}/callback`. |

Uwagi:

- **Apple wymaga HTTPS i prawdziwej domeny**, nie przyjmie `localhost`. Do lokalnego sprawdzenia Apple trzeba tunelu (np. własnej domeny z certyfikatem). Google i Facebook (aplikacja w trybie deweloperskim) przyjmują `http://localhost`.
- **Adresy stron prawnych**: Google (ekran zgody OAuth), Facebook (ustawienia aplikacji) i Apple (Services ID) wymagają adresu polityki prywatności (`WEB_URL/privacy`), a Google i Facebook także regulaminu (`WEB_URL/terms`).
- **Lokalnie**: `APP_URL=http://localhost:8000`, `WEB_URL=http://localhost:5173`. Adres powrotu to API, które przekierowuje do web, więc proxy Vite nie jest do tego potrzebne.
- **Kolejka**: maile idą kolejką. Przy `QUEUE_CONNECTION=database` musi działać `php artisan queue:work`, inaczej nic nie wyjdzie. Lokalnie wystarczy `QUEUE_CONNECTION=sync` albo Mailpit z `docker/compose.yaml` (SMTP 1025, interfejs na 8025).
- **Harmonogram**: `accounts:prune-tokens` (co godzinę) usuwa wygasłe i zużyte sekrety, `accounts:prune-guests` (codziennie) nieużywane konta gości. Potrzebny jest cron z `php artisan schedule:run` albo `schedule:work`.
- **Serwer WWW** musi oddawać `index.html` dla `/account/confirm` i `/auth/callback`, tak jak dla reszty tras aplikacji. Szablon nginx z `deploy/` to robi.
- **Dzienniki serwera**: w adresie `/account/confirm?token=…` i w adresie powrotu od Google i Facebooka jest sekret, więc log dostępu nginx z `deploy/` zapisuje adres tylko do znaku `?`. Własny serwer powinien robić to samo.
- **Wdrożenie** pod `raceidle.cerasusdigital.pl`, w tym adresy powrotu do wpisania u dostawców: `deploy/README.md`.

## Co jest sprawdzone, a co nie

- Testy API (Pest) obejmują tabele i sekrety, połączenie tożsamości, wszystkie punkty końcowe e-mail, start, callback i zakończenie przepływu dostawców, limity, zgodność języków. Przepływ dostawców jest testowany z `Socialite::fake` oraz z prawdziwymi adresami autoryzacji zbudowanymi offline (stan, adres powrotu, nonce i `form_post` Apple). Testy mutacyjne (zepsucie zabezpieczenia i oczekiwanie czerwonego testu) potwierdziły m.in. sprawdzanie weryfikatora, właściciela biletu i dostawcy w stanie.
- Testy web (Vitest) obejmują sklep konta, stronę potwierdzenia linku, stronę powrotu od dostawcy, okno z jego zgodami, fokusem i banerem, ekran „Mam już konto” i parzystość tłumaczeń.
- Ścieżka e-mail sprawdzona ręcznie w przeglądarce na lokalnym API (tymczasowa baza SQLite, poczta do logu, fałszywe klucze Google tylko po to, by pokazać przycisk): nowy gość → okno otwiera się samo, gdy odpytywanie wykryje pierwszy zarobek → wysyłka bez zgód jest zablokowana → link w mailu po polsku, adres znormalizowany → strona potwierdzenia usuwa sekret z adresu → garaż pokazuje „Twoje sposoby logowania” → wylogowanie → „Mam już konto” → link logujący → garaż. Przez API: pytanie o link dla znanego i nieznanego adresu daje identyczną odpowiedź (mail wychodzi tylko do właściciela), a użyty link daje 422. Okno na telefonie (375 px) jest szufladą od dołu i mieści się w całości, baner po „Później” jest dyskretny. Powrót od dostawcy z fałszywym stanem przekierował na `/auth/callback`, a strona usunęła fragment i pokazała komunikat.
- Dodawanie sposobu logowania sprawdzone tak samo: konto z samym Google (zgody zapisane) → w garażu „Dodaj kolejny sposób logowania” pokazuje tylko pole e-mail, bez zgód i bez przycisku Google, który już jest połączony → mail z nowym tematem „add” → po linku lista ma Google i e-mail, a przycisku dodawania już nie ma. Przez API: konto ze zgodami nie jest o nie pytane, zwykły gość dostaje 422.
- To sprawdzenie wykryło dwie wady, każdą poprawioną osobnym commitem. Pierwsza (z poprzedniego sprawdzenia): link z maila zabezpieczył konto z drugiej karty, a pierwsza zostawiała otwarte okno „Zapisz swój garaż”, bo profil był czytany tylko raz. Teraz profil gościa odświeża się razem z biegiem (co 15 s i po powrocie do karty). Druga: po „Użyj innego adresu” pole zachowywało stary adres, więc nowy dopisywał się na końcu i serwer go odrzucał; pole dostaje teraz fokus z zaznaczonym tekstem, który pisanie zastępuje.
- **Nie sprawdzono z prawdziwym Google, Facebookiem i Apple.** Wymaga to kluczy, których w repozytorium nie ma. Najmniej pewny jest Apple: powrót formularzem, nonce i podpisany sekret można zbadać tylko na prawdziwym koncie deweloperskim.

## Czego jeszcze nie ma

- Odłączania logowania i zmiany adresu e-mail. Zanim odłączanie powstanie, trzeba zdecydować, co z kontem, które odłączy ostatni sposób logowania (ostatniego nie wolno odłączyć), i czy odłączenie wymaga ponownego potwierdzenia.
- Nagrody za zabezpieczenie konta (w makiecie 10 diamentów): diamentów jeszcze nie ma w grze.
- Przeglądu prawnego regulaminu i polityki prywatności (`/terms`, `/privacy`, opis w `docs/prawo.md`). Teksty są roboczym projektem. **Próg wieku jest roboczy** (16 lat, `LEGAL_MIN_AGE`): ostateczną liczbę ma wskazać przegląd prawny (ryzyko R9 z roadmapy).
- Samouczka. Okno otwiera się po pierwszym okrążeniu, bo na ekranie „krok 3” makiety („Zapisz swój garaż”) jest pierwszy zarobek; baner „dzień 2 i 3” z makiety jest tu banerem stałym, dopóki konto jest gościem.
- Tłumaczenia francuskie, niemieckie i brazylijskie są robocze (mojego autorstwa) i warto, żeby przejrzała je osoba mówiąca w tych językach.
