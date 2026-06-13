<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/vendor/autoload.php';
require_once '../common/programmiPubbliciLib.php';

ruoloRichiesto('docente', 'dirigente', 'segreteria-didattica', 'studente', 'genitore');

$programId = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : -1;
$classId = isset($_REQUEST['class_id']) ? (int) $_REQUEST['class_id'] : -1;
$annoScolasticoId = isset($_REQUEST['anno_scolastico_id']) ? (int) $_REQUEST['anno_scolastico_id'] : -1;
$doPrint = isset($_REQUEST['print']) && ($_REQUEST['print'] == '1' || $_REQUEST['print'] === 'true');
$format = isset($_REQUEST['format']) ? strtolower((string)$_REQUEST['format']) : 'pdf';
$titolo = isset($_REQUEST['titolo']) ? $_REQUEST['titolo'] : 'Programma didattico';
$viewScope = isset($_REQUEST['view_scope']) ? strtolower((string)$_REQUEST['view_scope']) : 'full';
$soloProgrammaCorrente = ($viewScope === 'own' || $viewScope === 'solo');
$anno_scolastico_corrente_anno_safe = $GLOBALS['__anno_scolastico_corrente_anno'] ?? '';

if ($programId <= 0 && !($format === 'docx_classe' && $classId > 0 && $annoScolasticoId > 0)) {
    exit;
}

function programmiSvoltiHasProgramField(string $columnName): bool
{
    static $cache = [];
    if (!array_key_exists($columnName, $cache)) {
        $row = dbGetFirst("SHOW COLUMNS FROM programmi_svolti LIKE '" . dbEscape($columnName) . "'");
        $cache[$columnName] = ($row != null);
    }

    return $cache[$columnName];
}

function programmaSvoltoLooksLikeHtml(string $text): bool
{
    return preg_match('/<\/?(p|br|ul|ol|li|strong|b|em|i|u|h4|h5|blockquote)\b/i', $text) === 1;
}

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
    $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><u><h4><h5><blockquote>');
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

function renderProgrammaSvoltoRichHtmlForPrint(string $html): string
{
    $html = sanitizeProgrammaSvoltoRichHtml($html);
    if ($html === '') {
        return '&nbsp;';
    }

    return renderProgrammaSvoltoRichHtmlAsCompactPdfLines($html);
}

