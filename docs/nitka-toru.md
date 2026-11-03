# Nitka toru i symulacja okrążenia

Tor może mieć **nitkę**: uporządkowany łańcuch odcinków (prosta, zakręt), po którym auto jedzie jak punkt materialny: rusza, przyspiesza na prostych, hamuje przed zakrętami, w zakręcie jedzie tyle, ile pozwalają przyczepność i zwrotność, a na żwirze, błocie i śniegu zachowuje się inaczej niż na asfalcie. Propozycja, uzasadnienie i policzone przykłady: notatka projektowa „nitka toru” (etap 0). Ten dokument opisuje to, co jest zbudowane.

## Co jest zbudowane

| Etap | Co | Gdzie |
|---|---|---|
| T1 | Rdzeń symulacji: odcinki, stałe modelu, ruch auta, symulator okrążenia | `api/app/Domain/Run/` (`Section`, `Thread`, `Dynamics`, `CarOnSurface`, `Motion`, `LapSimulator`, `SectionRun`, `LapResult`) |
| T2 | Dokument (`thread`, `lap_from`, `dynamics`), walidator po ludzku, `ThreadLap` w zamrażarce, zużycie części po nawierzchniach odcinków, przejście przez formularz świata; stałe `dynamics` w `starter.json` i w Studiu (pomoc w 5 językach) | `content/schema/rules.schema.json`, `api/app/Content/ThreadDocument.php`, `api/app/Domain/Run/ThreadLap.php` |
| T3 | Strona „Nitka toru” w panelu: lista odcinków, gotowe kawałki, lustro i odwrócenie, rysunek z góry (kolor = prędkość auta wzorcowego), podział czasu okrążenia, tabela aut „ze wzoru i po nitce”, zapis do szkicu | `api/app/Filament/Pages/ThreadPage.php`, `api/app/Content/ThreadPreview.php`, `ThreadForm.php`, `api/app/Domain/Run/ThreadPath.php`, `api/resources/views/filament/forms/thread-preview.blade.php` |
| T4 | `GET /v1/runs/current/profile` i linia toru z autem na ekranie wyścigu (auto jako czysta funkcja czasu, boty jako duchy) | `api/app/Services/RunProfile.php`, `web/src/components/TrackMap.vue`, `web/src/features/run/trackPosition.ts` |
| T5 | Nitki wszystkich 15 torów w `starter.json` (jako rysunek: wszystkie zostają na `lap_from: stats`), pomoc Studia dla ich liczb | `content/rules/starter.json` |
| T4b | Wybór toru w grze: miniatura nitki, zdanie o tym, czego tor wymaga, i pasek „Twoje auto na tym torze” (co zyskuje i traci względem auta wzorcowego) | `api/app/Services/TrackCharacter.php`, `web/src/components/TrackPicker.vue`, `TrackThumb.vue`, `web/src/features/run/trackCharacter.ts` |
| T6 | Narzędzia strony „Nitka toru” (zamknij pętlę, skala promieni, rozciągnięcie prostej, szablony, klon odcinka, klik na rysunku), lista zmian po ludzku, rozrzut aut i ostrzeżenia w `tracks:simulate`, „weź z auta” dla auta wzorcowego | `ThreadCloser`, `ThreadForm`, `ThreadDiff`, `TrackReport`, `WorldFields` |

## Skąd bierze się czas okrążenia (`lap_from`)

Każdy tor ma pole `lap_from`:

- **`stats`** (domyślne, także gdy pola nie ma): czas okrążenia ze wzoru z wag toru, `base_lap_ms ÷ perf^1,15`. Tak jedzie każdy dzisiejszy tor. Nitka, jeśli tor ją ma, tylko go rysuje.
- **`thread`**: czas okrążenia z symulacji nitki. Wymaga pola `thread`.

