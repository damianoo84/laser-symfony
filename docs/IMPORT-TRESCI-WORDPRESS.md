# Pełne treści, media WordPressa i mobilny pasek kontaktu

## Zakres i sposób prezentacji

Źródłem są publiczne strony bazy `komsat_gz0h1` z prefiksem `xno8_`, z dostarczonego
archiwum `komsat_gz0h1_1795097138.sql.gz`. Nie importujemy całej bazy, kont WordPressa,
haseł, ustawień wtyczek, prywatnych danych ani rewizji wpisów.

Odzyskano 10 właściwych stron firmy: stronę główną, O nas, Kontakt, Galerię, Drewno,
Cegłę, Kamień, Beton, Cennik i Zastosowanie. Wykluczono puste strony i demonstracyjne
materiały, np. angielskie „Services” o projektowaniu stron oraz „View Case”.
Dekoracyjne nazwy widżetów „Rozdzielacz”/„Divider” i pozostałość „dd” nie są opisami usług.

Zachowano układ one-page, istniejące sekcje, kolory, karty i pierwsze sześć zdjęć galerii.
Pełne opisy są dostępne w 15 rozwijanych blokach we właściwych sekcjach; nie wstawiamy
kilkunastu ekranów tekstu zamiast dotychczasowych krótkich kart. Tekst jest renderowany
po stronie serwera, bez zależności od WordPressa. Linki „Dowiedz się więcej” prowadzą
do odpowiednich rozwijanych opisów. Stare adresy, np. `/drewno/`, przekierowują 301
do właściwego opisu na obecnej stronie, zamiast zwracać 404.

Zachowano treść źródłową, usuwając style, znaczniki techniczne i niebezpieczny HTML.
Opisy techniczne i deklaracje marketingowe pochodzą z dawnej strony — właściciel
powinien potwierdzić ich aktualność. Historyczny opis kontaktu nie zastępuje aktualnej
polityki prywatności ani konfiguracji obecnego formularza (który nadal zbiera telefon).

## Media — wynik audytu

- Wszystkie 129 załączników zarejestrowanych w bazie ma pliki w dostarczonym backupie.
- Rozpoznano 68 odnośników do mediów we właściwych publicznych stronach; 63 udało się
  odnaleźć w plikach (czasem zdjęcie było przeniesione między miesiącami).
- Projekt używa 85 zweryfikowanych sumami SHA-256 oryginalnych zasobów: 69 unikalnych
  zdjęć galerii, grafik towarzyszących opisom i czterech filmów. Część plików jest współdzielona.
- 74 oryginały uzupełniono w `public/uploads/wordpress/`, pozostałe są ponownie używane
  z `public/uploads/site/`. Utworzono 210 nowych wariantów WebP. Nie powielamy binarnie
  identycznych zdjęć tylko dlatego, że WordPress miał je pod różnymi nazwami/ID.
- Galeria pokazuje początkowo sześć zdjęć, a przycisk „Pokaż więcej zdjęć” odsłania
  następne partie. Bez JavaScript zdjęcia pozostają dostępne. Filmy mają kontrolki,
  `preload="none"`, nie odtwarzają się samoczynnie i nie korzystają z zewnętrznego YouTube.
- Nie dodajemy do realizacji nieużywanych eksperymentów z logo, pliku drukarskiego PDF,
  ikon motywu ani zdjęć portfolio demonstracyjnego. Są wyszczególnione w audycie,
  a ich oryginały nadal znajdują się w prywatnym backupie.

**Nie udało się odzyskać pięciu dawnych odnośników galerii.** Nie są to brakujące
zarejestrowane załączniki — są to dodatkowe, wiszące odwołania zapisane w widżecie:

```text
20250829_093956-scaled.jpg
20250826_173202-scaled.jpg
20250731_110550-scaled.jpg
20250731_102654-scaled.jpg
20250730_114749-scaled.jpg
```

Nie ma ich oryginałów ani wariantów w dostarczonych plikach. Nie zastąpiono ich losowymi
fotografiami i nie publikujemy uszkodzonych obrazów. Jeśli potrzebne jest absolutnie
kompletne odtworzenie dawnej galerii, należy poszukać ich w innej kopii Zenbox lub u właściciela.

