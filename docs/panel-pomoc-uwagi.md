# Uwagi z pisania katalogu pomocy panelu (etap P6) i co z nimi zrobiono

Katalog pomocy pisało dziesięciu autorów, każdy z prawdziwego kodu, schematów i dokumentacji (surowe notatki z listą źródeł i niepewności są w `docs/pomoc-notatki/`). Przy czytaniu kodu znaleźli rzeczy, które nie zgadzają się ze sobą: schemat z kodem, dokumentację z kodem, panel ze schematem. W etapie P6 niczego z tego nie poprawiano (zadanie brzmiało: opisać). **Ten dokument jest teraz wynikiem poprawek** (gałąź `pomoc-naprawy`, 2026-11-02): przy każdej pozycji stoi, co zrobiono, w którym commicie i czy coś czeka na decyzję właściciela.

Commity (wszystkie na gałęzi `pomoc-naprawy`, nic nie wypchnięte, nic nie scalone z `main`):

| Skrót | Zawartość |
|---|---|
| `667200c` | samouczek: krok dodany do żywego samouczka, krok bez tekstu przycisku |
| `5020975` | walidator i schematy: samouczek, liczby reguł, opisy schematu zgodne z kodem |
| `01bb83a` | panel: dolna granica liczby, nazwa pozycji w liście, macierz opon w procentach, przełączniki Studia, slug kreatora, limit CSS, martwe etykiety |
| `a03a67e` | aplikacja bierze z serwera przebieg nowego auta i próg „do serwisu” |
| `0b0375b` | punkt miasta w panelu trzyma się pasa mapy, który pokazuje aplikacja |
| ostatni | dokumentacja (`docs/api-runs.md`, `content/README.md`) i ten dokument |

Oznaczenia: **NAPRAWIONE** (kod albo schemat zmieniony, jest test), **ZAPISANE** (tak ma być; opisane w schemacie albo pomocy), **DO DECYZJI** (zmienia działanie gry albo bramki publikacji, czeka na właściciela).

## Rzeczy, które mogły coś psuć albo wprowadzać w błąd

