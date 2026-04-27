<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/vendor/autoload.php';

ruoloRichiesto('docente', 'dirigente', 'segreteria-didattica');

$programId = isset($_POST['id']) ? (int) $_POST['id'] : -1;
$classId = isset($_POST['class_id']) ? (int) $_POST['class_id'] : -1;
$annoScolasticoId = isset($_POST['anno_scolastico_id']) ? (int) $_POST['anno_scolastico_id'] : -1;
$doPrint = isset($_POST['print']) && ($_POST['print'] == '1' || $_POST['print'] === 'true');
$format = isset($_POST['format']) ? strtolower((string)$_POST['format']) : 'pdf';
$titolo = isset($_POST['titolo']) ? $_POST['titolo'] : 'Programma didattico';

if ($programId <= 0 && !($format === 'docx_classe' && $classId > 0 && $annoScolasticoId > 0)) {
    exit;
}

function getProgrammaSvoltoById(int $programId): ?array
{
    $query = "SELECT  programmi_svolti.id,
        programmi_svolti.id_materia AS svolti_id_materia,
        programmi_svolti.id_docente AS svolti_id_docente,
        programmi_svolti.id_classe AS svolti_id_classe,
        programmi_svolti.id_anno_scolastico AS anno_scolastico_id,
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
    return $program ?: null;
}

function getModuliProgrammaSvolto(int $programId): array
{
    $modules = dbGetAll("SELECT * FROM programmi_svolti_moduli WHERE id_programma = $programId ORDER BY ordine ASC");
    return $modules ?: [];
}

function userCanExportClasseDocx(int $classId, int $annoScolasticoId): bool
{
    global $__docente_id;

    if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
        return true;
    }

    if (!haRuolo('docente') || intval($__docente_id ?? 0) <= 0) {
        return false;
    }

    $coord = dbGetFirst("SELECT id FROM coordinatori WHERE id_docente=" . intval($__docente_id) . " AND id_classe=" . intval($classId) . " AND id_anno_scolastico=" . intval($annoScolasticoId));
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

$modules = $programId > 0 ? getModuliProgrammaSvolto($programId) : [];

$base64img = 'data:image/png;base64,' . base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'"));
$is_quinta = intval($program['classe_anno'] ?? 0) === 5;

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

