<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once __DIR__ . '/carenzeDownloadLib.php';

function mostraMessaggio(string $titolo, string $messaggio): void {
    echo '
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($titolo) . '</title>
        <style>
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f4f6f9;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .message-box {
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 400px;
            }
            h2 {
                color: #333;
                margin-bottom: 20px;
            }
            p {
                color: #555;
                font-size: 16px;
                margin-bottom: 30px;
            }
            .close-btn {
                background-color: #6c757d;
                color: white;
                border: none;
                padding: 10px 20px;
                font-size: 15px;
                border-radius: 8px;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            .close-btn:hover {
                background-color: #5a6268;
            }
        </style>
        <script>
            function chiudiFinestra() {
                window.open("", "_self");
                window.close();
            }
        </script>
    </head>
    <body>
        <div class="message-box">
            <h2>' . htmlspecialchars($titolo) . '</h2>
            <p>' . nl2br(htmlspecialchars($messaggio)) . '</p>
            <button class="close-btn" onclick="chiudiFinestra()">Chiudi la finestra</button>
        </div>
    </body>
    </html>';
    exit;
}

// Verifica presenza token
if (!isset($_GET['token'])) {
    http_response_code(400);
    mostraMessaggio("Token mancante", "Il link non è valido perché manca il token di identificazione.");
}

date_default_timezone_set('Europe/Rome');
$token = $_GET['token'];
info("Richiesta token per download: " . $token);

// Verifica esistenza token valido nel DB
$selectFields = carenzeDownloadSelectFields();
$query = "SELECT $selectFields FROM carenze_downloads WHERE download_token = '" . escapeString($token) . "' AND expires_at > NOW()";
$result = dbGetFirst($query);

if (!$result) {
    http_response_code(404);
    mostraMessaggio("Link non valido", "Il link è scaduto o non è valido.");
}

$storageType = carenzeDownloadStorageType($result);
$downloadFilename = carenzeDownloadOriginalFilename($result);
$filePath = carenzeDownloadResolveLocalPath((string)$result['file_path']);

if ($storageType !== 'DRIVE' && !file_exists($filePath)) {
    http_response_code(404);
    mostraMessaggio("File non disponibile", "Il file richiesto non è più disponibile sul server.");
}

if ($storageType === 'DRIVE' && (!carenzeDownloadTableHasColumn('drive_file_id') || trim((string)($result['drive_file_id'] ?? '')) === '')) {
    http_response_code(404);
    mostraMessaggio("File non disponibile", "Il file richiesto non Ã¨ piÃ¹ disponibile sul server.");
}

// BLOCCO: controllo tempo prima del download
$lastDownload = $result['last_download'] ?? null;
if ($lastDownload !== null) {
    $lastDownloadTime = strtotime($lastDownload);
    if ((time() - $lastDownloadTime) < 120) {
        mostraMessaggio("Attendi prima di riscaricare", "Hai già scaricato il file.\nAttendi almeno 2 minuti prima di riprovare.");
    }
}

// FORM HTML per confermare download
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm_download'])) {
    echo '
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Conferma Download</title>
        <style>
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f4f6f9;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .download-box {
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                text-align: center;
            }
            h2 {
                color: #333;
                margin-bottom: 20px;
            }
            .download-btn {
                background-color: #0057b7;
                color: white;
                border: none;
                padding: 12px 24px;
                font-size: 16px;
                border-radius: 8px;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            .download-btn:hover {
                background-color: #0045a3;
            }
            .close-btn {
                margin-top: 20px;
                background-color: #6c757d;
                color: white;
                border: none;
                padding: 10px 20px;
                font-size: 15px;
                border-radius: 8px;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            .close-btn:hover {
                background-color: #5a6268;
            }
        </style>
        <script>
            function chiudiFinestra() {
                window.open("", "_self");
                window.close();
            }
        </script>
    </head>
    <body>
        <div class="download-box">
            <h2>Conferma per scaricare il file</h2>
            <form method="post">
                <input type="hidden" name="confirm_download" value="1">
                <button class="download-btn" type="submit">Scarica ora</button>
            </form>
            <button class="close-btn" onclick="chiudiFinestra()">Chiudi la finestra</button>
        </div>
    </body>
    </html>';
    exit;
}

// DOWNLOAD effettivo
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadFilename) . '"');

$downloadOk = false;
if ($storageType === 'DRIVE') {
    require_once __DIR__ . '/../api/googleDriveLib.php';
    try {
        $download = googleDriveDownloadFileContent((string)$result['drive_file_id']);
        header('Content-Length: ' . intval($download['size'] ?? strlen((string)($download['content'] ?? ''))));
        echo (string)($download['content'] ?? '');
        $downloadOk = true;
    } catch (Throwable $e) {
        http_response_code(500);
        mostraMessaggio("Errore download", "Non Ã¨ stato possibile recuperare il file da Google Drive.");
    }
} else {
    header('Content-Length: ' . filesize($filePath));
    $downloadOk = readfile($filePath) !== false;
}

if ($downloadOk) {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $updateQuery = "
        UPDATE carenze_downloads 
        SET 
            last_ip = '$clientIP', 
            download_count = download_count + 1, 
            last_download = NOW(),
            last_user_agent = '$userAgent'
        WHERE download_token = '" . escapeString($token) . "'
    ";
    dbExec($updateQuery);
}

info("Download file eseguito.");
?>
