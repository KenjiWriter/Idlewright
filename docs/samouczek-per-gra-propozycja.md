# Samouczek ustawiany per gra (propozycja, 2026-11-04)

Cel właściciela: **cały samouczek ma się dać ustawić dla każdej gry osobno**: urządzenie, przez które pisze mentor (telefon w RideIdle, list w grze o statkach, tablet w grze kosmicznej itd.), nadawca, wszystkie słowa, historia na start. Musi to też umieć **asystent AI** (most AI, format `ride-changes/1`).

## Co dziś jest na sztywno

| Rzecz | Gdzie | Da się zmienić w panelu? |
|---|---|---|
| Wygląd telefonu (głośnik, pęknięty ekran, przycisk home, pasek z siecią i baterią 78%, dymki SMS) | `web/src/components/TutorialOverlay.vue`, `web/src/style.css` (`.phone`, `.sms-in` …) | nie |
| Numer nadawcy: tylko `+48 555 010 XXX` | formularz `TutorialFields.php:77` i schemat `tutorial.schema.json:52` | tylko 3 ostatnie cyfry |
| Historia na start: 4 sceny, ruch kamery, obraz `garage-hero.jpg`, numer pierwszego SMS-a | `web/src/views/StoryView.vue` | nie (teksty tylko przez Słownik) |
| Słowa urządzenia: „SIEĆ 3G”, „Kto to?”, „Nie pytaj. — K.”, „Odpowiedz…”, „Wyślij”, „Dziś …”, okno pomijania z „Kuba”, komunikaty nagród „K. przesłał Ci …” | `web/src/i18n/locales/*.json` (`tutorial.*`, `story.*`) | tylko przez Słownik, jako klucze aplikacji |
| Treść kroków: SMS-y, cel, odpowiedź, nagrody, liczby skryptu | dokument `rules/tutorial` | tak |
| Most AI | instrukcja zawiera tylko auta, części i reguły `starter` | samouczka nie ma w instrukcji; `set` ustawia tylko liczby; nadawca to zwykły napis, więc ani `set`, ani `text` go nie zmieni |

Każda gra ma już własny dokument samouczka (L4a), więc „per gra” znaczy w praktyce: wszystko powyżej przenosimy do tego dokumentu.

## Decyzje do zatwierdzenia

Przy każdej jest zalecenie (Z). (W) oznacza decyzję, przy której szczególnie zależy mi na Twoim zdaniu.

**D1. Jedno miejsce.** Wszystko, co dotyczy samouczka, trafia do dokumentu samouczka gry: nowe sekcje `messenger` (urządzenie i jego słowa) i `story` (historia na start), obok istniejących `script` i `steps`.
(Z) tak. Panel ma jedną stronę, AI zmienia jeden dokument, paczka niesie całość.

**D2. Urządzenia wbudowane (W).** Do wyboru w polu „Urządzenie”:
- `phone`: dzisiejszy stary telefon z pękniętym ekranem, wygląd bez zmian;
- `letter`: kartka papieru lub telegram: „Od: …” zamiast numeru, wiadomości jako akapity pisane ręcznie, odpowiedź jako przycisk ze stemplem, bez paska sieci i baterii;
- `tablet`: panel sci-fi z ramką i poświatą, „KANAŁ …” zamiast numeru, wiadomości jako wiersze konsoli;
- `plain`: zwykłe okno w kolorach gry, bez żadnej metafory, pasuje do każdej gry.

Każde urządzenie ma przełączniki części, które ma sens wyłączyć: pęknięcie, pasek stanu (sieć, zegar, bateria), pole „Odpowiedz…”, przycisk „Kto to?”. Przycisk „Pokaż” na pasku celu dostaje małą ikonę urządzenia.
(Z) te cztery. Wyglądu listu, tabletu i okna nie ma na żadnej planszy projektu, więc zaprojektuję je sam w skórce `ride.css` i pokażę Ci zrzuty. Jeśli masz obrazki wzorcowe, trzymam się ich.

**D3. Własny obraz urządzenia.** Piąta opcja `picture`: wgrywasz w panelu obraz ramki (PNG lub WebP z przezroczystym miejscem na ekran) do magazynu obrazów (jak zdjęcia aut, L2c) i podajesz, gdzie jest ekran (odstępy od krawędzi w %). Wiadomości wyświetlają się w tym miejscu. Do tego własna mała ikona na przycisk „Pokaż”.
(Z) tak. Tak powstaje butelka z listem, radiostacja czy cokolwiek innego bez pisania kodu.

**D4. Nadawca jako dowolny tekst.** `sender` to tekst 1–40 znaków w językach gry (wymagane en i pl, jak wszędzie). Może to być numer telefonu, „Kapitanat portu” albo „Kanał 7”. Domyślny nadawca jest w `messenger`, a krok może go nadpisać (tak jak dziś mentor zmienia numer co krok). Reguła `+48 555 010 XXX` znika. Dzisiejsze numery RideIdle przechodzą 1:1, w każdym języku ten sam.
(Z) tak.

