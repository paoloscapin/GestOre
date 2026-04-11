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

function failCfgDay($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$sottotipo = strtoupper(trim((string)($_POST['sottotipo'] ?? '')));
$dataGiorno = trim((string)($_POST['data_giorno'] ?? ''));
$tipo = 'ESCLUDI';
$descrizione = trim((string)($_POST['descrizione'] ?? ''));
$valido = intval($_POST['valido'] ?? 0) ? 1 : 0;

$allowedSottotipo = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA'];

if (!in_array($sottotipo, $allowedSottotipo, true)) {
    failCfgDay('Sottotipo non valido');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataGiorno)) {
    failCfgDay('Data giorno non valida');
}
if (!in_array($tipo, $allowedTipo, true)) {
    failCfgDay('Tipo non valido');
}
if ($descrizione === '') {
    failCfgDay('Inserisci una descrizione');
}

/*
 * Il giorno deve ricadere dentro una finestra del medesimo sottotipo.
 */
$stmt = $__con->prepare("
    SELECT id
    FROM permesso_ata_ferie_finestra
    WHERE UPPER(TRIM(codice)) = ?
      AND ? BETWEEN data_inizio AND data_fine
      AND (valido IS NULL OR valido = 1)
    LIMIT 1
");
if (!$stmt) failCfgDay('Errore verifica finestra', 500);
$stmt->bind_param('ss', $sottotipo, $dataGiorno);
$stmt->execute();
$res = $stmt->get_result();
$win = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$win) {
    failCfgDay('La data scelta non ricade in una finestra attiva del sottotipo selezionato');
}

/*
 * Evita duplicati sullo stesso sottotipo/data/tipo
 */
$sqlDup = "
    SELECT id
    FROM permesso_ata_ferie_giorni_speciali
    WHERE UPPER(TRIM(sottotipo)) = ?
      AND data_giorno = ?
      AND UPPER(TRIM(tipo)) = ?
";
if ($id > 0) {
    $sqlDup .= " AND id <> " . intval($id);
}
$sqlDup .= " LIMIT 1";

$stmt = $__con->prepare($sqlDup);
if (!$stmt) failCfgDay('Errore verifica duplicato', 500);
$stmt->bind_param('sss', $sottotipo, $dataGiorno, $tipo);
$stmt->execute();
$res = $stmt->get_result();
$dup = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($dup) {
    failCfgDay('Esiste già un giorno speciale uguale per questo sottotipo e questa data');
}

if ($id > 0) {
    $stmt = $__con->prepare("
        UPDATE permesso_ata_ferie_giorni_speciali
        SET sottotipo = ?, data_giorno = ?, tipo = ?, descrizione = ?, valido = ?
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) failCfgDay('Errore update giorno speciale', 500);
    $stmt->bind_param('ssssii', $sottotipo, $dataGiorno, $tipo, $descrizione, $valido, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) failCfgDay('Errore salvataggio giorno speciale', 500);
} else {
    $stmt = $__con->prepare("
        INSERT INTO permesso_ata_ferie_giorni_speciali (sottotipo, data_giorno, tipo, descrizione, valido)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) failCfgDay('Errore insert giorno speciale', 500);
    $stmt->bind_param('ssssi', $sottotipo, $dataGiorno, $tipo, $descrizione, $valido);
    $ok = $stmt->execute();
    $newId = intval($__con->insert_id);
    $stmt->close();

    if (!$ok) failCfgDay('Errore creazione giorno speciale', 500);
    $id = $newId;
}

echo json_encode([
    'ok' => true,
    'id' => $id
], JSON_UNESCAPED_UNICODE);