<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Grupy pól w panelu „Ustawienia" — które teksty edytować.
 */
class SettingFields
{
    /**
     * @return array<string,array<int,array{key:string,label:string,type:string}>>
     */
    public static function groups(): array
    {
        return [
            'Marka i wyszukiwarki (SEO)' => [
                ['key' => 'site.name', 'label' => 'Nazwa marki', 'type' => 'text'],
                ['key' => 'site.tagline', 'label' => 'Podpis pod nazwą', 'type' => 'text'],
                ['key' => 'site.logoText', 'label' => 'Litera / znak logo', 'type' => 'text'],
                ['key' => 'site.logo', 'label' => 'Logo (opcjonalne zdjęcie)', 'type' => 'image'],
                ['key' => 'site.title', 'label' => 'Tytuł strony w wyszukiwarce', 'type' => 'text'],
                ['key' => 'site.description', 'label' => 'Opis strony w wyszukiwarce', 'type' => 'text'],
                ['key' => 'site.topbar', 'label' => 'Tekst w górnym pasku', 'type' => 'text'],
                ['key' => 'site.heading', 'label' => 'Główny nagłówek strony (H1)', 'type' => 'text'],
                ['key' => 'footer.title', 'label' => 'Nazwa w stopce', 'type' => 'text'],
                ['key' => 'footer.copyright', 'label' => 'Podpis obok roku w stopce', 'type' => 'text'],
            ],
            'Animacje i efekty graficzne' => [
                ['key' => 'motion.mode', 'label' => 'Intensywność efektów', 'type' => 'select', 'choices' => ['full' => 'Pełne — animacje, paralaksa i dynamiczne grafiki', 'subtle' => 'Subtelne — łagodne przejścia, bez paralaksy', 'none' => 'Bez animacji']],
                ['key' => 'motion.sliderSeconds', 'label' => 'Czas wyświetlania slajdu (sekundy)', 'type' => 'number', 'min' => 4, 'max' => 15],
                ['key' => 'motion.autoplay', 'label' => 'Automatycznie zmieniaj slajdy', 'type' => 'checkbox'],
            ],
            'Kontakt' => [
                ['key' => 'contact.phone', 'label' => 'Telefon', 'type' => 'text'],
                ['key' => 'contact.phoneHref', 'label' => 'Telefon — link (np. tel:+48501965485)', 'type' => 'url'],
                ['key' => 'contact.email', 'label' => 'E-mail', 'type' => 'email'],
                ['key' => 'contact.company', 'label' => 'Firma', 'type' => 'text'],
                ['key' => 'contact.address', 'label' => 'Adres', 'type' => 'text'],
                ['key' => 'contact.nip', 'label' => 'NIP', 'type' => 'text'],
                ['key' => 'contact.regon', 'label' => 'REGON', 'type' => 'text'],
                ['key' => 'contact.hours', 'label' => 'Godziny otwarcia', 'type' => 'text'],
                ['key' => 'contact.area', 'label' => 'Obszar działania', 'type' => 'text'],
            ],
            'Powiadomienia e-mail z formularza' => [
                ['key' => 'mail.recipients', 'label' => 'Odbiorcy zapytań (opcjonalnie)', 'type' => 'emails', 'help' => 'Puste pole = wysyłka na adres „Kontakt → E-mail”. Możesz wpisać maksymalnie 5 adresów oddzielonych przecinkami. Zmiana działa dla kolejnych wysyłek. Dane SMTP i nadawca są w konfiguracji hostingu.'],
            ],
            'O nas — sekcja główna' => [
                ['key' => 'intro.kicker', 'label' => 'Odznaka (kicker)', 'type' => 'text'],
                ['key' => 'intro.title', 'label' => 'Nagłówek', 'type' => 'textarea'],
                ['key' => 'intro.p1', 'label' => 'Akapit 1', 'type' => 'textarea'],
                ['key' => 'intro.p2', 'label' => 'Akapit 2', 'type' => 'textarea'],
                ['key' => 'intro.cta1', 'label' => 'Przycisk 1 — tekst', 'type' => 'text'],
                ['key' => 'intro.cta1href', 'label' => 'Przycisk 1 — link', 'type' => 'url'],
                ['key' => 'intro.cta2', 'label' => 'Przycisk 2 — tekst', 'type' => 'text'],
                ['key' => 'intro.cta2href', 'label' => 'Przycisk 2 — link', 'type' => 'url'],
                ['key' => 'intro.baBefore', 'label' => 'Zdjęcie PRZED (URL lub plik)', 'type' => 'image'],
                ['key' => 'intro.baAfter', 'label' => 'Zdjęcie PO (URL lub plik)', 'type' => 'image'],
                ['key' => 'intro.baNote', 'label' => 'Podpis pod suwakiem PRZED/PO', 'type' => 'text'],
            ],
            'Technologia' => [
                ['key' => 'tech.kicker', 'label' => 'Odznaka (kicker)', 'type' => 'text'],
                ['key' => 'tech.title', 'label' => 'Nagłówek', 'type' => 'textarea'],
                ['key' => 'tech.p1', 'label' => 'Akapit 1', 'type' => 'textarea'],
                ['key' => 'tech.p2', 'label' => 'Akapit 2', 'type' => 'textarea'],
                ['key' => 'tech.benefitsTitle', 'label' => 'Tytuł listy zalet', 'type' => 'text'],
                ['key' => 'tech.img', 'label' => 'Zdjęcie (URL lub plik)', 'type' => 'image'],
            ],
            'Nagłówki sekcji' => [
                ['key' => 'offerHead.kicker', 'label' => 'Oferta — kicker', 'type' => 'text'],
                ['key' => 'offerHead.title', 'label' => 'Oferta — nagłówek', 'type' => 'textarea'],
                ['key' => 'appsHead.kicker', 'label' => 'Zastosowania — kicker', 'type' => 'text'],
                ['key' => 'appsHead.title', 'label' => 'Zastosowania — nagłówek', 'type' => 'textarea'],
                ['key' => 'appsCta', 'label' => 'Zastosowania — przycisk', 'type' => 'text'],
                ['key' => 'appsCtaHref', 'label' => 'Zastosowania — adres przycisku', 'type' => 'url'],
                ['key' => 'stepsHead.kicker', 'label' => 'Proces — kicker', 'type' => 'text'],
                ['key' => 'stepsHead.title', 'label' => 'Proces — nagłówek', 'type' => 'textarea'],
                ['key' => 'cennikHead.kicker', 'label' => 'Cennik — kicker', 'type' => 'text'],
                ['key' => 'cennikHead.title', 'label' => 'Cennik — nagłówek', 'type' => 'textarea'],
                ['key' => 'pricingNote', 'label' => 'Cennik — uwaga pod tabelą', 'type' => 'textarea'],
                ['key' => 'cennikCta', 'label' => 'Cennik — przycisk', 'type' => 'text'],
                ['key' => 'cennikCtaHref', 'label' => 'Cennik — adres przycisku', 'type' => 'url'],
                ['key' => 'galeriaHead.kicker', 'label' => 'Galeria — kicker', 'type' => 'text'],
                ['key' => 'galeriaHead.title', 'label' => 'Galeria — nagłówek', 'type' => 'textarea'],
                ['key' => 'galeriaHead.p', 'label' => 'Galeria — pod nagłówkiem', 'type' => 'text'],
                ['key' => 'aboutHead.kicker', 'label' => 'O nas — kicker', 'type' => 'text'],
                ['key' => 'aboutHead.title', 'label' => 'O nas — nagłówek', 'type' => 'textarea'],
                ['key' => 'aboutP', 'label' => 'O nas — tekst', 'type' => 'textarea'],
                ['key' => 'faqHead.kicker', 'label' => 'FAQ — kicker', 'type' => 'text'],
                ['key' => 'faqHead.title', 'label' => 'FAQ — nagłówek', 'type' => 'textarea'],
                ['key' => 'contactHead.kicker', 'label' => 'Kontakt — kicker', 'type' => 'text'],
                ['key' => 'contactHead.title', 'label' => 'Kontakt — nagłówek', 'type' => 'textarea'],
                ['key' => 'contactHead.p', 'label' => 'Kontakt — pod nagłówkiem', 'type' => 'text'],
                ['key' => 'contactNote', 'label' => 'Kontakt — uwaga', 'type' => 'text'],
                ['key' => 'formOk', 'label' => 'Formularz — tekst po wysłaniu', 'type' => 'text'],
            ],
            'Stopka' => [
                ['key' => 'footerTagline', 'label' => 'Opis w stopce', 'type' => 'textarea'],
            ],
            'Przyciski, etykiety i formularz' => self::interfaceFields(),
            'Widoczność sekcji' => array_map(static fn (string $id, string $label): array => [
                'key' => 'section.' . $id . '.visible', 'label' => $label, 'type' => 'checkbox',
            ], array_keys(self::sections()), array_values(self::sections())),
            'Porównanie' => [
                ['key' => 'comparisonHead.kicker', 'label' => 'Kicker (tekst nad nagłówkiem)', 'type' => 'text'],
                ['key' => 'comparisonHead.title', 'label' => 'Nagłówek', 'type' => 'textarea'],
                ['key' => 'comparisonCta', 'label' => 'Przycisk CTA', 'type' => 'text'],
                ['key' => 'comparisonCtaHref', 'label' => 'Adres przycisku', 'type' => 'url'],
            ],
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function imageKeys(): array
    {
        $keys = [];
        foreach (self::groups() as $fields) {
            foreach ($fields as $field) {
                if ($field['type'] === 'image') {
                    $keys[] = $field['key'];
                }
            }
        }
        return $keys;
    }

    public static function sections(): array
    {
        return ['hero' => 'Slider', 'strip' => 'Pasek materiałów', 'intro' => 'Wprowadzenie', 'oferta' => 'Oferta', 'tech' => 'Technologia', 'zastosowania' => 'Zastosowania', 'proces' => 'Proces', 'cennik' => 'Cennik', 'comparison' => 'Porównania', 'galeria' => 'Galeria', 'o-nas' => 'O nas i FAQ', 'kontakt' => 'Kontakt'];
    }

    public static function interfaceDefaults(): array
    {
        return [
            'ui.call' => 'Zadzwoń', 'ui.quote' => 'Wycena gratis', 'ui.materials' => 'Czyścimy m.in.:',
            'ui.before' => 'PRZED', 'ui.after' => 'PO', 'ui.priceName' => 'Materiał / usługa', 'ui.priceValue' => 'Cena',
            'ui.write' => 'Napisz →', 'ui.formHint' => 'Opisz powierzchnię, a przygotujemy wycenę.',
            'ui.openForm' => 'Przejdź do formularza →', 'ui.map' => 'Mapa dojazdu',
            'ui.name' => 'Imię', 'ui.phone' => 'Telefon', 'ui.subject' => 'Temat', 'ui.message' => 'Wiadomość',
            'ui.namePlaceholder' => 'Jan', 'ui.phonePlaceholder' => '600 000 000',
            'ui.subjectPlaceholder' => 'np. cegła 40 m², graffiti', 'ui.messagePlaceholder' => 'Materiał, metraż, miejscowość…',
            'ui.consent' => 'Wyrażam zgodę na przetwarzanie podanych danych w celu odpowiedzi na zapytanie o wycenę.',
            'ui.submit' => 'Wyślij zapytanie →', 'ui.required' => '* Pola wymagane. Zapytanie zostanie zapisane i odczytane przez nasz zespół.',
            'ui.footerLinks' => 'Przydatne linki', 'ui.contact' => 'Kontakt', 'ui.backTop' => 'Do góry ↑',
        ];
    }

    private static function interfaceFields(): array
    {
        $labels = ['ui.call' => 'Przycisk telefonu', 'ui.quote' => 'Przycisk wyceny', 'ui.materials' => 'Wstęp paska materiałów', 'ui.before' => 'Podpis PRZED', 'ui.after' => 'Podpis PO', 'ui.priceName' => 'Kolumna: usługa', 'ui.priceValue' => 'Kolumna: cena', 'ui.write' => 'Przycisk e-mail', 'ui.formHint' => 'Opis karty formularza', 'ui.openForm' => 'Przycisk otwarcia formularza', 'ui.map' => 'Przycisk mapy', 'ui.name' => 'Pole: imię', 'ui.phone' => 'Pole: telefon', 'ui.subject' => 'Pole: temat', 'ui.message' => 'Pole: wiadomość', 'ui.namePlaceholder' => 'Podpowiedź: imię', 'ui.phonePlaceholder' => 'Podpowiedź: telefon', 'ui.subjectPlaceholder' => 'Podpowiedź: temat', 'ui.messagePlaceholder' => 'Podpowiedź: wiadomość', 'ui.consent' => 'Treść zgody kontaktowej', 'ui.submit' => 'Przycisk wysyłania', 'ui.required' => 'Informacja o wymaganych polach', 'ui.footerLinks' => 'Tytuł linków stopki', 'ui.contact' => 'Tytuł kontaktu w stopce', 'ui.backTop' => 'Link do góry'];
        return array_map(static fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'type' => 'text'], array_keys($labels), array_values($labels));
    }

    public static function comparison(): array
    {
        return [
            ['key' => 'comparisonHead.kicker', 'label' => 'Kicker (tekst nad nagłówkiem)', 'type' => 'text'],
            ['key' => 'comparisonHead.title', 'label' => 'Nagłówek', 'type' => 'textarea'],
            ['key' => 'comparisonCta', 'label' => 'Przycisk CTA', 'type' => 'text'],
        ];
    }
}