Czas auta na torze z nitką to **czas bazowy toru razy (czas auta na nitce ÷ czas auta wzorcowego na nitce)**. Auto wzorcowe to `reference` toru (musi mieć `accel`, `top_speed`, `grip`, `handling`, `braking`; `clearance` domyślnie 20), na nawierzchni toru w suchym; na odcinku z inną nawierzchnią ma tyle przyczepności, ile jego opony wzorcowe (`reference_tires` albo fabryczne) mają tam w stosunku do nawierzchni toru. Dzięki temu:

- auto wzorcowe jedzie **dokładnie** czas bazowy toru (z `speed_kmh` albo ręcznego `base_lap_ms`), jakkolwiek narysowana jest nitka i jakiekolwiek są stałe modelu,
- paliwo, wypłata i XP toru (kształt A: obciążenie × czas, na godzinę × czas) **nie ruszają się**, gdy ktoś zmieni nitkę albo stałe,
- zmienia się tylko to, jak daleko od wzorca jest każde inne auto, i to jest właśnie gra: auto mocne na prostych wygrywa na szybkim torze, zwrotne na krętym.

Rozliczenie (`Settler`) nie zmienia się wcale: czyta `lap_ms`, gotówkę, XP i paliwo, tak jak zawsze. Symulacja liczy się **raz przy zamrażaniu biegu** (`RunFreezer`), jedno rozwiązanie na pogodę regionu plus jedno dla auta wzorcowego, około 0,1 ms na okrążenie nitki o 14 odcinkach i 0,5 ms o 60 (pomiar w PHP 8.4 bez opcache).

## Dokument

```json
"lap_from": "thread",
"thread": {
  "mode": "circuit",
  "return_ms": 0,
  "sections": [
    { "type": "straight", "length_m": 629 },
    { "type": "corner", "radius_m": 70, "angle_deg": 90, "turn": "right" },
    { "type": "straight", "length_m": 200, "surface": "gravel", "rough": 40, "limit_kmh": 80 }
  ]
}
```

- `mode`: `circuit` (pętla jeżdżona „lotnie”: auto mija linię z prędkością, z którą ją opuściło; stan ustalony pętli) albo `run` (każde okrążenie ze startu stojącego: dragster, sprint, podjazd). `return_ms` to stały czas powrotu na start, który `run` dodaje do każdego okrążenia (pętla go nie ma).
- Odcinek **`straight`**: `length_m` (5–5000). Odcinek **`corner`**: `radius_m` (8–1000), `angle_deg` (5–360), `turn` (`left`/`right`, tylko do rysunku). Oba mogą mieć `surface` (`asphalt` albo `gravel`, domyślnie nawierzchnia toru), `limit_kmh` (limit prędkości: ulica, pit lane) i `rough` (0–100: tyle prześwitu potrzebuje auto, żeby jechać po nim bez zwalniania).
- Do 60 odcinków. `length_m` toru jest sumą odcinków (każdy zaokrąglony do metra, zakręt to jego łuk); walidator wymaga zgodności z dokładnością do metra.
- Pogoda działa na każdy odcinek osobno: deszcz robi z asfaltu mokry asfalt, ze żwiru błoto, śnieg przykrywa oba (`Surface::of`), a macierz opon i przyczepność napędu liczą się dla nawierzchni odcinka.

Sekcja reguł **`dynamics`** (opcjonalna; bez niej obowiązują wartości domyślne modelu) trzyma stałe modelu: `power_ppm` (jaka część mocy na masę dochodzi do drogi, 60%), `lateral_g_ppm`, `brake_g_ppm`, `launch_g_ppm` (co daje statystyka 100 w zakręcie, na hamulcach i przy ruszaniu, w g), `agility` (udział zwrotności w zakręcie: największy w agrafce, najmniejszy od promienia `radius_m`), `top_speed_ppm` (co zostaje z prędkości maksymalnej na asfalcie suchym i mokrym, żwirze, błocie, śniegu), `rough` (prędkość po nierównym odcinku i wykładnik, z jakim zwalnia auto z za małym prześwitem). **Wartości startowe są moje, nie wyprowadzone z danych.**

