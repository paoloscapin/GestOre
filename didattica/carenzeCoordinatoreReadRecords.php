<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once __DIR__ . '/carenzeCoordinatoreLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

$vistaDocente = intval($_GET['vista_docente'] ?? 0) === 1;
$docenteId = intval($_GET['docente_id'] ?? 0);
$anniFiltroId = intval($_GET['anni_id'] ?? 0);
$classeFiltroId = intval($_GET['classe_id'] ?? 0);
$materiaFiltroId = intval($_GET['materia_id'] ?? 0);
$studenteFiltroId = intval($_GET['studente_id'] ?? 0);
$annoClasseFiltro = intval($_GET['anno'] ?? 0);

if ($docenteId <= 0 || !$vistaDocente) {
    $docenteId = carenzeCoordCurrentDocenteId();
}

$annoCorrente = intval($__anno_scolastico_corrente_id);
$classLabel = carenzeCoordClassLabel($docenteId, $annoCorrente);
$rows = carenzeCoordRows($docenteId, $annoCorrente, $anniFiltroId, $classeFiltroId, $materiaFiltroId, $studenteFiltroId, $annoClasseFiltro);

if ($classLabel === '') {
    echo '<div class="alert alert-info" style="margin:12px 0;">Non risulti coordinatore di una classe nell\'anno scolastico corrente.</div>';
    exit;
}

$html = '<div class="carenze-coord-summary">';
$html .= '<strong>Classe coordinata:</strong> ' . carenzeCoordH($classLabel);
$html .= ' <span class="text-muted">- righe: ' . count($rows) . '</span>';
$html .= '</div>';

if (!$rows) {
    $html .= '<div class="alert alert-success" style="margin:12px 0;">Nessuna carenza trovata per gli studenti della classe coordinata.</div>';
    echo $html;
    exit;
}

$html .= '
<div class="table-wrapper table-responsive">
<table class="table table-bordered table-striped table-green carenze-coord-table">
    <thead>
        <tr>
            <th class="text-center">Studente</th>
            <th class="text-center">Classe</th>
            <th class="text-center">Materia</th>
            <th class="text-center">Anno carenza</th>
            <th class="text-center">Docente</th>
            <th class="text-center">Stato</th>
            <th class="text-center">Tentativo</th>
            <th class="text-center">Dettaglio</th>
        </tr>
    </thead>
    <tbody>';

foreach ($rows as $row) {
    $esito = $row['esito'];
    $docente = trim((string)($row['doc_cognome'] ?? '') . ' ' . (string)($row['doc_nome'] ?? ''));
    $classe = carenzeCoordH($row['classe_attuale'] ?? '');
    if (($row['classe_carenza'] ?? '') !== '' && $row['classe_carenza'] !== $row['classe_attuale']) {
        $classe .= ' <span class="text-muted">(carenza: ' . carenzeCoordH($row['classe_carenza']) . ')</span>';
    }

    $html .= '<tr class="carenze-coord-row carenze-coord-' . carenzeCoordH($esito['classe_css']) . '">';
    $html .= '<td>' . carenzeCoordH(trim($row['stud_cognome'] . ' ' . $row['stud_nome'])) . '</td>';
    $html .= '<td class="text-center">' . $classe . '</td>';
    $html .= '<td>' . carenzeCoordH($row['materia']) . '</td>';
    $html .= '<td class="text-center">' . carenzeCoordH($row['anno_scolastico']) . '</td>';
    $html .= '<td>' . carenzeCoordH($docente) . '</td>';
    $html .= '<td class="text-center"><span class="label label-' . carenzeCoordH($esito['classe_css']) . '">' . carenzeCoordH($esito['stato']) . '</span></td>';
    $html .= '<td class="text-center">' . carenzeCoordH($esito['tentativo']) . '</td>';
    $html .= '<td>' . carenzeCoordH($esito['dettaglio']) . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table></div>';

echo $html;
