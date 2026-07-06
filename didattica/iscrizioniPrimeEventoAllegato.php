<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$eventId = intval($_GET['evento_id'] ?? 0);
if ($eventId <= 0) {
    http_response_code(400);
    echo 'Richiesta non valida.';
    exit;
}

iscrizioniPrimeEnsureSchema();
$event = dbGetFirst("
    SELECT allegato_path, allegato_original_name
    FROM iscrizioni_prime_eventi
    WHERE id = " . dbI($eventId) . "
      AND allegato_path IS NOT NULL
      AND allegato_path <> ''
    LIMIT 1
");

if (!$event) {
    http_response_code(404);
    echo 'Allegato non disponibile.';
    exit;
}

$absolute = realpath(__DIR__ . '/../' . (string)$event['allegato_path']);
$base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_eventi');
if (!$absolute || !$base || strpos($absolute, $base) !== 0 || !is_file($absolute)) {
    http_response_code(404);
    echo 'Allegato non disponibile.';
    exit;
}

$name = basename((string)($event['allegato_original_name'] ?? 'allegato'));
$name = preg_replace('/[^A-Za-z0-9_. -]+/', '_', $name);
$extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
$mime = $extension === 'pdf' ? 'application/pdf' : (in_array($extension, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png');

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($absolute));
readfile($absolute);
