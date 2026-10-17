# LAZER — Symfony CMS

## Lokalnie (XAMPP)

- Projekt roboczy: `C:\xampp\htdocs\laser-symfony`.
- Strona: <http://localhost/laser-symfony/public/>.
- Panel: <http://localhost/laser-symfony/public/admin>.
- Baza: `laser_symfony`, MariaDB 10.4.32, kodowanie `utf8mb4`.
- Login: `admin`. Hasło ustaw lokalnie; nie zapisuj go w repozytorium.
  Możesz je zmienić po zalogowaniu: „Zmień hasło”. W konfiguracji zapisany jest wyłącznie hash.
- Hasło i połączenie z bazą są w ignorowanym przez Git `.env.local`.
- Oryginalny projekt w `C:\Users\Damian\laser-symfony` pozostaje kopią sprzed zmian.

Uruchom Apache i MySQL w XAMPP. Nie jest potrzebny dodatkowy serwer PHP.
Folder główny przekierowuje do `public/`; kod, `.env`, `vendor/` i `var/` są blokowane przez `.htaccess`.

### Naprawa lokalnego MySQL — 16.10.2026

Serwer miał uszkodzone systemowe tabele Aria i niespójny dziennik InnoDB; przestał się uruchamiać.
Za zgodą właściciela odzyskano dane na kopii, wykonano eksport SQL i odtworzono bazy
oraz konta w świeżo zainicjalizowanym katalogu. `mysqlcheck --all-databases` przeszedł.
Eksporty wszystkich wierszy baz projektów przed/po odtworzeniu miały identyczny SHA-256.

- Kopia offline, eksport projektów i kont: `C:\xampp\backups\mysql-before-repair-20261016-2333`.
- Stary katalog danych: `C:\xampp\mysql\data-before-repair-20261016-2333`.
- Robocza kopia odzyskiwania: `C:\xampp\mysql\recovery-20261016-2333` (nie używać do pracy).
- Aktywny serwer: zwykły `C:\xampp\mysql\data`, port 3306, `innodb_force_recovery=0`.
- Aplikacja korzysta z osobnego konta `laser_app@127.0.0.1` z prawami
  SELECT/INSERT/UPDATE/DELETE **tylko** do `laser_symfony`. Nie korzysta z root.

Kopie zawierają dane i konta użytkowników — nie publikuj ich. Zachowaj je do potwierdzenia
poprawności wszystkich projektów. Uszkodzone tabele uprawnień `mysql.db` i `mysql.tables_priv`
po odzyskaniu są puste; globalne konta zostały zachowane, ale ewentualne dawne uprawnienia
nadane indywidualnie do baz/tabel mogą wymagać ponownego nadania.
Nie ustalono pierwotnej przyczyny uszkodzenia. W razie nawrotu sprawdź dysk,
oprogramowanie zabezpieczające i sposób zamykania MySQL; nie kasuj `ibdata1` ani logów na ślepo.

## Edycja strony

### Efekty graficzne

W ustawieniach rozwiń **Animacje i efekty graficzne**. Wybierz pełne efekty, subtelne
przejścia lub całkowite wyłączenie animacji. Możesz też ustawić czas slajdu (4–15 s)
i wyłączyć automatyczną zmianę. Preferencja systemowa „ogranicz ruch” ma pierwszeństwo.

Slider ma przenikanie zdjęć, warstwowe wejście napisów, delikatny zoom, paralaksę,
oryginalne geometryczne nakładki/świetlną linię i wskaźnik czasu. Poza nim działają
wejścia sekcji z różnych kierunków, liczniki, reakcje kart i przesuwany pasek materiałów.
Slider i pasek można wstrzymać. Animacje nie wymagają zewnętrznych bibliotek ani
komercyjnego Slider Revolution. Kod jest w `public/css/motion.css`, `public/js/motion.js`
i wspólnym kontrolerze slidera `public/js/main.js`.

Nie nadpisano zdjęć ani treści z panelu. Jeśli zewnętrzne zdjęcie slidera przestanie
działać, pokazywane jest zdjęcie z sekcji wprowadzenia, a w ostateczności lokalna
oryginalna ilustracja `public/images/laser-surface.svg`. Najlepiej wgrać własne zdjęcia.

### Treści

