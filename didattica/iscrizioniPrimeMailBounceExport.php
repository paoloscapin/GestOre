<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$tipoIscrizione = isset($_GET['tipo_iscrizione']) ? iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione']) : '';
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$rows = iscrizioniPrimeMailBounceReportRows($tipoIscrizione, $days);
$unmatchedRows = iscrizioniPrimeMailBounceUnmatchedReportRows($days);
$suffix = $tipoIscrizione !== '' ? $tipoIscrizione : 'tutte';
$filename = 'report_bounce_iscrizioni_' . $suffix . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
    'data_bounce',
    'data_invio',
    'collegata_pratica',
    'tipo_iscrizione',
    'studente',
    'codice_fiscale',
    'corso',
    'destinatario',
    'account_invio',
    'tipo_bounce',
    'motivo',
    'dettaglio',
], ';');

foreach ($rows as $row) {
    fputcsv($out, [
        (string)($row['bounced_at'] ?? ''),
        (string)($row['sent_at'] ?? ''),
        'si',
        (string)($row['tipo_iscrizione'] ?? ''),
        trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')),
        (string)($row['codice_fiscale'] ?? ''),
        (string)($row['corso_studi'] ?? ''),
        (string)($row['recipient_email'] ?? ''),
        (string)($row['account_email'] ?? ''),
        (string)($row['bounce_type'] ?? ''),
        (string)($row['bounce_reason'] ?? ''),
        preg_replace('/\s+/', ' ', (string)($row['bounce_snippet'] ?? '')),
    ], ';');
}

if ($tipoIscrizione === '') {
    foreach ($unmatchedRows as $row) {
        fputcsv($out, [
            (string)($row['checked_at'] ?? ''),
            '',
            'no',
            '',
            '',
            '',
            '',
            '',
            (string)($row['account_email'] ?? ''),
            (string)($row['bounce_type'] ?? ''),
            (string)($row['bounce_reason'] ?? ''),
            preg_replace('/\s+/', ' ', trim((string)($row['subject'] ?? '') . ' ' . (string)($row['snippet'] ?? ''))),
        ], ';');
    }
}

fclose($out);

?>
