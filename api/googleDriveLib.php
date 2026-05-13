<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/google-client-library/src/Google_Client.php';

define('GOOGLE_DRIVE_TOKEN_FILE', __DIR__ . '/../log/google_drive_token.json');

function googleDriveGetConfig()
{
    global $__settings;

    if (isset($__settings->local->googleDrive)) {
        return $__settings->local->googleDrive;
    }

    if (isset($__settings->GoogleAuth)) {
        return $__settings->GoogleAuth;
    }

    throw new Exception('Configurazione Google Drive mancante in GestOre.json');
}

function googleDriveGetRedirectUri(): string
{
    global $__settings;

    $protocol = (!empty($__settings->system->https) && $__settings->system->https) ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $protocol = strtolower(trim((string)$_SERVER['HTTP_X_FORWARDED_PROTO'])) === 'https' ? 'https' : 'http';
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $protocol = 'https';
    }

    return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/GestOre/api/googleDriveAuth.php';
}

function googleDriveCreateClient(bool $refreshExpired = true): Google_Client
{
    $cfg = googleDriveGetConfig();

    $client = new Google_Client();
    $client->setApplicationName($cfg->applicationName ?? 'GestOre Google Drive');
    $client->setClientId($cfg->clientId ?? '');
    $client->setClientSecret($cfg->clientSecret ?? '');
    $client->setRedirectUri(googleDriveGetRedirectUri());
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');
    $client->setScopes([
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/drive.metadata.readonly',
    ]);

    if (is_file(GOOGLE_DRIVE_TOKEN_FILE)) {
        $token = file_get_contents(GOOGLE_DRIVE_TOKEN_FILE);
        if ($token) {
            $client->setAccessToken($token);
        }
    }

    if ($refreshExpired && is_file(GOOGLE_DRIVE_TOKEN_FILE) && $client->isAccessTokenExpired()) {
        $oldToken = is_file(GOOGLE_DRIVE_TOKEN_FILE)
            ? json_decode((string)file_get_contents(GOOGLE_DRIVE_TOKEN_FILE), true)
            : [];
        $refreshToken = $oldToken['refresh_token'] ?? '';
        if ($refreshToken === '') {
            throw new Exception('Refresh token Google Drive mancante: autorizza prima Google Drive');
        }

        $client->refreshToken($refreshToken);
        $newToken = json_decode((string)$client->getAccessToken(), true);
        if (empty($newToken['refresh_token'])) {
            $newToken['refresh_token'] = $refreshToken;
        }
        googleDriveSaveToken($newToken);
        $client->setAccessToken(json_encode($newToken));
    }

    return $client;
}

function googleDriveSaveToken(array $token): void
{
    $dir = dirname(GOOGLE_DRIVE_TOKEN_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents(GOOGLE_DRIVE_TOKEN_FILE, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function googleDriveAccessToken(): string
{
    $client = googleDriveCreateClient();
    $token = json_decode((string)$client->getAccessToken(), true);
    if (empty($token['access_token'])) {
        throw new Exception('Access token Google Drive non disponibile');
    }
    return $token['access_token'];
}

function googleDriveApiRequest(string $method, string $url, $body = null, array $headers = []): array
{
    $accessToken = googleDriveAccessToken();
    $ch = curl_init();
    $requestHeaders = array_merge([
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ], $headers);

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_TIMEOUT => 180,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Errore CURL Google Drive: ' . $curlError);
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Errore Google Drive HTTP ' . $httpCode . ': ' . $response);
    }

    $decoded = json_decode((string)$response, true);
    return is_array($decoded) ? $decoded : [];
}

function googleDriveFindFolderByName(string $folderName): string
{
    $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and name='" . str_replace("'", "\\'", $folderName) . "'";
    $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name)&pageSize=1';
    $res = googleDriveApiRequest('GET', $url);
    return (string)($res['files'][0]['id'] ?? '');
}

function googleDriveCreateFolder(string $folderName): string
{
    $res = googleDriveApiRequest('POST', 'https://www.googleapis.com/drive/v3/files?fields=id,name', [
        'name' => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder',
    ]);
    return (string)($res['id'] ?? '');
}

function googleDriveGetLogFolderId(): string
{
    $cfg = googleDriveGetConfig();
    $folderId = trim((string)($cfg->logFolderId ?? $cfg->folderId ?? ''));
    if ($folderId !== '') {
        return $folderId;
    }

    $folderName = trim((string)($cfg->logFolderName ?? 'GestOre Log'));
    $folderId = googleDriveFindFolderByName($folderName);
    if ($folderId === '') {
        $folderId = googleDriveCreateFolder($folderName);
    }
    if ($folderId === '') {
        throw new Exception('Impossibile trovare o creare la cartella Google Drive dei log');
    }
    return $folderId;
}

function googleDriveUploadFile(string $path, string $driveName, string $folderId): array
{
    if (!is_file($path)) {
        throw new Exception('File non trovato: ' . $path);
    }

    $accessToken = googleDriveAccessToken();
    $metadata = [
        'name' => $driveName,
        'parents' => [$folderId],
    ];
    $size = filesize($path);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,webViewLink',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: text/plain',
            'X-Upload-Content-Length: ' . $size,
        ],
        CURLOPT_POSTFIELDS => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $headerSize = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Errore CURL Google Drive avvio upload: ' . $curlError);
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Errore Google Drive avvio upload HTTP ' . $httpCode . ': ' . substr((string)$response, $headerSize));
    }

    $headersText = substr((string)$response, 0, $headerSize);
    $uploadUrl = '';
    foreach (preg_split('/\r\n|\r|\n/', $headersText) as $line) {
        if (stripos($line, 'Location:') === 0) {
            $uploadUrl = trim(substr($line, 9));
            break;
        }
    }
    if ($uploadUrl === '') {
        throw new Exception('Google Drive non ha restituito URL resumable upload');
    }

    $fh = fopen($path, 'rb');
    if (!$fh) {
        throw new Exception('Impossibile aprire file log: ' . $path);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $uploadUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/plain',
            'Content-Length: ' . $size,
        ],
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $fh,
        CURLOPT_INFILESIZE => $size,
        CURLOPT_TIMEOUT => 600,
    ]);
    $uploadResponse = curl_exec($ch);
    $uploadHttpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $uploadCurlError = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if ($uploadCurlError) {
        throw new Exception('Errore CURL Google Drive upload file: ' . $uploadCurlError);
    }
    if ($uploadHttpCode < 200 || $uploadHttpCode >= 300) {
        throw new Exception('Errore Google Drive upload file HTTP ' . $uploadHttpCode . ': ' . $uploadResponse);
    }

    $decoded = json_decode((string)$uploadResponse, true);
    return is_array($decoded) ? $decoded : [];
}

?>
