<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('genitore');
header('Content-Type: application/json; charset=utf-8');

function genitoreTelegramToggleTableExists(string $tableName): bool
{
    return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

try {
    if (!genitoreTelegramToggleTableExists('genitore_telegram')) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Tabella Telegram genitori non presente. Applica prima lo script SQL dedicato.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idGenitore = (int)($__genitore_id ?? 0);
    if ($idGenitore <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Genitore non autenticato'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : null;
    if (!in_array($enabled, [0, 1], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Parametro enabled non valido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tg = dbGetFirst("
        SELECT idGenitore, telegram_chat_id
        FROM genitore_telegram
        WHERE idGenitore = " . dbI($idGenitore) . "
        LIMIT 1
    ");

    if (!$tg) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Il tuo profilo Telegram non è ancora configurato in GestOre. Prima devi associare il tuo account Telegram.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (trim((string)($tg['telegram_chat_id'] ?? '')) === '') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Il tuo account Telegram non è ancora associato al profilo genitore.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    dbExec("
        UPDATE genitore_telegram
        SET attivo = 1,
            consenso_notifiche = " . dbI($enabled) . "
        WHERE idGenitore = " . dbI($idGenitore) . "
    ");

    echo json_encode([
        'ok' => true,
        'enabled' => ((int)$enabled === 1),
        'message' => ((int)$enabled === 1)
            ? 'Notifiche Telegram abilitate'
            : 'Notifiche Telegram disabilitate'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
