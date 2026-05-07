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

function sanitizeProgrammaSvoltoRichHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

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

    return trim($html);
}

function programmaSvoltoPlainFromRichHtml(string $value): string
{
    $value = sanitizeProgrammaSvoltoRichHtml($value);
    $value = preg_replace('/<br\s*\/?>/i', "\n", $value);
    $value = preg_replace('/<\/(p|li|h4)>/i', "\n", $value);
    return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