function buildTwoLevelListFromText(string $text): string
{
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

        $segments = preg_split('/(?<!\.)\.(?!\.)\s*/u', $rawLine);

        foreach ($segments as $segment) {
            $raw = trim($segment);
            if ($raw === '') {
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

            if (isAllUppercase($raw)) {
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

    if (is_array($decoded) && (($decoded['schema'] ?? '') === 'programma_svolto_quinta_v1')) {
        return [
            'competenze_raggiunte' => (string)($decoded['competenze_raggiunte'] ?? ''),
            'contenuti_trattati' => (string)($decoded['contenuti_trattati'] ?? ''),
            'abilita' => (string)($decoded['abilita'] ?? ''),
            'metodologie' => (string)($decoded['metodologie'] ?? ''),
            'criteri_valutazione' => (string)($decoded['criteri_valutazione'] ?? ''),
            'testi_materiali' => (string)($decoded['testi_materiali'] ?? ''),
        ];
    }

    return [
        'competenze_raggiunte' => '',
        'contenuti_trattati' => $contenuto,
        'abilita' => '',
        'metodologie' => '',
        'criteri_valutazione' => '',
        'testi_materiali' => '',
    ];
}

function concatSectionText(array &$sections, string $key, string $moduleTitle, string $value): void
{
    $value = trim($value);
    if ($value === '') {
        return;
    }

    $block = trim($moduleTitle) !== '' ? ("__MODULE_TITLE__" . mb_strtoupper(trim($moduleTitle), 'UTF-8') . "\n" . $value) : $value;
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

function mergeQuintaSections(array &$target, array $source, string $docenteLabel = ''): void
{
    foreach ($target as $key => $value) {
        if (!array_key_exists($key, $source)) {
            continue;
        }
        appendSectionBlock($target[$key], wrapSectionForDocente((string)$source[$key], $docenteLabel));
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
        concatSectionText($sections, 'metodologie', $moduleTitle, $decoded['metodologie']);
        concatSectionText($sections, 'criteri_valutazione', $moduleTitle, $decoded['criteri_valutazione']);
        concatSectionText($sections, 'testi_materiali', $moduleTitle, $decoded['testi_materiali']);
    }

    return $sections;
}

function buildQuintaModuleRows(array $module): array
{
    $decoded = decodeQuintaModulo($module);

    return [
        'Conoscenze o contenuti trattati' => buildTwoLevelListFromText($decoded['contenuti_trattati']),
        "Abilita'" => buildTwoLevelListFromText($decoded['abilita']),
        'Competenze raggiunte' => buildTwoLevelListFromText($decoded['competenze_raggiunte']),
        'Metodologie' => buildTwoLevelListFromText($decoded['metodologie']),
        'Criteri di valutazione' => buildTwoLevelListFromText($decoded['criteri_valutazione']),
        'Testi e materiali / strumenti adottati' => buildTwoLevelListFromText($decoded['testi_materiali']),
    ];
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

function buildWordParagraphsXml(DOMDocument $dom, string $text): array
{
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

function fillWordTemplateXml(string $xml, array $sections, array $intestazioneLines): string
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadXML($xml);

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    setWordParagraphHeader($dom, $xpath, '(//w:body/w:p)[2]', $intestazioneLines);

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

function exportQuintaDocx(array $program, array $sections): void
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

    $intestazioneLines = buildWordProgramHeader([
        'Classe ' . $program['classe_nome'] . ($annoScolasticoLabel !== '' ? ' a.s. ' . $annoScolasticoLabel : ''),
        'Materia ' . $program['materia_nome'],
        'Docente ' . trim($program['doc_cognome'] . ' ' . $program['doc_nome']),
    ]);
    $zip->addFromString('word/document.xml', fillWordTemplateXml($xml, $sections, $intestazioneLines));
    $zip->close();

    $fileName = 'Programma svolto quinta - ' . $program['materia_nome'] . ' - Classe ' . $program['classe_nome'] . ' - ' . $program['doc_cognome'] . ' ' . $program['doc_nome'] . '.docx';
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
        SELECT programmi_svolti.id,
               programmi_svolti.id_materia
        FROM programmi_svolti
        INNER JOIN classi
        ON classi.id = programmi_svolti.id_classe
        WHERE programmi_svolti.id_classe = " . intval($classId) . "
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
        $classeNome = (string)$singleProgram['classe_nome'];
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
        }

        $docenti = array_values(array_unique($docenti));
        $intestazioneLines = buildWordProgramHeader([
            'Classe ' . $firstProgram['classe_nome'] . ($annoScolasticoLabel !== '' ? ' a.s. ' . $annoScolasticoLabel : ''),
            'Materia ' . $firstProgram['materia_nome'],
            (count($docenti) > 1 ? 'Docenti ' : 'Docente ') . implode(' / ', $docenti),
        ]);

        $programmiData[] = [
            'intestazione_lines' => $intestazioneLines,
            'sections' => $sections,
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

    $zip->addFromString('word/document.xml', buildCombinedWordXml($xml, $programmiData));
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
    if (impersonaRuolo('docente')) {
        http_response_code(403);
        echo 'Il docente puo scaricare solo il PDF del proprio programma svolto';
        exit;
    }

    if (!$is_quinta) {
        http_response_code(400);
        echo 'L\'export Word e disponibile solo per le classi quinte';
        exit;
    }

    exportQuintaDocx($program, buildQuintaSections($modules));
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
?><!DOCTYPE html>
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
            <p>Classe <?= htmlspecialchars($program['classe_nome']) ?> | Indirizzo <?= htmlspecialchars($program['indirizzo_nome']) ?><br>
                Materia <?= htmlspecialchars($program['materia_nome']) ?> | Docente <?= htmlspecialchars($program['doc_cognome'] . ' ' . $program['doc_nome']) ?> |
                Anno scolastico <?= $__anno_scolastico_corrente_anno ?></p>
        </div>
    </div>

    <?php if ($is_quinta): ?>
        <?php foreach ($modules as $m): ?>
            <?php $rows = buildQuintaModuleRows($m); ?>
            <div class="module-card">
                <table class="module">
                    <thead>
                        <tr>
                            <th colspan="2">Modulo <?= (int) rowField($m, 'ORDINE', 'ordine', 0) ?>: <?= htmlspecialchars((string)rowField($m, 'NOME', 'nome', '')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $label => $value): ?>
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
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($modules as $m): ?>
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
  Classe ' . htmlspecialchars($program['classe_nome']) . ' |
  Indirizzo ' . htmlspecialchars($program['indirizzo_nome']) . '<br>
  Materia ' . htmlspecialchars($program['materia_nome']) . ' |
  Docente ' . htmlspecialchars($program['doc_cognome'] . ' ' . $program['doc_nome']) . ' |
  Anno scolastico ' . $__anno_scolastico_corrente_anno . '</p><br>';

    $pdf->writeHTML($htmlIntro, true, false, true, false, '');

    if ($is_quinta) {
        foreach ($modules as $m) {
            $tbl = '<table width="100%" border="0" cellpadding="0" cellspacing="0">';
            $tbl .= '<thead><tr><th colspan="2" style="background-color:#0057b7;color:#ffffff;font-size:16px;padding:8px;text-align:left;border:2px solid #0057b7;">Modulo ' . ((int)rowField($m, 'ORDINE', 'ordine', 0)) . ': ' . htmlspecialchars((string)rowField($m, 'NOME', 'nome', '')) . '</th></tr></thead><tbody>';

            foreach (buildQuintaModuleRows($m) as $label => $data) {
                if (trim(strip_tags($data)) === '') {
                    continue;
                }
                $tbl .= '<tr>';
                $tbl .= '<td width="25%" style="background-color:#d9eefa;border:1px solid #0057b7;padding:6px 8px;vertical-align:top;">' . $label . '</td>';
                $tbl .= '<td width="75%" style="background-color:#f7fbfe;border:1px solid #0057b7;padding:6px 8px;vertical-align:top;">' . $data . '</td>';
                $tbl .= '</tr>';
            }

            $tbl .= '</tbody></table><div style="height:4mm"></div>';
            $pdf->writeHTML($tbl, true, false, true, false, '');
        }
    } else {
        foreach ($modules as $m) {
            $tbl = '<table width="100%" border="0" cellpadding="0" cellspacing="0">';
            $tbl .= '<thead><tr><th colspan="2" style="background-color:#0057b7;color:#ffffff;font-size:16px;padding:8px;text-align:left;border:2px solid #0057b7;">Modulo ' . ((int)rowField($m, 'ORDINE', 'ordine', 0)) . ': ' . htmlspecialchars((string)rowField($m, 'NOME', 'nome', '')) . '</th></tr></thead><tbody>';
            $tbl .= '<tr>';
            $tbl .= '<td width="25%" valign="middle" style="background-color:#d9eefa;border:1px solid #0057b7;padding:6px 8px;vertical-align:middle;text-align:center;">Conoscenze degli argomenti svolti</td>';
            $tbl .= '<td width="75%" style="background-color:#f7fbfe;border:1px solid #0057b7;padding:6px 8px;vertical-align:middle;">' . buildTwoLevelListFromText((string)rowField($m, 'CONTENUTO', 'contenuto', '')) . '</td>';
            $tbl .= '</tr></tbody></table><div style="height:4mm"></div>';
            $pdf->writeHTML($tbl, true, false, true, false, '');
        }
    }

    $pdf->Output($titolo . ' ' . $program['materia_nome'] . ' - Classe ' . $program['classe_nome'] . ' - Indirizzo ' . $program['indirizzo_nome'] . ' - Docente ' . $program['doc_cognome'] . ' ' . $program['doc_nome'] . '.pdf', 'D');
    exit;
}

echo $html;
?>
