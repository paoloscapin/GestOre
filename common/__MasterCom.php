<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

function mastercomBaseUrl(): string
{
    global $__settings;

    $url = trim((string)($__settings->config->ulrAPIMastercom ?? ''));
    return $url;
}

function mastercomAjatUrl(): string
{
    $url = mastercomBaseUrl();
    if ($url === '') {
        return '';
    }

    return str_replace('register_manager.php', 'ajat_manager.php', $url);
}

function mastercomNextApiBaseUrl(): string
{
    $url = mastercomBaseUrl();
    if ($url === '') {
        return '';
    }

    return str_replace('/mastercom/register_manager.php', '/next-api/v1', $url);
}

function mastercomStudentPhotoBaseUrl(): string
{
    $url = mastercomBaseUrl();
    if ($url === '') {
        return '';
    }

    return str_replace('/mastercom/register_manager.php', '/mastercom/foto_studenti/', $url);
}

function mastercomIndexUrl(): string
{
    $url = mastercomBaseUrl();
    if ($url === '') {
        return '';
    }

    return str_replace('/mastercom/register_manager.php', '/mastercom/index.php', $url);
}

function mastercomConfiguredUsername(): string
{
    return mastercomConfiguredUsernameByProfile('MasterComAuth');
}

function mastercomConfiguredPassword(): string
{
    return mastercomConfiguredPasswordByProfile('MasterComAuth');
}

function mastercomConfiguredUsernameByProfile(string $profile = 'MasterComAuth'): string
{
    global $__settings;

    return trim((string)($__settings->{$profile}->clientId ?? ''));
}

function mastercomConfiguredPasswordByProfile(string $profile = 'MasterComAuth'): string
{
    global $__settings;

    return trim((string)($__settings->{$profile}->clientSecret ?? ''));
}

function mastercomAuthCacheAvailable(): bool
{
    return session_status() === PHP_SESSION_ACTIVE;
}

function mastercomAuthCacheKey(string $profile, string $username): string
{
    return $profile . ':' . sha1($username);
}

