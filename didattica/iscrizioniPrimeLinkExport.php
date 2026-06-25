<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');

$rows = dbGetAll("
    SELECT
        id,
        anno_scolastico,
        codice_domanda,
        codice_fiscale,
        cognome,
        nome,
        email_genitore_1,
        email_genitore_2,
        telefono_genitore_1,
        telefono_genitore_2
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
      AND " . iscrizioniPrimeEffectiveExternalCondition('iscrizioni_prime_pratiche') . "
      AND stato IN ('importata', 'bozza', 'da_integrare')
    ORDER BY cognome ASC, nome ASC
");

$filename = 'iscrizioni_' . $tipoIscrizione . '_link_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'anno_scolastico',
    'codice_domanda',
    'codice_fiscale',
    'cognome',
    'nome',
    'email_genitore_1',
    'email_genitore_2',
    'telefono_genitore_1',
    'telefono_genitore_2',
    'link_conferma',
]);

foreach ($rows as $row) {
    $token = iscrizioniPrimeSetToken((int)$row['id']);
    $link = ($GLOBALS['__http_base_link'] ?? '') . '/iscrizioni/conferma.php?t=' . rawurlencode($token);

    fputcsv($out, [
        $row['anno_scolastico'] ?? '',
        $row['codice_domanda'] ?? '',
        $row['codice_fiscale'] ?? '',
        $row['cognome'] ?? '',
        $row['nome'] ?? '',
        $row['email_genitore_1'] ?? '',
        $row['email_genitore_2'] ?? '',
        $row['telefono_genitore_1'] ?? '',
        $row['telefono_genitore_2'] ?? '',
        $link,
    ]);
}

fclose($out);