function renderProgrammaSvoltoRichHtmlAsCompactPdfLines(string $html): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('div')->item(0);
    if (!$root instanceof DOMElement) {
        return '&nbsp;';
    }

    $out = '';
    $appendInline = function (DOMNode $node) use (&$appendInline): string {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES, 'UTF-8');
        }
        if (!$node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $appendInline($child);
        }

        if ($tag === 'strong' || $tag === 'b') {
            return '<b>' . $inner . '</b>';
        }
        if ($tag === 'em' || $tag === 'i') {
            return '<i>' . $inner . '</i>';
        }
        if ($tag === 'u') {
            return '<u>' . $inner . '</u>';
        }
        if ($tag === 'br') {
            return '<br>';
        }
        return $inner;
    };

    $walk = function (DOMNode $container, int $level = 0) use (&$walk, &$out, $appendInline): void {
        $inlineBuffer = '';
        $flushInlineBuffer = function () use (&$inlineBuffer, &$out): void {
            $text = trim($inlineBuffer);
            if ($text !== '') {
                $out .= '<span style="font-size:10px;line-height:1.08;">' . $text . '</span><br>';
            }
            $inlineBuffer = '';
        };

        foreach ($container->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                if (trim($node->nodeValue ?? '') !== '') {
                    $inlineBuffer .= htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES, 'UTF-8');
                }
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, ['strong', 'b', 'em', 'i', 'u', 'span', 'br'], true)) {
                $inlineBuffer .= $appendInline($node);
                continue;
            }

            $flushInlineBuffer();
            if ($tag === 'h5') {
                $title = mb_strtoupper(trim(html_entity_decode(strip_tags($node->textContent ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-8');
                if ($title !== '') {
                    $out .= '<span style="font-size:3px;line-height:3px;"><br></span>'
                        . '<table width="98%" border="0" cellpadding="2" cellspacing="0" style="margin:0;width:98%;">'
                        . '<tr><td style="background-color:#c8d0da;text-align:center;font-size:10px;font-weight:bold;color:#173f68;line-height:1.05;">'
                        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                        . '</td></tr></table>'
                        . '<span style="font-size:3px;line-height:3px;"><br></span>';
                }
                continue;
            }
            if ($tag === 'h4') {
                $title = mb_strtoupper(trim(html_entity_decode(strip_tags($node->textContent ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-8');
                if ($title !== '') {
                    $out .= '<span style="font-size:2px;line-height:2px;"><br></span>'
                        . '<table width="98%" border="0" cellpadding="0" cellspacing="0" style="margin:0;width:98%;">'
                        . '<tr>'
                        . '<td width="4%" style="font-size:10.5px;line-height:1;">&nbsp;</td>'
                        . '<td width="94%" style="font-size:10.5px;font-weight:bold;color:#173f68;line-height:1;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>'
                        . '</tr>'
                        . '</table>'
                        . '<span style="font-size:2px;line-height:2px;"><br></span>';
                }
                continue;
            }

            if ($tag === 'p') {
                $text = trim($appendInline($node));
                if ($text !== '') {
                    $out .= '<span style="font-size:10px;line-height:1.08;">' . $text . '</span><br>';
                }
                continue;
            }

            if ($tag === 'ul' || $tag === 'ol') {
                $index = 1;
                $type = strtolower((string)$node->getAttribute('type'));
                foreach ($node->childNodes as $li) {
                    if (!$li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                        continue;
                    }
                    if ($tag === 'ol') {
                        if ($type === 'a') {
                            $prefix = chr(ord('a') + (($index - 1) % 26)) . '.';
                        } elseif ($type === 'A') {
                            $prefix = chr(ord('A') + (($index - 1) % 26)) . '.';
                        } else {
                            $prefix = $index . '.';
                        }
                        $index++;
                    } else {
                        $prefix = '&bull;';
                    }

                    $item = '';
                    foreach ($li->childNodes as $child) {
                        if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                            continue;
                        }
                        $item .= $appendInline($child);
                    }
                    $item = trim($item);
                    if ($item !== '') {
                        if ($level > 0) {
                            $nestedPrefix = $tag === 'ol' ? $prefix : '&bull;';
                            $out .= '<table width="98%" border="0" cellpadding="0" cellspacing="0" style="margin:0;width:98%;">'
                                . '<tr>'
                                . '<td width="13%" style="font-size:10px;line-height:1.08;text-align:right;">' . $nestedPrefix . '&nbsp;&nbsp;</td>'
                                . '<td width="83%" style="font-size:10px;line-height:1.08;">' . $item . '</td>'
                                . '</tr>'
                                . '</table>';
                            foreach ($li->childNodes as $child) {
                                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                                    $walk($child, $level + 1);
                                }
                            }
                            continue;
                        }
                        $leftWidth = $level > 0 ? '12%' : '9%';
                        $rightWidth = $level > 0 ? '84%' : '87%';
                        $out .= '<table width="98%" border="0" cellpadding="0" cellspacing="0" style="margin:0;width:98%;">'
                            . '<tr>'
                            . '<td width="' . $leftWidth . '" style="font-size:10px;line-height:1.08;text-align:right;">' . $prefix . '&nbsp;&nbsp;</td>'
                            . '<td width="' . $rightWidth . '" style="font-size:10px;line-height:1.08;">' . $item . '</td>'
                            . '</tr>'
                            . '</table>';
                    }
                    foreach ($li->childNodes as $child) {
                        if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                            $walk($child, $level + 1);
                        }
                    }
                }
                continue;
            }

            if ($tag === 'blockquote') {
                $walk($node, $level + 1);
                continue;
            }

            $walk($node, $level);
        }

        $flushInlineBuffer();
    };

    $walk($root);
    return trim($out) !== '' ? $out : '&nbsp;';
}

function renderProgrammaSvoltoRichHtmlForPrintLegacy(string $html): string
{
    $html = preg_replace('/<p>/i', '<p style="margin:0 0 4px 0;line-height:1.35;">', $html);
    $html = preg_replace_callback('/<h4>(.*?)<\/h4>/is', function ($matches) {
        $title = htmlspecialchars(
            mb_strtoupper(trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 'UTF-8'),
            ENT_QUOTES,
            'UTF-8'
        );
        return '<span class="programma-print-title" style="font-size:10.5px;font-weight:bold;color:#173f68;line-height:1;">&nbsp;&nbsp;&nbsp;' . $title . '</span><br>';
    }, $html);
    $html = renderProgrammaPdfListsAsCompactRows($html);
    $html = preg_replace('/<blockquote>/i', '<blockquote style="margin:0 0 4px 18px;padding-left:8px;border-left:2px solid #c9d8e8;line-height:1.3;">', $html);

    return $html;
}

function renderProgrammaPdfListsAsCompactRows(string $html): string
{
    return preg_replace_callback('/<(ul|ol)([^>]*)>(.*?)<\/\1>/is', function ($matches) {
        $tag = strtolower($matches[1]);
        $attrs = (string)$matches[2];
        $itemsHtml = (string)$matches[3];
        $type = '1';
        if (preg_match('/type=["\']?([aA1])["\']?/i', $attrs, $typeMatch)) {
            $type = $typeMatch[1];
        }

        $index = 1;
        return preg_replace_callback('/<li[^>]*>(.*?)<\/li>/is', function ($itemMatch) use ($tag, $type, &$index) {
            $content = trim((string)$itemMatch[1]);
            $content = renderProgrammaPdfListsAsCompactRows($content);
            if ($tag === 'ol') {
                if ($type === 'a') {
                    $prefix = chr(ord('a') + (($index - 1) % 26)) . '.';
                } elseif ($type === 'A') {
                    $prefix = chr(ord('A') + (($index - 1) % 26)) . '.';
                } else {
                    $prefix = $index . '.';
                }
                $index++;
            } else {
                $prefix = '&bull;';
            }

            return '<table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin:0;width:100%;">'
                . '<tr>'
                . '<td width="5%" style="font-size:10px;line-height:1.08;text-align:right;">' . $prefix . '&nbsp;</td>'
                . '<td width="95%" style="font-size:10px;line-height:1.08;">' . $content . '</td>'
                . '</tr>'
                . '</table>';
        }, $itemsHtml);
    }, $html);
}

function renderProgrammaSvoltoRichHtmlForPreview(string $html): string
{
    $html = sanitizeProgrammaSvoltoRichHtml($html);
    return $html !== '' ? $html : '&nbsp;';
}

function normalizeQuintaInternalMarkersToHtml(string $text): string
{
    $text = normalizeProgrammaSvoltoInternalMarkers($text);
    if (strpos($text, '__MODULE_TITLE__') === false && strpos($text, '__SECTION_HEADING__') === false) {
        return $text;
    }

    $lines = preg_split('/\R/u', $text);
    if ($lines === false) {
        return $text;
    }

    $html = '';
    $plainBuffer = [];

    $flushPlain = function () use (&$html, &$plainBuffer): void {
        $plain = trim(implode("\n", $plainBuffer));
        if ($plain !== '') {
            $html .= buildTwoLevelListFromText($plain);
        }
        $plainBuffer = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim((string)$line);
        if ($trimmed === '') {
            $plainBuffer[] = '';
            continue;
        }

        if (strpos($trimmed, '__MODULE_TITLE__') === 0) {
            $flushPlain();
            $headingText = trim(substr($trimmed, strlen('__MODULE_TITLE__')));
            if ($headingText !== '') {
                $html .= '<h4>' . htmlspecialchars($headingText, ENT_QUOTES, 'UTF-8') . '</h4>';
            }
            continue;
        }

        if (strpos($trimmed, '__SECTION_HEADING__') === 0) {
            $flushPlain();
            $headingText = trim(substr($trimmed, strlen('__SECTION_HEADING__')));
            if ($headingText !== '') {
                $html .= '<h5>' . htmlspecialchars($headingText, ENT_QUOTES, 'UTF-8') . '</h5>';
            }
            continue;
        }

        if (programmaSvoltoLooksLikeHtml($trimmed)) {
            $flushPlain();
            $html .= sanitizeProgrammaSvoltoRichHtml($trimmed);
            continue;
        }

        $plainBuffer[] = $line;
    }

    $flushPlain();
    return trim($html);
}

function getClassiCollegateProgrammaSvolto(int $programId): array
{
    $rows = dbGetAll("
        SELECT c.id, c.classe, c.anno, c.id_primo_indirizzo
        FROM programmi_svolti_classi psc
        INNER JOIN classi c ON c.id = psc.id_classe
        WHERE psc.id_programma_svolto = " . intval($programId) . "
        ORDER BY c.classe ASC
    ");

    return $rows ?: [];
}

function getClassiCollegateLabelProgrammaSvolto(int $programId, string $fallback = ''): string
{
    $classi = getClassiCollegateProgrammaSvolto($programId);
    if (count($classi) === 0) {
        return $fallback;
    }

    $nomi = [];
    foreach ($classi as $classe) {
        $nomi[] = $classe['classe'];
    }

    return implode(' / ', $nomi);
}

function getIndirizziCollegatiLabelProgrammaSvolto(int $programId, string $fallback = ''): string
{
    $rows = dbGetAll("
        SELECT DISTINCT i.nome
        FROM programmi_svolti_classi psc
        INNER JOIN classi c ON c.id = psc.id_classe
        INNER JOIN indirizzo i ON i.id = c.id_primo_indirizzo
        WHERE psc.id_programma_svolto = " . intval($programId) . "
        ORDER BY i.nome ASC
    ");

    if ($rows == null || count($rows) === 0) {
        return $fallback;
    }

    $nomi = [];
    foreach ($rows as $row) {
        $nomi[] = $row['nome'];
    }

    return implode(' / ', $nomi);
}

function getProgrammaSvoltoById(int $programId): ?array
{
    $metodologieSql = programmiSvoltiHasProgramField('metodologie') ? "programmi_svolti.metodologie AS programma_metodologie," : "'' AS programma_metodologie,";
    $criteriSql = programmiSvoltiHasProgramField('criteri_valutazione') ? "programmi_svolti.criteri_valutazione AS programma_criteri_valutazione," : "'' AS programma_criteri_valutazione,";
    $testiSql = programmiSvoltiHasProgramField('testi_materiali') ? "programmi_svolti.testi_materiali AS programma_testi_materiali," : "'' AS programma_testi_materiali,";
    $query = "SELECT  programmi_svolti.id,
        programmi_svolti.id_materia AS svolti_id_materia,
        programmi_svolti.id_docente AS svolti_id_docente,
        programmi_svolti.id_classe AS svolti_id_classe,
        programmi_svolti.id_anno_scolastico AS anno_scolastico_id,
        $metodologieSql
        $criteriSql
        $testiSql
        materia.id AS materia_id,
        materia.nome AS materia_nome,
        docente.cognome AS doc_cognome,
        docente.nome AS doc_nome,
        docente.id AS doc_id,
        classi.id AS classe_id,
        classi.classe AS classe_nome,
        classi.anno AS classe_anno,
        classi.id_primo_indirizzo AS classe_id_indirizzo,
        indirizzo.nome AS indirizzo_nome
    FROM programmi_svolti
    INNER JOIN materia materia
        ON materia.id = programmi_svolti.id_materia
    INNER JOIN classi classi
        ON classi.id = programmi_svolti.id_classe
    INNER JOIN docente docente
        ON docente.id = programmi_svolti.id_docente
    INNER JOIN indirizzo indirizzo
        ON indirizzo.id = classi.id_primo_indirizzo
    WHERE programmi_svolti.id = $programId";

    $program = dbGetFirst($query);

    if ($program != null) {
        $program['classi_collegate'] = getClassiCollegateProgrammaSvolto($programId);
        $program['classe_nome_stampa'] = getClassiCollegateLabelProgrammaSvolto($programId, (string)$program['classe_nome']);
        $program['indirizzo_nome_stampa'] = getIndirizziCollegatiLabelProgrammaSvolto($programId, (string)$program['indirizzo_nome']);
    }

    return $program ?: null;
}

function getModuliProgrammaSvolto(int $programId): array
{
    $modules = dbGetAll("SELECT * FROM programmi_svolti_moduli WHERE id_programma = $programId ORDER BY ordine ASC");
    return $modules ?: [];
}

function userCanViewProgram(array $program): bool
{
    global $__docente_id, $__studente_id, $__genitore_id;

    if (haRuolo('genitore') || impersonaRuolo('genitore')) {
        if (!programmiPubbliciVisibleForRole('svolti', 'genitore')) {
            return false;
        }
        $publicStudentId = intval($_REQUEST['public_student_id'] ?? 0);
        return programmiPubbliciGenitoreCanAccessStudent(intval($__genitore_id ?? 0), $publicStudentId)
            && programmiPubbliciCanAccessProgram('svolti', intval($program['id'] ?? 0), $publicStudentId);
    }

    if (haRuolo('studente') || impersonaRuolo('studente')) {
        if (!programmiPubbliciVisibleForRole('svolti', 'studente')) {
            return false;
        }
        return programmiPubbliciCanAccessProgram('svolti', intval($program['id'] ?? 0), intval($__studente_id ?? 0));
    }

    if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
        return true;
    }

    if (!impersonaRuolo('docente') || intval($__docente_id ?? 0) <= 0) {
        return false;
    }

    $programDocenteId = intval($program['doc_id'] ?? $program['svolti_id_docente'] ?? 0);
    if ($programDocenteId > 0 && $programDocenteId === intval($__docente_id)) {
        return true;
    }

    if (!getSettingsValue('programmiSvolti', 'coordinatore_vede_programmi_altri_docenti', true)) {
        return false;
    }

    $coord = dbGetFirst("
        SELECT coord.id
        FROM coordinatori coord
        INNER JOIN programmi_svolti_classi psc
            ON psc.id_classe = coord.id_classe
        WHERE coord.id_docente = " . intval($__docente_id) . "
          AND psc.id_programma_svolto = " . intval($program['id'] ?? 0) . "
          AND (
                coord.id_anno_scolastico = " . intval($program['anno_scolastico_id'] ?? 0) . "
                OR coord.id_anno_scolastico IS NULL
                OR coord.id_anno_scolastico = 0
          )
        LIMIT 1
    ");

    return $coord != null;
}

function getProgrammiSvoltiCorrelati(array $program): array
{
    $query = "
        SELECT DISTINCT ps2.id
        FROM programmi_svolti ps2
        INNER JOIN programmi_svolti_classi psc2
            ON psc2.id_programma_svolto = ps2.id
        WHERE ps2.id_materia = " . intval($program['materia_id'] ?? $program['svolti_id_materia'] ?? 0) . "
          AND ps2.id_anno_scolastico = " . intval($program['anno_scolastico_id'] ?? 0) . "
          AND psc2.id_classe IN (
                SELECT psc1.id_classe
                FROM programmi_svolti_classi psc1
                WHERE psc1.id_programma_svolto = " . intval($program['id'] ?? 0) . "
          )
        ORDER BY ps2.id_docente ASC, ps2.id ASC
    ";

    $rows = dbGetAll($query) ?: [];
    $programs = [];
    foreach ($rows as $row) {
        $singleProgram = getProgrammaSvoltoById(intval($row['id'] ?? 0));
        if ($singleProgram != null) {
            $programs[] = $singleProgram;
        }
    }

    return $programs;
}

function mergeSharedProgramLevelQuintaSections(array $baseSections, array $sharedPrograms): array
{
    $sharedKeys = ['metodologie', 'criteri_valutazione', 'testi_materiali'];
    $seen = [];

    foreach ($sharedKeys as $key) {
        $seen[$key] = [];
        $baseSections[$key] = trim((string)($baseSections[$key] ?? ''));
        if ($baseSections[$key] !== '') {
            $seen[$key][md5($baseSections[$key])] = true;
        }
    }

    foreach ($sharedPrograms as $sharedProgram) {
        $programSections = getProgramLevelQuintaSections($sharedProgram);
        foreach ($sharedKeys as $key) {
            $text = trim((string)($programSections[$key] ?? ''));
            if ($text === '') {
                continue;
            }
            $hash = md5($text);
            if (isset($seen[$key][$hash])) {
                continue;
            }
            appendSectionBlock($baseSections[$key], $text);
            $seen[$key][$hash] = true;
        }
    }

    return $baseSections;
}

function userCanExportClasseDocx(int $classId, int $annoScolasticoId): bool
{
    global $__docente_id;

    if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
        return true;
    }

    if (!impersonaRuolo('docente') || intval($__docente_id ?? 0) <= 0) {
        return false;
    }

    $coord = dbGetFirst("SELECT id FROM coordinatori WHERE id_docente=" . intval($__docente_id) . " AND id_classe=" . intval($classId) . " AND (id_anno_scolastico=" . intval($annoScolasticoId) . " OR id_anno_scolastico IS NULL OR id_anno_scolastico=0)");
    if ($coord == null) {
        $coord = dbGetFirst("SELECT id FROM coordinatori WHERE id_docente=" . intval($__docente_id) . " AND id_classe=" . intval($classId) . " LIMIT 1");
    }
    return $coord != null;
}

function getAnnoScolasticoLabel(): string
{
    global $__anno_scolastico_corrente_anno;
    return trim((string)($__anno_scolastico_corrente_anno ?? ''));
}

$program = $programId > 0 ? getProgrammaSvoltoById($programId) : null;
if ($programId > 0 && $program == null) {
    exit;
}

$relatedPrograms = ($programId > 0 && !$soloProgrammaCorrente) ? getProgrammiSvoltiCorrelati($program) : [];
if ($programId > 0 && empty($relatedPrograms) && $program != null) {
    $relatedPrograms = [$program];
}

if ($programId > 0 && $program != null && !userCanViewProgram($program)) {
    http_response_code(403);
    echo 'Non sei autorizzato a visualizzare questo programma';
    exit;
}

$modules = $programId > 0 ? getModuliProgrammaSvolto($programId) : [];

$base64img = 'data:image/png;base64,' . base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'"));
$is_quinta = intval($program['classe_anno'] ?? 0) === 5;
$docentiLabelsProgramma = getDocentiProgrammaLabels($relatedPrograms);
$relatedModulesByProgram = [];
foreach ($relatedPrograms as $singleProgram) {
    $relatedModulesByProgram[] = [
        'program' => $singleProgram,
        'docente_label' => getProgramDocenteLabel($singleProgram),
        'program_sections' => getProgramLevelQuintaSections($singleProgram),
        'modules' => getModuliProgrammaSvolto(intval($singleProgram['id'] ?? 0)),
    ];
}

function rowField(array $row, string $upper, string $lower, $default = '')
{
    if (array_key_exists($upper, $row)) {
        return $row[$upper];
    }
    if (array_key_exists($lower, $row)) {
        return $row[$lower];
    }
    return $default;
}

function isAllUppercase(string $s): bool
{
    $s = trim($s);
    return preg_match('/\p{L}/u', $s) && !preg_match('/\p{Ll}/u', $s);
}

function detectListItemLevel(string $raw, ?int $currentParent): array
{
    $normalized = str_replace("\t", "  ", rtrim($raw));
    $trimmed = ltrim($normalized);

    if (preg_match('/^(?:[•●▪◦◦]\s+|--\s+|>\s+|-\s+|\*\s+)(.+)$/u', $trimmed, $m)) {
        return [
            'level' => ($currentParent !== null ? 1 : 0),
            'text' => trim($m[1]),
        ];
    }

    if (preg_match('/^ {2,}(.+)$/u', $normalized, $m)) {
        return [
            'level' => ($currentParent !== null ? 1 : 0),
            'text' => trim($m[1]),
        ];
    }

    return [
        'level' => 0,
        'text' => trim($trimmed),
    ];
}

function buildTwoLevelListFromText(string $text, bool $forPdf = false): string
{
    if (strpos($text, '__MODULE_TITLE__') !== false || strpos($text, '__SECTION_HEADING__') !== false) {
        $normalized = normalizeQuintaInternalMarkersToHtml($text);
        if ($normalized !== '' && programmaSvoltoLooksLikeHtml($normalized)) {
            return $forPdf ? renderProgrammaSvoltoRichHtmlForPrint($normalized) : renderProgrammaSvoltoRichHtmlForPreview($normalized);
        }
    }

    if (programmaSvoltoLooksLikeHtml($text)) {
        return $forPdf ? renderProgrammaSvoltoRichHtmlForPrint($text) : renderProgrammaSvoltoRichHtmlForPreview($text);
    }

    $lines = preg_split('/\R/u', $text);
    $tree = [];
    $currentParent = null;
    $nextIsChild = false;

    foreach ($lines as $line) {
        $rawLine = rtrim($line);
        if ($rawLine === '') {
            $nextIsChild = false;
            continue;
        }

        $literalDotMap = [];
        $rawLine = preg_replace_callback('/(?:\b\p{L}\.){2,}/u', function ($matches) use (&$literalDotMap) {
            $token = '__GESTORE_LITERAL_ABBR_' . count($literalDotMap) . '__';
            $literalDotMap[$token] = $matches[0];
            return $token;
        }, $rawLine);
        $rawLine = preg_replace_callback('/\.{2,}/u', function ($matches) use (&$literalDotMap) {
            $token = '__GESTORE_LITERAL_DOTS_' . count($literalDotMap) . '__';
            $literalDotMap[$token] = $matches[0] === '..' ? '.' : $matches[0];
            return $token;
        }, $rawLine);
        $segments = preg_split('/(?<!\.)\.(?!\.)\s*/u', $rawLine);

        foreach ($segments as $segment) {
            $raw = trim(strtr($segment, $literalDotMap));
            if ($raw === '') {
                continue;
            }

            if (strpos($raw, '__SECTION_HEADING__') === 0) {
                $headingText = trim(substr($raw, strlen('__SECTION_HEADING__')));
                if ($headingText !== '') {
                    $tree[] = ['type' => 'section_heading', 'text' => $headingText, 'children' => []];
                }
                $currentParent = null;
                $nextIsChild = false;
                continue;
            }

            if (strpos($raw, '__MODULE_TITLE__') === 0) {
                $headingText = trim(substr($raw, strlen('__MODULE_TITLE__')));
                if ($headingText !== '') {
                    $tree[] = ['type' => 'module_title', 'text' => $headingText, 'children' => []];
                }
                $currentParent = null;
                $nextIsChild = false;
                continue;
            }

            if (preg_match('/^>>\s*(.+)$/u', $raw, $hm)) {
                $headingText = trim($hm[1]);
                $headingText = preg_replace('/[.;:]\s*$/u', '', $headingText);
                $tree[] = ['type' => 'heading', 'text' => $headingText, 'children' => []];
                $currentParent = null;
                $nextIsChild = false;
                continue;
            }

            if (isAllUppercase($raw) && mb_strlen($raw, 'UTF-8') <= 90) {
                $raw = preg_replace('/[.;:]\s*$/u', '', $raw);
                $tree[] = ['type' => 'heading', 'text' => $raw, 'children' => []];
                $currentParent = null;
                continue;
            }

            $raw = preg_replace('/;\s*$/u', '', $raw);
            $endsWithColon = preg_match('/:\s*$/u', $raw) === 1;
            $detected = detectListItemLevel($raw, $currentParent);
            $textLi = $detected['text'];
            $level = $detected['level'];

            if ($nextIsChild && $level === 0 && $currentParent !== null) {
                $level = 1;
                $nextIsChild = false;
            }

            if ($level === 0) {
                $tree[] = ['text' => $textLi, 'children' => []];
                $currentParent = count($tree) - 1;
            } else {
                if ($currentParent === null) {
                    $tree[] = ['text' => '', 'children' => []];
                    $currentParent = count($tree) - 1;
                }
                $tree[$currentParent]['children'][] = ['text' => $textLi, 'children' => []];
            }

            if ($endsWithColon) {
                $nextIsChild = true;
            }
        }
    }

    return renderTwoLevelList($tree);
}

function renderTwoLevelList(array $nodes): string
{
    if (empty($nodes)) {
        return '';
    }

    $html = '';
    $ulOpen = false;
    $sectionHeadingCount = 0;

    foreach ($nodes as $n) {
        $type = $n['type'] ?? 'item';

        if ($type === 'heading') {
            if ($ulOpen) {
                $html .= '</ul>';
                $ulOpen = false;
            }
            $html .= '<p><strong>' . htmlspecialchars($n['text'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong></p>';
            continue;
        }

        if ($type === 'module_title') {
            if ($ulOpen) {
                $html .= '</ul>';
                $ulOpen = false;
            }
            $html .= '<p style="font-weight:bold;margin:4px 0 3px 0;">&nbsp;&nbsp;&nbsp;' . htmlspecialchars($n['text'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>';
            continue;
        }

        if ($type === 'section_heading') {
            if ($ulOpen) {
                $html .= '</ul>';
                $ulOpen = false;
            }
            $sectionHeadingCount++;
            $extraSpace = $sectionHeadingCount > 1 ? '<div style="height:3mm;"></div>' : '';
            $html .= '<table width="100%" border="0" cellpadding="2" cellspacing="0"><tr><td style="background-color:#c7d0da;text-align:center;font-weight:bold;">' . htmlspecialchars($n['text'] ?? '', ENT_QUOTES, 'UTF-8') . '</td></tr></table>' . $extraSpace;
            continue;
        }

        if (!$ulOpen) {
            $html .= '<ul>';
            $ulOpen = true;
        }

        $html .= '<li>' . htmlspecialchars($n['text'] ?? '', ENT_QUOTES, 'UTF-8');
        if (!empty($n['children'])) {
            $html .= '<ul>';
            foreach ($n['children'] as $c) {
                $html .= '<li>' . htmlspecialchars($c['text'] ?? '', ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</li>';
    }

    if ($ulOpen) {
        $html .= '</ul>';
    }

    return $html;
}

function decodeQuintaModulo(array $module): array
{
    $contenuto = (string)rowField($module, 'CONTENUTO', 'contenuto', '');
    $decoded = json_decode($contenuto, true);

    if (is_array($decoded) && (($decoded['schema'] ?? '') === 'programma_svolto_quinta_v1' || ($decoded['schema'] ?? '') === 'programma_svolto_quinta_v2')) {
        return [
            'competenze_raggiunte' => (string)($decoded['competenze_raggiunte_html'] ?? $decoded['competenze_raggiunte'] ?? ''),
            'contenuti_trattati' => (string)($decoded['contenuti_trattati_html'] ?? $decoded['contenuti_trattati'] ?? ''),
            'abilita' => (string)($decoded['abilita_html'] ?? $decoded['abilita'] ?? ''),
        ];
    }

    return [
        'competenze_raggiunte' => '',
        'contenuti_trattati' => $contenuto,
        'abilita' => '',
    ];
}

function getProgramLevelQuintaSections(array $program): array
{
    return [
        'metodologie' => trim((string)($program['programma_metodologie'] ?? '')),
        'criteri_valutazione' => trim((string)($program['programma_criteri_valutazione'] ?? '')),
        'testi_materiali' => trim((string)($program['programma_testi_materiali'] ?? '')),
    ];
}

function renderProgrammaSvoltoPlainTextAsRichHtml(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $text = normalizeProgrammaSvoltoInternalMarkers($text);
    if (programmaSvoltoLooksLikeHtml($text)) {
        return sanitizeProgrammaSvoltoRichHtml($text);
    }

    $paragraphs = preg_split('/\R{2,}/u', $text) ?: [$text];
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $lines = preg_split('/\R/u', $paragraph) ?: [$paragraph];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/(?<!\.)\.\.(?!\.)/u', '.', $line);
            $html .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }

    return $html;
}

function concatSectionText(array &$sections, string $key, string $moduleTitle, string $value): void
{
    $value = trim($value);
    if ($value === '') {
        return;
    }

    if (programmaSvoltoLooksLikeHtml($value)) {
        $block = trim($moduleTitle) !== ''
            ? '<h4>' . htmlspecialchars(mb_strtoupper(trim($moduleTitle), 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</h4>' . sanitizeProgrammaSvoltoRichHtml($value)
            : sanitizeProgrammaSvoltoRichHtml($value);
    } else {
        $block = trim($moduleTitle) !== ''
            ? '<h4>' . htmlspecialchars(mb_strtoupper(trim($moduleTitle), 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</h4>' . renderProgrammaSvoltoPlainTextAsRichHtml($value)
            : renderProgrammaSvoltoPlainTextAsRichHtml($value);
    }
    if ($sections[$key] !== '') {
        $sections[$key] .= "\n\n";
    }
    $sections[$key] .= $block;
}

function getEmptyQuintaSections(): array
{
    return [
        'competenze_raggiunte' => '',
        'contenuti_trattati' => '',
        'abilita' => '',
        'metodologie' => '',
        'criteri_valutazione' => '',
        'testi_materiali' => '',
    ];
}

function getProgramDocenteLabel(array $program): string
{
    return trim((string)$program['doc_cognome'] . ' ' . (string)$program['doc_nome']);
}

function getDocentiProgrammaLabels(array $programs): array
{
    $docenti = [];
    foreach ($programs as $singleProgram) {
        $docenteLabel = getProgramDocenteLabel($singleProgram);
        if ($docenteLabel !== '') {
            $docenti[] = $docenteLabel;
        }
    }

    return array_values(array_unique($docenti));
}

function wrapSectionForDocente(string $text, string $docenteLabel): string
{
    $text = trim($text);
    $docenteLabel = trim($docenteLabel);
    if ($text === '') {
        return '';
    }
    if ($docenteLabel === '') {
        return $text;
    }

    if (programmaSvoltoLooksLikeHtml($text)) {
        return '<h5>' . htmlspecialchars(mb_strtoupper($docenteLabel, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</h5>' . sanitizeProgrammaSvoltoRichHtml($text);
    }

    return '__SECTION_HEADING__' . mb_strtoupper($docenteLabel, 'UTF-8') . "\n" . $text;
}

function appendSectionBlock(string &$target, string $block): void
{
    $block = trim($block);
    if ($block === '') {
        return;
    }
    if (trim($target) !== '') {
        $target .= "\n\n";
    }
    $target .= $block;
}

function mergeQuintaSections(array &$target, array $source, string $docenteLabel = '', array $keysWithoutDocenteLabel = []): void
{
    foreach ($target as $key => $value) {
        if (!array_key_exists($key, $source)) {
            continue;
        }
        $label = in_array($key, $keysWithoutDocenteLabel, true) ? '' : $docenteLabel;
        appendSectionBlock($target[$key], wrapSectionForDocente((string)$source[$key], $label));
    }
}

function buildQuintaSections(array $modules): array
{
    $sections = getEmptyQuintaSections();

    foreach ($modules as $module) {
        $decoded = decodeQuintaModulo($module);
        $moduleTitle = (string)rowField($module, 'NOME', 'nome', '');

        concatSectionText($sections, 'competenze_raggiunte', $moduleTitle, $decoded['competenze_raggiunte']);
        concatSectionText($sections, 'contenuti_trattati', $moduleTitle, $decoded['contenuti_trattati']);
        concatSectionText($sections, 'abilita', $moduleTitle, $decoded['abilita']);
    }

    return $sections;
}

function buildQuintaMergedSectionsForPrograms(array $programs): array
{
    $sections = getEmptyQuintaSections();

    foreach ($programs as $singleProgram) {
        $singleSections = buildQuintaSections(getModuliProgrammaSvolto(intval($singleProgram['id'] ?? 0)));
        mergeQuintaSections($sections, $singleSections, count($programs) > 1 ? getProgramDocenteLabel($singleProgram) : '');
        mergeQuintaSections(
            $sections,
            getProgramLevelQuintaSections($singleProgram),
            '',
            ['metodologie', 'criteri_valutazione', 'testi_materiali']
        );
    }

    return $sections;
}

function buildQuintaWordStyleRows(array $sections, bool $forPdf = false): array
{
    return [
        'Competenze raggiunte' => buildTwoLevelListFromText((string)($sections['competenze_raggiunte'] ?? ''), $forPdf),
        'Conoscenze o contenuti trattati' => buildTwoLevelListFromText((string)($sections['contenuti_trattati'] ?? ''), $forPdf),
        "Abilita'" => buildTwoLevelListFromText((string)($sections['abilita'] ?? ''), $forPdf),
        'Metodologie' => buildTwoLevelListFromText((string)($sections['metodologie'] ?? ''), $forPdf),
        'Criteri di valutazione' => buildTwoLevelListFromText((string)($sections['criteri_valutazione'] ?? ''), $forPdf),
        'Testi e materiali / strumenti adottati' => buildTwoLevelListFromText((string)($sections['testi_materiali'] ?? ''), $forPdf),
    ];
}

function normalizeQuintaSectionsForDocx(array $sections): array
{
    foreach ($sections as $key => $value) {
        $text = trim((string)$value);
        $sections[$key] = $text === '' ? '' : buildTwoLevelListFromText($text, false);
    }

    return $sections;
}

function formatQuintaPdfLabel(string $label): string
{
    $escaped = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    if ($label === 'Conoscenze o contenuti trattati') {
        return '&nbsp;&nbsp;Conoscenze o contenuti<br>&nbsp;&nbsp;trattati';
    }

    return '&nbsp;&nbsp;' . $escaped;
}

function buildQuintaModuleRows(array $module, bool $forPdf = false): array
{
    $decoded = decodeQuintaModulo($module);

    return [
        'Conoscenze o contenuti trattati' => buildTwoLevelListFromText($decoded['contenuti_trattati'], $forPdf),
        "Abilita'" => buildTwoLevelListFromText($decoded['abilita'], $forPdf),
        'Competenze raggiunte' => buildTwoLevelListFromText($decoded['competenze_raggiunte'], $forPdf),
    ];
}

function addProgrammaPdfCellBreathingRoom(string $html): string
{
    return $html;
}

function buildParagraphHtmlFromText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '&nbsp;';
    }

    $parts = preg_split('/\R/u', $text);
    $html = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $html .= '<p style="margin:0 0 4px 0;">' . nl2br(htmlspecialchars($part, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    return $html !== '' ? $html : '&nbsp;';
}

function normalizeWordTextNodeValue(string $text): string
{
    $text = preg_replace('/&nbsp(?!;)/i', '&nbsp;', $text) ?? $text;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;

    return $text;
}

function createWordRunXml(DOMDocument $dom, string $text, bool $bold = false, bool $italic = false, bool $underline = false, int $size = 22, string $color = ''): DOMElement
{
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $text = normalizeWordTextNodeValue($text);
    $r = $dom->createElementNS($ns, 'w:r');
    $rPr = $dom->createElementNS($ns, 'w:rPr');
    $rFonts = $dom->createElementNS($ns, 'w:rFonts');
    foreach (['ascii', 'hAnsi', 'cs', 'eastAsia'] as $attribute) {
        $rFonts->setAttributeNS($ns, 'w:' . $attribute, 'Arial');
    }
    $rPr->appendChild($rFonts);
    if ($bold) {
        $rPr->appendChild($dom->createElementNS($ns, 'w:b'));
        $rPr->appendChild($dom->createElementNS($ns, 'w:bCs'));
    }
    if ($italic) {
        $rPr->appendChild($dom->createElementNS($ns, 'w:i'));
        $rPr->appendChild($dom->createElementNS($ns, 'w:iCs'));
    }
    if ($underline) {
        $u = $dom->createElementNS($ns, 'w:u');
        $u->setAttributeNS($ns, 'w:val', 'single');
        $rPr->appendChild($u);
    }
    if ($color !== '') {
        $colorNode = $dom->createElementNS($ns, 'w:color');
        $colorNode->setAttributeNS($ns, 'w:val', $color);
        $rPr->appendChild($colorNode);
    }
    if ($size > 0) {
        $sz = $dom->createElementNS($ns, 'w:sz');
        $sz->setAttributeNS($ns, 'w:val', (string)$size);
        $szCs = $dom->createElementNS($ns, 'w:szCs');
        $szCs->setAttributeNS($ns, 'w:val', (string)$size);
        $rPr->appendChild($sz);
        $rPr->appendChild($szCs);
    }
    $r->appendChild($rPr);

    $t = $dom->createElementNS($ns, 'w:t');
    if (preg_match('/^\s|\s$/u', $text)) {
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
    }
    $t->nodeValue = $text;
    $r->appendChild($t);
    return $r;
}

function appendWordInlineRunsFromHtml(DOMDocument $wordDom, DOMElement $paragraph, DOMNode $node, array $style = []): void
{
    $bold = !empty($style['bold']);
    $italic = !empty($style['italic']);
    $underline = !empty($style['underline']);
    $size = isset($style['size']) ? intval($style['size']) : 22;
    $color = isset($style['color']) ? (string)$style['color'] : '';
    $uppercase = !empty($style['uppercase']);

    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $text = preg_replace('/\s+/u', ' ', $child->nodeValue ?? '');
            if ($uppercase) {
                $text = mb_strtoupper($text, 'UTF-8');
            }
            if ($text !== '') {
                $paragraph->appendChild(createWordRunXml($wordDom, $text, $bold, $italic, $underline, $size, $color));
            }
            continue;
        }

        if (!$child instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($child->tagName);
        if ($tag === 'br') {
            $paragraph->appendChild($wordDom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br'));
            continue;
        }
        if ($tag === 'strong' || $tag === 'b') {
            appendWordInlineRunsFromHtml($wordDom, $paragraph, $child, array_merge($style, ['bold' => true]));
            continue;
        }
        if ($tag === 'em' || $tag === 'i') {
            appendWordInlineRunsFromHtml($wordDom, $paragraph, $child, array_merge($style, ['italic' => true]));
            continue;
        }
        if ($tag === 'u') {
            appendWordInlineRunsFromHtml($wordDom, $paragraph, $child, array_merge($style, ['underline' => true]));
            continue;
        }
        if ($tag === 'ul' || $tag === 'ol') {
            continue;
        }
        appendWordInlineRunsFromHtml($wordDom, $paragraph, $child, $style);
    }
}

function buildWordParagraphFromHtmlElement(DOMDocument $wordDom, DOMElement $element, string $prefix = '', int $level = 0): DOMElement
{
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $paragraph = $wordDom->createElementNS($ns, 'w:p');
    $tag = strtolower($element->tagName);
    $pPr = $wordDom->createElementNS($ns, 'w:pPr');
    $spacing = $wordDom->createElementNS($ns, 'w:spacing');
    $spacing->setAttributeNS($ns, 'w:before', $tag === 'h5' ? '180' : ($tag === 'h4' ? '60' : '0'));
    $spacing->setAttributeNS($ns, 'w:after', $tag === 'h5' ? '140' : ($tag === 'h4' ? '40' : '0'));
    $spacing->setAttributeNS($ns, 'w:line', '240');
    $spacing->setAttributeNS($ns, 'w:lineRule', 'auto');
    $pPr->appendChild($spacing);
    if ($tag === 'h5') {
        $jc = $wordDom->createElementNS($ns, 'w:jc');
        $jc->setAttributeNS($ns, 'w:val', 'center');
        $pPr->appendChild($jc);
        $shd = $wordDom->createElementNS($ns, 'w:shd');
        $shd->setAttributeNS($ns, 'w:val', 'clear');
        $shd->setAttributeNS($ns, 'w:color', 'auto');
        $shd->setAttributeNS($ns, 'w:fill', 'C8D0DA');
        $pPr->appendChild($shd);
    }
    if ($prefix !== '') {
        $ind = $wordDom->createElementNS($ns, 'w:ind');
        $ind->setAttributeNS($ns, 'w:left', (string)(180 * ($level + 1)));
        $ind->setAttributeNS($ns, 'w:hanging', '120');
        $pPr->appendChild($ind);
    } else if ($level > 0) {
        $ind = $wordDom->createElementNS($ns, 'w:ind');
        $ind->setAttributeNS($ns, 'w:left', (string)(360 * $level));
        $pPr->appendChild($ind);
    }
    $paragraph->appendChild($pPr);

    if ($prefix !== '') {
        $paragraph->appendChild(createWordRunXml($wordDom, $prefix, false, false, false, 22));
    }

    appendWordInlineRunsFromHtml($wordDom, $paragraph, $element, [
        'bold' => ($tag === 'h4' || $tag === 'h5' || $tag === 'strong' || $tag === 'b'),
        'italic' => ($tag === 'em' || $tag === 'i'),
        'underline' => ($tag === 'u'),
        'size' => 22,
        'color' => ($tag === 'h4' || $tag === 'h5') ? '173F68' : '',
        'uppercase' => ($tag === 'h4' || $tag === 'h5'),
    ]);

    return $paragraph;
}

function appendProgrammaInlineHtmlToWordBuffer(DOMDocument $htmlDom, DOMNode $node, string &$buffer): void
{
    if ($node->nodeType === XML_TEXT_NODE) {
        $buffer .= htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES, 'UTF-8');
        return;
    }

    if ($node instanceof DOMElement) {
        $buffer .= $htmlDom->saveHTML($node) ?: '';
    }
}

function flushProgrammaInlineWordBuffer(DOMDocument $wordDom, string &$buffer, array &$paragraphs, int $level): void
{
    if (trim(strip_tags($buffer)) === '') {
        $buffer = '';
        return;
    }

    $fakeDom = new DOMDocument();
    libxml_use_internal_errors(true);
    $fakeDom->loadHTML('<?xml encoding="UTF-8"><p>' . $buffer . '</p>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    $p = $fakeDom->getElementsByTagName('p')->item(0);
    if ($p instanceof DOMElement) {
        $paragraphs[] = buildWordParagraphFromHtmlElement($wordDom, $p, '', $level);
    }
    $buffer = '';
}

function buildWordParagraphsFromHtmlXmlLegacy(DOMDocument $wordDom, string $html): array
{
    $cleanHtml = sanitizeProgrammaSvoltoRichHtml($html);
    if ($cleanHtml === '') {
        return [$wordDom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p')];
    }

    $htmlDom = new DOMDocument();
    libxml_use_internal_errors(true);
    $htmlDom->loadHTML('<?xml encoding="UTF-8"><div>' . $cleanHtml . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    $root = $htmlDom->getElementsByTagName('div')->item(0);
    $paragraphs = [];

    $walk = function (DOMNode $container, int $level = 0) use (&$walk, &$paragraphs, $wordDom) {
        foreach ($container->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = trim($node->nodeValue ?? '');
                if ($text !== '') {
                    $fake = new DOMDocument();
                    $p = $fake->createElement('p', $text);
                    $paragraphs[] = buildWordParagraphFromHtmlElement($wordDom, $p, '', $level);
                }
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if ($tag === 'ul' || $tag === 'ol') {
                foreach ($node->childNodes as $li) {
                    if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                        $paragraphs[] = buildWordParagraphFromHtmlElement($wordDom, $li, ($tag === 'ol' ? '• ' : '• '), $level);
                        foreach ($li->childNodes as $child) {
                            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                                $walk($child, $level + 1);
                            }
                        }
                    }
                }
                continue;
            }
            if ($tag === 'blockquote') {
                $walk($node, $level + 1);
                continue;
            }
            if (in_array($tag, ['p', 'h4', 'h5', 'li'], true)) {
                $paragraphs[] = buildWordParagraphFromHtmlElement($wordDom, $node, $tag === 'li' ? '• ' : '', $level);
                continue;
            }
        }
    };

    if ($root instanceof DOMElement) {
        $walk($root, 0);
    }

    return !empty($paragraphs) ? $paragraphs : [$wordDom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p')];
}

function programmaWordListPrefix(DOMElement $list, int $index): string
{
    if (strtolower($list->tagName) !== 'ol') {
        return html_entity_decode('&#8226;', ENT_QUOTES, 'UTF-8') . ' ';
    }

    $type = strtolower((string)$list->getAttribute('type'));
    if ($type === 'a') {
        return chr(ord('a') + (($index - 1) % 26)) . '. ';
    }

    return $index . '. ';
}

function buildWordParagraphsFromHtmlXml(DOMDocument $wordDom, string $html): array
{
    $html = normalizeQuintaInternalMarkersToHtml($html);
    $cleanHtml = sanitizeProgrammaSvoltoRichHtml($html);
    if ($cleanHtml === '') {
        return [$wordDom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p')];
    }

    $htmlDom = new DOMDocument();
    libxml_use_internal_errors(true);
    $htmlDom->loadHTML('<?xml encoding="UTF-8"><div>' . $cleanHtml . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    $root = $htmlDom->getElementsByTagName('div')->item(0);
    $paragraphs = [];

    $walk = function (DOMNode $container, int $level = 0) use (&$walk, &$paragraphs, $wordDom, $htmlDom) {
        $inlineBuffer = '';
        foreach ($container->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                if (trim($node->nodeValue ?? '') !== '') {
                    appendProgrammaInlineHtmlToWordBuffer($htmlDom, $node, $inlineBuffer);
                }
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, ['strong', 'b', 'em', 'i', 'u', 'span', 'br'], true)) {
                appendProgrammaInlineHtmlToWordBuffer($htmlDom, $node, $inlineBuffer);
                continue;
            }

            flushProgrammaInlineWordBuffer($wordDom, $inlineBuffer, $paragraphs, $level);
            if ($tag === 'ul' || $tag === 'ol') {
                $listIndex = 1;
                foreach ($node->childNodes as $li) {
                    if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                        $paragraphs[] = buildWordParagraphFromHtmlElement($wordDom, $li, programmaWordListPrefix($node, $listIndex), $level);
                        $listIndex++;
                        foreach ($li->childNodes as $child) {
                            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                                $walk($child, $level + 1);
                            }
                        }
                    }
                }
                continue;
            }
            if ($tag === 'blockquote') {
                $walk($node, $level + 1);
                continue;
            }
            if (in_array($tag, ['p', 'h4', 'h5'], true)) {
                $paragraphs[] = buildWordParagraphFromHtmlElement($wordDom, $node, '', $level);
            }
        }
        flushProgrammaInlineWordBuffer($wordDom, $inlineBuffer, $paragraphs, $level);
    };

    if ($root instanceof DOMElement) {
        $walk($root, 0);
    }

    return !empty($paragraphs) ? $paragraphs : [$wordDom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p')];
}

function buildWordParagraphsXml(DOMDocument $dom, string $text): array
{
    if (strpos($text, '__MODULE_TITLE__') !== false || strpos($text, '__SECTION_HEADING__') !== false) {
        $normalizedText = normalizeQuintaInternalMarkersToHtml($text);
        if ($normalizedText !== '' && programmaSvoltoLooksLikeHtml($normalizedText)) {
            return buildWordParagraphsFromHtmlXml($dom, $normalizedText);
        }
    }

    if (programmaSvoltoLooksLikeHtml($text)) {
        return buildWordParagraphsFromHtmlXml($dom, $text);
    }

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $paragraphs = [];
    $lines = preg_split('/\R/u', trim($text));

    $nonEmptyLines = array_filter($lines ?: [], function ($line) {
        return trim((string)$line) !== '';
    });

    if ($lines === false || count($nonEmptyLines) === 0) {
        $paragraphs[] = $dom->createElementNS($ns, 'w:p');
        return $paragraphs;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $isModuleTitle = false;
        $isSectionHeading = false;
        if (strpos($line, '__MODULE_TITLE__') === 0) {
            $isModuleTitle = true;
            $line = trim(substr($line, strlen('__MODULE_TITLE__')));
        } else if (strpos($line, '__SECTION_HEADING__') === 0) {
            $isSectionHeading = true;
            $line = trim(substr($line, strlen('__SECTION_HEADING__')));
        }
        $line = preg_replace('/(?<!\.)\.\.(?!\.)/u', '.', $line);

        $p = $dom->createElementNS($ns, 'w:p');
        $pPr = $dom->createElementNS($ns, 'w:pPr');
        $spacing = $dom->createElementNS($ns, 'w:spacing');
        $spacing->setAttributeNS($ns, 'w:before', $isSectionHeading ? '160' : '0');
        $spacing->setAttributeNS($ns, 'w:after', $isSectionHeading ? '100' : '0');
        $pPr->appendChild($spacing);

        if ($isSectionHeading) {
            $jc = $dom->createElementNS($ns, 'w:jc');
            $jc->setAttributeNS($ns, 'w:val', 'center');
            $pBdr = $dom->createElementNS($ns, 'w:pBdr');
            foreach (['top', 'left', 'bottom', 'right'] as $side) {
                $border = $dom->createElementNS($ns, 'w:' . $side);
                $border->setAttributeNS($ns, 'w:val', 'single');
                $border->setAttributeNS($ns, 'w:sz', '8');
                $border->setAttributeNS($ns, 'w:space', '1');
                $border->setAttributeNS($ns, 'w:color', '7F9DB9');
                $pBdr->appendChild($border);
            }
            $shd = $dom->createElementNS($ns, 'w:shd');
            $shd->setAttributeNS($ns, 'w:val', 'clear');
            $shd->setAttributeNS($ns, 'w:color', 'auto');
            $shd->setAttributeNS($ns, 'w:fill', 'EAF3FB');
            $pPr->appendChild($jc);
            $pPr->appendChild($pBdr);
            $pPr->appendChild($shd);
        }
        $p->appendChild($pPr);
        $r = $dom->createElementNS($ns, 'w:r');
        if ($isModuleTitle || $isSectionHeading) {
            $rPr = $dom->createElementNS($ns, 'w:rPr');
            $bold = $dom->createElementNS($ns, 'w:b');
            $boldCs = $dom->createElementNS($ns, 'w:bCs');
            if ($isSectionHeading) {
                $size = $dom->createElementNS($ns, 'w:sz');
                $size->setAttributeNS($ns, 'w:val', '24');
                $sizeCs = $dom->createElementNS($ns, 'w:szCs');
                $sizeCs->setAttributeNS($ns, 'w:val', '24');
                $rPr->appendChild($size);
                $rPr->appendChild($sizeCs);
            }
            $rPr->appendChild($bold);
            $rPr->appendChild($boldCs);
            $r->appendChild($rPr);
        }
        $t = $dom->createElementNS($ns, 'w:t');
        if (preg_match('/^\s|\s$/', $line)) {
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }
        $t->nodeValue = $line;
        $r->appendChild($t);
        $p->appendChild($r);
        $paragraphs[] = $p;
    }

    return $paragraphs;
}

function setWordParagraphText(DOMDocument $dom, DOMXPath $xpath, string $query, string $text): void
{
    $paragraph = $xpath->query($query)->item(0);
    if (!$paragraph instanceof DOMElement) {
        return;
    }

    $childrenToRemove = [];
    foreach ($paragraph->childNodes as $childNode) {
        if ($childNode->nodeType === XML_ELEMENT_NODE && $childNode->localName === 'pPr') {
            continue;
        }
        $childrenToRemove[] = $childNode;
    }
    foreach ($childrenToRemove as $childNode) {
        $paragraph->removeChild($childNode);
    }

    $r = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
    $t = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
    $t->nodeValue = $text;
    $r->appendChild($t);
    $paragraph->appendChild($r);
}

function setWordTableCellStyle(DOMDocument $dom, DOMElement $cell, string $fill = '', string $borderColor = '0057B7', string $vAlign = 'top', string $width = ''): void
{
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $tcPr = getWordChildByLocalName($cell, 'tcPr');
    if (!$tcPr instanceof DOMElement) {
        $tcPr = $dom->createElementNS($ns, 'w:tcPr');
        if ($cell->firstChild) {
            $cell->insertBefore($tcPr, $cell->firstChild);
        } else {
            $cell->appendChild($tcPr);
        }
    }

    foreach (iterator_to_array($tcPr->childNodes) as $childNode) {
        if ($childNode instanceof DOMElement && in_array($childNode->localName, ['tcBorders', 'shd', 'vAlign', 'tcMar', 'tcW'], true)) {
            $tcPr->removeChild($childNode);
        }
    }

    if ($width !== '') {
        $tcW = $dom->createElementNS($ns, 'w:tcW');
        $tcW->setAttributeNS($ns, 'w:w', $width);
        $tcW->setAttributeNS($ns, 'w:type', 'pct');
        $tcPr->appendChild($tcW);
    }

    $borders = $dom->createElementNS($ns, 'w:tcBorders');
    foreach (['top', 'left', 'bottom', 'right'] as $side) {
        $border = $dom->createElementNS($ns, 'w:' . $side);
        $border->setAttributeNS($ns, 'w:val', 'single');
        $border->setAttributeNS($ns, 'w:sz', '8');
        $border->setAttributeNS($ns, 'w:space', '0');
        $border->setAttributeNS($ns, 'w:color', $borderColor);
        $borders->appendChild($border);
    }
    $tcPr->appendChild($borders);

    if ($fill !== '') {
        $shd = $dom->createElementNS($ns, 'w:shd');
        $shd->setAttributeNS($ns, 'w:val', 'clear');
        $shd->setAttributeNS($ns, 'w:color', 'auto');
        $shd->setAttributeNS($ns, 'w:fill', $fill);
        $tcPr->appendChild($shd);
    }

    $vertical = $dom->createElementNS($ns, 'w:vAlign');
    $vertical->setAttributeNS($ns, 'w:val', $vAlign);
    $tcPr->appendChild($vertical);

    $margin = $dom->createElementNS($ns, 'w:tcMar');
    $margins = [
        'top' => '80',
        'left' => '100',
        'bottom' => '60',
        'right' => '120',
    ];
    foreach ($margins as $side => $value) {
        $node = $dom->createElementNS($ns, 'w:' . $side);
        $node->setAttributeNS($ns, 'w:w', $value);
        $node->setAttributeNS($ns, 'w:type', 'dxa');
        $margin->appendChild($node);
    }
    $tcPr->appendChild($margin);
}

function setWordCellText(DOMDocument $dom, DOMElement $cell, string $text, bool $bold = false, string $color = '', int $size = 22, string $align = 'left'): void
{
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $childrenToRemove = [];
    foreach ($cell->childNodes as $childNode) {
        if ($childNode instanceof DOMElement && $childNode->localName === 'tcPr') {
            continue;
        }
        $childrenToRemove[] = $childNode;
    }
    foreach ($childrenToRemove as $childNode) {
        $cell->removeChild($childNode);
    }

    $paragraph = $dom->createElementNS($ns, 'w:p');
    $pPr = $dom->createElementNS($ns, 'w:pPr');
    $spacing = $dom->createElementNS($ns, 'w:spacing');
    $spacing->setAttributeNS($ns, 'w:before', '0');
    $spacing->setAttributeNS($ns, 'w:after', '0');
    $spacing->setAttributeNS($ns, 'w:line', '240');
    $spacing->setAttributeNS($ns, 'w:lineRule', 'auto');
    $pPr->appendChild($spacing);
    if ($align !== 'left') {
        $jc = $dom->createElementNS($ns, 'w:jc');
        $jc->setAttributeNS($ns, 'w:val', $align);
        $pPr->appendChild($jc);
    }
    $paragraph->appendChild($pPr);
    $paragraph->appendChild(createWordRunXml($dom, $text, $bold, false, false, $size, $color));
    $cell->appendChild($paragraph);
}

function getWordChildByLocalName(DOMElement $element, string $localName): ?DOMElement
{
    foreach ($element->childNodes as $childNode) {
        if ($childNode instanceof DOMElement && $childNode->localName === $localName) {
            return $childNode;
        }
    }

    return null;
}

function normalizeWordDocumentFont(string $xml): string
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadXML($xml);

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', $ns);

    foreach ($xpath->query('//w:r') as $run) {
        if (!$run instanceof DOMElement) {
            continue;
        }

        $rPr = getWordChildByLocalName($run, 'rPr');
        if (!$rPr instanceof DOMElement) {
            $rPr = $dom->createElementNS($ns, 'w:rPr');
            if ($run->firstChild) {
                $run->insertBefore($rPr, $run->firstChild);
            } else {
                $run->appendChild($rPr);
            }
        }

        $rFonts = getWordChildByLocalName($rPr, 'rFonts');
        if (!$rFonts instanceof DOMElement) {
            $rFonts = $dom->createElementNS($ns, 'w:rFonts');
            if ($rPr->firstChild) {
                $rPr->insertBefore($rFonts, $rPr->firstChild);
            } else {
                $rPr->appendChild($rFonts);
            }
        }
        foreach (['ascii', 'hAnsi', 'cs', 'eastAsia'] as $attribute) {
            $rFonts->setAttributeNS($ns, 'w:' . $attribute, 'Arial');
        }

        foreach (['sz', 'szCs'] as $sizeTag) {
            $size = getWordChildByLocalName($rPr, $sizeTag);
            if (!$size instanceof DOMElement) {
                $size = $dom->createElementNS($ns, 'w:' . $sizeTag);
                $rPr->appendChild($size);
            }

            $currentSize = intval($size->getAttributeNS($ns, 'val'));
            if ($currentSize <= 20) {
                $size->setAttributeNS($ns, 'w:val', '22');
            }
        }
    }

    return $dom->saveXML();
}

function buildWordProgramHeader(array $lines): array
{
    return array_values(array_filter(array_map('trim', $lines), function ($line) {
        return $line !== '';
    }));
}

function setWordParagraphHeader(DOMDocument $dom, DOMXPath $xpath, string $query, array $lines): void
{
    $paragraph = $xpath->query($query)->item(0);
    if (!$paragraph instanceof DOMElement) {
        return;
    }

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $childrenToRemove = [];
    $pPr = null;
    foreach ($paragraph->childNodes as $childNode) {
        if ($childNode->nodeType === XML_ELEMENT_NODE && $childNode->localName === 'pPr') {
            $pPr = $childNode;
            continue;
        }
        $childrenToRemove[] = $childNode;
    }
    foreach ($childrenToRemove as $childNode) {
        $paragraph->removeChild($childNode);
    }

    if (!$pPr instanceof DOMElement) {
        $pPr = $dom->createElementNS($ns, 'w:pPr');
        if ($paragraph->firstChild) {
            $paragraph->insertBefore($pPr, $paragraph->firstChild);
        } else {
            $paragraph->appendChild($pPr);
        }
    }

    foreach (iterator_to_array($pPr->childNodes) as $childNode) {
        if ($childNode->nodeType === XML_ELEMENT_NODE && $childNode->localName === 'jc') {
            $pPr->removeChild($childNode);
        }
    }
    $jc = $dom->createElementNS($ns, 'w:jc');
    $jc->setAttributeNS($ns, 'w:val', 'center');
    $pPr->appendChild($jc);

    $r = $dom->createElementNS($ns, 'w:r');
    $rPr = $dom->createElementNS($ns, 'w:rPr');
    $bold = $dom->createElementNS($ns, 'w:b');
    $boldCs = $dom->createElementNS($ns, 'w:bCs');
    $size = $dom->createElementNS($ns, 'w:sz');
    $size->setAttributeNS($ns, 'w:val', '28');
    $sizeCs = $dom->createElementNS($ns, 'w:szCs');
    $sizeCs->setAttributeNS($ns, 'w:val', '28');
    $rPr->appendChild($bold);
    $rPr->appendChild($boldCs);
    $rPr->appendChild($size);
    $rPr->appendChild($sizeCs);
    $r->appendChild($rPr);

    foreach ($lines as $index => $line) {
        if ($index > 0) {
            $br = $dom->createElementNS($ns, 'w:br');
            $r->appendChild($br);
        }
        $t = $dom->createElementNS($ns, 'w:t');
        $t->nodeValue = $line;
        $r->appendChild($t);
    }

    $paragraph->appendChild($r);
}

function isWordParagraphEmpty(DOMXPath $xpath, DOMElement $paragraph): bool
{
    $text = '';
    foreach ($xpath->query('.//w:t', $paragraph) as $textNode) {
        $text .= $textNode->textContent ?? '';
    }

    return trim($text) === '';
}

function mergeProgrammaSvoltoTemplateTables(DOMDocument $dom, DOMXPath $xpath): ?DOMElement
{
    $tables = [];
    foreach ($xpath->query('//w:body/w:tbl') as $table) {
        if ($table instanceof DOMElement) {
            $tables[] = $table;
        }
    }

    if (count($tables) === 0) {
        return null;
    }
    if (count($tables) === 1) {
        return $tables[0];
    }

    $targetTable = $tables[0];
    foreach (array_slice($tables, 1) as $sourceTable) {
        $node = $targetTable->nextSibling;
        while ($node !== null && !$node->isSameNode($sourceTable)) {
            $next = $node->nextSibling;
            if ($node instanceof DOMElement && $node->localName === 'p' && isWordParagraphEmpty($xpath, $node)) {
                $node->parentNode->removeChild($node);
            }
            $node = $next;
        }

        foreach (iterator_to_array($sourceTable->childNodes) as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->localName === 'tr') {
                $targetTable->appendChild($childNode);
            }
        }
        if ($sourceTable->parentNode instanceof DOMNode) {
            $sourceTable->parentNode->removeChild($sourceTable);
        }
    }

    return $targetTable;
}

function fillWordTemplateXml(string $xml, array $sections, array $intestazioneLines, bool $includeTopTitle = true): string
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadXML($xml);

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    setWordParagraphHeader($dom, $xpath, '(//w:body/w:p)[2]', $intestazioneLines);
    if (!$includeTopTitle) {
        $topTitle = $xpath->query('(//w:body/w:p)[1]')->item(0);
        if ($topTitle instanceof DOMElement && $topTitle->parentNode instanceof DOMNode) {
            $topTitle->parentNode->removeChild($topTitle);
        }
    }

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $table = mergeProgrammaSvoltoTemplateTables($dom, $xpath);
    if ($table instanceof DOMElement) {
        $firstRow = $xpath->query('.//w:tr', $table)->item(0);
        $headerRow = $dom->createElementNS($ns, 'w:tr');
        $headerCell = $dom->createElementNS($ns, 'w:tc');
        $headerPr = $dom->createElementNS($ns, 'w:tcPr');
        $gridSpan = $dom->createElementNS($ns, 'w:gridSpan');
        $gridSpan->setAttributeNS($ns, 'w:val', '2');
        $headerPr->appendChild($gridSpan);
        $headerCell->appendChild($headerPr);
        setWordTableCellStyle($dom, $headerCell, '0057B7', '0057B7', 'top', '5000');
        setWordCellText($dom, $headerCell, 'Programma svolto - classe quinta', false, 'FFFFFF', 32, 'left');
        $headerRow->appendChild($headerCell);
        if ($firstRow instanceof DOMElement) {
            $table->insertBefore($headerRow, $firstRow);
        } else {
            $table->appendChild($headerRow);
        }
    }

    $labels = [
        'Competenze raggiunte',
        'Conoscenze o contenuti trattati',
        "Abilita'",
        'Metodologie',
        'Criteri di valutazione',
        'Testi e materiali / strumenti adottati',
    ];
    $labelIndex = 0;
    foreach ($xpath->query('//w:tbl') as $styleTable) {
        if (!$styleTable instanceof DOMElement) {
            continue;
        }
        foreach ($xpath->query('./w:tr', $styleTable) as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }
            $rowCells = [];
            foreach ($xpath->query('./w:tc', $row) as $rowCell) {
                if ($rowCell instanceof DOMElement) {
                    $rowCells[] = $rowCell;
                }
            }
            if (count($rowCells) < 2 || !isset($labels[$labelIndex])) {
                continue;
            }
            setWordTableCellStyle($dom, $rowCells[0], 'D9EEFA', '0057B7', 'top', '1250');
            setWordTableCellStyle($dom, $rowCells[1], 'F7FBFE', '0057B7', 'top', '3750');
            setWordCellText($dom, $rowCells[0], $labels[$labelIndex], false, '000000', 22, 'left');
            $labelIndex++;
        }
    }

    $cells = $xpath->query('//w:tbl/w:tr/w:tc[position()=2]');
    $values = [
        $sections['competenze_raggiunte'],
        $sections['contenuti_trattati'],
        $sections['abilita'],
        $sections['metodologie'],
        $sections['criteri_valutazione'],
        $sections['testi_materiali'],
    ];

    for ($i = 0; $i < min($cells->length, count($values)); $i++) {
        $cell = $cells->item($i);
        $childrenToRemove = [];
        foreach ($cell->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE && $childNode->localName === 'tcPr') {
                continue;
            }
            $childrenToRemove[] = $childNode;
        }
        foreach ($childrenToRemove as $childNode) {
            $cell->removeChild($childNode);
        }

        foreach (buildWordParagraphsXml($dom, $values[$i]) as $paragraph) {
            $cell->appendChild($paragraph);
        }
    }

    return $dom->saveXML();
}

function buildCombinedWordXml(string $templateXml, array $programmi): string
{
    $templateDom = new DOMDocument();
    $templateDom->preserveWhiteSpace = false;
    $templateDom->formatOutput = false;
    $templateDom->loadXML($templateXml);

    $templateXpath = new DOMXPath($templateDom);
    $templateXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $body = $templateXpath->query('//w:body')->item(0);
    $sectPr = $templateXpath->query('//w:body/w:sectPr')->item(0);
    $blockNodes = [];
    foreach ($body->childNodes as $childNode) {
        if ($sectPr !== null && $childNode->isSameNode($sectPr)) {
            continue;
        }
        $blockNodes[] = $childNode->cloneNode(true);
    }

    while ($body->firstChild) {
        $body->removeChild($body->firstChild);
    }

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($programmi as $index => $item) {
        $filledXml = fillWordTemplateXml($templateXml, $item['sections'], $item['intestazione_lines']);
        $filledDom = new DOMDocument();
        $filledDom->preserveWhiteSpace = false;
        $filledDom->formatOutput = false;
        $filledDom->loadXML($filledXml);
        $filledXpath = new DOMXPath($filledDom);
        $filledXpath->registerNamespace('w', $ns);
        $filledBody = $filledXpath->query('//w:body')->item(0);
        $filledSectPr = $filledXpath->query('//w:body/w:sectPr')->item(0);
        $skipTopTitle = $index > 0;

        foreach ($filledBody->childNodes as $childNode) {
            if ($filledSectPr !== null && $childNode->isSameNode($filledSectPr)) {
                continue;
            }
            if ($skipTopTitle && $childNode->nodeType === XML_ELEMENT_NODE && $childNode->localName === 'p') {
                $skipTopTitle = false;
                continue;
            }
            $body->appendChild($templateDom->importNode($childNode, true));
        }

        if ($index < count($programmi) - 1) {
            $separator = $templateDom->createElementNS($ns, 'w:p');
            $body->appendChild($separator);
        }
    }

    if ($sectPr !== null) {
        $body->appendChild($sectPr->cloneNode(true));
    }

    return $templateDom->saveXML();
}

function getWordTemplatePath()
{
    $baseDir = dirname(__DIR__);
    $candidates = [
        $baseDir . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'template_programma_svolto_quinta.docx',
        $baseDir . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'template_programma_svolto_quinta.zip',
        __DIR__ . DIRECTORY_SEPARATOR . 'template_programma_svolto_quinta.docx',
        __DIR__ . DIRECTORY_SEPARATOR . 'template_programma_svolto_quinta.zip',
        $baseDir . DIRECTORY_SEPARATOR . '.codex_template_quinta.docx',
        $baseDir . DIRECTORY_SEPARATOR . '.codex_template_quinta.zip',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return false;
}

function exportQuintaDocx(array $program, array $sections, array $docentiLabels = []): void
{
    $annoScolasticoLabel = getAnnoScolasticoLabel();
    $templatePath = getWordTemplatePath();
    if ($templatePath === false || !file_exists($templatePath)) {
        http_response_code(500);
        echo 'Template Word non trovato';
        exit;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'prog_quinta_');
    if ($tmpPath === false) {
        http_response_code(500);
        echo 'Impossibile creare il file temporaneo';
        exit;
    }

    $docxPath = $tmpPath . '.docx';
    copy($templatePath, $docxPath);
    @unlink($tmpPath);

    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        @unlink($docxPath);
        http_response_code(500);
        echo 'Impossibile aprire il template Word';
        exit;
    }

    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) {
        $zip->close();
        @unlink($docxPath);
        http_response_code(500);
        echo 'Documento Word non valido';
        exit;
    }

    $docenti = array_values(array_filter(array_unique($docentiLabels)));
    if (count($docenti) === 0) {
        $docenteLabel = getProgramDocenteLabel($program);
        if ($docenteLabel !== '') {
            $docenti[] = $docenteLabel;
        }
    }

    $intestazioneLines = buildWordProgramHeader([
        'Classe ' . ($program['classe_nome_stampa'] ?? $program['classe_nome']) . ($annoScolasticoLabel !== '' ? ' a.s. ' . $annoScolasticoLabel : ''),
        'Materia ' . $program['materia_nome'],
        (count($docenti) > 1 ? 'Docenti ' : 'Docente ') . implode(' / ', $docenti),
    ]);
    $zip->addFromString('word/document.xml', normalizeWordDocumentFont(fillWordTemplateXml($xml, $sections, $intestazioneLines, false)));
    $zip->close();

    $fileName = 'Programma svolto quinta - ' . $program['materia_nome'] . ' - Classe ' . ($program['classe_nome_stampa'] ?? $program['classe_nome']) . ' - ' . $program['doc_cognome'] . ' ' . $program['doc_nome'] . '.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
    header('Content-Length: ' . filesize($docxPath));
    readfile($docxPath);
    @unlink($docxPath);
    exit;
}

function exportQuintaClasseDocx(int $classId, int $annoScolasticoId): void
{
    $annoScolasticoLabel = getAnnoScolasticoLabel();
    $templatePath = getWordTemplatePath();
    if ($templatePath === false || !file_exists($templatePath)) {
        http_response_code(500);
        echo 'Template Word non trovato';
        exit;
    }

    $programmi = dbGetAll("
        SELECT DISTINCT programmi_svolti.id,
               programmi_svolti.id_materia
        FROM programmi_svolti
        INNER JOIN programmi_svolti_classi psc
            ON psc.id_programma_svolto = programmi_svolti.id
        INNER JOIN classi
            ON classi.id = psc.id_classe
        WHERE psc.id_classe = " . intval($classId) . "
          AND programmi_svolti.id_anno_scolastico = " . intval($annoScolasticoId) . "
          AND classi.anno = 5
        ORDER BY programmi_svolti.id_materia ASC, programmi_svolti.id ASC
    ");

    if ($programmi == null) {
        http_response_code(404);
        echo 'Nessun programma svolto trovato per la classe selezionata';
        exit;
    }

    $programmiData = [];
    $classeNome = '';
    $programmiPerMateria = [];
    foreach ($programmi as $programmaRow) {
        $singleProgram = getProgrammaSvoltoById(intval($programmaRow['id']));
        if ($singleProgram == null) {
            continue;
        }
        $classeNome = (string)($singleProgram['classe_nome_stampa'] ?? $singleProgram['classe_nome']);
        $materiaKey = intval($singleProgram['materia_id']);
        if (!isset($programmiPerMateria[$materiaKey])) {
            $programmiPerMateria[$materiaKey] = [];
        }
        $programmiPerMateria[$materiaKey][] = $singleProgram;
    }

    foreach ($programmiPerMateria as $materiaPrograms) {
        $firstProgram = $materiaPrograms[0];
        $sections = getEmptyQuintaSections();
        $docenti = [];

        foreach ($materiaPrograms as $singleProgram) {
            $docenteLabel = getProgramDocenteLabel($singleProgram);
            if ($docenteLabel !== '') {
                $docenti[] = $docenteLabel;
            }
            $singleSections = buildQuintaSections(getModuliProgrammaSvolto(intval($singleProgram['id'])));
            mergeQuintaSections($sections, $singleSections, count($materiaPrograms) > 1 ? $docenteLabel : '');
            mergeQuintaSections(
                $sections,
                getProgramLevelQuintaSections($singleProgram),
                '',
                ['metodologie', 'criteri_valutazione', 'testi_materiali']
            );
        }

        $docenti = array_values(array_unique($docenti));
        $intestazioneLines = buildWordProgramHeader([
            'Classe ' . ($firstProgram['classe_nome_stampa'] ?? $firstProgram['classe_nome']) . ($annoScolasticoLabel !== '' ? ' a.s. ' . $annoScolasticoLabel : ''),
            'Materia ' . $firstProgram['materia_nome'],
            (count($docenti) > 1 ? 'Docenti ' : 'Docente ') . implode(' / ', $docenti),
        ]);

        $programmiData[] = [
            'intestazione_lines' => $intestazioneLines,
            'sections' => normalizeQuintaSectionsForDocx($sections),
        ];
    }

    if (count($programmiData) === 0) {
        http_response_code(404);
        echo 'Nessun programma svolto trovato per la classe selezionata';
        exit;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'prog_quinta_classe_');
    if ($tmpPath === false) {
        http_response_code(500);
        echo 'Impossibile creare il file temporaneo';
        exit;
    }

    $docxPath = $tmpPath . '.docx';
    copy($templatePath, $docxPath);
    @unlink($tmpPath);

    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        @unlink($docxPath);
        http_response_code(500);
        echo 'Impossibile aprire il template Word';
        exit;
    }

    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) {
        $zip->close();
        @unlink($docxPath);
        http_response_code(500);
        echo 'Documento Word non valido';
        exit;
    }

    $zip->addFromString('word/document.xml', normalizeWordDocumentFont(buildCombinedWordXml($xml, $programmiData)));
    $zip->close();

    $fileName = 'Programmi svolti quinta - Classe ' . $classeNome . '.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
    header('Content-Length: ' . filesize($docxPath));
    readfile($docxPath);
    @unlink($docxPath);
    exit;
}

if ($format === 'docx') {
    if (!$is_quinta) {
        http_response_code(400);
        echo 'L\'export Word e disponibile solo per le classi quinte';
        exit;
    }

    $sections = buildQuintaMergedSectionsForPrograms($relatedPrograms);
    $sharedPrograms = $soloProgrammaCorrente ? getProgrammiSvoltiCorrelati($program) : $relatedPrograms;
    $sections = mergeSharedProgramLevelQuintaSections($sections, $sharedPrograms);
    $docentiLabels = getDocentiProgrammaLabels($relatedPrograms);

    exportQuintaDocx($program, normalizeQuintaSectionsForDocx($sections), $docentiLabels);
}

if ($format === 'docx_classe') {
    if ($classId <= 0 || $annoScolasticoId <= 0) {
        http_response_code(400);
        echo 'Parametri classe non validi';
        exit;
    }

    if (!userCanExportClasseDocx($classId, $annoScolasticoId)) {
        http_response_code(403);
        echo 'Non sei autorizzato a esportare il Word della classe';
        exit;
    }

    exportQuintaClasseDocx($classId, $annoScolasticoId);
}

ob_start();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($titolo); ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            background: transparent;
            color: #2c3e50;
        }

        .print-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 9999;
            background: #FFA500;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 900;
            font-style: italic;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .first-logo {
            text-align: center;
            margin: 10mm 0 5mm;
        }

        .header {
            display: flex;
            align-items: center;
            background: linear-gradient(90deg, #0057b7, #3a8dd5);
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .header .info {
            flex: 1;
            text-align: center;
            color: #000;
        }

        .header .info h1 {
            margin: 0;
            font-size: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header .info p {
            margin: 4px 0 0;
            font-size: 20px;
            letter-spacing: 0.5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
        }

        thead {
            display: table-header-group !important;
        }

        tbody {
            display: table-row-group !important;
        }

        .module-card {
            margin: 0 4mm 15px;
            page-break-inside: auto;
        }

        .module th,
        .module td,
        .quinta td,
        .quinta th {
            border: 1px solid #0057b7;
            padding: 6px 8px;
            vertical-align: top;
        }

        .module thead th,
        .quinta thead th {
            background-color: #0057b7;
            color: #ffffff;
            font-size: 16px;
            padding: 8px;
            text-align: left;
        }

        .module .label-cell,
        .quinta .label-cell {
            width: 25%;
            background-color: #d9eefa;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
        }

        .module .value-cell,
        .quinta .value-cell {
            width: 75%;
            background-color: #f7fbfe;
        }

        .module .value-cell p,
        .quinta .value-cell p {
            margin: 0 0 4px 0;
            line-height: 1.35;
        }

        .module .value-cell h4,
        .quinta .value-cell h4 {
            margin: 4px 0 4px 6px;
            color: #173f68;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.25;
            text-transform: uppercase;
        }

        .module .value-cell h5,
        .quinta .value-cell h5 {
            margin: 8px 0 6px 0;
            padding: 3px 6px;
            background: #c8d0da;
            color: #173f68;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.25;
            text-align: center;
            text-transform: uppercase;
        }

        .module .value-cell .programma-print-title,
        .quinta .value-cell .programma-print-title {
            font-size: 13px !important;
            margin-left: 6px !important;
        }

        .module .value-cell .programma-print-title-text,
        .quinta .value-cell .programma-print-title-text {
            font-size: 13px !important;
        }

        .module .value-cell ul,
        .module .value-cell ol,
        .quinta .value-cell ul,
        .quinta .value-cell ol {
            margin: 0 0 4px 18px;
            padding-left: 14px;
            line-height: 1.3;
        }

        .module .value-cell li,
        .quinta .value-cell li {
            margin: 0 0 3px 0;
        }

        .module .value-cell blockquote,
        .quinta .value-cell blockquote {
            margin: 0 0 4px 20px;
            padding-left: 8px;
            border-left: 2px solid #c9d8e8;
            line-height: 1.3;
        }
    </style>
    <link rel="icon" href="../ore-32.png" />
</head>

<body>
    <?php if (!$doPrint): ?>
        <div class="print-button">
            <form method="post" action="">
                <input type="hidden" name="id" value="<?= $programId ?>">
                <input type="hidden" name="print" value="1">
                <input type="hidden" name="format" value="pdf">
                <input type="hidden" name="view_scope" value="<?= htmlspecialchars($viewScope, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="titolo" value="<?php echo htmlspecialchars($titolo, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" style="font-family: Arial, sans-serif; font-size: 16px; font-weight: bold;">Scarica PDF</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="first-logo">
        <img src="<?php echo $base64img ?>" alt="Logo Buonarroti" style="height:80px; width:auto;">
    </div>

    <div class="header">
        <div class="info">
            <h1><?php echo htmlspecialchars($titolo); ?></h1>
            <p>Classe <?= htmlspecialchars($program['classe_nome_stampa'] ?? $program['classe_nome']) ?> | Indirizzo <?= htmlspecialchars($program['indirizzo_nome_stampa'] ?? $program['indirizzo_nome']) ?><br>
                Materia <?= htmlspecialchars($program['materia_nome']) ?> | <?= count($docentiLabelsProgramma) > 1 ? 'Docenti' : 'Docente' ?> <?= htmlspecialchars(implode(' / ', !empty($docentiLabelsProgramma) ? $docentiLabelsProgramma : [trim($program['doc_cognome'] . ' ' . $program['doc_nome'])])) ?> |
                Anno scolastico <?= $__anno_scolastico_corrente_anno ?></p>
        </div>
    </div>

    <?php if ($is_quinta): ?>
        <?php
        $quintaRows = buildQuintaWordStyleRows(buildQuintaMergedSectionsForPrograms($relatedPrograms));
        ?>
        <?php if (trim(strip_tags(implode('', $quintaRows))) !== ''): ?>
            <div class="module-card">
                <table class="module">
                    <thead>
                        <tr>
                            <th colspan="2">Programma svolto - classe quinta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quintaRows as $label => $value): ?>
                            <?php if (trim(strip_tags($value)) !== ''): ?>
                                <tr>
                                    <td class="label-cell"><?= $label ?></td>
                                    <td class="value-cell"><?= $value ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($relatedModulesByProgram as $programEntry): ?>
            <?php if (count($relatedModulesByProgram) > 1): ?>
                <div class="module-card">
                    <table class="module">
                        <thead>
                            <tr>
                                <th colspan="2" style="font-size:18px; font-weight:700; padding:8px 0;">Docente <?= htmlspecialchars($programEntry['docente_label']) ?></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            <?php endif; ?>
            <?php foreach ($programEntry['modules'] as $m): ?>
                <div class="module-card">
                    <table class="module">
                        <thead>
                            <tr>
                                <th colspan="2">Modulo <?= (int) rowField($m, 'ORDINE', 'ordine', 0) ?>: <?= htmlspecialchars((string)rowField($m, 'NOME', 'nome', '')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="label-cell">Conoscenze degli argomenti svolti</td>
                                <td class="value-cell"><?= buildTwoLevelListFromText((string)rowField($m, 'CONTENUTO', 'contenuto', '')) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>

</html>
<?php

use TCPDF;

class MyPDF extends TCPDF
{
    public $footerText = 'Documento ufficiale - Segreteria Didattica - generato il ';

    public function Footer()
    {
        $this->SetY(-10);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(8, $this->GetY(), $this->getPageWidth() - 8, $this->GetY());
        $this->Ln(2);
        $this->SetFont('dejavusans', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $current = $this->PageNo();
        $total = method_exists($this, 'getAliasNbPages') ? $this->getAliasNbPages() : '';
        $today = date('d/m/Y');
        $line = $this->footerText . $today . ' - Pag. ' . $current . ($total ? '/' . $total : '');
        $this->Cell(0, 4, $line, 0, 0, 'C');
    }
}

$html = ob_get_clean();

if ($doPrint) {
    $pdf = new MyPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(8, 10, 8, 8);
    $pdf->SetAutoPageBreak(true, 15);
    if (method_exists($pdf, 'AliasNbPages')) {
        $pdf->AliasNbPages();
    }
    $pdf->setFooterData([100, 100, 100], [200, 200, 200]);
    $pdf->setFooterFont(['dejavusans', 'I', 8]);
    $pdf->SetFooterMargin(10);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 10);

    $htmlIntro = '
<div style="text-align:center;margin:0px">
  <img src="' . $base64img . '" style="height:50px;width:auto" />
</div>
<h1 style="font-family:dejavusans;font-size:24px;text-align:center;margin:0 0 0mm;">' . htmlspecialchars($titolo) . '</h1>
<p style="text-align:center;margin:0px;font-size:12px">
  Classe ' . htmlspecialchars($program['classe_nome_stampa'] ?? $program['classe_nome']) . ' |
  Indirizzo ' . htmlspecialchars($program['indirizzo_nome_stampa'] ?? $program['indirizzo_nome']) . '<br>
  Materia ' . htmlspecialchars($program['materia_nome']) . ' |
  ' . (count($docentiLabelsProgramma) > 1 ? 'Docenti ' : 'Docente ') . htmlspecialchars(implode(' / ', !empty($docentiLabelsProgramma) ? $docentiLabelsProgramma : [trim($program['doc_cognome'] . ' ' . $program['doc_nome'])])) . ' |
  Anno scolastico ' . $anno_scolastico_corrente_anno_safe . '</p><br>';

    $pdf->writeHTML($htmlIntro, true, false, true, false, '');

    if ($is_quinta) {
        $quintaRows = buildQuintaWordStyleRows(buildQuintaMergedSectionsForPrograms($relatedPrograms), true);
        if (trim(strip_tags(implode('', $quintaRows))) !== '') {
            $tbl = '<table width="100%" border="0" cellpadding="4" cellspacing="0">';
            $tbl .= '<thead><tr><th colspan="2" style="background-color:#0057b7;color:#ffffff;font-size:16px;padding:8px;text-align:left;border:2px solid #0057b7;">Programma svolto - classe quinta</th></tr></thead><tbody>';
            foreach ($quintaRows as $label => $data) {
                if (trim(strip_tags($data)) === '') {
                    continue;
                }
                $tbl .= '<tr>';
                $tbl .= '<td width="25%" style="background-color:#d9eefa;border:1px solid #0057b7;vertical-align:top;">' . formatQuintaPdfLabel($label) . '</td>';
                $tbl .= '<td width="75%" style="background-color:#f7fbfe;border:1px solid #0057b7;vertical-align:top;padding:4px 12px 2px 6px;">' . addProgrammaPdfCellBreathingRoom($data) . '</td>';
                $tbl .= '</tr>';
            }
            $tbl .= '</tbody></table><div style="height:4mm"></div>';
            $pdf->writeHTML($tbl, true, false, true, false, '');
        }
    } else {
        foreach ($relatedModulesByProgram as $programEntry) {
            if (count($relatedModulesByProgram) > 1) {
                $pdf->writeHTML('<p style="font-weight:bold;text-align:center;font-size:18px;line-height:1.25;margin:0 0 3mm;">Docente ' . htmlspecialchars($programEntry['docente_label']) . '</p>', true, false, true, false, '');
            }
            foreach ($programEntry['modules'] as $m) {
                $tbl = '<table width="100%" border="0" cellpadding="4" cellspacing="0">';
                $tbl .= '<thead><tr><th colspan="2" style="background-color:#0057b7;color:#ffffff;font-size:16px;padding:8px;text-align:left;border:2px solid #0057b7;">Modulo ' . ((int)rowField($m, 'ORDINE', 'ordine', 0)) . ': ' . htmlspecialchars((string)rowField($m, 'NOME', 'nome', '')) . '</th></tr></thead><tbody>';
                $tbl .= '<tr>';
                $tbl .= '<td width="25%" valign="top" style="background-color:#d9eefa;border:1px solid #0057b7;vertical-align:top;text-align:center;">Conoscenze degli argomenti svolti</td>';
                $contentHtml = buildTwoLevelListFromText((string)rowField($m, 'CONTENUTO', 'contenuto', ''), true);
                $tbl .= '<td width="75%" style="background-color:#f7fbfe;border:1px solid #0057b7;vertical-align:top;">' . addProgrammaPdfCellBreathingRoom($contentHtml) . '</td>';
                $tbl .= '</tr></tbody></table><div style="height:4mm"></div>';
                $pdf->writeHTML($tbl, true, false, true, false, '');
            }
        }
    }

    $pdf->Output($titolo . ' ' . $program['materia_nome'] . ' - Classe ' . ($program['classe_nome_stampa'] ?? $program['classe_nome']) . ' - Indirizzo ' . ($program['indirizzo_nome_stampa'] ?? $program['indirizzo_nome']) . ' - Docente ' . $program['doc_cognome'] . ' ' . $program['doc_nome'] . '.pdf', 'D');
    exit;
}

echo $html;
?>
