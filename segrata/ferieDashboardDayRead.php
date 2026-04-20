<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata', 'ras');

header('Content-Type: application/json; charset=utf-8');

$data = trim((string)($_GET['data'] ?? ''));
$finestra = strtoupper(trim((string)($_GET['finestra'] ?? 'ESTIVE')));
$mode = strtoupper(trim((string)($_GET['mode'] ?? 'APPROVATI_E_RICHIESTI')));

if (!$data || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Data non valida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedMode = ['APPROVATI_E_RICHIESTI', 'APPROVATI_ONLY', 'RICHIESTI_ONLY'];
$allowedFinestra = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA', 'ORDINARIE'];
if (!in_array($finestra, $allowedFinestra, true)) {
    $finestra = 'ESTIVE';
}
if (!in_array($mode, $allowedMode, true)) {
    $mode = 'APPROVATI_E_RICHIESTI';
}

$rows = dbGetAll("
    SELECT
        req.id AS richiesta_id,
        rr.id AS riga_id,
        rr.dettagli_json,
        p.cognome,
        p.nome,
        p.username,
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
      AND rr.data_dal <= " . dbQ($data) . "
      AND rr.data_al >= " . dbQ($data) . "
    ORDER BY u.nome ASC, p.cognome ASC, p.nome ASC
");

if (!is_array($rows)) {
    $rows = [];
}

$byOffice = [];
$total = 0;

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

    $nome = trim((string)($r['cognome'] ?? '') . ' ' . (string)($r['nome'] ?? ''));
    if ($nome === '') $nome = (string)($r['username'] ?? 'Utente');

    if (!isset($byOffice[$office])) {
        $byOffice[$office] = [];
    }

    $byOffice[$office][] = [
        'nome' => $nome,
        'profilo' => (string)($r['profilo_codice'] ?? ''),
        'stato_giorno' => $statoGiorno
    ];

    $total++;
}

echo json_encode([
    'ok' => true,
    'data' => $data,
    'totale' => $total,
    'uffici' => $byOffice
], JSON_UNESCAPED_UNICODE);
