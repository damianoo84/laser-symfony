<?php

declare(strict_types=1);

namespace App\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** One validation boundary for CMS input and legacy content rendering. */
final class ContentValidator
{
    public static function html(string $value): string
    {
        $config = new HtmlSanitizerConfig();
        foreach (['b', 'strong', 'em', 'i', 'br', 'p', 'ul', 'ol', 'li'] as $tag) {
            $config = $config->allowElement($tag);
        }

        return (new HtmlSanitizer($config))->sanitize($value);
    }

    public static function url(string $value, bool $image = false): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\x00-\x20\x7f\\\\<>"\'()]/u', $value)) {
            throw new \InvalidArgumentException('Link zawiera niedozwolone znaki.');
        }
        if (preg_match('~^https?://~i', $value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        if ($image && preg_match('~^uploads/[a-zA-Z0-9/_-]+\.(?:jpe?g|png|webp|gif)$~i', $value)) {
            return $value;
        }
        if (!$image && (preg_match('~^#[\pL\pN_-]*$~u', $value) || preg_match('~^/(?!/)[a-zA-Z0-9/_-]*$~', $value))) {
            return $value;
        }
        if (!$image && preg_match('~^tel:\+?[0-9-]+$~', $value)) {
            return $value;
        }
        if (!$image && str_starts_with($value, 'mailto:') && filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
        throw new \InvalidArgumentException($image ? 'Zdjęcie: podaj adres HTTPS/HTTP lub wgraj plik.' : 'Podaj bezpieczny link (https://, #sekcja, /ścieżka, tel: lub mailto:).');
    }

    public static function field(array $field, mixed $value): string|int|bool
    {
        if (!is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException('Nieprawidłowa wartość pola: ' . $field['label']);
        }
        $text = trim((string) $value);
        if (mb_strlen($text) > 20000) {
            throw new \InvalidArgumentException('Pole „' . $field['label'] . '” jest zbyt długie (maks. 20 000 znaków).');
        }
        return match ($field['type']) {
            'checkbox' => in_array($value, [true, '1', 'on', 1], true),
            'number' => self::number($field, $text),
            'textarea' => self::html($text),
            'image' => self::url($text, true),
            'url' => self::url($text),
            'email' => $text === '' || filter_var($text, FILTER_VALIDATE_EMAIL) ? $text : throw new \InvalidArgumentException('Podaj poprawny adres e-mail.'),
            'emails' => $text === '' ? '' : implode(', ', self::emails($text)),
            'select' => isset($field['choices'][$text]) ? $text : throw new \InvalidArgumentException('Wybierz wartość pola „' . $field['label'] . '”.'),
            default => $text,
        };
    }

    /** @return list<string> */
    public static function emails(string $value): array
    {
        $addresses = array_values(array_unique(array_map('trim', explode(',', $value))));
        if (count($addresses) > 5) {
            throw new \InvalidArgumentException('Podaj maksymalnie 5 adresów e-mail, oddzielonych przecinkami.');
        }
        foreach ($addresses as $address) {
            if (strlen($address) > 254 || !filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $address)) {
                throw new \InvalidArgumentException('Podaj poprawne adresy e-mail oddzielone przecinkami (bez nazw i nawiasów).');
            }
        }
        return $addresses;
    }

    private static function number(array $field, string $text): int
    {
        $number = filter_var($text, FILTER_VALIDATE_INT);
        if ($number === false || $number < ($field['min'] ?? 0) || $number > ($field['max'] ?? 1000000)) {
            throw new \InvalidArgumentException('Podaj poprawną liczbę w polu „' . $field['label'] . '”.');
        }
        return $number;
    }
}
