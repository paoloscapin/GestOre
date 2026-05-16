<?php

function programmaInizialeWordLikeLooksLikeHtml(string $text): bool
{
    return preg_match('/<\/?(p|div|br|ul|ol|li|h[1-6]|strong|b|em|i|u|blockquote|span)\b/i', $text) === 1;
}

function programmaInizialeWordLikeIsUppercase(string $text): bool
{
    $text = trim($text);
    return preg_match('/\p{L}/u', $text) === 1 && preg_match('/\p{Ll}/u', $text) !== 1;
}

function programmaInizialeWordLikeSanitizeHtml(string $html): string
{
    $html = html_entity_decode((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = str_replace("\xc2\xa0", ' ', $html);
    $html = preg_replace('/&(nbsp|amp;nbsp);/i', ' ', $html);
    $html = str_replace(['__MODULE_TITLE__', '__SECTION_HEADING__'], '', $html);
    $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $html);
    $html = preg_replace('/<\s*(script|style|meta|link|object|iframe)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html);
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', $html);
    $html = preg_replace('/\s+(class|id|style)\s*=\s*(["\']).*?\2/is', '', $html);
    $html = preg_replace_callback('/<ol\b([^>]*)>/i', function ($matches) {
        $attrs = strtolower($matches[1] ?? '');
        if (strpos($attrs, 'lower-alpha') !== false || preg_match('/type\s*=\s*["\']?a/i', $attrs)) {
            return '<ol type="a">';
        }
        return '<ol type="1">';
    }, $html);
    $html = preg_replace('/<\s*b\b[^>]*>/i', '<strong>', $html);
    $html = preg_replace('/<\s*\/\s*b\s*>/i', '</strong>', $html);
    $html = preg_replace('/<\s*i\b[^>]*>/i', '<em>', $html);
    $html = preg_replace('/<\s*\/\s*i\s*>/i', '</em>', $html);
    $html = preg_replace('/<\s*h[1-6]\b[^>]*>/i', '<h4>', $html);
    $html = preg_replace('/<\s*\/\s*h[1-6]\s*>/i', '</h4>', $html);
    $html = strip_tags($html, '<p><div><br><ul><ol><li><strong><em><u><h4><blockquote><span>');

    return trim($html);
}

function programmaInizialeWordLikeLegacyTextToHtml(string $text): string
{
    $lines = preg_split('/\r\n|\r|\n/u', str_replace("\t", '  ', $text));
    if ($lines === false) {
        return '';
    }

    $html = '';

    foreach ($lines as $line) {
        $rawLine = rtrim((string)$line);
        if ($rawLine === '') {
            $nextIsChild = false;
            continue;
        }

        $literalDotMap = [];
        $rawLine = preg_replace_callback('/\.{2,}/u', function ($matches) use (&$literalDotMap) {
            $token = '__GESTORE_LITERAL_DOTS_' . count($literalDotMap) . '__';
            $literalDotMap[$token] = $matches[0];
            return $token;
        }, $rawLine);

        $segments = preg_split('/(?<!\.)\.(?!\.)\s*/u', $rawLine);
        if ($segments === false) {
            $segments = [$rawLine];
        }

        foreach ($segments as $segment) {
            $raw = trim(strtr((string)$segment, $literalDotMap));
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^>>\s*(.+)$/u', $raw, $hm)) {
                $title = preg_replace('/[.;:]\s*$/u', '', trim($hm[1]));
                $html .= '<h4>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
                continue;
            }

            if (programmaInizialeWordLikeIsUppercase($raw)) {
                $title = preg_replace('/[.;:]\s*$/u', '', $raw);
                $html .= '<h4>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
                continue;
            }

            $raw = preg_replace('/;\s*$/u', '', $raw);
            $raw = preg_replace('/^\s*(?:[\x{2022}\x{00b7}\x{25cf}\x{25e6}\x{2043}\x{f0b7}\x{f0a7}\x{f076}]\s+|--\s+|>\s+|-\s+|\*\s+|\d+[\.)]\s+|[a-zA-Z][\.)]\s+)/u', '', $raw);
            $raw = trim($raw);
            if ($raw !== '') {
                $html .= '<p>' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
    }

    return programmaInizialeWordLikeSanitizeHtml($html);
}

function programmaInizialeWordLikeEnsureHtml($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    return programmaInizialeWordLikeLooksLikeHtml($value)
        ? programmaInizialeWordLikeSanitizeHtml($value)
        : programmaInizialeWordLikeLegacyTextToHtml($value);
}
