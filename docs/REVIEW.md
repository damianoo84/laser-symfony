# Przegląd projektu — 16.10.2026

## Naprawione problemy

- Odczyt zagnieżdżonych formularzy przez `InputBag::get()` zastąpiono `all()`;
  w Symfony 6.4 `get()` nie służy do pobierania tablic.
- Przy zapisie dopuszczane są wyłącznie zdefiniowane ustawienia/pola.
  Dodano sprawdzanie typów, długości, adresów, zakresów liczb i rodzaju karty kontaktowej.
- Edycja/usuwanie/przenoszenie sprawdza przynależność elementu do właściwej sekcji.
- Logowanie ma pełny szablon, hash hasła, limit prób, rotację identyfikatora sesji,
  godzinny limit bezczynności i ochronę CSRF. Wylogowanie wymaga POST i tokenu.
- Panel umożliwia zmianę hasła; zmiana unieważnia dotychczasowe sesje.
- Dowolny `raw` dla treści zastąpiono sanitizacją prostego HTML. Linki i obrazy
  mają walidację także podczas renderowania starszych danych.
- Upload sprawdza rzeczywistą zawartość, MIME, rozmiar i wymiary obrazka;
  nazwy są losowe, a wykonanie skryptów w uploadach zablokowane.
- Pliki konfiguracyjne, kod i dawna baza SQLite nie są dostępne przez Apache.
- Klucz `Setting.key` ma cytowanie wymagane przy przeniesieniu na MariaDB.
- Sortowanie ma stabilną kolejność także dla elementów z jednakowym numerem.
- `app:seed` nie usuwa już treści. Import SQLite odmawia nadpisania niepustej bazy.
- Uzupełniono edycję menu, marki, logo, SEO, linków, etykiet formularza i widoczności sekcji.
- Przebudowano panel: grupy, wyszukiwanie pól, podgląd, nazwy elementów,
  ostrzeżenie o niezapisanych zmianach, potwierdzenie usunięcia i obsługa błędów.
- Usunięto powielane skrypty porównań; wspólny suwak obsługuje mysz, dotyk i klawiaturę.
  Oba zdjęcia mają identyczną geometrię. Aktualizowane są wartości ARIA.
- Menu mobilne poprawnie aktualizuje stan, galeria używa natywnego dialogu,
  slajdy można zatrzymać, a treść pozostaje widoczna bez JavaScript.
- Formularz kontaktowy nie udaje wysyłania: zapisuje wiadomość w `app_inquiries`.
  W panelu można ją przeczytać, oznaczyć i usunąć. Dodano CSRF, limit i pułapkę antyspamową.

## Sprawdzenie

- PHPUnit: **26 testów, 206 asercji**, na SQLite `var/test.db`, nigdy na bazie projektu. Zestaw rozszerzono
  o SMTP, wybór odbiorców, statusy, ponowienie wysyłki i zachowanie przy awarii transportu.
- Testy obejmują wszystkie edytory, zapis ustawień, CRUD/kolejność bloków,
  upload, niedozwolone linki/HTML, CSRF, dostęp anonimowy, limit logowania,
  zapis/obsługę zapytań i zachowanie własnej treści przy ponownym seedowaniu.
- Chrome przez Apache na MariaDB: logowanie, wyszukiwanie, brak powielonych identyfikatorów,
  wersja desktop i mobilna bez przewijania poziomego, menu, galeria, suwaki, brak wyjątków JS.
- Efekty (17.10.2026): warstwowy slider, zmiana aktywnego slajdu/licznika,
  widoczność napisów po animacji, ruchomy pasek, regulacja/wyłączenie animacji w CMS
  i dynamiczna preferencja `prefers-reduced-motion`. Efekty nie kopiują bibliotek ani grafik strony wzorcowej.
- Dodatkowy test rzeczywistego zapisu ustawienia w MariaDB potwierdził zmianę na stronie,
  po czym automatycznie przywrócił pierwotną treść (`CMS_TEST_WRITE=1`).
- `doctrine:schema:validate`, `lint:container`, `lint:twig`, `lint:yaml` — OK.
- `composer audit` — brak zgłoszonych podatności w zainstalowanych zależnościach.
- `.env.local` i `var/app.db` przez Apache — HTTP 403.
- phpMyAdmin i `ligatyperow3` — HTTP 200 po odtworzeniu MySQL.

## Przed publikacją

To jest konfiguracja **lokalna**, nie gotowy serwer produkcyjny. Zaktualizuj niewspierane
PHP 8.1 i stare MariaDB 10.4/XAMPP, ustaw HTTPS i tryb produkcyjny oraz zmień hasło administratora.
Sprawdź prawa do zdjęć zewnętrznych i rzetelność treści marketingowych.
Zweryfikuj prawnie zgodę kontaktową/informację o przetwarzaniu danych i zasady ich retencji.
Kod SMTP jest gotowy. Przed wysyłką trzeba skonfigurować skrzynkę i włączyć ją zgodnie z `docs/SMTP.md`.
Nie ustalono pierwotnej przyczyny uszkodzenia MySQL. Kopie i opis odzyskiwania są wskazane w README.