## Model (jak auto jedzie po nitce)

Punkt materialny, quasi-ustalony, po odcinkach.

1. **Limit prędkości odcinka**: w zakręcie `√(a_lat · R)` z `a_lat = lateral_g · g · grip^(1−h) · handling^h / 100` (udział zwrotności `h` od `agility.max` w agrafce do `agility.min` w szerokim łuku); na prostej prędkość maksymalna na nawierzchni; potem `limit_kmh` i, na nierównym odcinku, prędkość z prześwitu.
2. **Prędkość na złączach odcinków**: przebieg wstecz (hamulce nie wyhamują auta w odcinku, więc prędkość na początku jest niższa) i w przód (silnik nie rozpędzi go do wyższej). Pętla przechodzi to trzy razy; przejazd startuje od zera.
3. **W odcinku**: przyspieszanie, jazda na limicie, hamowanie. Przyspieszanie ma dwie fazy: przyczepność (stałe przyspieszenie `launch_g · g · grip / 100`, do prędkości, przy której silnik już nie daje więcej) i moc (`a = ρ/v − c·v²`, gdzie opór `c` jest dobrany tak, żeby przy prędkości maksymalnej przyspieszenie spadło do zera). W zmiennej `u = v³` ta druga faza jest równaniem liniowym, więc prędkość po dystansie i dystans do prędkości mają **wzory zamknięte** (wykładnik i logarytm), a czas po dystansie całkuje kwadratura Gaussa–Legendre'a o ośmiu węzłach. Punkt przecięcia przyspieszania i hamowania szuka się bisekcją o stałej liczbie 40 kroków.

Statystyki auta (`accel` w kW/t, `top_speed`, `grip`, `handling`, `braking`, `clearance`) są już policzone ze wszystkiego, co auto ma (silnik, doładowanie z opóźnieniem, skrzynia, masa, napęd, opony, zawieszenie, hamulce, zużycie, serwis, afiksy), więc symulator **niczego nie liczy drugi raz**. Auto na każdej nawierzchni i w każdej pogodzie to `CarOnSurface`: statystyki z już wmnożonymi oponami na tej nawierzchni, napędem i pogodą (`RunInputsFactory::statMultipliersPpm`).

`float` jest tu dozwolony tak jak w `RunFreezer`: wynik zaokrągla się do ms i zamraża. Symulator nie czyta zegara, losowości ani bazy.

## Walidator (po ludzku)

`ThreadDocument::problems` i `PackValidator::checkThreads`. **Błędy**: odcinek prostej bez długości, zakrętu bez promienia lub kąta; suma odcinków inna niż `length_m` o więcej niż metr; tor z `lap_from: thread` bez nitki albo z autem wzorcowym bez któregoś z pięciu statystyk; w `dynamics` większy udział zwrotności w szerokim łuku niż w agrafce. **Ostrzeżenia**: pętla bez zakrętu (wygrywa wszędzie auto z największą prędkością maksymalną), zakręty pętli nie sumują się do pełnego obrotu (±15°: linia nie zamknie się na rysunku), czas powrotu przy pętli (nie jest liczony).

## Testy

`api/tests/Unit/Run/LapSimulatorTest.php` i `ThreadTest.php` przypinają liczby prototypu, na którym liczono propozycję (trzy auta na ulicy, dragu i serpentynach, deszcz, mieszana nawierzchnia, limit, nierówny odcinek), formuły zamknięte kontra całkowanie krokowe, niezmienniki (stan ustalony pętli, start od zera, lepsze auto nigdy nie wolniejsze, żwir/śnieg/błoto trzymają prędkość maksymalną, limit prędkości) i budżet czasu. `api/tests/Feature/Content/ThreadRulesTest.php`: auto wzorcowe jedzie dokładnie czas bazowy (także po zmianie stałych), inne auta według modelu (ulica, ok.: Bizon 73 006 ms, Orzeł 67 832 ms, Rdzak w deszczu 78 887 ms; test sprawdza przedział ±1 ms), tor bez `lap_from: thread` jedzie jak dotąd, zużycie części po udziałach nawierzchni, walidator, formularz świata nie gubi nitki.

