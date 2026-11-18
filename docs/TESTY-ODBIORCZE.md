# Testy po wdrożeniu: telefon, SEO, panel i poczta

## Telefon i tablet

Testy przeglądarkowe wykonano w Chrome, w emulacji ekranów 320×568, 360×800,
390×844, 412×915, 768×1024 i 844×390, przy skali urządzenia 2.
Sprawdzono brak poziomego przewijania, ładowanie grafik, menu hamburgerowe i jego
zamykanie po wyborze sekcji, otwieranie i zamykanie galerii, dostępność przycisku
formularza i odrzucanie pustego formularza. Emulacja nie zastępuje fizycznego telefonu.

Na prawdziwym Androidzie (Chrome) i iPhonie (Safari) dodatkowo:

1. Otwórz stronę pionowo i poziomo; sprawdź menu i czy nic nie wychodzi poza ekran.
2. Kliknij miniaturę, zamknij powiększenie i upewnij się, że można dalej przewijać.
3. Sprawdź przyciski Zadzwoń i Wycena oraz czy nie zasłaniają aktywnych pól formularza.
4. Wpisz dane, otwórz klawiaturę ekranową i sprawdź dostęp do zgody oraz przycisku wysyłki.
5. Wyślij tylko jedno oznaczone zapytanie testowe i potwierdź je w panelu i skrzynce.

## SEO — sprawność techniczna a wyniki pozycjonowania

Sprawność techniczną można potwierdzić od razu. Wzrost pozycji/kliknięć wymaga danych
z Google w kolejnych tygodniach i nie jest gwarantowany przez poprawne metadane.

- **Google Search Console:** zweryfikuj własność domeny przez DNS TXT. Dodaj sitemapę
  `https://oczyszczanielaserowe.pl/sitemap.xml`. W Inspekcji URL sprawdź stronę główną,
  użyj testu wersji opublikowanej, sprawdź zgodę na indeksowanie i adres kanoniczny,
  a następnie poproś o indeksowanie. Obserwuj raport indeksowania i Skuteczność.
- **Rich Results Test:** <https://search.google.com/test/rich-results> — wklej adres
  strony. Sprawdź błędy danych firmy; poprawny wynik nie gwarantuje rozszerzonego wyniku.
- **PageSpeed Insights:** <https://pagespeed.web.dev/> — sprawdź telefon i komputer.
  Odróżniaj pomiar laboratoryjny od rzeczywistych Core Web Vitals; mała/nowa strona
  może nie mieć jeszcze danych terenowych. Sam wynik punktowy nie mierzy pozycji w Google.
- **Podstawy HTTP:** główna strona HTTPS zwraca 200 bez `noindex`; HTTP i www przekierowują
  301 na HTTPS bez www. Robots i sitemap zwracają 200. Panel admina ma pozostać `noindex`.
- **Treść:** sprawdź jeden widoczny H1, tytuł/opis z panelu, prawdziwe podpisy zdjęć,
  aktualne dane firmy i autentyczność opinii. Nie oceniaj postępu wyłącznie przez `site:`
  ani codzienne wyszukiwanie w swojej przeglądarce — wyniki są zmienne i personalizowane.

## Logowanie do panelu

Adres: <https://oczyszczanielaserowe.pl/admin>. Login pochodzi z `ADMIN_USER` (domyślnie
`admin`). Hasło nie jest zapisane w repozytorium. W `.env.local` jest jego hash.
`ADMIN_PASSWORD_HASH=disabled` lub przykładowy hash oznacza, że poprawne logowanie
nie jest skonfigurowane. Nie wysyłaj nikomu hasła ani całego `.env.local`.

Jeżeli nie masz działającego hasła, na SSH w Bash wygeneruj nowy hash bez wpisywania
hasła w historię poleceń (wybierz co najmniej 12 znaków, maksymalnie 72 bajty):

```bash
read -r -s -p 'Nowe hasło administratora: ' ADMIN_PASS
printf '\n'
printf '%s' "$ADMIN_PASS" | /opt/alt/php83/usr/bin/php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT), PHP_EOL;'
unset ADMIN_PASS
```

Wpisane hasło jest ukryte. Skopiuj tylko wygenerowany hash do `.env.local`:

```dotenv
ADMIN_USER=admin
ADMIN_PASSWORD_HASH='TU_WKLEJ_WYGENEROWANY_HASH'
```

Nie zakomentowuj tych dwóch wpisów. Zostaw pojedyncze cudzysłowy wokół hasha,
aby znaki `$` nie były interpretowane. Nie zmieniaj pozostałych danych produkcyjnych.

```bash
/opt/alt/php83/usr/bin/php bin/console cache:clear --env=prod --no-debug
```

Zaloguj się hasłem, które wpisałeś podczas generowania — nie hashem i nie `APP_SECRET`.
Po pięciu nieudanych próbach logowania trzeba odczekać 15 minut. W razie problemów
sprawdź, czy zmienne hostingu lub skompilowany `.env.local.php` nie nadpisują ustawień.

Po zalogowaniu sprawdź edycję tekstu, wgranie zdjęcia, jego podgląd oraz stronę publiczną.
Nie zmieniaj treści produkcyjnych bez zapisania ich poprzedniej wartości.

## Formularz i SMTP — oddzielne etapy

1. **Walidacja:** brak wymaganych danych/zgody nie może tworzyć zapytania.
2. **Zapis:** poprawne zapytanie ma być widoczne w panelu w Zapytaniach klientów.
3. **Przekazanie SMTP:** w szczegółach zapytania sprawdź status powiadomienia i odbiorców.
4. **Dostarczenie:** odbiorca musi fizycznie zobaczyć wiadomość; sprawdź też spam/kwarantannę.
5. **Uwierzytelnienie:** w nagłówkach odebranego e-maila sprawdź SPF, DKIM i DMARC.

Potwierdzenie formularza nie oznacza jeszcze dostarczenia e-maila. „Przekazano do SMTP”
oznacza przyjęcie przez serwer pocztowy, nie gwarantuje trafienia do folderu Odebrane.

Przy testowaniu wykryto błąd SQL w zapisie do MySQL/MariaDB: kolumna `read` jest
słowem zastrzeżonym i wymaga cytowania. Poprawiono wyłącznie mapowanie Doctrine.
Nie trzeba zmieniać nazwy kolumny ani wykonywać ALTER TABLE. Po pobraniu poprawki:

```bash
git pull --ff-only origin main
/opt/alt/php83/usr/bin/php bin/console cache:clear --env=prod --no-debug
```

Oddzielny test samego SMTP, bez tworzenia zapytania klienta:

```bash
/opt/alt/php83/usr/bin/php bin/console app:mail:test --env=prod --no-debug
```

Wysyła on prawdziwy e-mail na bieżących odbiorców z panelu. Uruchom raz i sprawdź skrzynkę.
Domyślnym odbiorcą jest kontaktowy e-mail firmy; lista w Powiadomieniach e-mail ma
pierwszeństwo. Nie podawaj hasła SMTP w argumentach ani w rozmowie.

Jeżeli test nie przechodzi, sprawdź host, port, STARTTLS/TLS i dozwolonego nadawcę
według instrukcji Zenbox. Nie wyłączaj weryfikacji certyfikatu. Przy niedoręczeniu
sprawdź logi poczty/bounce, spam oraz SPF/DKIM/DMARC. Nie ponawiaj wielu prób w ciemno.

HTTP 500 po wysłaniu wymaga odczytania logu serwera. Nie zakładaj automatycznie, że
zapytanie nie zostało zapisane — najpierw sprawdź panel i skrzynkę, by nie tworzyć duplikatów.
