<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('studente');

if (!getSettingsValue('config', 'permessi', false) || !getSettingsValue('permessi', 'visibile_studenti', false)) {
    redirect('/error/unauthorized.php');
}

function studentePermessiEh($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentePermessiBadge($stato): string
{
    switch ((int)$stato) {
        case 1:
            return '<span class="badge" style="background-color:#f0ad4e;color:#111;">Richiesto</span>';
        case 2:
            return '<span class="badge" style="background-color:green;color:white;">Confermato</span>';
        case 3:
            return '<span class="badge" style="background-color:red;color:white;">Rifiutato</span>';
        case 4:
            return '<span class="badge" style="background-color:red;color:white;">Assente</span>';
        default:
            return '<span class="badge" style="background-color:#777;color:white;">Sconosciuto</span>';
    }
}

$rows = dbGetAll("
    SELECT
        permessi_uscita.id,
        permessi_uscita.data,
        permessi_uscita.ora_uscita,
        permessi_uscita.ora_rientro,
        permessi_uscita.rientro,
        permessi_uscita.motivo,
        permessi_uscita.stato,
        genitori.nome AS genitore_nome,
        genitori.cognome AS genitore_cognome,
        studente.nome AS studente_nome,
        studente.cognome AS studente_cognome
    FROM permessi_uscita
    INNER JOIN genitori ON permessi_uscita.id_genitore = genitori.id
    INNER JOIN studente ON permessi_uscita.id_studente = studente.id
    WHERE permessi_uscita.id_studente = " . dbI((int)$__studente_id) . "
    ORDER BY permessi_uscita.data DESC, permessi_uscita.ora_uscita DESC
");

if (!$rows) {
    echo '<div class="alert alert-info">Non risultano permessi di uscita.</div>';
    exit;
}

echo '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">';
echo '<thead><tr>';
echo '<th class="text-center">Data</th>';
echo '<th class="text-center">Ora uscita</th>';
echo '<th class="text-center">Ora rientro</th>';
echo '<th class="text-center">Studente</th>';
echo '<th class="text-center">Genitore</th>';
echo '<th class="text-center">Motivo</th>';
echo '<th class="text-center">Segreteria</th>';
echo '</tr></thead><tbody>';

foreach ($rows as $row) {
    $data = !empty($row['data']) ? date('d/m/Y', strtotime((string)$row['data'])) : '';
    $oraUscita = !empty($row['ora_uscita']) ? date('H:i', strtotime((string)$row['ora_uscita'])) : '';
    $oraRientro = ((int)($row['rientro'] ?? 0) === 1 && !empty($row['ora_rientro'])) ? date('H:i', strtotime((string)$row['ora_rientro'])) : '-';
    $studente = trim((string)$row['studente_nome'] . ' ' . (string)$row['studente_cognome']);
    $genitore = trim((string)$row['genitore_nome'] . ' ' . (string)$row['genitore_cognome']);

    echo '<tr>';
    echo '<td class="text-center">' . studentePermessiEh($data) . '</td>';
    echo '<td class="text-center">' . studentePermessiEh($oraUscita) . '</td>';
    echo '<td class="text-center">' . studentePermessiEh($oraRientro) . '</td>';
    echo '<td class="text-center">' . studentePermessiEh($studente) . '</td>';
    echo '<td class="text-center">' . studentePermessiEh($genitore) . '</td>';
    echo '<td>' . studentePermessiEh($row['motivo'] ?? '') . '</td>';
    echo '<td class="text-center">' . studentePermessiBadge($row['stato'] ?? 0) . '</td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

