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
        'https://www.googleapis.com/auth/drive',
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

function googleDriveUploadFile(string $path, string $driveName, string $folderId, string $mimeType = ''): array
{
    if (!is_file($path)) {
        throw new Exception('File non trovato: ' . $path);
    }

    if ($mimeType === '') {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $path) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
    }

    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }

    $accessToken = googleDriveAccessToken();
    $metadata = [
        'name' => $driveName,
        'parents' => [$folderId],
    ];
    $size = filesize($path);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,mimeType,webViewLink',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: ' . $mimeType,
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
        throw new Exception('Impossibile aprire file: ' . $path);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $uploadUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $mimeType,
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

function googleDriveFindFolderByNameInParent(string $folderName, string $parentId): string
{
    $safeName = str_replace("'", "\\'", $folderName);

    $query = "mimeType='application/vnd.google-apps.folder' "
        . "and trashed=false "
        . "and name='$safeName' "
        . "and '$parentId' in parents";

    $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name)&pageSize=1';
    $res = googleDriveApiRequest('GET', $url);

    return (string)($res['files'][0]['id'] ?? '');
}

function googleDriveCreateFolderInParent(string $folderName, string $parentId): string
{
    $res = googleDriveApiRequest('POST', 'https://www.googleapis.com/drive/v3/files?fields=id,name', [
        'name' => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents' => [$parentId],
    ]);

    return (string)($res['id'] ?? '');
}

function googleDriveGetOrCreateFolderInParent(string $folderName, string $parentId): string
{
    $folderId = googleDriveFindFolderByNameInParent($folderName, $parentId);

    if ($folderId === '') {
        $folderId = googleDriveCreateFolderInParent($folderName, $parentId);
    }

    if ($folderId === '') {
        throw new Exception('Impossibile trovare o creare la cartella Drive: ' . $folderName);
    }

    return $folderId;
}

function googleDriveDeleteFile(string $fileId): void
{
    if ($fileId === '') {
        return;
    }

    googleDriveApiRequest(
        'DELETE',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId),
        null,
        ['Content-Type: application/json']
    );
}

function googleDriveShareFileWithUser(string $fileId, string $email, string $role = 'reader'): array
{
    $fileId = trim($fileId);
    $email = strtolower(trim($email));
    $role = trim($role) !== '' ? trim($role) : 'reader';

    if ($fileId === '' || $email === '' || strpos($email, '@') === false) {
        return [];
    }

    $listUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
        . '/permissions?fields=permissions(id,emailAddress,role,type)&pageSize=100';
    $existing = googleDriveApiRequest('GET', $listUrl);

    foreach (($existing['permissions'] ?? []) as $permission) {
        $permissionEmail = strtolower(trim((string)($permission['emailAddress'] ?? '')));
        if ($permissionEmail === $email) {
            $currentRole = trim((string)($permission['role'] ?? ''));
            if ($currentRole === $role || $currentRole === 'writer' || $currentRole === 'owner') {
                return $permission;
            }

            $permissionId = (string)($permission['id'] ?? '');
            if ($permissionId !== '') {
                return googleDriveApiRequest(
                    'PATCH',
                    'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
                        . '/permissions/' . rawurlencode($permissionId)
                        . '?fields=id,emailAddress,role,type',
                    ['role' => $role]
                );
            }
        }
    }

    return googleDriveApiRequest(
        'POST',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
            . '/permissions?sendNotificationEmail=false&fields=id,emailAddress,role,type',
        [
            'type' => 'user',
            'role' => $role,
            'emailAddress' => $email,
        ]
    );
}

function googleDriveStartResumableUpload(string $driveName, string $mimeType, int $size, string $folderId): array
{
    $accessToken = googleDriveAccessToken();

    $metadata = [
        'name' => $driveName,
        'parents' => [$folderId],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,mimeType,webViewLink',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: ' . $mimeType,
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

    return [
        'uploadUrl' => $uploadUrl,
    ];
}

function googleDriveGetBonusFolderId(): string
{
    $cfg = googleDriveGetConfig();
    $folderId = trim((string)($cfg->bonusFolderId ?? ''));

    if ($folderId === '') {
        throw new Exception('bonusFolderId mancante in configurazione Google Drive');
    }

    return $folderId;
}

function googleDriveFindFileByNameInParent(string $fileName, string $parentId): string
{
    $safeName = str_replace("'", "\\'", $fileName);

    $query = "mimeType!='application/vnd.google-apps.folder' "
        . "and trashed=false "
        . "and name='$safeName' "
        . "and '$parentId' in parents";

    $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name)&pageSize=1';
    $res = googleDriveApiRequest('GET', $url);

    return (string)($res['files'][0]['id'] ?? '');
}

function googleDriveListFilesInFolder(string $folderId): array
{
    $files = [];
    $pageToken = '';

    do {
        $query = "trashed=false and '$folderId' in parents";
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query)
            . '&fields=nextPageToken,files(id,name,size,mimeType,createdTime,modifiedTime,webViewLink)'
            . '&pageSize=1000';

        if ($pageToken !== '') {
            $url .= '&pageToken=' . urlencode($pageToken);
        }

        $res = googleDriveApiRequest('GET', $url);
        foreach (($res['files'] ?? []) as $f) {
            $files[] = $f;
        }

        $pageToken = (string)($res['nextPageToken'] ?? '');
    } while ($pageToken !== '');

    return $files;
}
