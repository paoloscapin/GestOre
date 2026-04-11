<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

function fmtDateITCfg($iso)
{
    if (!$iso) return '';
    $ts = strtotime($iso);
    if (!$ts) return (string)$iso;
    return date('d/m/Y', $ts);
}

$finestre = dbGetAll("
    SELECT id, codice, data_inizio, data_fine, valido
    FROM permesso_ata_ferie_finestra
    ORDER BY
      FIELD(UPPER(TRIM(codice)), 'ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA'),
      data_inizio,
      id
");
if (!is_array($finestre)) {
    $finestre = [];
}

foreach ($finestre as &$f) {
    $f['data_inizio_fmt'] = fmtDateITCfg($f['data_inizio'] ?? '');
    $f['data_fine_fmt']   = fmtDateITCfg($f['data_fine'] ?? '');
    $f['codice'] = strtoupper(trim((string)($f['codice'] ?? '')));
    $f['valido'] = intval($f['valido'] ?? 0);
}
unset($f);

$giorni = dbGetAll("
    SELECT id, sottotipo, data_giorno, tipo, descrizione, valido
    FROM permesso_ata_ferie_giorni_speciali
    ORDER BY
      FIELD(UPPER(TRIM(sottotipo)), 'ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA'),
      data_giorno,
      id
");
if (!is_array($giorni)) {
    $giorni = [];
}

foreach ($giorni as &$g) {
    $g['sottotipo'] = strtoupper(trim((string)($g['sottotipo'] ?? '')));
    $g['tipo'] = strtoupper(trim((string)($g['tipo'] ?? '')));
    $g['descrizione'] = trim((string)($g['descrizione'] ?? ''));
    $g['data_fmt'] = fmtDateITCfg($g['data_giorno'] ?? '');
    $g['valido'] = intval($g['valido'] ?? 0);
}
unset($g);

echo json_encode([
    'ok' => true,
    'finestre' => $finestre,
    'giorni_speciali' => $giorni,
    'counts' => [
        'finestre' => count($finestre),
        'giorni_speciali' => count($giorni)
    ]
], JSON_UNESCAPED_UNICODE);