<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('genitore');
header('Content-Type: application/json; charset=utf-8');

function genitoreTelegramStatusTableExists(string $tableName): bool
{
    return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

try {
    $idGenitore = (int)($__genitore_id ?? 0);
    if ($idGenitore <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Genitore non autenticato'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $genitore = dbGetFirst("
        SELECT id, nome, cognome, email
        FROM genitori
        WHERE id = " . dbI($idGenitore) . "
          AND attivo = 1
        LIMIT 1
    ");

    if (!$genitore) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Genitore non trovato'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $enabled = false;
    $hasTelegramProfile = false;
    $telegramReady = genitoreTelegramStatusTableExists('genitore_telegram');

    if ($telegramReady) {
        $tg = dbGetFirst("
            SELECT attivo, consenso_notifiche, telegram_chat_id
            FROM genitore_telegram
            WHERE idGenitore = " . dbI($idGenitore) . "
            LIMIT 1
        ");

        if ($tg) {
            $hasTelegramProfile = trim((string)($tg['telegram_chat_id'] ?? '')) !== '';
            $enabled = ((int)($tg['attivo'] ?? 0) === 1) && ((int)($tg['consenso_notifiche'] ?? 0) === 1);
        }
    }

    echo json_encode([
        'ok' => true,
        'idGenitore' => $idGenitore,
        'genitore' => trim((string)($genitore['cognome'] ?? '') . ' ' . (string)($genitore['nome'] ?? '')),
        'email' => trim((string)($genitore['email'] ?? '')),
        'enabled' => $enabled,
        'hasTelegramProfile' => $hasTelegramProfile,
        'telegramReady' => $telegramReady
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
