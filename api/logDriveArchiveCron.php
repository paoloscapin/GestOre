<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleDriveLib.php';

header('Content-Type: application/json; charset=utf-8');

global $__settings;

function logDriveArchiveConfig()
{
    global $__settings;
    return $__settings->local->googleDrive ?? null;
}

function logDriveArchiveSecret(): string
{
    global $__settings;
    $cfg = logDriveArchiveConfig();
    $secret = trim((string)($cfg->syncSecret ?? ''));
    if ($secret !== '') {
        return $secret;
    }
    return trim((string)($__settings->local->watch_secret ?? ''));
}

function logDriveArchiveLogDir(): string
{
    global $__settings;
    if (!empty($__settings->log->logIntoAppFolder)) {
        return realpath(__DIR__ . '/../log') ?: (__DIR__ . '/../log');
    }
    return dirname((string)($__settings->log->logFile ?? (__DIR__ . '/../log/GestOre.log')));
}

function logDriveArchiveActiveLogNames(): array
{
    global $__settings;
    $names = [];
    $keys = [
        'logFile',
        'logLoginFile',
        'logCronFile',
        'logImportSostituzioniFile',
        'logTelegramFile',
        'logGoogleCalendarFile',
        'logGoogleCalendarMBAppFile',
        'logGmailFile',
    ];

    foreach ($keys as $key) {
        $value = trim((string)($__settings->log->{$key} ?? ''));
        if ($value !== '') {
            $names[] = basename($value);
        }
    }

    return array_values(array_unique($names));
}

function logDriveArchiveIsRotatedLog(string $fileName, array $activeNames): bool
{
    if (in_array($fileName, $activeNames, true)) {
        return false;
    }

    foreach ($activeNames as $activeName) {
        $info = pathinfo($activeName);
        $base = (string)($info['filename'] ?? '');
        $ext = (string)($info['extension'] ?? '');
        if ($base === '' || $ext === '') {
            continue;
        }

        if (preg_match('/^' . preg_quote($base, '/') . '_\d{2}_\d{2}_\d{4}_\d{2}_\d{2}_\d{2}\.' . preg_quote($ext, '/') . '$/', $fileName)) {
            return true;
        }
    }

    return false;
}

function logDriveArchiveRotatedAt(string $fileName, array $activeNames): ?int
{
    foreach ($activeNames as $activeName) {
        $info = pathinfo($activeName);
        $base = (string)($info['filename'] ?? '');
        $ext = (string)($info['extension'] ?? '');
        if ($base === '' || $ext === '') {
            continue;
        }

        if (preg_match('/^' . preg_quote($base, '/') . '_(\d{2})_(\d{2})_(\d{4})_(\d{2})_(\d{2})_(\d{2})\.' . preg_quote($ext, '/') . '$/', $fileName, $m)) {
            $dt = DateTime::createFromFormat(
                'd_m_Y_H_i_s',
                $m[1] . '_' . $m[2] . '_' . $m[3] . '_' . $m[4] . '_' . $m[5] . '_' . $m[6],
                new DateTimeZone('Europe/Rome')
            );

            return $dt instanceof DateTime ? $dt->getTimestamp() : null;
        }
    }

    return null;
}

function logDriveArchiveCandidates(): array
{
    $logDir = logDriveArchiveLogDir();
    $activeNames = logDriveArchiveActiveLogNames();
    $files = [];
    $todayStart = (new DateTime('today', new DateTimeZone('Europe/Rome')))->getTimestamp();

    if (!is_dir($logDir)) {
        return [];
    }

    foreach (scandir($logDir) ?: [] as $fileName) {
        if ($fileName === '.' || $fileName === '..') {
            continue;
        }
        $path = $logDir . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path)) {
            continue;
        }
        if (!logDriveArchiveIsRotatedLog($fileName, $activeNames)) {
            continue;
        }
        $rotatedAt = logDriveArchiveRotatedAt($fileName, $activeNames);
        if ($rotatedAt === null || $rotatedAt >= $todayStart) {
            continue;
        }

        $files[] = [
            'path' => $path,
            'name' => $fileName,
            'size' => filesize($path),
            'mtime' => filemtime($path),
            'rotated_at' => $rotatedAt,
        ];
    }

    usort($files, function ($a, $b) {
        return intval($a['rotated_at'] ?? 0) <=> intval($b['rotated_at'] ?? 0);
    });

    return $files;
}

function logDriveArchiveDriveName(array $file): string
{
    $date = date('Y-m-d', intval($file['rotated_at'] ?? $file['mtime'] ?? time()));
    return $date . ' - ' . (string)$file['name'];
}

$providedSecret = trim((string)($_GET['secret'] ?? $_POST['secret'] ?? ''));
$expectedSecret = logDriveArchiveSecret();
if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Forbidden',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = logDriveArchiveConfig();
$deleteAfterUpload = !isset($cfg->deleteAfterUpload) || filter_var($cfg->deleteAfterUpload, FILTER_VALIDATE_BOOLEAN);

try {
    $folderId = googleDriveGetLogFolderId();
    $files = logDriveArchiveCandidates();
    $uploaded = [];
    $failed = [];

    foreach ($files as $file) {
        try {
            $driveName = logDriveArchiveDriveName($file);
            $upload = googleDriveUploadFile($file['path'], $driveName, $folderId);
            $deleted = false;
            if ($deleteAfterUpload) {
                $deleted = @unlink($file['path']);
            }
            $uploaded[] = [
                'file' => $file['name'],
                'size' => intval($file['size'] ?? 0),
                'drive_name' => $driveName,
                'drive_id' => $upload['id'] ?? '',
                'deleted' => $deleted,
            ];
            infocron('Archivio log Drive OK: ' . $file['name'] . ' -> ' . ($upload['id'] ?? ''));
        } catch (Throwable $fileError) {
            $failed[] = [
                'file' => $file['name'],
                'error' => $fileError->getMessage(),
            ];
            errorcron('Archivio log Drive KO: ' . $file['name'] . ' | ' . $fileError->getMessage());
        }
    }

    echo json_encode([
        'ok' => empty($failed),
        'folder_id' => $folderId,
        'found' => count($files),
        'uploaded' => count($uploaded),
        'failed' => count($failed),
        'delete_after_upload' => $deleteAfterUpload,
        'items' => $uploaded,
        'errors' => $failed,
        'time' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    errorcron('Archivio log Drive errore generale: ' . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'time' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>
