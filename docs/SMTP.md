# Formularz i wysyłka SMTP

## Jak to działa

1. Formularz jest sprawdzany (CSRF, wymagane dane, zgoda, limit i pułapka antyspamowa).
2. Zapytanie zostaje **najpierw zapisane w bazie**.
3. Jeśli SMTP jest włączone i skonfigurowane, aplikacja próbuje wysłać powiadomienie.
4. Klient otrzymuje potwierdzenie zapisania zapytania. Awaria SMTP nie kasuje wiadomości
   i nie powoduje fałszywego komunikatu, że zapytanie przepadło.

Powiadomienie zawiera numer, datę, imię, telefon, temat i wiadomość. Formularz nie zbiera
adresu e-mail klienta, więc nie wysyła automatycznej odpowiedzi do niego — kontakt odbywa się telefonicznie.
Nie są wysyłane załączniki. Wiadomość ma format tekstowy; dane klienta nie sterują nagłówkami/nadawcą.

## Odbiorcy w panelu

- Domyślnie: **Treści i ustawienia → Kontakt → E-mail**.
- Aby wysyłać na inne adresy lub kilka skrzynek, uzupełnij
  **Powiadomienia e-mail z formularza → Odbiorcy zapytań**.
- Maksymalnie 5 poprawnych adresów oddzielonych przecinkami, bez nazw i nawiasów.
- Pozostawienie pustej listy ponownie włącza korzystanie z adresu kontaktowego.
- Zmiana jest pobierana przy wysyłce; nie trzeba czyścić cache.
- Lista odbiorców powiadomień nie jest wyświetlana publicznie. W wiadomości odbiorcy widzą się w polu „Do”.

## Konfiguracja skrzynki na hostingu

Od operatora poczty potrzebujesz **hosta SMTP, portu, rodzaju szyfrowania, loginu, hasła
lub hasła aplikacji oraz adresu uprawnionego do wysyłania**. Nie wpisuj ich do panelu ani repozytorium.
Ustaw je jako zmienne środowiskowe hostingu albo w chronionym `.env.local` projektu:

```dotenv
MAILER_ENABLED=1
MAILER_DSN='smtps://LOGIN:HASLO@smtp.twoj-hosting.pl:465'
MAILER_FROM='formularz@twoja-domena.pl'
```

Powyższy host/login/hasło to **przykład**, nie działające dane. Dla portu 465 `smtps://`
oznacza TLS od początku połączenia. Gdy operator wymaga portu 587 i STARTTLS, użyj
`smtp://LOGIN:HASLO@smtp.twoj-hosting.pl:587`; Symfony negocjuje STARTTLS,
gdy serwer je oferuje. Użyj ustawień operatora i nie wyłączaj weryfikacji certyfikatu TLS.

Login i hasło muszą być zakodowane dla URL: np. `@` jako `%40`, `#` jako `%23`,
`:` jako `%3A`, `/` jako `%2F`, `%` jako `%25`. Koduj **każdą wartość oddzielnie**,
nie cały DSN. Przykład loginu `formularz@firma.pl`: `formularz%40firma.pl`.

`MAILER_FROM` jest stałym adresem nadawcy akceptowanym przez operatora. Nie jest to
adres odbiorcy z panelu. Dzięki temu zmiana odbiorcy nie psuje autoryzacji nadawcy/DMARC.
W domenie skonfiguruj SPF, DKIM i DMARC według instrukcji operatora.

Po zmianie konfiguracji wykonaj na hostingu:

```sh
php bin/console cache:clear --env=prod
php bin/console app:mail:test --env=prod
```

Polecenie testowe wysyła na aktualnych odbiorców z panelu i nie tworzy fikcyjnego zapytania.
Sprawdź fizycznie odbiór i folder spam. Przy konfiguracji lokalnej z `MAILER_ENABLED=0`
polecenie odmawia wysyłki. Transport `null://null` nie jest uznawany za działający SMTP.

Na hostingu muszą być dostępne OpenSSL, poprawne certyfikaty CA i połączenia wychodzące
na port SMTP (niektórzy operatorzy je blokują). Wysyłka jest synchroniczna — bez workera/cron.
Limit połączeń SMTP wynika z ustawień PHP/operatora; przy wolnej poczcie warto rozważyć kolejkę.

## Status i ponowienie

W **Zapytania klientów** zobaczysz stan każdego powiadomienia:

- **Przekazano do SMTP** — serwer przyjął e-mail; to nie gwarantuje dostarczenia do skrzynki.
- **Błąd wysyłki** — treść jest zachowana; sprawdź konfigurację i kliknij „Wyślij powiadomienie e-mail”.
- **Wysyłka wyłączona / konfiguracja niepełna** — uzupełnij konfigurację, a następnie ponów wysyłkę.
- **Nie wysłano** — np. wcześniejsze zapytanie sprzed dodania SMTP; nic nie jest automatycznie rozsyłane wstecz.

Ponowienie korzysta z **aktualnych odbiorców**. Już przyjętego przez SMTP powiadomienia
aplikacja nie wysyła ponownie. Blokada chroni przed równoległym kliknięciem na jednym serwerze.
Przy wielu instancjach aplikacji skonfiguruj wspólny magazyn blokad (LOCK_DSN).
Nie ma automatycznej kolejki ponowień — nieudane wysyłki obsługuje administrator.
W rzadkim przypadku zerwania połączenia po przyjęciu DATA przez SMTP wynik może być niejednoznaczny;
przed ręcznym ponowieniem sprawdź skrzynkę, by uniknąć duplikatu.

Usunięcie zapytania z bazy nie usuwa kopii ze skrzynek e-mail; uwzględnij to w zasadach retencji danych.

## Istniejąca baza przy wdrożeniu aktualizacji

Zrób eksport przed aktualizacją. Jednorazowo wykonaj jako administrator bazy:

```sql
-- Plik: docs/sql/20261017_smtp_notifications.sql
ALTER TABLE app_inquiries
    ADD notification_status VARCHAR(16) DEFAULT 'not_sent' NOT NULL,
    ADD notification_attempted_at DATETIME DEFAULT NULL,
    ADD notification_sent_at DATETIME DEFAULT NULL,
    ADD notification_recipients VARCHAR(1400) DEFAULT NULL;
```

**W lokalnym XAMPP ta aktualizacja jest już wykonana — nie uruchamiaj jej drugi raz.**
Kopia sprzed aktualizacji: `C:\xampp\backups\laser-before-smtp-20261017.sql`.
Dla nowej bazy standardowe `doctrine:schema:create` tworzy już wszystkie kolumny.
Na końcu wykonaj `php bin/console doctrine:schema:validate --env=prod`.

## Testy bez prawdziwej poczty

```powershell
php vendor/phpunit/phpunit/phpunit
node tools/smtp-smoke.mjs
```

PHPUnit wymusza wyłączenie rzeczywistej wysyłki niezależnie od konfiguracji hostingu.
Test Node uruchamia wyłącznie lokalny odbiornik SMTP na losowym porcie loopback,
sprawdza pełną wiadomość Symfony i zamyka go; nic nie przekazuje do Internetu.
