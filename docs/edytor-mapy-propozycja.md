# Edytor mapy świata (propozycja, 2026-11-11)

Cel właściciela: **mapa w panelu ma wyglądać tak, jak zobaczy ją gracz**, a budowanie mapy nie może narzucać miasta-środka, z którego wychodzą wszystkie drogi (dziś Kruszyn). Ma się dać ułożyć dowolną sieć dróg, np. łańcuch miast w kolejności poziomów.

## Co jest dziś

**Silnik gry nie zakłada środka.** Drogi to dowolna sieć (`rules/starter`: `roads` z `from`, `to`, `km`), przejazd bierze najkrótszą trasę (`RoadMap`, Dijkstra), a walidator wymaga tylko, żeby z miasta startowego (miasto pierwszego toru) dało się dojechać do każdego miasta i żeby droga nie prowadziła do tego samego miasta. Kruszyn w środku to wyłącznie dane: w plikach i w panelu wszystkie cztery drogi wychodzą z Kruszyna.

**Panel nie pokazuje mapy tak, jak gra.**

| Rzecz | Dziś | Skutek |
|---|---|---|
| Miasta | lista „Miasta”, przy każdym mała plansza `MapPoint` z kropkami i podpisem z prawej | nie widać, jak miasto wygląda w grze: gra rysuje kółko 44 px z podpisem pod spodem (nazwa, poziom, nawierzchnia) |
| Drogi | osobna lista „Drogi” (skąd, dokąd, km) bez mapy | droga powstaje w liście, a jej przebieg widać dopiero na planszy któregoś miasta; przesunięcie miasta nie zmienia jego dróg |
| Ostrzeżenia | brak | nic nie mówi, że podpis wyjdzie poza ekran, że dwa miasta na siebie nachodzą albo że droga przechodzi przez miasto, którego nie łączy |
| Pomoc pola „Miejsce na mapie” | „y od 0 do 716 (plansza 390 × 716)” | nieprawda: schemat i gra przyjmują y 60–360 |

**Gra rysuje to, co jest w danych, z dwiema wadami.**
- Podpis miasta jest wyśrodkowany pod kółkiem i obcinany na krawędzi karty. Dziś na serwerze (paczka 2026.11.5) miasta stoją przy lewej krawędzi (x 15–54), więc połowa podpisów jest ucięta.
- **Auto w czasie przejazdu jedzie w linii prostej** między miastem startu a celem (`MapView.vue`, `marker`), a nie po drogach. Przy gwieździe z Kruszynem tego nie widać, bo każda trasa ma najwyżej dwa odcinki. W łańcuchu auto jechałoby na skróty przez pustą planszę.

**Stan na serwerze (2026-11-11):** pozycje z panelu (x, y): Złomowo 15, 347; Kruszyn 16, 268; Suchy Pas 16, 177; Wilcza Przełęcz 19, 81; Autodrom 54, 83. Drogi: Złomowo–Kruszyn 38 km, Kruszyn–Suchy Pas 23 km, Kruszyn–Wilcza Przełęcz 14 km, Kruszyn–Autodrom 30 km. Droga Kruszyn–Wilcza Przełęcz biegnie na rysunku przez Suchy Pas.

## Decyzje do zatwierdzenia

Przy każdej jest zalecenie (Z). (W) oznacza decyzję, przy której szczególnie zależy mi na Twoim zdaniu.

**D1. Gdzie.** Nowa pierwsza zakładka **„Mapa”** na stronie „Świat”, przed „Miasta”, „Drogi” i „Tory”. Zakładki z listami zostają do szczegółów (pogoda, tory, km).
(Z) tak. Jedno miejsce, ten sam szkic, ten sam przycisk „Zapisz szkic”.

