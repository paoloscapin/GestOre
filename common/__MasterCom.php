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

    return mastercomAuthenticate($username, $password, $options);
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

    return mastercomRawRequest($payload, array_merge($options, [
        'base_url' => $options['base_url'] ?? mastercomBaseUrl(),
        'cookie' => $options['cookie'] ?? $cookieHeader,
        'method' => $options['method'] ?? 'POST',
        'send_in_body' => array_key_exists('send_in_body', $options) ? $options['send_in_body'] : true,
    ]));
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

function mastercomCurrentKey(array $authResult): ?string
{
    return $authResult['response']['result']['current_key'] ?? null;
}

function mastercomCurrentUser(array $authResult): ?int
{
    if (!isset($authResult['response']['result']['current_user'])) {
        return null;
    }
    return intval($authResult['response']['result']['current_user']);
}