function mastercomBase64UrlDecode(string $value): string
{
    $value = strtr($value, '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($value, true);
    return $decoded === false ? '' : $decoded;
}

function mastercomTokenExpiresAt(?string $token): int
{
    $token = trim((string)$token);
    $parts = explode('.', $token);
    if (count($parts) < 2) {
        return 0;
    }

    $payload = json_decode(mastercomBase64UrlDecode($parts[1]), true);
    if (!is_array($payload)) {
        return 0;
    }

    $expiresAt = intval($payload['expiration'] ?? $payload['exp'] ?? 0);
    return $expiresAt > 0 ? $expiresAt : 0;
}

function mastercomRememberAuthResult(string $profile, string $username, array $authResult): array
{
    $authResult['_mastercom_profile'] = $profile;
    $authResult['_mastercom_cached'] = false;

    if (!mastercomAuthCacheAvailable() || empty($authResult['ok'])) {
        return $authResult;
    }

    $currentKey = mastercomCurrentKey($authResult);
    $expiresAt = mastercomTokenExpiresAt($currentKey);
    if ($expiresAt <= 0) {
        $expiresAt = time() + 1800;
    }

    if (!isset($_SESSION['mastercom_auth_cache']) || !is_array($_SESSION['mastercom_auth_cache'])) {
        $_SESSION['mastercom_auth_cache'] = [];
    }

    $_SESSION['mastercom_auth_cache'][mastercomAuthCacheKey($profile, $username)] = [
        'profile' => $profile,
        'username_hash' => sha1($username),
        'current_key' => $currentKey,
        'expires_at' => $expiresAt,
        'auth_result' => $authResult,
    ];

    return $authResult;
}

function mastercomCachedAuthResult(string $profile, string $username): ?array
{
    if (!mastercomAuthCacheAvailable()) {
        return null;
    }

    $cacheKey = mastercomAuthCacheKey($profile, $username);
    $entry = $_SESSION['mastercom_auth_cache'][$cacheKey] ?? null;
    if (!is_array($entry) || !isset($entry['auth_result']) || !is_array($entry['auth_result'])) {
        return null;
    }

    if (intval($entry['expires_at'] ?? 0) <= time() + 60) {
        unset($_SESSION['mastercom_auth_cache'][$cacheKey]);
        return null;
    }

    $authResult = $entry['auth_result'];
    $authResult['_mastercom_profile'] = $profile;
    $authResult['_mastercom_cached'] = true;
    return $authResult;
}

function mastercomInvalidateAuthCache(?string $currentKey = null): void
{
    if (!mastercomAuthCacheAvailable() || empty($_SESSION['mastercom_auth_cache']) || !is_array($_SESSION['mastercom_auth_cache'])) {
        return;
    }

    $currentKey = trim((string)$currentKey);
    if ($currentKey === '') {
        $_SESSION['mastercom_auth_cache'] = [];
        return;
    }

    foreach ($_SESSION['mastercom_auth_cache'] as $cacheKey => $entry) {
        if (is_array($entry) && trim((string)($entry['current_key'] ?? '')) === $currentKey) {
            unset($_SESSION['mastercom_auth_cache'][$cacheKey]);
        }
    }
}

function mastercomRequest(array $queryParams, array $options = []): array
{
    $baseUrl = trim((string)($options['base_url'] ?? mastercomBaseUrl()));
    if ($baseUrl === '') {
        return [
            'ok' => false,
            'error' => 'URL MasterCom non configurato',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    $url = $baseUrl . '?' . http_build_query($queryParams);
    $headers = [
        'User-Agent: GestOreMasterCom/1.0',
        'Accept: application/json',
    ];

    if (!empty($options['cookie'])) {
        $headers[] = 'Cookie: ' . trim((string)$options['cookie']);
    }

    if (!empty($options['bearer_token'])) {
        $headers[] = 'Authorization: Bearer ' . trim((string)$options['bearer_token']);
    }

    if (!empty($options['headers']) && is_array($options['headers'])) {
        foreach ($options['headers'] as $header) {
            $header = trim((string)$header);
            if ($header !== '') {
                $headers[] = $header;
            }
        }
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => intval($options['timeout'] ?? 60),
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => '',
        CURLOPT_CUSTOMREQUEST => strtoupper((string)($options['method'] ?? 'POST')),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_COOKIEJAR => '',
    ]);

    $raw = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    $headerSize = intval(curl_getinfo($curl, CURLINFO_HEADER_SIZE));
    curl_close($curl);

    if ($err) {
        error('MasterCom request error: ' . $err);
        return [
            'ok' => false,
            'error' => $err,
            'response' => null,
            'raw' => null,
            'http_code' => $httpCode,
            'cookies' => [],
        ];
    }

    $headerText = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);
    $decoded = json_decode($body, true);

    $cookies = [];
    foreach (preg_split("/\r\n|\n|\r/", $headerText) as $headerLine) {
        if (stripos($headerLine, 'Set-Cookie:') !== 0) {
            continue;
        }
        $cookiePair = trim(substr($headerLine, strlen('Set-Cookie:')));
        $cookiePair = explode(';', $cookiePair, 2)[0] ?? '';
        if ($cookiePair !== '') {
            $cookies[] = $cookiePair;
        }
    }

    if (!is_array($decoded)) {
        warning('MasterCom response is not valid JSON');
        return [
            'ok' => false,
            'error' => 'Risposta MasterCom non valida',
            'response' => null,
            'raw' => $body,
            'http_code' => $httpCode,
            'cookies' => $cookies,
        ];
    }

    $isListResponse = array_keys($decoded) === range(0, count($decoded) - 1);
    $isOk = $isListResponse || !empty($decoded['auth']);
    if (!$isOk && array_key_exists('auth', $decoded) && $decoded['auth'] === false) {
        mastercomInvalidateAuthCache($queryParams['current_key'] ?? null);
    }

    return [
        'ok' => $isOk,
        'error' => $isOk ? '' : ((string)($decoded['debug_code'] ?? $decoded['error_code'] ?? 'AUTH_FAILED')),
        'response' => $decoded,
        'raw' => $body,
        'http_code' => $httpCode,
        'cookies' => $cookies,
    ];
}

function mastercomRawRequest(array $params, array $options = []): array
{
    $baseUrl = trim((string)($options['base_url'] ?? mastercomBaseUrl()));
    if ($baseUrl === '') {
        return [
            'ok' => false,
            'error' => 'URL MasterCom non configurato',
            'body' => null,
            'http_code' => 0,
            'content_type' => null,
        ];
    }

    $method = strtoupper((string)($options['method'] ?? 'POST'));
    $sendInBody = !empty($options['send_in_body']);
    $url = $baseUrl;
    if (!($sendInBody && $method === 'POST')) {
        $url .= '?' . http_build_query($params);
    }

    $headers = [
        'User-Agent: GestOreMasterCom/1.0',
    ];

    if (!empty($options['cookie'])) {
        $headers[] = 'Cookie: ' . trim((string)$options['cookie']);
    }

    if (!empty($options['bearer_token'])) {
        $headers[] = 'Authorization: Bearer ' . trim((string)$options['bearer_token']);
    }

    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => intval($options['timeout'] ?? 60),
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => '',
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($sendInBody && $method === 'POST') {
        $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($params);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $curlOptions[CURLOPT_HTTPHEADER] = $headers;
    }

    $curl = curl_init();
    curl_setopt_array($curl, $curlOptions);
    $body = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: null;
    curl_close($curl);

    if ($err) {
        return [
            'ok' => false,
            'error' => $err,
            'body' => null,
            'http_code' => $httpCode,
            'content_type' => $contentType,
        ];
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'error' => ($httpCode >= 200 && $httpCode < 300) ? '' : ('HTTP_' . $httpCode),
        'body' => $body,
        'http_code' => $httpCode,
        'content_type' => $contentType,
    ];
}

function mastercomAuthenticate(string $username, string $password, array $options = []): array
{
    return mastercomRequest([
        'form_user' => $username,
        'form_password' => $password,
    ], $options);
}

function mastercomAuthenticateService(array $options = []): array
{
    $profile = trim((string)($options['profile'] ?? 'MasterComAuth'));
    $username = mastercomConfiguredUsernameByProfile($profile);
    $password = mastercomConfiguredPasswordByProfile($profile);

    if ($username === '' || $password === '') {
        return [
            'ok' => false,
            'error' => 'Credenziali di servizio MasterCom non configurate per il profilo ' . $profile,
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if (empty($options['force_refresh'])) {
        $cachedAuth = mastercomCachedAuthResult($profile, $username);
        if ($cachedAuth !== null) {
            return $cachedAuth;
        }
    }

    $authResult = mastercomAuthenticate($username, $password, $options);
    return mastercomRememberAuthResult($profile, $username, $authResult);
}

function mastercomAuthenticatedRequest(array $queryParams, array $authResult, array $options = []): array
{
    $currentUser = mastercomCurrentUser($authResult);
    $currentKey = mastercomCurrentKey($authResult);

    if ($currentUser === null || $currentKey === null || $currentKey === '') {
        return [
            'ok' => false,
            'error' => 'Autenticazione MasterCom non valida o incompleta',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    $requestParams = array_merge([
        'current_user' => $currentUser,
        'current_key' => $currentKey,
        'form_tipo_utente' => $authResult['response']['result']['tipo_utente'] ?? 'amministratore',
    ], $queryParams);

    $cookiePairs = $authResult['cookies'] ?? [];
    $cookieHeader = implode('; ', array_filter($cookiePairs));

    return mastercomRequest($requestParams, array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomAjatUrl(),
        'cookie' => $options['cookie'] ?? $cookieHeader,
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadParents(array $authResult, array $options = []): array
{
    return mastercomAuthenticatedRequest([
        'form_azione' => 'carica_parenti_istituto',
    ], $authResult, $options);
}

function mastercomLoadUsersList(array $authResult, array $options = []): array
{
    return mastercomRequest([
        'action' => 'get_user_list',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadCurrentUserInfo(array $authResult, array $options = []): array
{
    return mastercomRequest([
        'action' => 'get_user_info',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadPeriodsData(array $authResult, int $classId, array $options = []): array
{
    if ($classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID classe non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_periods_data',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_classe' => $classId,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadFestivitiesData(array $authResult, int $startTs, int $endTs, array $options = []): array
{
    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return [
            'ok' => false,
            'error' => 'Intervallo date non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_festivities_data',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_data_inizio' => $startTs,
        'form_data_fine' => $endTs,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadStudentsList(array $authResult, int $classId, array $options = []): array
{
    if ($classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID classe non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_students_list',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_classe' => $classId,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadCalendarNotes(array $authResult, int $classId, int $startTs, int $endTs, array $options = []): array
{
    if ($classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID classe non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return [
            'ok' => false,
            'error' => 'Intervallo date non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_calendar_notes',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_classe' => $classId,
        'form_data_inizio' => $startTs,
        'form_data_fine' => $endTs,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadDisciplinaryNotes(array $authResult, int $classId, int $startTs, int $endTs, array $options = []): array
{
    if ($classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID classe non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return [
            'ok' => false,
            'error' => 'Intervallo date non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_disciplinary_notes',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_classe' => $classId,
        'form_data_inizio' => $startTs,
        'form_data_fine' => $endTs,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadAppealData(array $authResult, int $classId, array $options = []): array
{
    if ($classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID classe non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_appeal_data',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_classe' => $classId,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadAbsencesData(array $authResult, int $studentId, int $startTs, int $endTs, array $options = []): array
{
    if ($studentId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID studente non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return [
            'ok' => false,
            'error' => 'Intervallo date non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_absences_data',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_studente' => $studentId,
        'form_data_inizio' => $startTs,
        'form_data_fine' => $endTs,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadGradesAvg(array $authResult, int $classId, int $subjectId, int $startTs, int $endTs, array $options = []): array
{
    if ($classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID classe non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if ($subjectId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID materia non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return [
            'ok' => false,
            'error' => 'Intervallo date non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_grades_avg',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_classe' => $classId,
        'form_id_materia' => $subjectId,
        'form_data_inizio' => $startTs,
        'form_data_fine' => $endTs,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomLoadGradesData(array $authResult, int $classId, int $subjectId, int $startTs, int $endTs, array $options = []): array
{
    if ($classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID classe non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if ($subjectId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID materia non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return [
            'ok' => false,
            'error' => 'Intervallo date non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    return mastercomRequest([
        'action' => 'get_grades_data',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
        'form_id_classe' => $classId,
        'form_id_materia' => $subjectId,
        'form_data_inizio' => $startTs,
        'form_data_fine' => $endTs,
    ], array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
    ]));
}

function mastercomStudentPhotoUrl(string $fileName): string
{
    $fileName = trim($fileName);
    if ($fileName === '') {
        return '';
    }

    return mastercomStudentPhotoBaseUrl() . rawurlencode($fileName);
}

function mastercomDownloadStudentPhoto(string $fileName, array $options = []): array
{
    $url = mastercomStudentPhotoUrl($fileName);
    if ($url === '') {
        return [
            'ok' => false,
            'error' => 'Nome file foto non valido',
            'content_type' => null,
            'body' => null,
            'http_code' => 0,
        ];
    }

    $headers = [
        'User-Agent: GestOreMasterCom/1.0',
        'Accept: image/*',
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => intval($options['timeout'] ?? 60),
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $body = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: null;
    curl_close($curl);

    if ($err) {
        return [
            'ok' => false,
            'error' => $err,
            'content_type' => $contentType,
            'body' => null,
            'http_code' => $httpCode,
        ];
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300 && $body !== false,
        'error' => ($httpCode >= 200 && $httpCode < 300) ? '' : ('HTTP_' . $httpCode),
        'content_type' => $contentType,
        'body' => $body,
        'http_code' => $httpCode,
    ];
}

function mastercomSubmitAdminAbsenceAction(array $authResult, array $formParams, array $options = []): array
{
    $currentUser = mastercomCurrentUser($authResult);
    $currentKey = mastercomCurrentKey($authResult);
    if ($currentUser === null || $currentKey === null || $currentKey === '') {
        return [
            'ok' => false,
            'error' => 'Autenticazione MasterCom non valida o incompleta',
            'body' => null,
            'http_code' => 0,
            'content_type' => null,
        ];
    }

    unset($formParams['current_user'], $formParams['current_key']);
    $payload = array_merge($formParams, [
        'current_user' => $currentUser,
        'current_key' => $currentKey,
    ]);

    $cookieHeader = implode('; ', array_filter($authResult['cookies'] ?? []));

    $submitResult = mastercomRawRequest($payload, array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomIndexUrl(),
        'cookie' => $options['cookie'] ?? $cookieHeader,
        'method' => $options['method'] ?? 'POST',
        'send_in_body' => array_key_exists('send_in_body', $options) ? $options['send_in_body'] : false,
    ]));
    $submitResult['submitted_url'] = mastercomIndexUrl() . '?' . http_build_query($payload);
    $submitResult['submitted_payload'] = $payload;

    if (!$submitResult['ok']) {
        return $submitResult;
    }

    $body = (string)($submitResult['body'] ?? '');
    $plainBody = trim(strip_tags(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $normalizedBody = mb_strtoupper(preg_replace('/\s+/', ' ', $plainBody), 'UTF-8');
    $htmlLooksLikeLogin = preg_match('/<form[^>]+login|name=["\']form_user["\']|name=["\']form_password["\']/i', $body) === 1;
    $errorTokens = ['ERRORE', 'OPERAZIONE NON CONSENTITA', 'SESSIONE SCADUTA'];
    if ($htmlLooksLikeLogin) {
        $errorTokens[] = 'LOGIN_FORM';
    }

    $warnings = [];
    foreach ($errorTokens as $errorToken) {
        $needle = $errorToken === 'LOGIN_FORM' ? 'LOGIN' : $errorToken;
        if (strpos($normalizedBody, $needle) === false && $errorToken !== 'LOGIN_FORM') {
            continue;
        }
        $warnings[] = 'MASTERCOM_HTML_WARNING_' . str_replace(' ', '_', $errorToken);
    }
    if (!empty($warnings)) {
        $submitResult['html_warnings'] = $warnings;
    }

    return $submitResult;
}

function mastercomExtractTeacherUsers(array $usersResult): array
{
    $records = $usersResult['response']['result'] ?? [];
    if (!is_array($records)) {
        return [];
    }

    $teachers = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        if (($record['type'] ?? '') !== 'Professore') {
            continue;
        }
        $teachers[] = $record;
    }

    return $teachers;
}

function mastercomExtractClasses(array $userInfoResult): array
{
    $classes = $userInfoResult['response']['result']['classi'] ?? [];
    return is_array($classes) ? $classes : [];
}

function mastercomLoadStudentDetails(array $authResult, int $studentId, array $options = []): array
{
    if ($studentId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID studente non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    $currentUser = mastercomCurrentUser($authResult);
    $currentKey = mastercomCurrentKey($authResult);
    if ($currentUser === null || $currentKey === null || $currentKey === '') {
        return [
            'ok' => false,
            'error' => 'Autenticazione MasterCom non valida o incompleta',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    $baseUrl = rtrim((string)($options['base_url'] ?? mastercomNextApiBaseUrl()), '/');
    $endpointUrl = $baseUrl . '/user/mastercom_m/student/' . intval($studentId);
    $cookiePairs = $authResult['cookies'] ?? [];
    $cookieHeader = implode('; ', array_filter($cookiePairs));

    return mastercomRequest([
        'current_user' => $currentUser,
        'current_key' => $currentKey,
    ], array_merge($options, [
        'base_url' => $endpointUrl,
        'cookie' => $options['cookie'] ?? $cookieHeader,
        'bearer_token' => $options['bearer_token'] ?? $currentKey,
        'method' => $options['method'] ?? 'GET',
    ]));
}

function mastercomLoadParentDetails(array $authResult, int $parentId, array $options = []): array
{
    if ($parentId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID genitore non valido',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    $currentUser = mastercomCurrentUser($authResult);
    $currentKey = mastercomCurrentKey($authResult);
    if ($currentUser === null || $currentKey === null || $currentKey === '') {
        return [
            'ok' => false,
            'error' => 'Autenticazione MasterCom non valida o incompleta',
            'response' => null,
            'raw' => null,
            'http_code' => 0,
            'cookies' => [],
        ];
    }

    $baseUrl = rtrim((string)($options['base_url'] ?? mastercomNextApiBaseUrl()), '/');
    $endpointUrl = $baseUrl . '/user/mastercom_m/parent/' . intval($parentId);
    $cookiePairs = $authResult['cookies'] ?? [];
    $cookieHeader = implode('; ', array_filter($cookiePairs));

    return mastercomRequest([
        'current_user' => $currentUser,
        'current_key' => $currentKey,
    ], array_merge($options, [
        'base_url' => $endpointUrl,
        'cookie' => $options['cookie'] ?? $cookieHeader,
        'bearer_token' => $options['bearer_token'] ?? $currentKey,
        'method' => $options['method'] ?? 'GET',
    ]));
}

function mastercomLoadStudentAdminProfileHtml(array $authResult, array $studentContext, array $options = []): array
{
    $studentId = intval($studentContext['id_studente'] ?? $studentContext['id_student'] ?? 0);
    $classId = intval($studentContext['id_classe'] ?? $studentContext['id_class'] ?? 0);
    if ($studentId <= 0 || $classId <= 0) {
        return [
            'ok' => false,
            'error' => 'ID studente o classe non valido',
            'body' => null,
            'http_code' => 0,
            'content_type' => null,
        ];
    }

    $currentUser = mastercomCurrentUser($authResult);
    $currentKey = mastercomCurrentKey($authResult);
    if ($currentUser === null || $currentKey === null || $currentKey === '') {
        return [
            'ok' => false,
            'error' => 'Autenticazione MasterCom non valida o incompleta',
            'body' => null,
            'http_code' => 0,
            'content_type' => null,
        ];
    }

    $params = [
        'form_stato' => 'amministratore',
        'stato_principale' => 'classi_principale',
        'stato_secondario' => 'visualizza_studente',
        'indirizzo' => (string)($studentContext['indirizzo'] ?? ''),
        'classe' => (string)($studentContext['classe'] ?? ''),
        'id_classe' => $classId,
        'id_studente' => $studentId,
        'id_indirizzo' => (string)($studentContext['id_indirizzo'] ?? ''),
        'current_user' => $currentUser,
        'current_key' => $currentKey,
    ];

    return mastercomRawRequest($params, array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomIndexUrl(),
        'cookie' => $options['cookie'] ?? implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => $options['method'] ?? 'POST',
        'send_in_body' => $options['send_in_body'] ?? true,
    ]));
}

function mastercomCurrentKey(array $authResult): ?string
{
    return $authResult['response']['result']['current_key']
        ?? $authResult['response']['result']['utente']['current_key']
        ?? null;
}

function mastercomCurrentUser(array $authResult): ?int
{
    $currentUser = $authResult['response']['result']['current_user']
        ?? $authResult['response']['result']['utente']['current_user']
        ?? null;

    if ($currentUser === null) {
        return null;
    }
    return intval($currentUser);
}
