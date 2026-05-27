<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once 'geometriCatalogoDefaults.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

geometriEnsureDefaultExams();

function ge_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$rows = dbGetAll("
    SELECT
        e.*,
        (SELECT COUNT(*) FROM geometri_sessioni s WHERE s.id_esame = e.id) AS sessioni
    FROM geometri_esami e
    ORDER BY e.anno_corso ASC, e.ordine ASC, e.titolo ASC
");
if (!$rows) $rows = [];

$html = '
<table class="table table-bordered table-striped table-green">
<thead>
<tr>
    <th class="text-center" style="width:10%;">Anno</th>
    <th class="text-center" style="width:12%;">Codice</th>
    <th class="text-center" style="width:38%;">Titolo</th>
    <th class="text-center" style="width:10%;">Ordine</th>
    <th class="text-center" style="width:10%;">Attivo</th>
    <th class="text-center" style="width:10%;">Sessioni</th>
    <th class="text-center" style="width:10%;">Azioni</th>
</tr>
</thead>
<tbody>';

if (count($rows) === 0) {
    $html .= '<tr><td colspan="7" class="text-center text-muted">Nessun esame nel catalogo</td></tr>';
}

foreach ($rows as $row) {
    $html .= '<tr>';
    $html .= '<td class="text-center">' . intval($row['anno_corso']) . '</td>';
    $html .= '<td class="text-center">' . ge_h($row['codice']) . '</td>';
    $html .= '<td>' . ge_h($row['titolo']) . ($row['descrizione'] ? '<br><small>' . ge_h($row['descrizione']) . '</small>' : '') . '</td>';
    $html .= '<td class="text-center">' . intval($row['ordine']) . '</td>';
    $html .= '<td class="text-center">' . (intval($row['attivo']) === 1 ? '<span class="label label-success">Sì</span>' : '<span class="label label-default">No</span>') . '</td>';
    $html .= '<td class="text-center">' . intval($row['sessioni']) . '</td>';
    $html .= '<td class="text-center">';
    $html .= '<button class="btn btn-xs btn-warning" onclick="geometriEsameGetDetails(' . intval($row['id']) . ')" data-toggle="tooltip" title="Modifica esame"><span class="glyphicon glyphicon-pencil"></span></button> ';
    if (intval($row['sessioni']) === 0) {
        $html .= '<button class="btn btn-xs btn-danger" onclick="geometriEsameDelete(' . intval($row['id']) . ')" data-toggle="tooltip" title="Cancella esame"><span class="glyphicon glyphicon-trash"></span></button>';
    }
    $html .= '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

echo $html;
