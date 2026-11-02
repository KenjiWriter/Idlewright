# Nitka toru i symulacja okrążenia

Tor może mieć **nitkę**: uporządkowany łańcuch odcinków (prosta, zakręt), po którym auto jedzie jak punkt materialny: rusza, przyspiesza na prostych, hamuje przed zakrętami, w zakręcie jedzie tyle, ile pozwalają przyczepność i zwrotność, a na żwirze, błocie i śniegu zachowuje się inaczej niż na asfalcie. Propozycja, uzasadnienie i policzone przykłady: notatka projektowa „nitka toru” (etap 0). Ten dokument opisuje to, co jest zbudowane.

## Co jest zbudowane

| Etap | Co | Gdzie |
|---|---|---|
| T1 | Rdzeń symulacji: odcinki, stałe modelu, ruch auta, symulator okrążenia | `api/app/Domain/Run/` (`Section`, `Thread`, `Dynamics`, `CarOnSurface`, `Motion`, `LapSimulator`, `SectionRun`, `LapResult`) |
| T2 | Dokument (`thread`, `lap_from`, `dynamics`), walidator po ludzku, `ThreadLap` w zamrażarce, zużycie części po nawierzchniach odcinków, przejście przez formularz świata | `content/schema/rules.schema.json`, `api/app/Content/ThreadDocument.php`, `api/app/Domain/Run/ThreadLap.php` |
| T3–T5 | Edytor w panelu, ekran wyścigu, nitki 15 torów i przestawianie torów | jeszcze nie |

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

## Czego jeszcze nie ma

Edytor w panelu i stałe `dynamics` w Studiu z pomocą w 5 językach (T3), ekran wyścigu z animacją po nitce i wyjaśnieniem „co twoje auto robi tu dobrze i źle” (T4), nitki dla 15 torów i przestawianie torów po jednym z bramkami (T5), wzniesienia (poza V1).
