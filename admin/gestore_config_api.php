<?php

require_once '../common/checkSession.php';

ruoloRichiesto('admin');

header('Content-Type: application/json; charset=UTF-8');

function gestoreConfigRespond($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function gestoreConfigFilePath()
{
    $path = __DIR__ . '/../GestOre.json';
    $realPath = realpath($path);
    return $realPath !== false ? $realPath : $path;
}

function gestoreConfigBackupDir()
{
    return __DIR__ . '/../log/config_backups';
}

function gestoreConfigRead()
{
    $path = gestoreConfigFilePath();
    if (!is_file($path)) {
        gestoreConfigRespond(['ok' => false, 'error' => 'File GestOre.json non trovato.'], 404);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        gestoreConfigRespond(['ok' => false, 'error' => 'Impossibile leggere GestOre.json.'], 500);
    }
    return $raw;
}

function gestoreConfigDecode($raw)
{
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        gestoreConfigRespond([
            'ok' => false,
            'error' => 'JSON non valido: ' . json_last_error_msg(),
        ], 400);
    }
    if (!is_array($data)) {
        gestoreConfigRespond(['ok' => false, 'error' => 'La configurazione deve essere un oggetto JSON.'], 400);
    }
    return $data;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'load';

if ($action === 'load') {
    $raw = gestoreConfigRead();
    $data = gestoreConfigDecode($raw);
    gestoreConfigRespond([
        'ok' => true,
        'config' => $data,
        'checksum' => hash('sha256', $raw),
        'modifiedAt' => date('d/m/Y H:i:s', filemtime(gestoreConfigFilePath())),
    ]);
}

if ($action === 'save') {
    $postedRaw = $_POST['json'] ?? '';
    $checksum = $_POST['checksum'] ?? '';
    $force = ($_POST['force'] ?? '') === '1';

    if (trim($postedRaw) === '') {
        gestoreConfigRespond(['ok' => false, 'error' => 'JSON vuoto.'], 400);
    }

    $currentRaw = gestoreConfigRead();
    $currentChecksum = hash('sha256', $currentRaw);
    if (!$force && $checksum !== '' && !hash_equals($currentChecksum, $checksum)) {
        gestoreConfigRespond([
            'ok' => false,
            'conflict' => true,
            'error' => 'GestOre.json e stato modificato dopo il caricamento della pagina. Ricarica prima di salvare, oppure forza il salvataggio.',
            'currentChecksum' => $currentChecksum,
        ], 409);
    }

    $data = gestoreConfigDecode($postedRaw);
    $pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($pretty === false) {
        gestoreConfigRespond(['ok' => false, 'error' => 'Impossibile serializzare il JSON.'], 500);
    }
    $pretty .= "\n";

    $backupDir = gestoreConfigBackupDir();
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
        gestoreConfigRespond(['ok' => false, 'error' => 'Impossibile creare la cartella backup configurazione.'], 500);
    }

    $backupFile = $backupDir . '/GestOre_' . date('Ymd_His') . '.json';
    if (file_put_contents($backupFile, $currentRaw, LOCK_EX) === false) {
        gestoreConfigRespond(['ok' => false, 'error' => 'Impossibile creare il backup prima del salvataggio.'], 500);
    }

    $configFile = gestoreConfigFilePath();
    if (file_put_contents($configFile, $pretty, LOCK_EX) === false) {
        gestoreConfigRespond(['ok' => false, 'error' => 'Backup creato, ma salvataggio GestOre.json non riuscito.'], 500);
    }
    clearstatcache(true, $configFile);

    $writtenRaw = file_get_contents($configFile);
    if ($writtenRaw === false || hash('sha256', $writtenRaw) !== hash('sha256', $pretty)) {
        gestoreConfigRespond([
            'ok' => false,
            'error' => 'Backup creato, ma la verifica dopo il salvataggio non corrisponde al contenuto inviato.',
        ], 500);
    }

    gestoreConfigRespond([
        'ok' => true,
        'message' => 'Configurazione salvata correttamente.',
        'checksum' => hash('sha256', $writtenRaw),
        'modifiedAt' => date('d/m/Y H:i:s', filemtime($configFile)),
        'backup' => basename($backupFile),
    ]);
}

gestoreConfigRespond(['ok' => false, 'error' => 'Azione non riconosciuta.'], 400);
