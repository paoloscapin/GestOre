<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

header('Content-Type: text/csv; charset=utf-8');

function expandDateRangeIso($from, $to)
{
    $out = [];
    if (!$from) return $out;
    if (!$to) $to = $from;

    $start = DateTime::createFromFormat('Y-m-d', $from);
    $end   = DateTime::createFromFormat('Y-m-d', $to);

    if (!$start || !$end || $end < $start) return $out;

    $cur = clone $start;
    while ($cur <= $end) {
        $out[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }
    return $out;
}

function fmtDateCsv($iso)
{
    if (!$iso) return '';
    $ts = strtotime($iso);
    if (!$ts) return (string)$iso;
    return date('d/m/Y', $ts);
}

function isWeekendIso($iso)
{
    $ts = strtotime($iso);
    if (!$ts) return false;
    $n = (int)date('N', $ts);
    return ($n === 6 || $n === 7);
}

$finestra = strtoupper(trim((string)($_GET['finestra'] ?? 'ESTIVE')));
$mode = strtoupper(trim((string)($_GET['mode'] ?? 'APPROVATI_E_RICHIESTI')));

$allowedFinestra = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA', 'ORDINARIE'];
$allowedMode = ['APPROVATI_E_RICHIESTI', 'APPROVATI_ONLY', 'RICHIESTI_ONLY'];

if (!in_array($finestra, $allowedFinestra, true)) {
    $finestra = 'ESTIVE';
}
if (!in_array($mode, $allowedMode, true)) {
    $mode = 'APPROVATI_E_RICHIESTI';
}

$win = dbGetFirst("
    SELECT id, codice, data_inizio, data_fine
    FROM permesso_ata_ferie_finestra
    WHERE UPPER(TRIM(codice)) = " . dbQ($finestra) . "
      AND (valido IS NULL OR valido = 1)
    LIMIT 1
");

if (!$win || !is_array($win) || empty($win['data_inizio']) || empty($win['data_fine'])) {
    http_response_code(404);
    echo "Finestra ferie non trovata";
    exit;
}

$days = expandDateRangeIso($win['data_inizio'], $win['data_fine']);
$daySet = array_fill_keys($days, true);

/*
 * Giorni chiusi:
 * - weekend
 * - giorni speciali ESCLUSO / ESCLUDI
 * Non li escludo dal CSV, ma li lascio con valore vuoto se nessuno è conteggiato.
 * Se vuoi puoi anche marcare con CHIUSO in un secondo momento.
 */
$closedDaysMap = [];
foreach ($days as $iso) {
    if (isWeekendIso($iso)) {
        $closedDaysMap[$iso] = true;
    }
}

$giorniSpecialiRows = dbGetAll("
    SELECT data_giorno, tipo, descrizione
    FROM permesso_ata_ferie_giorni_speciali
    WHERE UPPER(TRIM(sottotipo)) = " . dbQ($finestra) . "
      AND (valido IS NULL OR valido = 1)
");

if (is_array($giorniSpecialiRows)) {
    foreach ($giorniSpecialiRows as $g) {
        $iso = (string)($g['data_giorno'] ?? '');
        $tipo = strtoupper(trim((string)($g['tipo'] ?? '')));
        if ($iso !== '' && isset($daySet[$iso]) && in_array($tipo, ['ESCLUSO', 'ESCLUDI'], true)) {
            $closedDaysMap[$iso] = true;
        }
    }
}

$rows = dbGetAll("
    SELECT
        req.id AS richiesta_id,
        req.stato AS stato_richiesta,
        req.ferie_sottotipo,
        rr.id AS riga_id,
        rr.data_dal,
        rr.data_al,
        rr.dettagli_json,
        p.username,
        p.cognome,
        p.nome,
        u.nome AS ufficio_nome
    FROM permesso_ata_richiesta req
    JOIN permesso_ata_tipo t
      ON t.id = req.permesso_ata_tipo_id
    JOIN permesso_ata_richiesta_riga rr
      ON rr.permesso_ata_richiesta_id = req.id
    JOIN personale_ata p
      ON p.id = req.personale_ata_id
    LEFT JOIN personale_ata_assegnazioni pa
      ON pa.username = p.username
     AND pa.attiva = 1
    LEFT JOIN personale_ata_uffici u
      ON u.id = pa.id_ufficio
    WHERE t.codice = 'FERIE'
      AND UPPER(TRIM(req.ferie_sottotipo)) = " . dbQ($finestra) . "
      AND rr.data_dal <= " . dbQ($win['data_fine']) . "
      AND rr.data_al >= " . dbQ($win['data_inizio']) . "
    ORDER BY u.nome ASC, p.cognome ASC, p.nome ASC
");

if (!is_array($rows)) {
    $rows = [];
}

$countsByOfficeDate = [];
$officeNames = [];

foreach ($rows as $r) {
    $det = [];
    if (!empty($r['dettagli_json'])) {
        $tmp = json_decode($r['dettagli_json'], true);
        if (is_array($tmp)) {
            $det = $tmp;
        }
    }

    $statoGiorno = strtoupper(trim((string)($det['stato_giorno'] ?? 'RICHIESTO')));

    $countThis = false;
    if ($mode === 'APPROVATI_ONLY') {
        $countThis = ($statoGiorno === 'APPROVATO');
    } elseif ($mode === 'RICHIESTI_ONLY') {
        $countThis = ($statoGiorno === 'RICHIESTO');
    } else {
        $countThis = in_array($statoGiorno, ['APPROVATO', 'RICHIESTO'], true);
    }

    if (!$countThis) {
        continue;
    }

    $office = trim((string)($r['ufficio_nome'] ?? 'Senza ufficio'));
    if ($office === '') $office = 'Senza ufficio';

    $officeNames[$office] = true;

    $range = expandDateRangeIso((string)($r['data_dal'] ?? ''), (string)($r['data_al'] ?? ''));

    foreach ($range as $iso) {
        if (!isset($daySet[$iso])) continue;

        if (!isset($countsByOfficeDate[$office])) {
            $countsByOfficeDate[$office] = [];
        }
        if (!isset($countsByOfficeDate[$office][$iso])) {
            $countsByOfficeDate[$office][$iso] = 0;
        }

        $countsByOfficeDate[$office][$iso]++;
    }
}

$offices = array_keys($officeNames);
sort($offices, SORT_NATURAL | SORT_FLAG_CASE);

$filename = 'ferie_heatmap_uffici_' . strtolower($finestra) . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

$header = ['Ufficio'];
foreach ($days as $iso) {
    $header[] = fmtDateCsv($iso);
}
fputcsv($out, $header, ';');

foreach ($offices as $office) {
    $row = [$office];
    foreach ($days as $iso) {
        $row[] = (string)intval($countsByOfficeDate[$office][$iso] ?? 0);
    }
    fputcsv($out, $row, ';');
}

fclose($out);
exit;