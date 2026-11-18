# SEO i wydajność

## Najważniejsze ustalenia z audytu hostingu (18.11.2026)

- Strona główna odpowiadała nagłówkiem `X-Robots-Tag: noindex`, blokując indeksowanie.
- `/robots.txt` i `/sitemap.xml` zwracały 404, a błędy ujawniały diagnostykę Symfony.
- HTTP oraz wersje z `www` i bez `www` odpowiadały 200, bez ujednolicenia adresu.
- Brakowało adresu kanonicznego i metadanych podglądu linku. Jedyny H1 był ukryty wizualnie.
- Karty ładowały duże zdjęcia w tłach CSS. Brakowało responsywnych wariantów zdjęć.
- Hosting nadal pokazywał poprzednie adresy zdjęć — wymagał wdrożenia poprawionej bazy.

## Co zmieniono w projekcie

- `SITE_URL` określa adres kanoniczny, sitemapę, podgląd linków oraz adresy w JSON-LD.
  Domyślny adres to obecna publiczna domena aplikacji, nie adres źródła zdjęć.
- W trybie `prod` bez debugowania wersje HTTP i `www` przekierowują 301 na skonfigurowaną
  wersję. Ścieżka i parametry zostają zachowane. POST nie jest przekierowywany.
- Nieznane hosty, środowiska dev/test, debugowanie, panel admina i odpowiedzi błędów
  zachowują `noindex`. Nie wyłączamy zabezpieczenia Symfony przed indeksowaniem deweloperskiej strony.
- Dodano dynamiczne `robots.txt` i `sitemap.xml`. Sitemap zawiera jedną stronę, bo
  fragmenty `#oferta`, `#kontakt` itp. nie są osobnymi podstronami.
- Dodano Open Graph, karty Twitter/X i bezpiecznie kodowane dane `LocalBusiness`,
  `PostalAddress`, `WebSite`, `WebPage` i widocznych usług. Dane pochodzą z panelu.
  Nie dodano fikcyjnych ocen, opinii, dat aktualizacji ani obietnic rozszerzonych wyników Google.
- H1 jest widoczny. Tytuł i opis domyślny uwzględniają Limanową oraz Małopolskę.
- Podgląd linku i jego opis można zmieniać w panelu: **Marka i wyszukiwarki (SEO)**.
  Karty oferty i zastosowań mają edytowalne opisy zdjęć ALT. Opis ma odpowiadać zdjęciu,
  nie być listą słów kluczowych. Dekoracyjne obrazy mogą mieć pusty ALT.
- Zdjęcia używają `<picture>`, WebP, `srcset`, rozmiarów `width`/`height`, dekodowania
  asynchronicznego i lazy loadingu poniżej pierwszego ekranu. Pierwsze zdjęcie slidera
  ma wysoki priorytet, nie lazy loading. JPG pozostają fallbackiem, również po błędzie WebP.
- Utworzono 33 warianty WebP w szerokościach do 480/960/1600 px, bez powiększania.
  Suma największego wariantu każdego z 15 zdjęć to 3 086 058 B zamiast 7 239 973 B JPG,
  czyli o 57,4% mniej. To porównanie plików, nie wynik Lighthouse ani gwarancja Core Web Vitals.
- `.htaccess` umożliwia kompresję tekstu i cache statycznych plików, jeśli hosting
  udostępnia odpowiednie moduły. HTML z formularzem i tokenami CSRF nie jest publicznie cache'owany.

## Wdrożenie na Zenbox

1. Wykonaj kopię bazy i plików. Pobierz zmiany kodu oraz katalog zdjęć z Gita po ich wypchnięciu.
2. Jeśli nie zaimportowałeś jeszcze poprawionej bazy zdjęć, zrób to zgodnie
   z `docs/GRAFIKI-LOKALNE.md`. Nie importuj jej ponownie po rozpoczęciu edycji treści.
3. W **produkcyjnym** `.env.local` ustaw, zachowując pozostałe sekrety i połączenie z bazą:

   ```dotenv
   APP_ENV=prod
   APP_DEBUG=0
   SITE_URL=https://oczyszczanielaserowe.pl/
   ```

   Nie kopiuj lokalnego `.env.local` na hosting. Sprawdź, czy panel hostingu albo
   `.env.local.php` nie nadpisują tych ustawień. PHP w terminalu i dla domeny może się różnić.
4. Po ewentualnym imporcie bazy wykonaj w katalogu projektu na hostingu:

   ```bash
   php bin/console app:seo-upgrade --env=prod --no-debug
   php bin/console cache:clear --env=prod --no-debug
   ```

   Pierwsze polecenie zmienia wyłącznie trzy rozpoznane stare domyślne metadane
   (tytuł, opis, H1), nie nadpisuje własnych tekstów. Można uruchomić ponownie.
   Nie jest potrzebna zmiana struktury bazy ani ponowne uruchamianie `app:seed`.
5. Sprawdź odpowiedzi:

   ```bash
   curl -I https://oczyszczanielaserowe.pl/
   curl -I http://oczyszczanielaserowe.pl/
   curl -I https://www.oczyszczanielaserowe.pl/
   curl https://oczyszczanielaserowe.pl/robots.txt
   curl https://oczyszczanielaserowe.pl/sitemap.xml
   ```

   Główny HTTPS: 200, **bez `noindex`**. Wersje alternatywne: 301. Robots i sitemap: 200.
   Robots produkcji ma `Allow: /`, nie `Disallow: /`. Panel `/admin` nadal ma `noindex`.
   Reguły robots nie zastępują uwierzytelniania ani zabezpieczeń plików.