**D2. Wygląd jak w grze.** Plansza ma proporcje ekranu mapy (390 × 300, pas y 60–360) w skali telefonu, kółka 44 px z ikoną (start, flaga, kłódka dla miast z poziomem powyżej 1), pod spodem nazwa i linia „LV n+ · nawierzchnia”, drogi jako szerokie przerywane pasy. Język nazw do wyboru (domyślnie język panelu). Linie siatki co 30 jednostek i zaznaczony „bezpieczny pas”, w którym całe kółko mieści się na ekranie.
(Z) tak. Kod rysowania biorę z `MapView.vue`, żeby oba widoki się nie rozjechały.

**D3. Miasta przeciąga się po planszy.** Chwyć kółko i upuść. Pozycja zaokrągla się do całych jednostek, a pod planszą widać x i y wybranego miasta do ręcznej poprawki. Nowe miasto dodaje się jak dziś w zakładce „Miasta” i pojawia się na środku planszy.
(Z) tak.

**D4. Drogi rysuje się kliknięciami (W).** Kliknij miasto A, potem miasto B: powstaje droga A–B. Kliknięcie drogi zaznacza ją: pod planszą pole km i przycisk „Usuń drogę”. Druga droga między tymi samymi miastami nie powstaje, bo panel wtedy tylko zaznacza istniejącą. Nie ma żadnego miasta-środka ani kierunku dróg (drogi działają w obie strony, jak dziś).
(Z) tak. Lista „Drogi” zostaje i pokazuje to samo.

**D5. Kilometry nowej drogi.** Panel podpowiada km z długości narysowanej drogi i skali mapy, czyli mediany „km na jednostkę planszy” istniejących dróg (dziś w plikach ok. 0,19 km na jednostkę). Gdy dróg nie ma: 0,2. Liczbę zawsze można zmienić, a gra dalej liczy wyłącznie km z danych, nie odległość z rysunku. Jednostka jak wszędzie w panelu: km, mile morskie albo mile gry.
(Z) tak, podpowiedź z możliwością zmiany. Alternatywa: pole puste i obowiązkowe.

**D6. Ostrzeżenia na żywo (W).** Pod planszą lista, a na planszy pomarańczowe obwódki przy:
- kółku, które wychodzi poza ekran mapy (środek bliżej niż 24 jednostki od krawędzi);
- dwóch miastach, których kółka albo podpisy na siebie nachodzą;
- drodze, która przechodzi przez miasto, którego nie łączy (jak dziś Kruszyn–Wilcza Przełęcz przez Suchy Pas);
- dwóch drogach, które się przecinają (gracz weźmie skrzyżowanie za miasto albo węzeł);
- mieście, do którego nie da się dojechać z miasta startowego (na czerwono, bo to już dziś błąd przy zapisie).
Pierwsze cztery **nie blokują** zapisu ani publikacji, bo to sprawy rysunku, nie zasad gry.
(Z) tak, jako ostrzeżenia w edytorze. Nie dokładam ich do bramek publikacji.

**D7. Jak to działa w panelu.** Przeciąganie i rysowanie działają w przeglądarce (Alpine), a każdy ruch kończy się wywołaniem metody strony „Świat” (`moveCity`, `addRoad`, `removeRoad`, `setRoadKm`), która zmienia stan formularza na serwerze. Ostrzeżenia liczy jedna klasa PHP (geometria planszy), z której korzysta też widok.
(Z) tak. Jedno źródło prawdy, metody i ostrzeżenia da się sprawdzić testami Pest, a lista „Drogi” i plansza nie mogą się rozjechać.

**D8. Mała plansza przy mieście.** Plansza `MapPoint` w zakładce „Miasta” znika. Zostają same liczby x i y oraz link „Pokaż na mapie”, który przełącza na zakładkę „Mapa” z zaznaczonym miastem.
(Z) tak. Dwie plansze z innym wyglądem to źródło dzisiejszego nieporozumienia.

**D9. Podpisy w grze nigdy nie są ucięte.** Podpis przy krawędzi przesuwa się do środka karty: wyrównuje się do lewej albo prawej zamiast środka. Kółko zostaje na swoim miejscu.
(Z) tak. Naprawia też dzisiejszą mapę na serwerze, gdy tylko wydanie wejdzie, bez przestawiania miast.