| # | Co | Wynik | Gdzie |
|---|---|---|---|
| 1 | Krok z wiadomościami bez `reply` psuł `GET /tutorial` (błąd 500) | **NAPRAWIONE.** Zasób oddaje „OK”, gdy kroku nie ma tekstu przycisku; schemat (`if/then`) i walidator wymagają tekstu przycisku od kroku z wiadomościami. | `667200c`, `5020975`; testy `TutorialAddedStepsTest`, `TutorialValidationTest` |
| 2 | `acknowledged` jako wyzwalacz nigdy nie zachodzi; `recovered` prawdziwe też dla auta, które się nie zepsuło | **NAPRAWIONE walidatorem.** `acknowledged` w wyzwalaczu to błąd. `recovered` wolno tylko jako koniec kroku, który zaczyna się od `breakdown` (gdzie indziej to błąd: krok skończyłby się w chwili startu). Sam fakt `recovered` zostaje taki, jaki jest, bo dla kroku naprawy jest właściwy. | `5020975` |
| 3 | Nowy krok wstawiony do żywego samouczka startuje u każdego, kto nie ma dla niego wiersza | **Część naprawiona, część do decyzji.** Naprawione: krok dodany za graczem, którego wyzwalacz nie zachodzi, nie wstrzymuje już kroków za sobą (wcześniej gracz stał w miejscu), nie liczy się do jego postępu i nie jest jego celem, dopóki się nie zacznie; pokazywany jest pierwszy w kolejności samouczka, a odpowiedź i „zostaw na później” trafiają do pokazanego kroku, nie do wszystkich naraz. Gracz, który zrezygnował z samouczka, nie dostaje kroków dodanych później (strażnik z misji, już w `main`). **DO DECYZJI:** czy nowy krok ma się zaczynać u graczy, którzy są już dalej albo skończyli. Dziś tak (misje właśnie tego chcą: ktoś, kto skończył dawne dwadzieścia kroków, ma usłyszeć o misjach). Rekomendacja: zostawić; jeśli kiedyś trzeba będzie dodać krok „tylko dla nowych graczy”, zrobić to jako pole kroku w schemacie, a nie zgadywać z jego położenia. | `667200c`; testy `TutorialAddedStepsTest` |
| 4 | Serwer zna klucze kroków `repair`, `mount`, `crate`; `car_bought` w wyzwalaczu nie daje oferty samouczka | **ZAPISANE i ostrzeżenie.** Opis klucza w schemacie mówi o trzech kluczach; walidator ostrzega, gdy samouczek nie ma któregoś z nich (to ostrzeżenie, nie błąd, bo inna gra może nie mieć naprawy). `car_bought` w wyzwalaczu: ostrzeżenie, że oferta samouczka pokazuje się tylko przy końcu kroku; bez oferty w skrypcie błąd zostaje tylko dla końca. | `5020975` |
| 5 | Magazyny: brak górnej granicy kondycji, min ≤ max, `good_from` ≥ `fair_from`, rosnące pasma, szanse | **NAPRAWIONE.** Schemat: kondycja klasy magazynu i strata na usterkę nie przekraczają 1 000 000 (`ppm_range`). Walidator (w szkicu reguł i w publikacji): min ≤ max w każdym przedziale, pasma usterek rosną według przebiegu i mają jakąś wagę, słowo ogłoszenia „dobry” zaczyna się od wyższej kondycji niż „średni”, każdy rodzaj magazynu ma szanse i szanse na dobre auto lub perłę (gwarancja losuje tylko z nich). | `5020975`; `RuleNumbersValidationTest` |
| 6 | Policja: `police_from` ≤ `police_to`; `bribe_fail_fine_ppm` poniżej `bribe_ppm` | **NAPRAWIONE.** Dwa błędy walidatora. Opis `bribe_fail_fine_ppm` w schemacie poprawiony: to jedna kwota (łapówka stracona i mandat razem), nie łapówka plus mandat. | `5020975` |
| 7 | Boty: `reward_ppm_by_position` o jeden wpis dłuższe niż `offsets_ppm` | **NAPRAWIONE.** Błąd walidatora przy złej długości; do tego: nagroda nie rośnie z miejscem (tak mówił schemat), a nazw jest co najmniej tyle co botów. | `5020975` |
| 8 | `exclusiveMinimum 0` bez dolnej granicy w Studiu | **NAPRAWIONE.** Studio podaje najniższą wartość: o krok nad granicą (1 dla liczby całkowitej, jeden krok jednostki dla ułamka), a wpisane 0 jest zaokrąglane do niej. | `01bb83a`; `StudioFixesTest` |
| 9 | `catch_up_level` musi być wyższy niż koniec tabeli, a minimum w panelu to 2 | **NAPRAWIONE walidatorem** (także dla kopii w `economy.json`): poziom nadrabiania ponad koniec tabeli (przy 9 wpisach: ponad 10), tabela rośnie. Minimum pola w panelu zostaje 2, bo zależy od długości tabeli. | `5020975` |
| 10 | `economy.json` ma własne kopie pojemności i ceny garażu, ceny wraku i krzywej XP; `RulesConsistency` porównuje tylko cenę paliwa, XP za okrążenie i `other_cars_xp_share` | **DO DECYZJI, niezrobione.** Rekomendacja: niech `EconomyRules` czyta te liczby z reguł gry (jedno źródło, kopia znika); drugi wariant to dopisanie ich do `RulesConsistency`, ale wtedy zmiana liczby w Studiu bez zmiany `economy.json` zatrzyma publikację (bramka `--strict-game`), więc to decyzja o bramce, nie poprawka. Pomoc każdej z tych liczb już mówi, że trzeba ją zmienić w obu miejscach. | |

## Niespójności schematu, kodu i dokumentacji

