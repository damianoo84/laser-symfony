<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Definicje typów bloków — pola edytowalne w panelu admina.
 * type: text | textarea | image | number | checkbox
 */
class BlockTypes
{
    /**
     * @return array<string,array{label:string,fields:array<int,array{key:string,label:string,type:string}>}>
     */
    public static function types(): array
    {
        return [
            'navLink' => [
                'label' => 'Menu główne',
                'fields' => [
                    ['key' => 'label', 'label' => 'Nazwa pozycji', 'type' => 'text'],
                    ['key' => 'href', 'label' => 'Link (np. #oferta)', 'type' => 'url'],
                ],
            ],
            'socialLink' => [
                'label' => 'Media społecznościowe',
                'fields' => [
                    ['key' => 'label', 'label' => 'Nazwa serwisu', 'type' => 'text'],
                    ['key' => 'icon', 'label' => 'Skrót lub emoji', 'type' => 'text'],
                    ['key' => 'href', 'label' => 'Adres profilu', 'type' => 'url'],
                ],
            ],
            'slide' => [
                'label' => 'Slider',
                'fields' => [
                    ['key' => 'img', 'label' => 'Zdjęcie główne (duże, np. 1920px)', 'type' => 'image'],
                    ['key' => 'img_before', 'label' => 'Obrazek "Przed" (opcjonalne)', 'type' => 'image'],
                    ['key' => 'img_after', 'label' => 'Obrazek "Po" (opcjonalne)', 'type' => 'image'],
                    ['key' => 'eyebrow', 'label' => 'Odznaka nad nagłówkiem', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Nagłówek', 'type' => 'textarea'],
                    ['key' => 'text', 'label' => 'Tekst pod nagłówkiem', 'type' => 'textarea'],
                    ['key' => 'cta1', 'label' => 'Przycisk 1 — tekst', 'type' => 'text'],
                    ['key' => 'cta1href', 'label' => 'Przycisk 1 — link', 'type' => 'url'],
                    ['key' => 'cta2', 'label' => 'Przycisk 2 — tekst', 'type' => 'text'],
                    ['key' => 'cta2href', 'label' => 'Przycisk 2 — link', 'type' => 'url'],
                ],
            ],
            'strip' => [
                'label' => 'Pasek materiałów',
                'fields' => [
                    ['key' => 'value', 'label' => 'Nazwa materiału', 'type' => 'text'],
                ],
            ],
            'introStat' => [
                'label' => 'Licznik (O nas)',
                'fields' => [
                    ['key' => 'v', 'label' => 'Liczba', 'type' => 'number'],
                    ['key' => 'suffix', 'label' => 'Przyrostek (np. +, h)', 'type' => 'text'],
                    ['key' => 'label', 'label' => 'Opis', 'type' => 'text'],
                ],
            ],
            'offer' => [
                'label' => 'Karta oferty',
                'fields' => [
                    ['key' => 'img', 'label' => 'Zdjęcie karty', 'type' => 'image'],
                    ['key' => 'name', 'label' => 'Nazwa (np. METAL)', 'type' => 'text'],
                    ['key' => 'desc', 'label' => 'Opis', 'type' => 'textarea'],
                    ['key' => 'link', 'label' => 'Tekst linku "Dowiedz się więcej"', 'type' => 'text'],
                    ['key' => 'href', 'label' => 'Adres linku (puste = sekcja kontakt)', 'type' => 'url'],
                ],
            ],
            'icon' => [
                'label' => 'Ikona pod ofertą',
                'fields' => [
                    ['key' => 'ico', 'label' => 'Emoji (np. 🛡️)', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'Tekst', 'type' => 'text'],
                ],
            ],
            'techStat' => [
                'label' => 'Statystyka (technologia)',
                'fields' => [
                    ['key' => 'big', 'label' => 'Wielka liczba (np. 300+)', 'type' => 'text'],
                    ['key' => 'small', 'label' => 'Podpis', 'type' => 'text'],
                ],
            ],
            'techBenefit' => [
                'label' => 'Zaleta technologii',
                'fields' => [
                    ['key' => 'value', 'label' => 'Zaleta (punkt listy)', 'type' => 'text'],
                ],
            ],
            'app' => [
                'label' => 'Karta zastosowania',
                'fields' => [
                    ['key' => 'img', 'label' => 'Zdjęcie karty', 'type' => 'image'],
                    ['key' => 'kicker', 'label' => 'Kategoria', 'type' => 'text'],
                    ['key' => 'name', 'label' => 'Nazwa', 'type' => 'text'],
                    ['key' => 'desc', 'label' => 'Opis', 'type' => 'text'],
                ],
            ],
            'appMore' => [
                'label' => 'Dodatkowe zastosowanie',
                'fields' => [
                    ['key' => 'ico', 'label' => 'Emoji', 'type' => 'text'],
                    ['key' => 'name', 'label' => 'Nazwa', 'type' => 'text'],
                    ['key' => 'desc', 'label' => 'Opis', 'type' => 'textarea'],
                ],
            ],
            'step' => [
                'label' => 'Krok procesu',
                'fields' => [
                    ['key' => 'num', 'label' => 'Numer (01–04)', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Tytuł kroku', 'type' => 'text'],
                    ['key' => 'desc', 'label' => 'Opis', 'type' => 'textarea'],
                ],
            ],
            'pricing' => [
                'label' => 'Pozycja cennika',
                'fields' => [
                    ['key' => 'name', 'label' => 'Materiał / usługa', 'type' => 'text'],
                    ['key' => 'price', 'label' => 'Cena (np. od 45 zł/m²)', 'type' => 'text'],
                ],
            ],
            'gallery' => [
                'label' => 'Zdjęcie galerii',
                'fields' => [
                    ['key' => 'src', 'label' => 'Miniaturka', 'type' => 'image'],
                    ['key' => 'full', 'label' => 'Pełne zdjęcie (powiększenie)', 'type' => 'image'],
                    ['key' => 'alt', 'label' => 'Alt (opis dla Google)', 'type' => 'text'],
                    ['key' => 'cap', 'label' => 'Podpis na zdjęciu', 'type' => 'text'],
                ],
            ],
            'review' => [
                'label' => 'Opinia klienta',
                'fields' => [
                    ['key' => 'stars', 'label' => 'Gwiazdki (1–5)', 'type' => 'number', 'min' => 1, 'max' => 5],
                    ['key' => 'text', 'label' => 'Treść opinii', 'type' => 'textarea'],
                    ['key' => 'author', 'label' => 'Autor (np. Marek, Kraków)', 'type' => 'text'],
                ],
            ],
            'faq' => [
                'label' => 'Pytanie FAQ',
                'fields' => [
                    ['key' => 'q', 'label' => 'Pytanie', 'type' => 'text'],
                    ['key' => 'a', 'label' => 'Odpowiedź', 'type' => 'textarea'],
                    ['key' => 'open', 'label' => 'Otwarte domyślnie', 'type' => 'checkbox'],
                ],
            ],
            'knowledge' => [
                'label' => 'Kafelek Wiedza',
                'fields' => [
                    ['key' => 'title', 'label' => 'Tytuł', 'type' => 'text'],
                    ['key' => 'desc', 'label' => 'Opis', 'type' => 'text'],
                ],
            ],
            'comparison' => [
                'label' => 'Przed / Po (suwak)',
                'fields' => [
                    ['key' => 'img_before', 'label' => 'Obrazek "Przed"', 'type' => 'image'],
                    ['key' => 'img_after', 'label' => 'Obrazek "Po"', 'type' => 'image'],
                    ['key' => 'handle_position', 'label' => 'Pozycja suwaka (%)', 'type' => 'number', 'min' => 0, 'max' => 100],
                ],
            ],
            'contactCard' => [
                'label' => 'Karta kontaktowa',
                'fields' => [
                    ['key' => 'ico', 'label' => 'Emoji', 'type' => 'text'],
                    ['key' => 'name', 'label' => 'Nazwa karty', 'type' => 'text'],
                    ['key' => 'kind', 'label' => 'Rodzaj karty', 'type' => 'select', 'choices' => ['mail' => 'E-mail', 'tel' => 'Telefon', 'form' => 'Formularz']],
                ],
            ],
            'footerLink' => [
                'label' => 'Link w stopce',
                'fields' => [
                    ['key' => 'label', 'label' => 'Nazwa linku', 'type' => 'text'],
                    ['key' => 'href', 'label' => 'Link (np. #oferta)', 'type' => 'url'],
                ],
            ],
        ];
    }

    public static function label(string $type): string
    {
        return self::types()[$type]['label'] ?? $type;
    }

    public static function fields(string $type): array
    {
        return self::types()[$type]['fields'] ?? [];
    }

    public static function isImage(string $type, string $key): bool
    {
        foreach (self::fields($type) as $f) {
            if ($f['key'] === $key && $f['type'] === 'image') {
                return true;
            }
        }
        return false;
    }
}
