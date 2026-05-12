<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/googleCalendarLib.php';
require_once __DIR__ . '/../common/__Log.php';

header('Content-Type: application/json; charset=utf-8');

ruoloRichiesto('admin');

try {
    if (isset($_GET['id']) && intval($_GET['id']) > 0) {
        $config = dbGetFirst("
            SELECT *
            FROM google_calendar_config
            WHERE id = " . intval($_GET['id']) . "
              AND attivo = 1
        ");

        if ($config == null) {
            throw new Exception('Calendario non trovato o non attivo');
        }

        $res = googleCalendarStartWatchForConfig($config);
    } else {
        $res = googleCalendarStartWatchAll();
    }

    echo json_encode([
        'ok' => true,
        'response' => $res,
        'time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}