## Panel: strona „Nitka toru”

Jeden tor naraz (lista rozwijana), z polami: skąd bierze się czas okrążenia (`lap_from`), jak się jedzie (pętla albo przejazd z czasem powrotu), lista odcinków (rodzaj, długość albo promień i kąt, kierunek, nawierzchnia, limit prędkości, nierówność) ze zmianą kolejności strzałkami, gotowe kawałki (agrafka, ciasny zakręt, szeroki łuk, szykana), „odbij lustrzanie” i „odwróć kierunek jazdy”. Obok formularza (odświeżane po każdej zmianie): linia toru z góry, **narysowana tak, jak ją tworzą odcinki** (jeśli się nie zamyka, czerwona przerywana linia pokazuje szczelinę do startu), pokolorowana prędkością auta wzorcowego (ciepło = wolno, zielono = szybko), numery odcinków, podział czasu okrążenia (przyspieszanie, na limicie, hamowanie, zakręty, powrót) i tabela aut katalogu: okrążenie **ze wzoru** (dzisiejsze) i **po nitce** (które byłoby, gdyby tor jechał po nitce), różnica w procentach (zielone do 5%, żółte do 10%, czerwone dalej), średnia i szczytowa prędkość. Zapis kładzie nitkę do szkicu reguł świata; **długość toru zapisuje się jako suma odcinków**, a walidator mówi po ludzku, co jest nie tak.

Nie ma osobnego przycisku „ustaw prędkość z nitki”: schemat wymaga dla toru `speed_kmh` albo `base_lap_ms`, więc prędkość ustawia się w formularzu świata. Przestawienie toru na nitkę to lista „Czas okrążenia z” i tabela aut pod rysunkiem (różnice w procentach przed zapisem).

### Narzędzia strony

- **Zamknij pętlę** (`ThreadCloser`): rusza trzy liczby i nic więcej. Kąt **ostatniego zakrętu** dobiera tak, żeby zakręty dawały pełny obrót (360° w jedną albo w drugą stronę, bliższy obecnemu), potem długości **dwóch prostych**, żeby linia wróciła na start (przy ustalonym kącie to układ liniowy 2×2). Z par prostych, dla których wychodzi długość z zakresu 5 do 5000 m, bierze tę, która zmienia się najmniej; dwie proste na jednej linii (boki owalu) spotykają się w połowie (600 i 500 m dają 550 i 550). Długości są w pełnych metrach, kąt w dziesiątych stopnia, szczelina po zamknięciu poniżej metra. Mówi po ludzku, czemu nie może: przejazd (`run`) się nie zamyka, brak liczb w odcinku, brak zakrętu, jedna prosta, zakręty nie dają pełnego obrotu (z kątem, jaki musiałby mieć ostatni), żadna para prostych nie pasuje. Nie zamyka ósemki (suma obrotów 0).
- **Przeskaluj promienie** (mnoży wszystkie, promień zostaje między 8 a 1000 m) i **Rozciągnij najdłuższą prostą** (do podanej długości, 5 do 5000 m): po nich pętla zwykle przestaje się zamykać, więc obok jest „Zamknij pętlę”.
- **Szablony** (zastępują tryb jazdy i wszystkie odcinki, pytają o potwierdzenie): ulice miasta (14 odcinków, ulica ze Złomowa), dragster (800 m i 5 s powrotu), podjazd (przejazd z czterema agrafkami i 60 s powrotu), rajd (asfalt i żwir, pętla zamknięta), owal. Pętle zamykają się do 3 m (test).
- **Klon odcinka** na liście oraz **klik na linii rysunku**: przewija listę do tego odcinka i obwodzi go na chwilę.

## Ekran wyścigu

