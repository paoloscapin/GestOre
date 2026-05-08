<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once __DIR__ . '/programmiAutoreUtils.php';
ruoloRichiesto('segreteria-didattica', 'docente');

function isProgrammaSvoltoInternalMarkerTitleCandidate(string $text): bool
{
    $letters = preg_replace('/[^\p{L}]/u', '', $text);
    if ($letters === '') {
        return true;
    }

    return mb_strtoupper($letters, 'UTF-8') === $letters;
}

function splitProgrammaSvoltoInternalMarkerTitle(string $markerType, string $rawText): array
{
    $text = trim(html_entity_decode(strip_tags($rawText), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') {
        return ['', ''];
    }

    // Nei testi vecchi puo' capitare "__MODULE_TITLE__TITOLO Testo normale..."
    // sulla stessa riga: separiamo il titolo solo se la prima parte e' davvero tutta maiuscola.
    if (preg_match('/^(.{3,90}?)(\s+\p{Lu}?\p{Ll}.*)$/u', $text, $matches)) {
        $candidateTitle = trim($matches[1]);
        $rest = trim($matches[2]);
        if ($candidateTitle !== '' && $rest !== '' && isProgrammaSvoltoInternalMarkerTitleCandidate($candidateTitle)) {
            return [$candidateTitle, $rest];
        }
    }

    return [$text, ''];
}

function isProgrammaSvoltoHeadingTextPlausible(string $text): bool
{
    $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') {
        return false;
    }

    return mb_strlen($text, 'UTF-8') <= 90;
}

function demoteProgrammaSvoltoLegacyLongHeadings(string $html): string
{
    return preg_replace_callback('/<h4>(.*?)<\/h4>/is', function ($matches) {
        $raw = $matches[1] ?? '';
        if (isProgrammaSvoltoHeadingTextPlausible($raw)) {
            return $matches[0];
        }
        return '<p>' . trim($raw) . '</p>';
    }, $html) ?? $html;
}

function normalizeProgrammaSvoltoInternalMarkers(string $html): string
{
    if (strpos($html, '__MODULE_TITLE__') === false && strpos($html, '__SECTION_HEADING__') === false) {
        return $html;
    }

    $html = preg_replace('/<\/(p|div|li|h4|h5)>\s*/i', "\n", $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<[^>]+>\s*(?=__(?:MODULE_TITLE|SECTION_HEADING)__)/i', "\n", $html);
    $html = preg_replace('/(?<!^)(__(?:MODULE_TITLE|SECTION_HEADING)__)/m', "\n$1", $html);

    return preg_replace_callback('/__(MODULE_TITLE|SECTION_HEADING)__\s*([^\r\n<]+)/u', function ($matches) {
        $tag = $matches[1] === 'SECTION_HEADING' ? 'h5' : 'h4';
        [$title, $rest] = splitProgrammaSvoltoInternalMarkerTitle($matches[1], $matches[2]);
        if ($title === '') {
            return '';
        }
        $html = '<' . $tag . '>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</' . $tag . '>';
        if ($rest !== '') {
            $html .= "\n" . htmlspecialchars($rest, ENT_QUOTES, 'UTF-8');
        }
        return $html;
    }, $html);
}

function sanitizeProgrammaSvoltoRichHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    // Normalizza residui HTML incollati male, ad esempio "&nbsp" senza punto e virgola.
    $html = preg_replace('/&nbsp(?!;)/i', '&nbsp;', $html);
    $html = str_ireplace('&nbsp;', ' ', $html);
    $html = str_replace("\xc2\xa0", ' ', $html);
    $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $html);
    $html = normalizeProgrammaSvoltoInternalMarkers($html);
    $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
    $html = preg_replace_callback('/<ol\b([^>]*)>/i', function ($matches) {
        $attrs = strtolower($matches[1] ?? '');
        if (strpos($attrs, 'lower-alpha') !== false || preg_match('/type\s*=\s*["\']?a/i', $attrs)) {
            return '<ol type="a">';
        }
        return '<ol type="1">';
    }, $html);
    $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><u><h4><blockquote>');
    $html = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function ($matches) {
        $tag = strtolower($matches[1] ?? '');
        $attrs = $matches[2] ?? '';
        if ($tag === 'ol' && preg_match('/type\s*=\s*["\']?([aA1])["\']?/i', $attrs, $typeMatch)) {
            return '<ol type="' . $typeMatch[1] . '">';
        }
        return '<' . $tag . '>';
    }, $html);
    $html = str_ireplace(['<b>', '</b>', '<i>', '</i>'], ['<strong>', '</strong>', '<em>', '</em>'], $html);
    $html = demoteProgrammaSvoltoLegacyLongHeadings($html);

    return trim($html);
}

