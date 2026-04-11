<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

global $__con;

if (!($__con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Connessione database non disponibile'], JSON_UNESCAPED_UNICODE);
    exit;
}

function failCfg($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$codice = strtoupper(trim((string)($_POST['codice'] ?? '')));
$dataInizio = trim((string)($_POST['data_inizio'] ?? ''));
$dataFine = trim((string)($_POST['data_fine'] ?? ''));
$valido = intval($_POST['valido'] ?? 0) ? 1 : 0;

$allowed = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA'];

if (!in_array($codice, $allowed, true)) {
    failCfg('Codice finestra non valido');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInizio)) {
    failCfg('Data inizio non valida');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFine)) {
    failCfg('Data fine non valida');
}
if ($dataFine < $dataInizio) {
    failCfg('La data fine non può essere precedente alla data inizio');
}

/*
 * Evita sovrapposizioni attive dello stesso codice.
 * Se vuoi consentirle in futuro, basta rimuovere questo controllo.
 */
$sqlOverlap = "
    SELECT id
    FROM permesso_ata_ferie_finestra
    WHERE UPPER(TRIM(codice)) = ?
      AND (valido IS NULL OR valido = 1)
      AND NOT (data_fine < ? OR data_inizio > ?)
";
if ($id > 0) {
    $sqlOverlap .= " AND id <> " . intval($id);
}
$sqlOverlap .= " LIMIT 1";

$stmt = $__con->prepare($sqlOverlap);
if (!$stmt) failCfg('Errore prepare overlap', 500);
$stmt->bind_param('sss', $codice, $dataInizio, $dataFine);
$stmt->execute();
$res = $stmt->get_result();
$overlap = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($overlap) {
    failCfg('Esiste già una finestra attiva sovrapposta per questo sottotipo');
}

if ($id > 0) {
    $stmt = $__con->prepare("
        UPDATE permesso_ata_ferie_finestra
        SET codice = ?, data_inizio = ?, data_fine = ?, valido = ?
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) failCfg('Errore update finestra', 500);
    $stmt->bind_param('sssii', $codice, $dataInizio, $dataFine, $valido, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) failCfg('Errore salvataggio finestra', 500);
} else {
    $stmt = $__con->prepare("
        INSERT INTO permesso_ata_ferie_finestra (codice, data_inizio, data_fine, valido)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) failCfg('Errore insert finestra', 500);
    $stmt->bind_param('sssi', $codice, $dataInizio, $dataFine, $valido);
    $ok = $stmt->execute();
    $newId = intval($__con->insert_id);
    $stmt->close();

    if (!$ok) failCfg('Errore creazione finestra', 500);
    $id = $newId;
}

echo json_encode([
    'ok' => true,
    'id' => $id
], JSON_UNESCAPED_UNICODE);