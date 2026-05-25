<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('esterno', 'docente', 'segreteria-didattica', 'dirigente');

$anno_id = intval($_GET['anno_id'] ?? $__anno_scolastico_corrente_id);
$esame_id = intval($_GET['esame_id'] ?? 0);

$ruolo_eff = $__utente_ruolo ?? '';
if (impersonaRuolo('docente')) $ruolo_eff = 'docente';
if (impersonaRuolo('esterno')) $ruolo_eff = 'esterno';

$docente_id = intval($__docente_id ?? 0);
$esterno_id = intval($__utente_id ?? 0);
$canEdit = (haRuolo('segreteria-didattica') || haRuolo('dirigente'));

function g_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function g_dt($value)
{
    if (!$value) return '';
    try {
        return (new DateTime((string)$value))->format('d/m/Y H:i');
    } catch (Exception $e) {
        return (string)$value;
    }
}

function g_badge($text, $type = 'default')
{
    return '<span class="label label-' . $type . '">' . g_h($text) . '</span>';
}

$where = "WHERE s.id_anno_scolastico = " . dbI($anno_id);
if ($esame_id > 0) {
    $where .= " AND s.id_esame = " . dbI($esame_id);
}

if ($ruolo_eff === 'docente') {
    if ($docente_id > 0) {
        $where .= " AND EXISTS (
            SELECT 1 FROM geometri_sessioni_docenti sd
            WHERE sd.id_sessione = s.id AND sd.id_docente = " . dbI($docente_id) . "
        )";
    } else {
        $where .= " AND 1=0";
    }
} elseif ($ruolo_eff === 'esterno') {
    if ($esterno_id > 0) {
        $where .= " AND EXISTS (
            SELECT 1 FROM geometri_sessioni_esterni se
            WHERE se.id_sessione = s.id AND se.id_utente = " . dbI($esterno_id) . "
        )";
    } else {
        $where .= " AND 1=0";
    }
}

$rows = dbGetAll("
    SELECT
        s.id,
        s.data,
        s.stato,
        e.titolo AS esame_titolo,
        e.anno_corso,
        a.anno AS anno_scolastico,
        GROUP_CONCAT(DISTINCT c.classe ORDER BY c.classe SEPARATOR ', ') AS classi,
        GROUP_CONCAT(DISTINCT CONCAT(d.cognome, ' ', d.nome) ORDER BY d.cognome, d.nome SEPARATOR ', ') AS docenti,
        GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(u.cognome,''), ' ', COALESCE(u.nome,''))) ORDER BY u.cognome, u.nome, u.username SEPARATOR ', ') AS esterni,
        COUNT(DISTINCT es.id) AS esiti_compilati
    FROM geometri_sessioni s
    INNER JOIN geometri_esami e ON e.id = s.id_esame
    INNER JOIN anno_scolastico a ON a.id = s.id_anno_scolastico
    LEFT JOIN geometri_sessioni_classi sc ON sc.id_sessione = s.id
    LEFT JOIN classi c ON c.id = sc.id_classe
    LEFT JOIN geometri_sessioni_docenti sd ON sd.id_sessione = s.id
    LEFT JOIN docente d ON d.id = sd.id_docente
    LEFT JOIN geometri_sessioni_esterni se ON se.id_sessione = s.id
    LEFT JOIN utente u ON u.id = se.id_utente
    LEFT JOIN geometri_esiti es ON es.id_sessione = s.id
    $where
    GROUP BY s.id, s.data, s.stato, e.titolo, e.anno_corso, a.anno
    ORDER BY s.data DESC, e.anno_corso ASC, e.ordine ASC
");
if (!$rows) $rows = [];

$html = '
<div class="table-wrapper">
<table class="table table-bordered table-striped table-green">
<thead>
<tr>
    <th class="text-center" style="width:9%;">Anno corso</th>
    <th class="text-center" style="width:22%;">Esame</th>
    <th class="text-center" style="width:14%;">Data</th>
    <th class="text-center" style="width:17%;">Classi</th>
    <th class="text-center" style="width:16%;">Abilitati</th>
    <th class="text-center" style="width:7%;">Stato</th>
    <th class="text-center" style="width:8%;">Azioni</th>
</tr>
</thead>
<tbody>';

if (count($rows) === 0) {
    $html .= '<tr><td colspan="7" class="text-center text-muted">Nessuna sessione trovata</td></tr>';
}

foreach ($rows as $row) {
    $stato = (string)($row['stato'] ?? 'bozza');
    $badge = $stato === 'chiusa' ? g_badge('Chiusa', 'success') : ($stato === 'programmata' ? g_badge('Programmata', 'primary') : g_badge('Bozza', 'default'));
    $abilitati = trim((string)($row['docenti'] ?? ''));
    $esterni = trim((string)($row['esterni'] ?? ''));
    if ($esterni !== '') $abilitati .= ($abilitati ? '<br>' : '') . '<b>Esterni:</b> ' . g_h($esterni);

    $html .= '<tr>';
    $html .= '<td class="text-center">' . intval($row['anno_corso']) . '</td>';
    $html .= '<td>' . g_h($row['esame_titolo']) . '</td>';
    $html .= '<td class="text-center">' . g_h(g_dt($row['data'])) . '</td>';
    $html .= '<td class="text-center">' . g_h($row['classi']) . '</td>';
    $html .= '<td>' . ($abilitati ?: '&mdash;') . '</td>';
    $html .= '<td class="text-center">' . $badge . '<br><small>Esiti: ' . intval($row['esiti_compilati']) . '</small></td>';
    $html .= '<td class="text-center geometri-actions">';
    $html .= '<button class="btn btn-xs btn-warning" onclick="geometriGetDetails(' . intval($row['id']) . ')" data-toggle="tooltip" title="' . ($canEdit ? 'Modifica sessione' : 'Visualizza sessione') . '"><span class="glyphicon glyphicon-pencil"></span></button>';
    $html .= '<button class="btn btn-xs btn-success" onclick="geometriEsitiGet(' . intval($row['id']) . ')" data-toggle="tooltip" title="Esiti sessione"><span class="glyphicon glyphicon-check"></span></button>';
    if ($canEdit) {
        $html .= '<button class="btn btn-xs btn-danger" onclick="geometriDelete(' . intval($row['id']) . ')" data-toggle="tooltip" title="Cancella sessione"><span class="glyphicon glyphicon-trash"></span></button>';
    }
    $html .= '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table></div>';

echo $html;