- **Treści i ustawienia**: wyszukiwarka pól, marka i logo, SEO, kontakt, teksty sekcji,
  etykiety formularza, stopka oraz włączanie/wyłączanie sekcji.
- **Elementy powtarzalne**: menu, slider, galeria, cennik, opinie, FAQ,
  porównania przed/po, linki społecznościowe i pozostałe karty/listy.
- Rozwiń element, zmień dane i kliknij **Zapisz zmiany**.
- Zdjęcie: adres HTTP/HTTPS lub plik JPG/PNG/WebP/GIF (do 5 MB, zależnie od limitu PHP).
  Pliki są w `public/uploads/`; wyczyszczenie adresu usuwa zdjęcie z treści,
  ale nie kasuje pliku, ponieważ może być używany w innym miejscu.
- Teksty wielowierszowe dopuszczają proste formatowanie, nie skrypty i dowolny HTML.
- **Zapytania klientów**: formularz rzeczywiście zapisuje wiadomości w bazie.
  Po skonfigurowaniu SMTP wysyła też e-mail na adres z panelu. Błąd SMTP nie usuwa zapytania.
  Dane można oznaczyć jako przeczytane lub trwale usunąć.
- Zgoda kontaktowa i informacje dla klientów wymagają weryfikacji prawnej przed publikacją.

## Polecenia

```powershell
composer install
php bin/console cache:clear
php bin/console doctrine:schema:validate
php bin/console lint:twig templates
php bin/console lint:yaml config
vendor\bin\phpunit
# Przed testem przeglądarkowym ustaw CMS_ADMIN_PASSWORD w środowisku procesu.
node tools/browser-smoke.mjs
node tools/smtp-smoke.mjs
```

Pierwsza konfiguracja na **nowym**, lokalnym XAMPP:

```powershell
Copy-Item .env.local.example .env.local
# Uzupełnij połączenie i hash hasła w .env.local przed kolejnymi krokami.
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console app:import-sqlite var/app.db
php bin/console app:seed
```

`app:import-sqlite` czyta źródło tylko do odczytu i odmawia importu do niepustej bazy.
`app:seed` uzupełnia brakujące ustawienia/puste typy bloków, nie nadpisuje treści.
`app:upgrade-content` dodaje nowe ustawienia i zmienia tylko rozpoznane, stare domyślne
komunikaty (np. sukces formularza). Nie odtwarza celowo usuniętych bloków.
Nie uruchamiaj go po celowym opróżnieniu sekcji, jeśli nie chcesz przywrócić jej przykładów.
Przykładowa konfiguracja jest w `.env.local.example`. Nie nadpisuj istniejącego `.env.local`.

## Kopie zapasowe i publikacja

Kopiuj bazę `laser_symfony` (eksport w phpMyAdmin), `public/uploads/` i bezpiecznie `.env.local`.
Szczegóły przeglądu i wykonanych testów: `docs/REVIEW.md`.
Przed publikacją ustaw DocumentRoot na `public/`, HTTPS, `APP_ENV=prod`, `APP_DEBUG=0`,
nowy `APP_SECRET`, silne hasło administratora i oddzielne konto bazy (bez root).
Wykonaj przegląd treści marketingowych: część zdjęć/przykładów pochodzi z zewnętrznych domen;
sprawdź prawa do publikacji i wgraj własne pliki. Dostępność tych zewnętrznych zdjęć nie jest gwarantowana.
PHP 8.1 z tego XAMPP jest niewspierane; przed publikacją zaktualizuj PHP i MariaDB,
a następnie uruchom testy ponownie.

## E-maile z formularza (SMTP)

Obsługa SMTP jest gotowa, ale lokalnie wysyłka pozostaje celowo wyłączona:
nie podano jeszcze hosta/loginu/hasła skrzynki. Szczegółowa instrukcja: **`docs/SMTP.md`**.

Odbiorca domyślny: **Treści i ustawienia → Kontakt → E-mail**.
Opcjonalnie ustaw **Powiadomienia e-mail z formularza → Odbiorcy zapytań**
(do 5 adresów rozdzielonych przecinkami). Puste pole oznacza używanie adresu kontaktowego.
Nadawca i hasło SMTP są w konfiguracji serwera, a nie w bazie/panelu.