`GET /v1/runs/current/profile?weather=…` (`RunProfile`) daje linię nitki toru biegu (pętlę wygiętą tak, żeby się zamykała, około 180 punktów), dla każdego punktu odległość, prędkość auta **takiego, jakie jest teraz**, w pogodzie z zapytania, i ułamek czasu okrążenia, a do tego odcinki. Odpowiedź jest zawsze świeża (`no-store`), liczy się w poniżej milisekundy i **nigdy w czasie jazdy**: ekran pyta o nią przy wejściu i gdy zmieni się tor, auto, pogoda albo czas okrążenia. Auto na ekranie to **czysta funkcja** profilu i ułamka okrążenia (`trackSpot`): czas profilu jest rozciągnięty na **zamrożony** `lap_ms`, więc auto jest tam, gdzie mówi rozliczenie, co do milisekundy, także gdy model zmienił się po zamrożeniu. Boty jadą jako duchy swoje suche okrążenia z tabeli botów na zegarze serwera liczonym od jednego momentu (u wszystkich graczy w tych samych miejscach). Ekran rysuje linię, auto, boty, prędkość i nazwę odcinka („Zakręt R70, 90°”); osoba, która poprosiła system o mniej ruchu, dostaje ruch raz na sekundę.

Dla toru na `stats` rysunek pokazuje prędkości modelu, a animacja trwa dokładnie tyle, co okrążenie toru (stąd tempo ≠ 1 z propozycji: prędkościomierz pokazuje prędkość modelu).

## Nitki piętnastu torów

Narysowałem je ja, wszystkie jako **rysunek** (`lap_from` zostaje `stats`, czasy okrążeń, tabela botów i bramki się nie ruszają). Pętle zamykają się geometrycznie (szczelina do 3 m po zaokrągleniach; proste dobiera rozwiązanie najmniejszych kwadratów, kierunki zakrętów tak, żeby zakręty dawały pełny obrót), tory krótkie to owale, Drag Suchy Pas i Krótki pas to przejazdy (`mode: run`, drag z `return_ms` 6,9 s: razem z fizycznym przejazdem wzorca daje dzisiejsze 45 s). Kamieniołom, żwirowy sprint i maraton mają nierówność odcinków. **Które tory przestawić na `lap_from: thread`, decyduje właściciel** (tabela „tempa” z propozycji: tempo bliskie 1 mają Autodrom, Obwodnica, Długi dystans, Maraton, Krótki pas i Przejazd przez przełęcz); strona „Nitka toru” pokazuje przed przestawieniem, jak zmieniłoby się okrążenie każdego z aut.

## Wybór toru w grze

`GET /v1/map` daje dla torów **miasta, w którym stoi auto**, blok `thread` (`TrackCharacter`; w innych miastach `null`, żeby mapa nie liczyła symulacji dla piętnastu torów):

- `points`: linia nitki w metrach, około 56 punktów (pętla zamknięta jak na ekranie wyścigu); web rysuje z niej miniaturę (`TrackThumb`).
- `shares`: na co idzie czas okrążenia **auta wzorcowego toru**, w procentach: przyspieszanie, jazda na limicie, hamowanie, zakręty. Z tego `character` (zdanie na karcie): **zakręty** od 35% czasu w zakrętach, **limit** od 35% na limicie, **moc** od 70% w przyspieszaniu, inaczej **zrównoważony**. Na dzisiejszych torach: ulica Złomowa zrównoważona, Sprint po złomowisku i Mała pętla Autodromu to tory zakrętów (53% i 61%), Obwodnica, Długi dystans i dragi to tory mocy.
- `car`: o ile sekund **okrążenia toru** auto, którym się gra, jest szybsze (minus) albo wolniejsze (plus) od auta wzorcowego w tych samych czterech grupach. Sekundy modelu są przeskalowane tak, żeby auto wzorcowe jechało dokładnie czas bazowy toru; na torze jeżdżonym po nitce **sumują się do różnicy między okrążeniem auta a okrążeniem bazowym** (test). Web pokazuje grupy, w których różnica to co najmniej 2% okrążenia, i jedno zdanie: gdzie auto zyskuje najwięcej, gdzie traci najwięcej. Na torze z `lap_from: stats` pasek mówi, że czas okrążenia jest ustalony ręcznie, a pasek opisuje kształt toru, nie ten czas.

