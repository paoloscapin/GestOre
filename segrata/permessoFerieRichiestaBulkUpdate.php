<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

global $__con;

if (!isset($_POST['richiesta_id']) || !isset($_POST['stato_giorno'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parametri mancanti'], JSON_UNESCAPED_UNICODE);
    exit;
}

$richiestaId = intval($_POST['richiesta_id']);
$statoGiorno = strtoupper(trim((string)$_POST['stato_giorno']));
$notaApprovatore = trim((string)($_POST['nota_approvatore'] ?? ''));

if ($richiestaId <= 0 || !in_array($statoGiorno, ['APPROVATO','RESPINTO'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Dati non validi'], JSON_UNESCAPED_UNICODE);
    exit;
}

$richiesta = dbGetFirst("
    SELECT
        req.*,
        t.codice AS tipo_codice
    FROM permesso_ata_richiesta req
    JOIN permesso_ata_tipo t ON t.id = req.permesso_ata_tipo_id
    WHERE req.id = $richiestaId
    LIMIT 1
");

if (!$richiesta || !is_array($richiesta)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Richiesta non trovata'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($richiesta['tipo_codice'] ?? '') !== 'FERIE') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'La richiesta non è FERIE'], JSON_UNESCAPED_UNICODE);
    exit;
}

$righe = dbGetAll("
    SELECT id, dettagli_json
    FROM permesso_ata_richiesta_riga
    WHERE permesso_ata_richiesta_id = $richiestaId
");

if (!is_array($righe) || count($righe) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nessuna riga ferie trovata'], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach ($righe as $rr) {
    $det = [];
    if (!empty($rr['dettagli_json'])) {
        $tmp = json_decode($rr['dettagli_json'], true);
        if (is_array($tmp)) {
            $det = $tmp;
        }
    }

    $det['stato_giorno'] = $statoGiorno;
    $det['nota_approvatore'] = $notaApprovatore;

    $detJsonEsc = mysqli_real_escape_string($__con, json_encode($det, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $ok = dbExec("
        UPDATE permesso_ata_richiesta_riga
        SET dettagli_json = '$detJsonEsc'
        WHERE id = " . intval($rr['id']) . "
        LIMIT 1
    ");

    if ($ok === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Errore aggiornamento righe'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$righeRichiesta = dbGetAll("
    SELECT id, dettagli_json
    FROM permesso_ata_richiesta_riga
    WHERE permesso_ata_richiesta_id = $richiestaId
");

if (!is_array($righeRichiesta)) {
    $righeRichiesta = [];
}

$cntRichiesti = 0;
$cntApprovati = 0;
$cntRespinti = 0;

foreach ($righeRichiesta as $rr) {
    $dj = [];
    if (!empty($rr['dettagli_json'])) {
        $tmp = json_decode($rr['dettagli_json'], true);
        if (is_array($tmp)) {
            $dj = $tmp;
        }
    }

    $sg = strtoupper((string)($dj['stato_giorno'] ?? 'RICHIESTO'));

    if ($sg === 'APPROVATO') {
        $cntApprovati++;
    } elseif ($sg === 'RESPINTO') {
        $cntRespinti++;
    } else {
        $cntRichiesti++;
    }
}

$nuovoStato = 'INVIATO';
if ($cntApprovati > 0 && $cntRespinti === 0 && $cntRichiesti === 0) {
    $nuovoStato = 'APPROVATO';
} elseif ($cntRespinti > 0 && $cntApprovati === 0 && $cntRichiesti === 0) {
    $nuovoStato = 'RESPINTO';
} elseif ($cntApprovati > 0 || $cntRespinti > 0) {
    $nuovoStato = 'PARZIALE';
}

$headDet = [];
if (!empty($richiesta['dettagli_json'])) {
    $tmp = json_decode($richiesta['dettagli_json'], true);
    if (is_array($tmp)) {
        $headDet = $tmp;
    }
}

$headDet['giorni_richiesti_count'] = count($righeRichiesta);
$headDet['giorni_approvati_count'] = $cntApprovati;
$headDet['giorni_respinti_count'] = $cntRespinti;

$headDetJsonEsc = mysqli_real_escape_string($__con, json_encode($headDet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$nuovoStatoEsc = mysqli_real_escape_string($__con, $nuovoStato);

$gestitoDa = (isset($__utente_id) && intval($__utente_id) > 0) ? intval($__utente_id) : "NULL";

$ok = dbExec("
    UPDATE permesso_ata_richiesta
    SET
        stato = '$nuovoStatoEsc',
        dettagli_json = '$headDetJsonEsc',
        updated_at = NOW(),
        gestito_il = NOW(),
        gestito_da_utente_id = $gestitoDa
    WHERE id = $richiestaId
    LIMIT 1
");

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore aggiornamento richiesta'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'richiesta_id' => $richiestaId,
    'stato_richiesta' => $nuovoStato,
    'giorni' => [
        'richiesti' => $cntRichiesti,
        'approvati' => $cntApprovati,
        'respinti' => $cntRespinti
    ]
], JSON_UNESCAPED_UNICODE);