| Co | Wynik | Gdzie |
|---|---|---|
| `storage/pity_every`: opis przesunięty o jeden | **NAPRAWIONE (opis schematu).** Kod gwarantuje dobre auto przy `pity_every`-tym otwarciu (15. po 14 chybieniach). | `5020975` |
| `/travel/fuel_ml_per_km`: „pragnienie toru” | **NAPRAWIONE (opis schematu).** Liczba jest mnożona przez zużycie paliwa auta. | `5020975` |
| `bribe_fail_fine_ppm`: łapówka plus mandat | **NAPRAWIONE (opis schematu i walidator, patrz pozycja 6).** | `5020975` |
| Plansza mapy 390 × 716, a `MapView.vue` rysuje pas y = 60..360 | **NAPRAWIONE.** Schemat przyjmuje `y` od 60 do 360 (miasto poza pasem było ważne, a niewidoczne), punkt na mapie w panelu też trzyma się tego pasa. **Uwaga dla właściciela:** gra z miastem poza pasem nie przejdzie już walidacji (rdzeń gry ma miasta w y 104..290). | `5020975`, `01bb83a` |
| Stare pierwsze miasto | **NAPRAWIONE.** Drogi sprawdza się od miasta, w którym zaczyna kierowca (miasto pierwszego toru), a komunikat tak mówi. Ważność się nie zmienia: drogi są dwukierunkowe, więc spójność mapy jest ta sama, zmienia się tylko, które miasto jest wskazane. | `5020975` |
| `fits` części egzekwuje tylko `TrackSimulator` | **ZAPISANE i ostrzeżenie.** Opis w schemacie i ostrzeżenie walidatora. **DO DECYZJI:** czy egzekwować `fits` w warsztacie, sklepie i podglądzie. Żadna część rdzenia nie ma `fits`, więc dziś nikogo to nie dotyczy; rekomendacja: dopiero gdy pierwsza część go dostanie. | `5020975` |
| Statystyki części `cooling`, `fade_resistance`, `gears` i pola auta bez skutku (`engine_tune`, `seats`, `engine_motors`, `battery_kwh`, `max_charge_kw`, `cargo_kg`) | **ZAPISANE.** Opisy w schematach części i auta mówią, że żaden wzór ich nie czyta (sprawdzone w kodzie). | `5020975` |
| `power_kw` i `engine_mass_kg` części silnika vs reguły `engines` | **NAPRAWIONE jako ostrzeżenie** walidatora (nie błąd: gra używa liczby części, a własny silnik może być inny). `EngineCatalogTest` zostaje. Poprawka notatki: `tier_efficiency_ppm_per_tier` i `kw_per_litre` czyta żywy kod (`Rules::enginePowerKw` dla silników fabrycznych aut), pomoc ich nie opisywała błędnie. | `5020975` |
| Nowa część trafia do sklepu tylko przez `shop.parts`; `tier_price_ppm` bez skutku w sklepie | **ZAPISANE.** Tak działa; pomoc to mówi. Bez zmian. | |
| Warsztat: ceny `[0]`, kolejność cen, `time_cut_ppm_per_level` na maksimum, `fee_cents` | **NAPRAWIONE walidatorem i opisem.** Ceny stanowisk i mechaników muszą rosnąć (błąd); pierwsza cena różna od 0 i pierwszy poziom wyposażenia z ceną to ostrzeżenie „nigdy nie pobierana”. `time_cut_ppm_per_level` zostaje na maksimum schematu (2%) i opis mówi, że to zamierzony bezpiecznik. `fee_cents`: bez zmian, opisane w pomocy. | `5020975` |
| Dealer „0–15 km” na sztywno w web | **NAPRAWIONE.** `GET /market` oddaje `dealer_mileage_km`, ekran dealera pokazuje przedział od 0 do tej liczby (w regułach jest 10 km, na ekranie było 15). | `a03a67e`; testy API i web |
| Pula magazynów ignoruje `sources` auta | **ZAPISANE.** Pula to każde auto spalinowe od poziomu puli; opisane w pomocy. Bez zmian. | |
| `PartCondition.vue` miał na sztywno 20% | **NAPRAWIONE.** Część mówi `unserviceable` (próg `wear.unserviceable_ppm` z serwera); pasek stanu i pierścień punktu na aucie używają tego. | `a03a67e`; `PartCondition.spec.ts`, `RulesReachTheAppTest` |
| `mass_penalty_ppm`, `stat_ppm` jako `[]`, AWD bez `power_limit` | **ZAPISANE.** Znaczenia opisane w pomocy; `[]` zamiast `{}` to zapis pustego obiektu w PHP, który Studio obsługuje; AWD bez limitu to zamierzone. | |
| Dokumentacja: stan i zużycie jako przyszłe, 1539 okrążeń, przykład pogody, `content/README.md` | **NAPRAWIONE.** `docs/api-runs.md`: „Stan auta” odsyła do F3 zamiast mówić „do zrobienia”, 5% życia opon to 2 000 km, czyli 769 okrążeń Złomowa, przykład pogody ma dopisek, że jest sprzed botów, XP za okrążenie jest liczbą toru; `content/README.md`: `tier_price_ppm` ma dzisiejsze wartości `{1: 100%, 7: 163%, 8: 199%}`; opis `wear` w schemacie bez `boost_ppm`, z opisami `power_exponent_ppm` i `stage_ppm`. | ostatni commit, `5020975` |
| Panel: stare etykiety o ppm, `GameResource::HOST`, slug kreatora | **NAPRAWIONE / ZAPISANE.** Martwe etykiety `admin.world.ppm` i `weather_hint` usunięte w pięciu językach (nic ich nie używało). Slug z kreatora zaczyna się od litery i ma najwyżej 32 znaki (`GameResource::slugFrom`). Host w formularzu edycji przyjmujący jedną etykietę jest zamierzony (adresy `localhost` w pracy lokalnej), kreator wymaga dwóch, bo prosi o DNS i certyfikat. | `01bb83a` |
| Marka: `cyan_bg`, limit CSS, język słownika, przełączniki Studia | `cyan_bg`: **ZAPISANE** (pomoc mówi, że to nie tło). Limit CSS: **NAPRAWIONE**, pole liczy bajty jak polityka i mówi to po pięciu językach. Pusty język słownika wraca do angielskiego tekstu paczki: **ZAPISANE**, to zamierzone (komentarz w kodzie: francuski kierowca gry o statkach nie ma czytać „Pneus” obok „Hull”), pomoc to mówi. Przełączniki trybu prostego i suwaków: **NAPRAWIONE**, zachowują wpisane i niezapisane liczby; zmiana sekcji nadal je gubi i ma podpowiedź. | `01bb83a` |
| Etykiety wag wad liczą od 1; ta sama etykieta dla liczb o wspólnym wzorcu | **NAPRAWIONE.** Przy etykiecie z katalogu stoi miejsce w liście (`#2`) albo nazwa (auto), więc trzy ceny wraków i dziewięć progów XP różnią się. Pierwsza waga wad nadal oznacza 0 wad (pomoc to tłumaczy). | `01bb83a` |

