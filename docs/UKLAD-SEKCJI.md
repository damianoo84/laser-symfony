# Uporządkowany układ opisów i galeria slajdów

## Widok strony

1. Pierwsze „O nas” pokazuje pełny tekst firmy odzyskany z opisu `wp-onas`.
   Usunięto powtórzone nagłówki tego tekstu oraz drugi przycisk „O nas”. Główna
   nawigacja „O nas” prowadzi teraz do tej pierwszej sekcji, nie do dolnego bloku opinii.
2. Nad przyciskiem „Nasza oferta” jest natywne „Więcej / Mniej”. Rozwija tekst
   odzyskany z `wp-uslugi` w tej samej kolumnie. Nie pokazuje żadnych logotypów ani grafik.
3. Materiały graficzne galerii, osobny pełny opis firmy, dodatkowy cennik i powtórzone
   dane kontaktowe nie są osobnymi belkami na stronie. Rekordy pozostają w CMS.
4. Oferta ma wyłącznie duże kafle Metal, Drewno, Kamień, Cegła i beton, bez dodatkowych
   małych zakładek. Wspólny panel opisu początkowo jest niewidoczny; otwiera go zdjęcie,
   dowolne miejsce dużego kafla lub jego link „Dowiedz się więcej”. Kafel Cegła i beton
   pokazuje oba pełne opisy materiałów wewnątrz jednej wspólnej belki.
5. Zastosowania mają sześć wyborów: Przemysł, Budownictwo, Rolnictwo, Zabytki / Sztuka,
   Dom / Ogród, Gastronomia / Przemysł spożywczy. Każdy wyświetla pełną edytowalną treść
   w jednym panelu, początkowo ukrytym. Każdy wybór jest dużym kaflem ze zdjęciem
   i linkiem „Dowiedz się więcej”, nie ma małych przycisków. Bez JavaScript opisy
   są dostępne jako zwykłe sekcje z odnośnikami.
6. Galeria pokazuje sześć miniatur, bez przycisku rozszerzania całej strony. Po kliknięciu
   zdjęcia można przejrzeć wszystkie aktualne zdjęcia galerii (nie tylko sześć pierwszych):
   strzałkami ekranowymi, klawiszami lewo/prawo, Home/End albo przesunięciem palca.
   Escape i przycisk zamknięcia kończą podgląd. Jest licznik i podpis zdjęcia.

Nie ma automatycznego odtwarzania slajdów galerii. Zmiana zakładki opisu zatrzymuje
film w poprzednim panelu. Pasek kontaktowy pozostaje ukryty w galerii i przy formularzu.
Kafle „Ablacja laserowa” i „Laser vs piaskowanie” zostały usunięte z widoku.
Przy zwykłym wejściu na stronę opisy są ukryte. Bezpośredni link do konkretnego opisu
otwiera go automatycznie, aby stare adresy i zapisane odnośniki nadal działały.

## Gdzie edytować

- **Opisy firmy, oferty i zastosowań**: treść, tytuł i krótka nazwa wyboru.
- Pole **Sposób wyświetlania** pozwala wybrać główny tekst O nas, rozwinięcie Więcej,
  opis Oferty, opis Zastosowań, osobną belkę lub ukrycie.
- Tryb **Automatycznie** rozpoznaje istniejący import, więc nie trzeba ponownie
  importować bazy ani ręcznie przenosić rekordów.
- Zakładka Metal korzysta z tekstu Przemysłu, ponieważ w źródle nie było osobnej strony
  Metalu. Jej nazwę i tytuł edytuje się w ustawieniach nagłówków. Można również utworzyć
  osobny opis z nazwą wyboru Metal i sposobem wyświetlania Oferta — zastąpi automatyczne powiązanie.
- **O nas — sekcja główna**: etykiety Więcej/Mniej, obraz i przycisk Nasza oferta.
  Akapity zapasowe są używane tylko wtedy, gdy nie ma pełnego opisu firmy.
- Zdjęcia kafli zastosowań i ich ALT edytuje się przy odpowiednim pełnym opisie.
  Puste pole zdjęcia wykorzystuje przypisaną fotografię z archiwum firmy.
- **Galeria**: kolejność, zdjęcia, ALT i podpisy. Pierwsze sześć rekordów to miniatury;
  cała lista jest dostępna w podglądzie slajdów. Podpis i ALT nie są wstawiane jako HTML.
- **Przyciski, etykiety i formularz**: nazwy przycisków galerii, licznika i komunikatu błędu.
- Stare karty zastosowań są oznaczone jako układ zapasowy. Są używane tylko wtedy,
  gdy nie ma pełnych opisów Zastosowań; nie trzeba ich usuwać.

## Aktualizacja hostingu

Jeżeli treści WordPressa są już w bazie, wystarczy:

```bash
git pull --ff-only origin main
/opt/alt/php83/usr/bin/php bin/console cache:clear --env=prod --no-debug
```

Ta aktualizacja zmienia prezentację, nie kasuje rekordów, zdjęć, zapytań ani ustawień SMTP.
Nie wymaga ponownego importowania SQL ani ponownego uruchamiania importu WordPressa.
Nie należy nadpisywać produkcyjnego `.env.local`.

## Testy

PHPUnit obejmuje projekcję treści, zachowanie danych CMS i edycję. Test Chrome
`node tools/seo-smoke.mjs` sprawdza przełączanie tekstów, sześć wyborów zastosowań,
przejście do siódmego i ostatniego zdjęcia, zawijanie galerii, klawiaturę i gest telefonu.
Sprawdza także cztery filmy, brak poziomego przewijania i mobilny pasek kontaktu.