Szczegółowe zestawienia znajdują się w `resources/wordpress/media-audit.json`,
a publiczne treści i mapowanie plików w `resources/wordpress/content.json`.
Nie ma tam surowej bazy ani danych logowania. Sam plik `.sql.gz` pozostaje poza repozytorium.

## Panel administracyjny

Po imporcie nowe elementy są zapisane w obecnej bazie Symfony, nie odczytywane z kopii
WordPressa. W panelu „Elementy powtarzalne” dodano:

- **Pełne opisy usług i firmy** — edycja tytułu, całej treści, sekcji i identyfikatora;
- **Filmy i grafiki przy opisach** — wybór opisu z listy, podmiana pliku, okładki, ALT i podpisu;
- nowe zdjęcia są w dotychczasowej **Galerii**, gdzie można je edytować, przenosić i usuwać.

Zmiana identyfikatora opisu aktualizuje powiązania jego mediów. Przed usunięciem opisu
trzeba przenieść lub usunąć przypisane materiały. Nazw identyfikatorów odzyskanych opisów
nie należy zmieniać bez potrzeby — wykorzystują je także linki oferty i przekierowania.

Filmy można wgrywać przez panel do 50 MB, zdjęcia do 5 MB; niższe limity PHP mają pierwszeństwo.
Kopie bezpieczeństwa powinny obejmować bazę i cały `public/uploads/`.

## Wdrożenie na hostingu

Najpierw zrób standardową kopię bazy. W katalogu projektu uruchom kolejno:

```bash
git pull --ff-only origin main
/opt/alt/php83/usr/bin/php bin/console cache:clear --env=prod --no-debug
/opt/alt/php83/usr/bin/php bin/console app:wordpress:import --env=prod --no-debug
```

Ostatnie polecenie pokazuje plan bez zmieniania danych. Po sprawdzeniu wyniku:

```bash
/opt/alt/php83/usr/bin/php bin/console app:wordpress:import --apply --env=prod --no-debug
```

Import weryfikuje SHA-256 plików, tworzy prywatną kopię istniejących ustawień i bloków
w `var/backups/wordpress-content/`, a następnie wykonuje zapis transakcyjnie.
Nie zmienia zapytań klientów, konta administratora, SMTP ani własnych tekstów i zdjęć.
Dodaje brakujące galerie i opisy oraz podłącza domyślne linki oferty; własne linki zostają.
Nie wymaga ALTER TABLE, `app:seed` ani importowania pliku SQL do bazy aplikacji.

Zapisany znacznik migracji zapobiega ponownemu importowi. Kolejne uruchomienie nie
przywróci zdjęć/usuniętych opisów ani nie nadpisze edycji kolegi. Nie usuwaj tego znacznika
z bazy w celu wymuszenia importu bez wcześniejszej analizy i kopii.

## Pasek mobilny

Pasek Zadzwoń/Wycena nie jest już pokazywany na sliderze. Pojawia się po jego przewinięciu,
ma oddzielne tło, kompaktową wysokość i pola dotykowe min. 48 px. Ukrywa się przy
formularzu, otwartym menu, galerii i podczas edycji pola. Nie jest wyświetlany na szerokim
ani bardzo niskim ekranie. Telefon i kontakt nadal są dostępne w nagłówku i treści.

## Kontrola po aktualizacji

1. Otwórz „Dowiedz się więcej” dla Drewna/Kamienia/Cegły i sprawdź kompletne opisy.
2. Rozwiń opisy zastosowań i „O nas”. W panelu zmień testowo krótki fragment, sprawdź
   stronę i przywróć treść. Nie modyfikuj danych produkcyjnych bez zapisania poprzedniej wartości.
3. Kliknij „Pokaż więcej zdjęć”, otwórz powiększenia i sprawdź cztery filmy przy materiałach.
4. Na telefonie sprawdź slider, pasek po przewinięciu, menu i pola formularza.
5. Nie wysyłaj formularza tylko dla kontroli wyglądu; działa to na prawdziwym SMTP.

Testy lokalne: PHPUnit, lint Twig/kontenera oraz `node tools/seo-smoke.mjs`.