6. Jeśli występuje pętla przekierowań mimo HTTPS, sprawdź konfigurację reverse proxy
   w Zenbox. Zaufane adresy proxy muszą pochodzić od operatora; nie ustawiaj w ciemno
   zaufania do wszystkich proxy ani do dowolnego `X-Forwarded-Proto`.
   Przekierowania aplikacji dotyczą żądań obsługiwanych przez Symfony. Ujednolicenie
   hosta/protokołu również dla statycznych plików można skonfigurować w panelu Zenbox.
7. Usuń ewentualne stare fizyczne `robots.txt`/`sitemap.xml` z wdrożenia dopiero po ich
   sprawdzeniu — serwer serwuje fizyczne pliki zamiast nowych tras Symfony.

## Zdjęcia dodawane przez panel

Oryginały nadal trafiają do dotychczasowych podkatalogów `public/uploads/`,
a baza przechowuje rzeczywistą lokalną ścieżkę. Nie ma podmiany adresów starej strony.
Jeśli PHP dla WWW ma GD z obsługą WebP oraz EXIF, warianty powstają przy wgrywaniu JPG/PNG.
Bez tych rozszerzeń oryginał nadal poprawnie działa. GIF/WebP, obrazy ponad 12 mln pikseli
i JPG wymagające obrotu EXIF pozostają w oryginalnym formacie, aby uniknąć błędnej konwersji.
Oryginały i poprzednie zdjęcia nie są kasowane.

Warianty istniejących plików można utworzyć ponownie poleceniem:

```bash
php bin/console app:images:optimize --env=prod --no-debug
```

Na lokalnym XAMPP, bez trwałej zmiany `php.ini`:

```powershell
C:\xampp\php\php.exe -d extension=gd bin\console app:images:optimize
```

Przygotowane WebP ze zdjęć startowych są częścią projektu i nie wymagają GD na hostingu.
Kopie bezpieczeństwa muszą obejmować bazę i **cały** `public/uploads/`, także WebP.

## Zadania poza kodem — konieczne do dalszego pozycjonowania

1. Dodaj i zweryfikuj domenę w Google Search Console (najlepiej rekordem DNS TXT).
   Zgłoś `https://oczyszczanielaserowe.pl/sitemap.xml`, sprawdź URL strony głównej
   i poproś o indeksowanie po usunięciu `noindex`. Nie gwarantuje to terminu indeksacji.
2. Sprawdź adresy starego WordPressa w Search Console i kopii starej mapy strony.
   Potrzebne podstrony przekieruj **indywidualnie 301 do odpowiadających treści**.
   Nie przekierowuj w ciemno wszystkich 404 na stronę główną (ryzyko soft 404).
3. Uzupełnij autentyczny Profil Firmy w Google i spójne dane firmy. Nie twórz
   fikcyjnych lokalizacji ani kupionych opinii. Wskaż rzeczywisty obszar obsługi.
4. Popraw testowe wpisy „LAZERR” i „Strona główna test”, jeśli pozostały w panelu.
   Zweryfikuj podpisy zdjęć — część opisuje metal/drewno, choć zdjęcia przedstawiają cegłę/kamień.
   Używaj autentycznych opisów realizacji, lokalizacji i zdjęć, do których masz prawa.
5. Potwierdź prawdziwość przykładowych opinii i stwierdzeń marketingowych (m.in.
   „do 98%”, „bez pyłu”, „nie generuje odpadów”). Nie publikuj niezweryfikowanych obietnic.
6. Rozwijaj treści na podstawie realnych usług i zapytań: czyszczenie cegły/kamienia,
   usuwanie graffiti, konserwacja zabytków, czyszczenie drewna/metalu — wyłącznie jeśli
   faktycznie je wykonujecie. Osobne, wartościowe podstrony można przygotować w kolejnym etapie.
   Nie twórz automatycznie dziesiątek identycznych stron pod miasta.
7. Po publikacji sprawdź Rich Results Test i PageSpeed Insights (telefon i komputer).
   Obserwuj w Search Console rzeczywiste Core Web Vitals, kliknięcia i zapytania.
   Wyników terenowych nie da się potwierdzić lokalnym testem.

## Weryfikacja lokalna

```powershell
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe -d extension=gd vendor\bin\phpunit
C:\xampp\php\php.exe bin\console lint:twig templates
C:\xampp\php\php.exe bin\console lint:container
node tools/seo-smoke.mjs
```

Zestawów PHPUnit nie uruchamiaj równolegle: używają tej samej izolowanej bazy `var/test.db`.
Test przeglądarkowy używa świeżego profilu Chrome, bez logowania i zmiany danych.
Sprawdza telefon/komputer, widoczny H1, WebP, fallback JPG i błędy JavaScript.
Zrzuty są w ignorowanym `var/seo-checks/`.

Materiały Google: [dyrektywy indeksowania](https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag),
[mapy witryn](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview),
[dane lokalnej firmy](https://developers.google.com/search/docs/appearance/structured-data/local-business).
