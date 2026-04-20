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

function ferieDashboardResolvePeriod(string $finestra, string $dateFrom, string $dateTo): ?array
{
    if ($finestra === 'ORDINARIE') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            return null;
        }
        if ($dateTo < $dateFrom) {
            return null;
        }

        return [
            'codice' => 'ORDINARIE',
            'data_inizio' => $dateFrom,
            'data_fine' => $dateTo
        ];
    }

    return dbGetFirst("
        SELECT id, codice, data_inizio, data_fine
        FROM permesso_ata_ferie_finestra
        WHERE UPPER(TRIM(codice)) = " . dbQ($finestra) . "
          AND (valido IS NULL OR valido = 1)
        LIMIT 1
    ");
}

$finestra = strtoupper(trim((string)($_GET['finestra'] ?? 'ESTIVE')));
$mode = strtoupper(trim((string)($_GET['mode'] ?? 'APPROVATI_E_RICHIESTI')));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$allowedFinestra = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA', 'ORDINARIE'];
$allowedMode = ['APPROVATI_E_RICHIESTI', 'APPROVATI_ONLY', 'RICHIESTI_ONLY'];

if (!in_array($finestra, $allowedFinestra, true)) {
    $finestra = 'ESTIVE';
}
if (!in_array($mode, $allowedMode, true)) {
    $mode = 'APPROVATI_E_RICHIESTI';
}

$win = ferieDashboardResolvePeriod($finestra, $dateFrom, $dateTo);

if (!$win || !is_array($win) || empty($win['data_inizio']) || empty($win['data_fine'])) {
    http_response_code(404);
    echo "Finestra ferie non trovata";
    exit;
}

$days = expandDateRangeIso($win['data_inizio'], $win['data_fine']);
$daySet = array_fill_keys($days, true);

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
        pr.codice AS profilo_codice,
        u.nome AS ufficio_nome
    FROM permesso_ata_richiesta req
    JOIN permesso_ata_tipo t
      ON t.id = req.permesso_ata_tipo_id
    JOIN permesso_ata_richiesta_riga rr
      ON rr.permesso_ata_richiesta_id = req.id
    JOIN personale_ata p
      ON p.id = req.personale_ata_id
    LEFT JOIN personale_ata_profili pr
      ON pr.id = p.id_profilo
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

$people = []; // key = username|nome

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

    $username = trim((string)($r['username'] ?? ''));
    $nome = trim((string)($r['cognome'] ?? '') . ' ' . (string)($r['nome'] ?? ''));
    if ($nome === '') {
        $nome = $username !== '' ? $username : 'Utente sconosciuto';
    }

    $profilo = trim((string)($r['profilo_codice'] ?? ''));
    $ufficio = trim((string)($r['ufficio_nome'] ?? 'Senza ufficio'));
    if ($ufficio === '') $ufficio = 'Senza ufficio';

    $key = $username !== '' ? $username : md5($nome . '|' . $profilo . '|' . $ufficio);

    if (!isset($people[$key])) {
        $people[$key] = [
            'nome' => $nome,
            'profilo' => $profilo,
            'ufficio' => $ufficio,
            'days' => array_fill_keys($days, '')
        ];
    }

    $range = expandDateRangeIso((string)($r['data_dal'] ?? ''), (string)($r['data_al'] ?? ''));
    foreach ($range as $iso) {
        if (!isset($daySet[$iso])) continue;
        $people[$key]['days'][$iso] = 'X';
    }
}

/*
 * Ordino per ufficio, poi nome
 */
uasort($people, function ($a, $b) {
    $cmp = strcasecmp((string)$a['ufficio'], (string)$b['ufficio']);
    if ($cmp !== 0) return $cmp;

    $cmp = strcasecmp((string)$a['profilo'], (string)$b['profilo']);
    if ($cmp !== 0) return $cmp;

    return strcasecmp((string)$a['nome'], (string)$b['nome']);
});

$filename = 'ferie_persone_giorni_' . strtolower($finestra) . '_' . $win['data_inizio'] . '_' . $win['data_fine'] . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

$header = ['Nome', 'Profilo', 'Ufficio'];
foreach ($days as $iso) {
    $header[] = fmtDateCsv($iso);
}
fputcsv($out, $header, ';');

foreach ($people as $p) {
    $row = [
        $p['nome'],
        $p['profilo'],
        $p['ufficio']
    ];

    foreach ($days as $iso) {
        $row[] = $p['days'][$iso] ?? '';
    }

    fputcsv($out, $row, ';');
}

fclose($out);
exit;