**D10. Auto jedzie po drogach (W).** W czasie przejazdu znacznik auta jedzie odcinek po odcinku trasy, przez miasta pośrednie, z szybkością proporcjonalną do km każdego odcinka. Trasę liczy aplikacja z dróg, które już dostaje w `GET /map` (ta sama najkrótsza droga, co na serwerze), więc API się nie zmienia.
(Z) tak. Gdy są dwie trasy o tej samej długości, aplikacja może narysować inną niż serwer. Długość i czas przejazdu są te same, różnica jest tylko na rysunku.

**D11. Podświetlona trasa.** Po wybraniu miasta docelowego na mapie gry podświetlają się odcinki trasy, którą pojedzie auto, a w karcie miasta pod km pojawia się „przez: Kruszyn, Suchy Pas”.
(Z) tak. Przy łańcuchu gracz od razu widzi, dlaczego Autodrom jest daleko.

**D12. Układ dróg RideIdle.** Nie zmieniam dróg gry sam. Ułożysz je w nowym edytorze, opublikujesz i uruchomisz, a bramki publikacji (ekonomia, misje, zlecenia) sprawdzą skutki. Dla orientacji: łańcuch Złomowo–Kruszyn–Suchy Pas–Wilcza Przełęcz–Autodrom z dzisiejszymi km (38, 23, 14, 30) wydłuża drogę ze Złomowa do Wilczej Przełęczy z 52 do 75 km, a do Autodromu z 68 do 105 km. Przejazd trwa wtedy dłużej i zużywa więcej paliwa, a zmieniają się też odległości zleceń kurierskich. Samouczek dalej działa, bo jego pierwszy przejazd to Złomowo–Kruszyn.
(Z) tak, to Twoja decyzja o grze, nie o edytorze.

## Etapy

| Etap | Co | Gdzie |
|---|---|---|
| M1 | Plansza „Mapa” jak w grze: miasta, drogi, przeciąganie, rysowanie i usuwanie dróg, km z podpowiedzią, link z „Miasta” (D1–D5, D7, D8) | `WorldFields`, `WorldPage`, nowy widok Blade z Alpine, klasa geometrii planszy |
| M2 | Ostrzeżenia na żywo (D6) | klasa geometrii + widok |
| M3 | Gra: podpisy bez ucinania, auto po drogach, podświetlona trasa (D9–D11) | `MapView.vue`, mała funkcja trasy w `web/src/game` |
| M4 | Teksty: pomoc pól w 5 językach (w tym poprawiony zakres y), brakujące tłumaczenie `admin.pending.kind.table` (dziś na „Zmiany do publikacji” widać surowy klucz), rozdział podręcznika panelu o świecie z nowym zrzutem | `api/lang/*/help`, `api/lang/*/admin.php`, `docs/podrecznik-panelu.md` |

Każdy etap to osobny commit z testami: Pest dla metod strony i geometrii (ruch miasta, dodanie i usunięcie drogi, brak dubli, podpowiedź km w każdej jednostce, każde ostrzeżenie), specyfikacje web dla trasy, ruchu znacznika i podpisów przy krawędziach. Sprawdzenie w przeglądarce na lokalnej kopii, ze zrzutem planszy panelu obok mapy gry. Format danych się nie zmienia (`regions.*.map`, `roads`), więc nie ma migracji, paczki z panelu i plików pozostają zgodne, a most AI działa bez zmian.

## Czego to nie obejmuje

- Większej lub przewijanej planszy dla gier z wieloma miastami (plansza zostaje 390 × 300). Gdy miast będzie więcej niż mieści ekran, to osobny projekt.
- Dróg krzywych, punktów pośrednich bez miasta i nawierzchni lub pogody na drodze.
- Przesuwania całej mapy naraz albo wyrównywania miast do siatki jednym przyciskiem.
- Zmian w zasadach przejazdu (czas, paliwo, policja liczą się jak dziś z km trasy).