**D5. Wszystkie słowa samouczka w dokumencie samouczka.** Słowa urządzenia (sieć, „Kto to?”, odpowiedź mentora, „Odpowiedz…”, „Wyślij”, „Dziś {time}”), imię mentora (`mentor`, np. „K.”), okno pomijania i komunikaty nagród trafiają do `messenger.labels`. Każde urządzenie ma w aplikacji własne słowa domyślne (list nie ma „sieci”), a dokument je nadpisuje. Klucze `tutorial.*` i `story.*` znikają ze Słownika, a wpisy, które gry tam już mają, przenoszę do dokumentu samouczka.
(Z) tak. Inaczej te same słowa byłyby w dwóch miejscach i nie byłoby wiadomo, które wygrywa.

**D6. Historia na start per gra.** Sekcja `story`: włączona albo wyłączona (gra bez historii idzie od razu do wyboru auta), obraz z magazynu obrazów, 1–6 scen, a w każdej tekst, czas w sekundach i ruch kamery do wyboru (`zoom-in`, `zoom-out`, `pan-left`, `pan-right`, `still`) z punktem skupienia (x %, y %). Do tego pierwsza wiadomość: nadawca i tekst. RideIdle dostaje swoje 4 dzisiejsze sceny 1:1.
(Z) tak, z gotowymi ruchami kamery zamiast liczb skali i przesunięcia. Te liczby są nieczytelne dla operatora.

**D7. Wyzwalacze i cele kroków bez zmian.** `trigger`, `completion` i `target` dalej nazywają ekrany silnika (garaż, warsztat, sklep, mapa). Te ekrany są w każdej grze, a ich nazwy już zmienia Słownik.
(Z) tak.

**D8. Most AI dla samouczka (W).**
- W instrukcji nowe pole „Samouczek” obok aut, części i reguł. Dokłada linie TEXTS (każdy tekst: SMS-y, cele, odpowiedzi, słowa urządzenia, historia), RULES (liczby skryptu i nagród w jednostkach Studia: ml → l, ms → s, centy → $), CHOICES (pola wyboru z dozwolonymi wartościami: urządzenie, przełączniki, ruchy kamery) i listę kroków z kluczami i warunkami.
- **Kroki adresowane kluczem, nie numerem**: `/steps/refuel/sms/0`. Serwer sam zamienia klucz na pozycję, bo numery przesuwają się po dodaniu kroku.
- **`set` dla wyborów i napisów**: `value` może być tekstem dla pola, które dane podają jako wybór (`unit: "choice"`). Sprawdza go schemat (lista wartości, długość).
- **Nowa operacja `insert`**: nowy krok po kroku o danym kluczu, zapisany jak kroki obok, z warunkami z listy w danych.
- Bez zmian zostaje: AI nie usuwa, nie zmienia kolejności, nie wysyła obrazów, wszystko trafia do szkicu, publikuje człowiek.
- Format zostaje `ride-changes/1`. Zmiany tylko dokładają możliwości, więc stare bloki dalej przechodzą.
(Z) tak, ze wszystkimi czterema punktami.

**D9. Panel.** Na stronie Samouczek nowe sekcje „Urządzenie” (wybór z miniaturami, przełączniki, własny obraz), „Słowa samouczka” i „Historia na start”. Każde pole ma pomoc w 5 językach w katalogu pomocy (`HelpCatalogTest`). Przycisk „Podgląd w grze” otwiera grę ze szkicem (podgląd na żywo z P4), żeby zobaczyć urządzenie z prawdziwym krokiem.
(Z) tak.

**D10. Bramki publikacji.** Walidator sprawdza: własny obraz istnieje w magazynie, włączona historia ma co najmniej jedną scenę, teksty mieszczą się w limitach urządzenia (telefon: 2 krótkie dymki, list: 2 dłuższe akapity), w każdym języku są te same `{name}`.
(Z) tak.

**D11. Dla graczy RideIdle nic się nie zmienia.** Migracja daje RideIdle `phone` z dzisiejszymi słowami, numerami i historią. Test akceptacyjny: telefon i historia wyglądają tak samo jak przed zmianą (specy weba i zrzut przed/po).
(Z) tak.

## Etapy

| Etap | Co | Zależy od |
|---|---|---|
| **S1** model treści | schemat (`messenger`, `story`, `sender` jako tekst, `labels`), migracja RideIdle 1:1, API wysyła nowe pola, walidator i bramki | — |
| **S2** web | telefon wydzielony bez zmian, nowe urządzenia `plain`, `letter`, `tablet`, `picture`, ikona na pasku celu, `StoryView` z treści; zrzuty każdego urządzenia do oceny | S1 |
| **S3** panel | sekcje strony Samouczek, wgrywanie obrazu, miniatury, pomoc ×5, podgląd w grze | S1 |
| **S4** most AI | pole „Samouczek” w instrukcji, kroki po kluczu, `set` dla wyborów, `insert`, przykłady i test pętli naprawy | S1 |
| **S5** podręcznik | rozdział o samouczku i AI, nowe zrzuty panelu | S2–S4 |

Po S1 etapy S2, S3 i S4 dotykają różnych plików (web, panel, `app/Content/Bridge`), więc mogą iść równolegle w osobnych sesjach.

## Czego to nie obejmuje

- Pasek celu, strzałka „Cel jest niżej” i przycisk „Pomiń” zostają takie, jak są. Ich słowa zmienia Słownik.
- Kolory i czcionki urządzeń `plain` i `tablet` biorą się z motywu gry (Marka i strona, L4d). Własny CSS gry dalej może je nadpisać.
- Francuskie, niemieckie i brazylijskie słowa domyślne listu i tabletu napiszę sam, więc warto, żeby przeczytał je ktoś, kto zna te języki.