## Wpisy, których autorzy nie byli pewni

Sprawdzone tanio w kodzie:

- `vehicle.engine_tune`: żaden wzór tego nie czyta (zgadza się z pomocą; opis w schemacie dopisany).
- Bramka publikacji zatrzyma paczkę z ceną części poza krzywą: tak, `economy:simulate --strict --strict-game` porównuje cenę każdej części z kotwicami w tolerancji 20%.
- Ukryty slot `ecu`: rdzeń paczki nie ma części z tym slotem (zgadza się).
- Tabela botów: publikacja jej nie przelicza, tylko sprawdza, czy jest taka, jaką dałaby treść (błąd, gdy nie), a gdy jej nie ma, ostrzega „auta jeżdżą same”. Zdanie w pomocy o braku rywali jest więc prawdziwe.
- `version` samouczka: nic w silniku jej nie czyta (zgadza się z pomocą).
- `StarterRulesTest` ma na sztywno 200 i 600 XP dla `car/xp_per_level` (zgadza się).
- „Około 750 liczb” w Studiu: jest ich 770.
- Czy Studio pozwala dodać albo usunąć linię pogody miasta: pozwala (powtarzalne pole z przyciskiem dodawania, najmniej jedna linia).

Nie sprawdzone (zostają jak były, tylko do wiadomości): skutek `powertrain = ev` i rada o źródle `start` dla elektryka (gra nie umie uruchomić aut elektrycznych), kolumna tieru 9 w tabeli botów, `listing_age_years` poza giełdą, „około czterech okrążeń” paliwa pierwszej jazdy, konwencja „bez XP i garażu” w fazach 3-4, opisy czterech klas magazynu i „kilka punktów odsprzedaży zbliża mały magazyn do 100%” (skalowanie, nie pomiar), znikanie ofert przy odświeżeniu, nazwy sekcji Studia we wskazówkach, kolor motywu na prawdziwym urządzeniu, przykłady „ok. 75 s”, „~333 okrążenia” i „ok. 595 $”, pomiary z dokumentacji z 2026-10-27 i „pierwsza legendarna część około dnia 70”, opisy użycia kolorów marki, rozmiar 1200 × 630 obrazka linku. Liczby gry samochodowej w pomocy („siedem procent, dziesięć, osiem, trzy, pół prędkości, 60 ml na km”) będą nieprawdziwe w grze z innymi regułami; to jest cecha pomocy napisanej dla jednej gry.

## Co dalej (do decyzji właściciela)

1. Pozycja 3: zostawić start nowych kroków u graczy będących dalej (jak robią misje) czy dodać pole kroku „tylko dla nowych”?
2. Pozycja 10: kopie liczb w `economy.json`: czytać z reguł gry (rekomendowane) czy porównywać w `RulesConsistency` (zmienia bramkę publikacji)?
3. `fits`: egzekwować w warsztacie, sklepie i podglądzie dopiero wtedy, gdy pierwsza część go dostanie (rekomendacja), czy od razu?
4. Zaostrzone teraz walidacje (np. `y` miasta w pasie 60..360, `catch_up_level` ponad tabelą, stanowiska rosnące cenami) dotyczą publikacji i szkiców reguł; rdzeń gry je przechodzi. Gdyby w bazie była inna gra z liczbami, które łamią któreś z nich, jej następna publikacja zostanie zatrzymana z wyjaśnieniem; tego nie sprawdzałem na prawdziwej bazie.