Tor, którego auto wzorcowe nie ma któregoś z pięciu statystyk modelu (`accel`, `top_speed`, `grip`, `handling`, `braking`), dostaje **tylko linię**, bez zdania i paska: nie ma z czym porównywać. **Dziś to trzy tory Kruszyna** (referencje żwirowych torów nie mają `top_speed`, bo wzór go nie ocenia). Auto wzorcowe można uzupełnić przyciskiem „Weź z auta” (ikona ciężarówki przy liniach wzorca w formularzu świata): wypełnia je statystykami auta z gry takiego, z jakim startuje, z opublikowanej paczki, w liczbach całkowitych. Wartość `top_speed` dla żwiru to decyzja właściciela (nie ruszałem `starter.json`).

## Rozrzut aut i ostrzeżenia bramek

`tracks:simulate` liczy dla każdego toru **rozrzut**: najwolniejsze auto (jego najlepsze suche okrążenie z dowolnymi częściami) przez najszybsze. Dziś, na wzorze, tory mają od 1,24 do 1,98. Dla torów jeżdżonych po nitce raport **ostrzega, nie psując wyniku komendy**: rozrzut poniżej 1,15 (tor prawie nic nie wymaga od auta), powyżej 2,2 (większość aut przepada) albo zwycięzca toru wygrywa też co najmniej dwa inne tory. Pomiar na nitkach (wszystkie tory z kompletnym autem wzorcowym przestawione na `thread`, bez zapisu): rozrzut ulicy 1,10, sprintu po złomowisku 1,08, Małej pętli 1,15, Wilczej Agrafki 1,21; **Bizon M-2 wygrywa 8 z 12 torów**, czyli gdyby przestawić wszystkie naraz, bramka M3 by nie przeszła. Przestawiać więc po jednym, patrząc na ostrzeżenia. Pomiar trwa 44 s (45 tysięcy zamrożeń z symulacją) wobec 7 s na wzorze.

## Lista zmian po ludzku

Strona „Zmiany do publikacji” nie pokazuje już zmiany kolejności odcinków jako zmiany każdego odcinka. `ThreadDiff` znajduje odcinki, które zachowały kolejność (najdłuższy wspólny ciąg), potem te **przesunięte** (taki sam odcinek w innym miejscu), **zmienione** (ten sam rodzaj, inne liczby: jeden wiersz na pole, np. „Odcinek 4: limit prędkości: — → 70”), **dodane** i **usunięte**; wiersze są w pięciu językach.

## Czego jeszcze nie ma

- **Wzniesienia** (`grade_pct`). Zatwierdzony projekt (D3) wyłączał je z pierwszej wersji: spadek psuje wzory zamknięte (równanie przestaje być liniowe w `v³`), a 15 torów nie ma o nich danych. Zostają jako rozszerzenie, liczone numerycznie tylko na odcinkach ze spadkiem.
- **Przestawienie któregokolwiek z 15 torów na `lap_from: thread`** (decyzja właściciela, po jednym, z bramkami `tracks:simulate` i `economy:simulate` i z ostrzeżeniami wyżej).
- Zamrożone w biegu `thread: {id, pace_ppm}` (D6, „diagnostyczne”): nic z niego nie korzysta, a zmieniłoby kształt zamrożonej konfiguracji każdego biegu; nie dodałem.
- Pamięć podręczna wyników symulacji (D5): niepotrzebna, symulacja trwa 0,1 ms na okrążenie.
- Zamknięcie ósemki (suma obrotów 0), „charaktery” botów, `runs:refreeze`: poza zakresem pierwszej wersji (D2, D8, D6).
