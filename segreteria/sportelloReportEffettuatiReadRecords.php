<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once 'sportelloReportEffettuatiLib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti', 'segreteria-didattica', 'docente');

function sr_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$filters = sportelloReportEffettuatiFilters();
$rows = sportelloReportEffettuatiRows($filters);
$title = sportelloReportEffettuatiTitle($filters);
$totals = sportelloReportEffettuatiTotals($rows);

$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
    <thead>
    <tr>
        <th class="text-center col-md-12" colspan="8">' . sr_h($title) . '</th>
    </tr>
    <tr>
        <th class="text-center col-md-1">Data</th>
        <th class="text-center col-md-1">Ora</th>
        <th class="text-center col-md-3">Materia</th>
        <th class="text-center col-md-2">Docente</th>
        <th class="text-center col-md-1">Ore</th>
        <th class="text-center col-md-1">Stato</th>
        <th class="text-center col-md-1">Iscritti</th>
        <th class="text-center col-md-1">Presenti</th>
    </tr>
    </thead><tbody>';

if (count($rows) === 0) {
    $data .= '<tr><td colspan="8" class="text-center text-muted">Nessuno sportello trovato con i filtri selezionati</td></tr>';
}

foreach ($rows as $row) {
    $sportello_id = intval($row['sportello_id']);
    $state = sportelloReportEffettuatiRowState($row);
    $statoMarker = '';
    if ($state === 'cancellato') {
        $statoMarker = '<span class="label label-danger">cancellato</span>';
    } elseif ($state === 'firmato') {
        $statoMarker = '<span class="label label-success">firmato</span>';
    } else {
        $statoMarker = '<span class="label label-default">non firmato</span>';
    }

    $iscrittiTip = '';
    $presentiTip = '';
    if (intval($row['numero_iscritti']) > 0) {
        foreach (sportelloReportEffettuatiStudenti($sportello_id) as $studente) {
            $label = sr_h(trim((string)$studente['studente_cognome'] . ' ' . (string)$studente['studente_nome']) . ' ' . (string)$studente['studente_classe']) . '<br>';
            if (!empty($studente['sportello_studente_iscritto'])) $iscrittiTip .= $label;
            if (!empty($studente['sportello_studente_presente'])) $presentiTip .= $label;
        }
    }

    $isPastUnsignedWithStudents = strtotime((string)$row['sportello_data']) <= strtotime(date('Y-m-d'))
        && intval($row['numero_iscritti'] ?? 0) > 0
        && empty($row['sportello_firmato']);
    $rowClass = $isPastUnsignedWithStudents ? ' class="danger"' : '';

    $data .= '<tr' . $rowClass . '>
        <td class="text-center">' . sr_h(sportelloReportEffettuatiDateIt($row['sportello_data'])) . '</td>
        <td class="text-center">' . sr_h($row['sportello_ora']) . '</td>
        <td>' . sr_h($row['materia_nome']) . '</td>
        <td class="text-center">' . sr_h($row['docente_nome'] . ' ' . $row['docente_cognome']) . '</td>
        <td class="text-center">' . sr_h($row['sportello_numero_ore']) . '</td>
        <td class="text-center">' . $statoMarker . '</td>
        <td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="' . $iscrittiTip . '">' . intval($row['numero_iscritti']) . '</td>
        <td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="' . $presentiTip . '">' . intval($row['numero_presenti']) . '</td>
    </tr>';
}

$data .= '</tbody><tfoot>';
$data .= '<tr class="btn-lima4"><td class="text-right" colspan="5"><strong>Totale Sportelli Fatti:</strong></td><td class="text-center"><strong>' . $totals['sportelli_fatti'] . '</strong></td><td class="text-right"><strong>Ore:</strong></td><td class="text-center"><strong>' . $totals['ore_fatte'] . '</strong></td></tr>';
$data .= '<tr class="btn-salmon"><td class="text-right" colspan="5"><strong>Totale Sportelli Saltati:</strong></td><td class="text-center"><strong>' . $totals['sportelli_saltati'] . '</strong></td><td class="text-right"><strong>Ore:</strong></td><td class="text-center"><strong>' . $totals['ore_saltate'] . '</strong></td></tr>';
$data .= '</tfoot></table></div>';

echo $data;
