<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

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

function fmtDateIT($iso)
{
    if (!$iso) return '';
    $ts = strtotime($iso);
    if (!$ts) return (string)$iso;
    return date('d/m/Y', $ts);
}

function weekendReasonIso($iso)
{
    $ts = strtotime($iso);
    if (!$ts) return '';
    $n = (int)date('N', $ts); // 6=sab, 7=dom
    if ($n === 6) return 'Sabato';
    if ($n === 7) return 'Domenica';
    return '';
}

function isWeekendIso($iso)
{
    $ts = strtotime($iso);
    if (!$ts) return false;
    $n = (int)date('N', $ts); // 6=sab, 7=dom
    return ($n === 6 || $n === 7);
}

$finestra = strtoupper(trim((string)($_GET['finestra'] ?? 'ESTIVE')));
$mode = strtoupper(trim((string)($_GET['mode'] ?? 'APPROVATI_E_RICHIESTI')));

$allowedFinestra = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA'];
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
    echo json_encode([
        'ok' => false,
        'error' => 'Finestra ferie non trovata'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$days = expandDateRangeIso($win['data_inizio'], $win['data_fine']);
$daySet = array_fill_keys($days, true);

/**
 * Giorni chiusi:
 * - sabato e domenica
 * - giorni speciali di tipo ESCLUSO / ESCLUDI
 */
$closedDaysMap = [];
$closedDayReasons = [];

foreach ($days as $iso) {
    if (isWeekendIso($iso)) {
        $closedDaysMap[$iso] = true;
        $closedDayReasons[$iso] = weekendReasonIso($iso);
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
        $descrizione = trim((string)($g['descrizione'] ?? ''));

        if ($iso !== '' && isset($daySet[$iso]) && in_array($tipo, ['ESCLUSO', 'ESCLUDI'], true)) {
            $closedDaysMap[$iso] = true;
            $closedDayReasons[$iso] = $descrizione !== '' ? $descrizione : 'Giorno di chiusura';
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
    ORDER BY rr.data_dal ASC, u.nome ASC, p.cognome ASC, p.nome ASC
");

if (!is_array($rows)) {
    $rows = [];
}

$countsByOfficeDate = [];
$namesByOfficeDate = [];
$officeTotals = [];
$officePeak = [];
$totalsByDate = array_fill_keys($days, 0);

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
    if ($office === '') {
        $office = 'Senza ufficio';
    }

    $fullName = trim((string)($r['cognome'] ?? '') . ' ' . (string)($r['nome'] ?? ''));
    if ($fullName === '') {
        $fullName = (string)($r['username'] ?? 'Utente');
    }

    $from = $r['data_dal'] ?? '';
    $to   = $r['data_al'] ?? '';
    if ($to === '') $to = $from;

    foreach (expandDateRangeIso($from, $to) as $iso) {
        if (!isset($daySet[$iso])) {
            continue;
        }

        if (!isset($countsByOfficeDate[$office])) {
            $countsByOfficeDate[$office] = array_fill_keys($days, 0);
        }
        if (!isset($namesByOfficeDate[$office])) {
            $namesByOfficeDate[$office] = [];
        }
        if (!isset($namesByOfficeDate[$office][$iso])) {
            $namesByOfficeDate[$office][$iso] = [];
        }

        $countsByOfficeDate[$office][$iso]++;
        $totalsByDate[$iso]++;

        if (!in_array($fullName, $namesByOfficeDate[$office][$iso], true)) {
            $namesByOfficeDate[$office][$iso][] = $fullName;
        }

        if (!isset($officeTotals[$office])) {
            $officeTotals[$office] = 0;
        }
        $officeTotals[$office]++;

        if (!isset($officePeak[$office]) || $countsByOfficeDate[$office][$iso] > $officePeak[$office]) {
            $officePeak[$office] = $countsByOfficeDate[$office][$iso];
        }
    }
}

ksort($countsByOfficeDate);

$series = [];
foreach ($countsByOfficeDate as $office => $dateMap) {
    $vals = [];
    foreach ($days as $iso) {
        $vals[] = intval($dateMap[$iso] ?? 0);
    }

    $series[] = [
        'label' => $office,
        'data' => $vals
    ];
}

$peakDate = '';
$peakValue = 0;
foreach ($totalsByDate as $iso => $v) {
    if ($v > $peakValue) {
        $peakValue = $v;
        $peakDate = $iso;
    }
}

$officeSummary = [];
foreach ($countsByOfficeDate as $office => $dateMap) {
    $activeDays = 0;
    foreach ($days as $iso) {
        if (intval($dateMap[$iso] ?? 0) > 0) {
            $activeDays++;
        }
    }

    $officeSummary[] = [
        'ufficio' => $office,
        'totale_giorni_persona' => intval($officeTotals[$office] ?? 0),
        'picco_giornaliero' => intval($officePeak[$office] ?? 0),
        'giorni_coinvolti' => $activeDays
    ];
}

echo json_encode([
    'ok' => true,
    'finestra' => [
        'codice' => $finestra,
        'data_inizio' => $win['data_inizio'],
        'data_fine' => $win['data_fine'],
        'data_inizio_fmt' => fmtDateIT($win['data_inizio']),
        'data_fine_fmt' => fmtDateIT($win['data_fine'])
    ],
    'mode' => $mode,
    'labels' => $days,
    'labels_fmt' => array_map('fmtDateIT', $days),
    'closed_days' => array_values(array_keys($closedDaysMap)),
    'closed_day_reasons' => $closedDayReasons,
    'totali_per_giorno' => array_values($totalsByDate),
    'series' => $series,
    'office_summary' => $officeSummary,
    'names_by_office_date' => $namesByOfficeDate,
    'summary' => [
        'totale_giorni_persona' => array_sum($officeTotals),
        'picco_giornaliero' => $peakValue,
        'data_picco' => $peakDate,
        'data_picco_fmt' => fmtDateIT($peakDate),
        'uffici_presenti' => count($countsByOfficeDate)
    ]
], JSON_UNESCAPED_UNICODE);