function programmaSvoltoPlainFromRichHtml(string $value): string
{
    $value = sanitizeProgrammaSvoltoRichHtml($value);
    $value = preg_replace('/<br\s*\/?>/i', "\n", $value);
    $value = preg_replace('/<\/(p|li|h4)>/i', "\n", $value);
    return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function programmaSvoltoLooksLikeHtml(string $text): bool
{
    return preg_match('/<\/?(p|br|ul|ol|li|strong|b|em|i|u|h4|blockquote)\b/i', $text) === 1;
}

function programmaSvoltoLegacyTextToRichHtml(string $text): string
{
    $lines = preg_split('/\R/u', str_replace("\t", '  ', $text));
    if ($lines === false) {
        return '';
    }

    $html = '';
    $listOpen = false;

    $closeList = function () use (&$html, &$listOpen): void {
        if ($listOpen) {
            $html .= '</ul>';
            $listOpen = false;
        }
    };

    foreach ($lines as $line) {
        $raw = (string)$line;
        $trimmed = trim($raw);
        if ($trimmed === '') {
            $closeList();
            continue;
        }

        if (preg_match('/^>>\s*(.+)$/u', $trimmed, $m)) {
            $closeList();
            $html .= '<h4>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</h4>';
            continue;
        }

        if (mb_strlen($trimmed, 'UTF-8') <= 90 && preg_match('/\p{L}/u', $trimmed) && !preg_match('/\p{Ll}/u', $trimmed)) {
            $closeList();
            $html .= '<h4>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</h4>';
            continue;
        }

        if (preg_match('/^(?:[•●▪◦\x{F0B7}\x{F0A7}]\s+|--\s+|>\s+|-\s+|\*\s+)(.+)$/u', ltrim($raw), $m)) {
            if (!$listOpen) {
                $html .= '<ul>';
                $listOpen = true;
            }
            $html .= '<li>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</li>';
            continue;
        }

        $closeList();
        $html .= '<p>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $closeList();
    return trim($html);
}

function programmaSvoltoEnsureRichHtml(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return programmaSvoltoLooksLikeHtml($value)
        ? sanitizeProgrammaSvoltoRichHtml($value)
        : sanitizeProgrammaSvoltoRichHtml(programmaSvoltoLegacyTextToRichHtml($value));
}

if (!isset($_POST)) {
    exit;
}

$id = intval($_POST['id'] ?? 0);
$id_programma = intval($_POST['id_programma'] ?? 0);
$ordine = intval($_POST['ordine'] ?? 0);
$titolo = trim((string)($_POST['titolo'] ?? ''));
$contenuto = sanitizeProgrammaSvoltoRichHtml((string)($_POST['contenuto'] ?? ''));
$competenze_raggiunte = sanitizeProgrammaSvoltoRichHtml((string)($_POST['competenze_raggiunte'] ?? ''));
$contenuti_trattati = sanitizeProgrammaSvoltoRichHtml((string)($_POST['contenuti_trattati'] ?? ''));
$abilita_quinta = sanitizeProgrammaSvoltoRichHtml((string)($_POST['abilita_quinta'] ?? ''));

if ($id_programma <= 0 || $ordine <= 0 || $titolo === '') {
    exit;
}

$programma = dbGetFirst("
    SELECT classi.anno AS classe_anno
    FROM programmi_svolti
    INNER JOIN classi
    ON classi.id = programmi_svolti.id_classe
    WHERE programmi_svolti.id = " . intval($id_programma)
);

$classe_anno = intval($programma['classe_anno'] ?? 0);
$is_quinta = ($classe_anno === 5);

if ($is_quinta) {
    $competenze_raggiunte = programmaSvoltoEnsureRichHtml($competenze_raggiunte);
    $contenuti_trattati = programmaSvoltoEnsureRichHtml($contenuti_trattati);
    $abilita_quinta = programmaSvoltoEnsureRichHtml($abilita_quinta);

    $contenuto = json_encode([
        'schema' => 'programma_svolto_quinta_v2',
        'competenze_raggiunte' => programmaSvoltoPlainFromRichHtml($competenze_raggiunte),
        'contenuti_trattati' => programmaSvoltoPlainFromRichHtml($contenuti_trattati),
        'abilita' => programmaSvoltoPlainFromRichHtml($abilita_quinta),
        'competenze_raggiunte_html' => $competenze_raggiunte,
        'contenuti_trattati_html' => $contenuti_trattati,
        'abilita_html' => $abilita_quinta,
    ], JSON_UNESCAPED_UNICODE);
}

$titolo_sql = dbEscape($titolo);
$contenuto_sql = dbEscape($contenuto);

date_default_timezone_set("Europe/Rome");
$update = date("Y-m-d H-i-s");
$id_utente = programmiUtenteAutoreDaProgrammaSvolto($id_programma, intval($__utente_id));

if ($id > 0) {
    $query = "UPDATE programmi_svolti_moduli
        SET id_programma = '$id_programma',
            id_utente = '$id_utente',
            ordine = '$ordine',
            nome = '$titolo_sql',
            contenuto = '$contenuto_sql',
            updated = '$update'
        WHERE id = '$id'";
    dbExec($query);
    info("aggiornato programma svolto modulo id=$id id_programma=$id_programma id_utente=$id_utente updated=$update");
} else {
    $query = "INSERT INTO programmi_svolti_moduli(id_programma,ordine,nome,contenuto,id_utente,updated)
        VALUES('$id_programma', '$ordine', '$titolo_sql', '$contenuto_sql','$id_utente','$update')";
    dbExec($query);
    $id = dblastId();
    info("aggiunto programma svolto modulo id=$id  id_programma=$id_programma id_utente=$id_utente updated=$update");
}
?>
