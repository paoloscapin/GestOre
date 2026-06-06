<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('studente');

if (!getSettingsValue('config', 'permessi', false) || !getSettingsValue('permessi', 'visibile_studenti', false)) {
    redirect('/error/unauthorized.php');
}

function studentePermessiMobileEh($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentePermessiMobileBadge(array $row): array
{
    switch ((int)($row['stato'] ?? 0)) {
        case 1:
            return ['Richiesto', '#fff3cd', '#7a4f00'];
        case 2:
            return ['Confermato', '#dcfce7', '#166534'];
        case 3:
            return ['Rifiutato', '#fee2e2', '#991b1b'];
        case 4:
            return ['Assente', '#fee2e2', '#991b1b'];
        default:
            return ['Sconosciuto', '#e5e7eb', '#374151'];
    }
}

$rows = dbGetAll("
    SELECT
        permessi_uscita.data,
        permessi_uscita.ora_uscita,
        permessi_uscita.ora_rientro,
        permessi_uscita.rientro,
        permessi_uscita.motivo,
        permessi_uscita.stato,
        genitori.nome AS genitore_nome,
        genitori.cognome AS genitore_cognome
    FROM permessi_uscita
    INNER JOIN genitori ON permessi_uscita.id_genitore = genitori.id
    WHERE permessi_uscita.id_studente = " . dbI((int)$__studente_id) . "
    ORDER BY permessi_uscita.data DESC, permessi_uscita.ora_uscita DESC
");

if (!$rows) {
    echo '<div class="student-permesso-empty">Non risultano permessi di uscita.</div>';
    exit;
}

foreach ($rows as $row) {
    [$label, $bg, $fg] = studentePermessiMobileBadge($row);
    $data = !empty($row['data']) ? date('d/m/Y', strtotime((string)$row['data'])) : '';
    $oraUscita = !empty($row['ora_uscita']) ? date('H:i', strtotime((string)$row['ora_uscita'])) : '';
    $oraRientro = ((int)($row['rientro'] ?? 0) === 1 && !empty($row['ora_rientro'])) ? date('H:i', strtotime((string)$row['ora_rientro'])) : '';
    $time = 'Uscita ' . $oraUscita . ($oraRientro !== '' ? ' - rientro ' . $oraRientro : '');
    $genitore = trim((string)$row['genitore_nome'] . ' ' . (string)$row['genitore_cognome']);

    echo '<div class="student-permesso-card">';
    echo '<div class="student-permesso-card-main">';
    echo '<div class="student-permesso-date">' . studentePermessiMobileEh($data) . '</div>';
    echo '<span class="student-permesso-badge" style="background:' . studentePermessiMobileEh($bg) . ';color:' . studentePermessiMobileEh($fg) . ';">' . studentePermessiMobileEh($label) . '</span>';
    echo '</div>';
    echo '<div class="student-permesso-time">' . studentePermessiMobileEh($time) . '</div>';
    echo '<div class="student-permesso-meta">Richiesto da ' . studentePermessiMobileEh($genitore) . '</div>';
    echo '<div class="student-permesso-reason">' . studentePermessiMobileEh($row['motivo'] ?? '') . '</div>';
    echo '</div>';
}

