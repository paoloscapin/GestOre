<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');

if (!function_exists('googleDriveDownloadFileContent')) {
  function googleDriveDownloadFileContent(string $fileId): array
  {
    $fileId = trim($fileId);
    if ($fileId === '') {
      throw new Exception('ID file Drive mancante');
    }

    $accessToken = googleDriveAccessToken();
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
      ],
      CURLOPT_TIMEOUT => 600,
    ]);

    $response = curl_exec($ch);
    $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $headerSize = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
      throw new Exception('Errore CURL Google Drive download: ' . $curlError);
    }

    $body = substr((string)$response, $headerSize);
    if ($httpCode < 200 || $httpCode >= 300) {
      throw new Exception('Errore Google Drive download HTTP ' . $httpCode . ': ' . $body);
    }

    return [
      'content' => $body,
      'contentType' => $contentType !== '' ? $contentType : 'application/octet-stream',
      'size' => strlen($body),
    ];
  }
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$forceDownload = isset($_GET['download']) && $_GET['download'] == '1';
$disposition = $forceDownload ? 'attachment' : 'inline';

if ($id<=0) { http_response_code(400); exit; }

$row = dbGetFirst("
  SELECT a.*, bd.docente_id, bd.anno_scolastico_id
  FROM bonus_docente_allegato a
  JOIN bonus_docente bd ON bd.id = a.bonus_docente_id
  WHERE a.id = $id
");
if (!$row) {
  http_response_code(404);
  exit;
}

if (!$row) { http_response_code(404); exit; }

if (!haRuolo('dirigente') && intval($row['docente_id']) !== intval($__docente_id)) {
  http_response_code(403); exit;
}

$anno = intval($row['anno_scolastico_id']);
$downloadName = str_replace('"','', (string)$row['original_name']);

if (($row['storage_type'] ?? 'LOCAL') === 'DRIVE') {
  $fileId = trim((string)($row['drive_file_id'] ?? ''));
  if ($fileId === '') {
    http_response_code(404);
    exit;
  }

  try {
    $download = googleDriveDownloadFileContent($fileId);
  } catch (Throwable $e) {
    http_response_code(502);
    echo 'Errore download Drive: ' . htmlspecialchars($e->getMessage());
    exit;
  }

  header('Content-Type: ' . $download['contentType']);
  header('Content-Length: ' . intval($download['size']));
  header('Content-Disposition: '.$disposition.'; filename="' . $downloadName . '"');
  header('Cache-Control: private, max-age=0, must-revalidate');
  header('Pragma: public');
  echo $download['content'];
  exit;
}

$baseDir = realpath(__DIR__ . '/bonus_upload');
if (!$baseDir) { http_response_code(500); exit; }

$filePath = $baseDir . '/' . $anno . '/' . intval($row['docente_id']) . '/' . intval($row['bonus_docente_id']) . '/' . $row['stored_name'];
if (!is_file($filePath)) { http_response_code(404); exit; }

header('Content-Type: ' . (($row['mime_type'] ?? '') !== '' ? $row['mime_type'] : 'application/pdf'));
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: '.$disposition.'; filename="' . $downloadName . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($filePath);
exit;
