<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$praticaId = intval($_GET['pratica_id'] ?? 0);
$tipo = trim((string)($_GET['tipo'] ?? ''));
$page = max(1, intval($_GET['page'] ?? 1));
$dpi = min(450, max(180, intval($_GET['dpi'] ?? 300)));
$temporaryFiles = [];

try {
    iscrizioniPrimeEnsureSchema();
    if ($praticaId <= 0 || $tipo === '') {
        throw new RuntimeException('Richiesta non valida.');
    }

    $data = iscrizioniPrimeDocumentForSecretaryEdit($praticaId, $tipo);
    $path = iscrizioniPrimeDocumentPathForAppend($data['document'], $temporaryFiles);
    if (!$path) {
        throw new RuntimeException('PDF non recuperabile.');
    }
    $pageCount = iscrizioniPrimePdfPageCount($path);
    if ($page < 1 || $page > $pageCount) {
        throw new RuntimeException('Pagina PDF non valida.');
    }

    $image = iscrizioniPrimeRenderPdfPageToPng($path, $page, $dpi);
    $temporaryFiles[] = $image;
    $content = file_get_contents($image);
    if ($content === false || $content === '') {
        throw new RuntimeException('Anteprima PDF non disponibile.');
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $content;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} finally {
    foreach ($temporaryFiles as $temporaryFile) {
        if (is_file($temporaryFile)) {
            @unlink($temporaryFile);
        }
    }
    foreach ($temporaryFiles as $temporaryFile) {
        $dir = dirname($temporaryFile);
        if (is_dir($dir) && strpos(basename($dir), 'iscrizioni_crop_') === 0) {
            iscrizioniPrimeRemoveDirectory($dir);
        }
    }
}
