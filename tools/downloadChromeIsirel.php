<?php
/**
 * Scarica automaticamente lo ZIP dell'estensione Chrome ISIREL pagoPA.
 * Legge la versione da tools/chrome-isirel-pagopa/manifest.json
 * e genera: GestOre-Import-ISIREL-vX.Y.Z.zip
 */

require_once __DIR__ . '/../common/connect.php';

$extensionDir = __DIR__ . '/chrome-isirel-pagopa';
$manifestPath = $extensionDir . '/manifest.json';

if (!is_dir($extensionDir)) {
    http_response_code(404);
    exit('Cartella estensione non trovata.');
}

if (!file_exists($manifestPath)) {
    http_response_code(404);
    exit('manifest.json non trovato.');
}

$manifest = json_decode(file_get_contents($manifestPath), true);

if (!is_array($manifest) || empty($manifest['version'])) {
    http_response_code(500);
    exit('Versione non trovata nel manifest.json.');
}

$version = preg_replace('/[^0-9A-Za-z\.\-_]/', '', $manifest['version']);
$downloadName = 'GestOre-Import-ISIREL-v' . $version . '.zip';

$tmpZip = tempnam(sys_get_temp_dir(), 'isirel_ext_');

if ($tmpZip === false) {
    http_response_code(500);
    exit('Impossibile creare file temporaneo.');
}

$zip = new ZipArchive();

if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Impossibile creare ZIP.');
}

$excludeDirs = [
    '.git',
    '.vscode',
    '__MACOSX',
    'node_modules'
];

$excludeFiles = [
    '.DS_Store',
    'Thumbs.db'
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($extensionDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    $path = $file->getPathname();
    $relativePath = substr($path, strlen($extensionDir) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    $parts = explode('/', $relativePath);
    $skip = false;

    foreach ($parts as $part) {
        if (in_array($part, $excludeDirs, true)) {
            $skip = true;
            break;
        }
    }

    if ($skip) {
        continue;
    }

    if ($file->isFile() && in_array($file->getFilename(), $excludeFiles, true)) {
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else {
        $zip->addFile($path, $relativePath);
    }
}

$zip->close();

if (!file_exists($tmpZip) || filesize($tmpZip) <= 0) {
    @unlink($tmpZip);
    http_response_code(500);
    exit('ZIP generato vuoto.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($tmpZip);
@unlink($tmpZip);
exit;