# Zdjęcia w projekcie Symfony

**Aktualizacja:** naprawa proporcji, podpisów i miniatur jest opisana w
[`NAPRAWA-GRAFIK.md`](NAPRAWA-GRAFIK.md). Przy aktualizacji działającej strony
użyj podanego tam polecenia `app:images:repair`, zamiast ponownie importować bazę.
Poniższy opis eksportu dotyczy pierwszego przeniesienia zdjęć ze starej strony.

Zdjęcia i miniatury strony są w `public/uploads/site/` (początkowo 15 plików JPG,
następnie dodano dwa właściwe zdjęcia drewna i metalowych okuć).
Odzyskano je z kopii starej strony, z `wp-content/uploads/2026/02/`.
Skopiowano wyłącznie używane pliki, bez kodu, konfiguracji ani bazy WordPressa.
Pliki są identyczne z kopią źródłową (sprawdzone SHA-256).

## Wgranie na hosting

Z projektu `C:\xampp\htdocs\laser-symfony` prześlij, zachowując strukturę:

- cały katalog `public/uploads/site/`;
- `src/Content/ContentValidator.php`;
- `src/Content/Defaults.php`;
- `public/css/style.css`;
- `templates/admin/_field.html.twig`.

Zachowaj istniejący `public/uploads/.htaccess` i wszystkie pozostałe zdjęcia wgrane
przez panel. Nie kopiuj na hosting `.env.local` ani całego katalogu `var/`.
Jeśli wcześniej wgrałeś `src/Content/LocalImages.php`, usuń ten konkretny plik
z hostingu — mechanizm podmiany starych adresów został usunięty z aplikacji.

## Podmiana bazy

Poprawiona lokalna baza ma ścieżki `uploads/site/...` zapisane bezpośrednio
w polach zdjęć. Zmieniono 26 pól zdjęć w 20 rekordach, bez zmiany innych treści.
Przed zmianą wykonano pełną kopię bazy.

Pliki SQL są poza katalogiem publicznym:

```text
var/backups/local-images-20261118-120326/before.sql
var/backups/local-images-20261118-120326/laser_symfony-grafiki-lokalne.sql
```

Pierwszy to kopia sprzed porządków. **Do importu na hosting użyj drugiego.**
Identyczna kopia poprawionego eksportu, gotowa do wskazania w phpMyAdmin:
`C:\Users\Damian\Downloads\laser_symfony-grafiki-lokalne.sql`.
Zawiera strukturę i dane wszystkich tabel Symfony, bez tworzenia ani wyboru bazy
(`CREATE DATABASE` / `USE`), więc można go zaimportować do nazwy nadanej przez Zenbox.
Import zastępuje istniejące tabele aplikacji — nie scala danych.

1. Zrób eksport aktualnej bazy Symfony na hostingu na wypadek potrzeby powrotu.
2. Wgraj powyższe pliki kodu i grafiki, najlepiej w krótkim oknie serwisowym.
3. W phpMyAdmin wybierz właściwą **bazę Symfony**, nie starego WordPressa ani inną bazę.
4. Zaimportuj `laser_symfony-grafiki-lokalne.sql` przez zakładkę **Import**.
   Nie trzeba ręcznie usuwać tabel — eksport zawiera ich odtworzenie.
5. Zachowaj produkcyjne dane dostępowe w `.env.local`; nie zastępuj ich lokalnymi.

Nie umieszczaj SQL w katalogu dostępnym z przeglądarki ani w repozytorium.
Eksport może zawierać dane osobowe z formularza kontaktowego.

Po wgraniu plików wykonaj w głównym katalogu projektu na hostingu:

```bash
php bin/console cache:clear --env=prod --no-debug
```

Nie uruchamiaj `app:seed` i nie importuj ponownie starego eksportu bazy.

## Bez podmieniania adresów w czasie wyświetlania

Zarówno baza, jak i treści domyślne wskazują bezpośrednio na `uploads/site/...`.
Nie ma mapowania ani mechanizmu rozpoznawania starych adresów. Szablony używają
`asset()`, więc działają pod lokalnym podkatalogiem `/laser-symfony/public/`
i w głównej domenie na hostingu. Zachowano aktualne dane kontaktowe i domenę firmy
w treściach; nie są one odwołaniami do starych plików.

## Slider

W kopii nie było trzech oryginalnych grafik slidera z zewnętrznego serwera.
Zastąpiono je następującymi zdjęciami z własnego backupu:

1. `20251104_130814-scaled.jpg` — detal kamienny;
2. `20251201_184610-scaled.jpg` — wnętrze obiektu;
3. `20250305_122423-scaled.jpg` — drewniane drzwi z metalowymi okuciami (po naprawie przypisań).

Można je zmienić w panelu: **Elementy powtarzalne → Slider**. W aktualizacji naprawiono
rozpoznane domyślne podpisy i nagłówki, aby pasowały do rzeczywistych zdjęć. Własne
treści, które nie odpowiadają wzorcom naprawy, wymagają ręcznego przeglądu w panelu.

Pełna lista kopiowanych plików jest widoczna w `public/uploads/site/`.
Katalog `public/uploads/site/` jest objęty Gitem, pozostałe uploady nadal są ignorowane.
