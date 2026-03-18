<?php
class TextHelper
{
    public static function cleanHtml($text)
    {
        $text = (string) $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);

        return self::normalizeWhitespace($text);
    }

    public static function truncate($text, $limit = 220, $suffix = '...')
    {
        $text = self::normalizeWhitespace((string) $text);

        if ($text === '') {
            return '';
        }

        if (self::length($text) <= $limit) {
            return $text;
        }

        $sliceLength = max(0, $limit - self::length($suffix));
        $slice = self::substring($text, 0, $sliceLength);
        $lastSpace = self::strrpos($slice, ' ');

        if ($lastSpace !== false) {
            $slice = self::substring($slice, 0, $lastSpace);
        }

        return rtrim($slice, " \t\n\r\0\x0B.,;:-") . $suffix;
    }

    public static function normalizeWhitespace($text)
    {
        $text = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', (string) $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    public static function fallback($text, $default = 'Sin resumen disponible.')
    {
        $normalized = self::normalizeWhitespace((string) $text);

        return $normalized !== '' ? $normalized : $default;
    }

    public static function removeFeedFooter($text)
    {
        $text = self::normalizeWhitespace((string) $text);

        if ($text === '') {
            return '';
        }

        $patterns = array(
            '/\s*La entrada .*? se public(?:Ã³|ó) primero en ABI\.?$/u',
            '/\s*La entrada .*? se publicó primero en ABI\.?$/u',
            '/\s*La entrada .*? se publico primero en ABI\.?$/u',
            '/\s*Navegaci(?:Ã³|ó)n de entradas.*$/u',
            '/\s+(?:(?:\/\/?[A-Z]{2,6}\/\/?)|(?:[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*))?\s*Navegaci\S*\s+de\s+entradas[\s\S]*$/iu',
            '/\s*\.?\s*(?=[\/A-Za-z]*\/)(?:\/{0,3}[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*\/{0,3})\s*$/u',
        );

        $cleaned = preg_replace($patterns, '', $text);
        $cleaned = self::normalizeWhitespace((string) $cleaned);
        $cleaned = preg_replace('/\s*(?:\/\/\/|\/\/)[A-Za-z]{2,5}(?:\/\/\/|\/\/)\s*$/u', '', $cleaned);
        $cleaned = preg_replace('/\s*[A-Za-z]{2,5}(?:\/[A-Za-z]{2,5})+\s*$/u', '', $cleaned);
        $cleaned = preg_replace('/([.!?\"])\s+[A-Z]{2,5}\s*$/u', '$1', $cleaned);

        return trim((string) $cleaned);
    }

    private static function length($text)
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private static function substring($text, $start, $length)
    {
        return function_exists('mb_substr') ? mb_substr($text, $start, $length, 'UTF-8') : substr($text, $start, $length);
    }

    private static function strrpos($text, $needle)
    {
        return function_exists('mb_strrpos') ? mb_strrpos($text, $needle, 0, 'UTF-8') : strrpos($text, $needle);
    }
}

