# Naprawa proporcji i przypisań zdjęć

## Co poprawiono

- Przywrócono naturalne proporcje zwykłych obrazów (`height: auto`). Wymiary HTML
  nadal rezerwują miejsce, ale nie rozciągają zdjęcia technologii do 1920 px wysokości.
  Slider, karty i galeria zachowują swoje celowe kadrowanie `object-fit: cover`.
- Galeria korzysta z dużych oryginałów i responsywnych WebP, zamiast powiększać
  pliki 169–300 px. Panel opisuje teraz potrzebę wgrywania dużego zdjęcia.
- Dodano dwa zdjęcia z prywatnego backupu: `20250218_110112-scaled.jpg` (drewno)
  i `20250305_122423-scaled.jpg` (drewniane drzwi z metalowymi okuciami).
  Oryginały skopiowano bez zmian; wygenerowano sześć wariantów WebP.
- Karta METAL pokazuje metalowe okucia, DREWNO drewnianą płycinę, KAMIEŃ kamienny detal,
  a CEGŁA I BETON ceglaną ścianę. Opisy ALT i podpisy galerii opisują widoczne materiały.
- Wyróżnione zastosowania i slajdy dopasowano do posiadanych zdjęć. Nie podpisujemy
  ceglanej ściany jako metalu/motoryzacji ani zdjęcia wnętrza jako maszyn przemysłowych.
- Nie ma potwierdzonej pary dwóch zdjęć tego samego kadru przed/po. Poprzednią parę
  różnych obiektów zastępuje jedno zdjęcie częściowo oczyszczonego drewna, bez udawania
  dwóch etapów pracy. Porównanie pozostaje dostępne w panelu po dodaniu prawdziwej pary.
- W samym komponencie porównania poprawiono strony: PRZED po lewej, PO po prawej.
- CSS i JS mają wersję opartą na zawartości pliku. Po kolejnej aktualizacji przeglądarka
  pobierze nowy styl, zamiast trzymać poprzednią wersję w cache przez godzinę.

## Aktualizacja hostingu — bez ponownego importowania całej bazy

Najpierw zrób standardową kopię bazy. W katalogu projektu wykonaj kolejno poniższe
polecenia, zatrzymując się przy błędzie. Użyj sprawdzonego interpretera PHP 8.3,
nie domyślnego `php`, które na tym hostingu uruchamiało PHP 5.6.

```bash
git pull --ff-only origin main
/opt/alt/php83/usr/bin/php bin/console cache:clear --env=prod --no-debug
/opt/alt/php83/usr/bin/php bin/console app:images:repair --env=prod --no-debug
```

Ostatnie polecenie jest tylko podglądem. Jeśli plan jest zgodny z oczekiwaniem:

```bash
/opt/alt/php83/usr/bin/php bin/console app:images:repair --apply --env=prod --no-debug
```

Na wyjściowej bazie poprawiono 22 rekordy. Inna liczba jest możliwa, jeśli treści były
edytowane. Naprawa rozpoznaje konkretne błędne zestawy pól — nie aktualizuje wszystkiego
po numerach ID i nie nadpisuje niestandardowych zdjęć ani zmienionych podpisów.
Zachowuje kolejność bloków, własne linki, inne opisy usług, kontakt i zapytania klientów.
Własne niestandardowe karty, które zostaną pominięte, należy przejrzeć w panelu.

Przed zapisem sprawdzane są zdjęcia i tworzona jest prywatna kopia zmienianych rekordów
w `var/backups/image-repair/`. JSON zawiera identyfikatory oraz wartości `before` i `after`.
Dokładna ścieżka jest wypisywana przez polecenie. Nie publikuj tej kopii.
Zapisy są transakcyjne: błąd wycofuje całą operację. Ponowne uruchomienie po naprawie
nie zmienia niczego i nie tworzy zbędnej kopii.

Nie trzeba ponownie importować SQL, uruchamiać `app:seed`, usuwać uploadów ani generować
WebP na hostingu — przygotowane zdjęcia i warianty są w repozytorium.

## Obsługa w panelu

- **O nas — sekcja główna:** przy pustych polach PRZED i PO wyświetlane jest zwykłe
  zdjęcie sekcji. Można je podmienić, a także edytować jego ALT i podpis.
- Aby włączyć suwak, dodaj oba zdjęcia tej samej powierzchni, z możliwie zgodnym kadrem,
  oraz popraw podpis pod nim. Nie porównuj dwóch różnych stron drzwi ani różnych obiektów.
- **Galeria:** wgrywaj duże zdjęcie (najlepiej co najmniej 1200 px szerokości),
  nie gotową małą miniaturę. Osobny plik do powiększenia jest opcjonalny.
- Zdjęcia technologii i kart mają osobne opisy ALT. Nie nazywaj kamienia metalem
  w celu dodania słów kluczowych — podpis ma być zgodny z fotografią.

## Sprawdzenie

Odśwież stronę i sprawdź zdjęcie technologii, karty, galerię i jej powiększenia na telefonie
i komputerze. Nowy test `node tools/seo-smoke.mjs` sprawdza teraz również rzeczywiste
proporcje zdjęć i rozdzielczość pikselową wybranych przez przeglądarkę zdjęć galerii.
Nie wystarcza już samo sprawdzenie, że plik zwrócił HTTP 200.
