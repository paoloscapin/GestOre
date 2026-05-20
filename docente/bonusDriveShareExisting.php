<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';

ruoloRichiesto('admin', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('googleDriveShareFileWithUser')) {
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
}

function bonusShareExistingEmailIstituzionale(array $docente): string
{
    $email = strtolower(trim((string)($docente['email'] ?? '')));
    if ($email !== '' && substr($email, -strlen('@buonarroti.tn.it')) === '@buonarroti.tn.it') {
        return $email;
    }

    $username = strtolower(trim((string)($docente['username'] ?? '')));
    if ($username === '') {
        return '';
    }
    if (strpos($username, '@') !== false) {
        return substr($username, -strlen('@buonarroti.tn.it')) === '@buonarroti.tn.it' ? $username : '';
    }

    return $username . '@buonarroti.tn.it';
}

$anno = intval($_GET['anno_scolastico_id'] ?? $__anno_scolastico_corrente_id);
$docenteId = intval($_GET['docente_id'] ?? 0);

$where = "a.storage_type = 'DRIVE'
    AND a.drive_file_id IS NOT NULL
    AND a.drive_file_id <> ''
    AND a.anno_scolastico_id = $anno";

if ($docenteId > 0) {
    $where .= " AND a.docente_id = $docenteId";
}

$rows = dbGetAll("
    SELECT
        a.id,
        a.drive_file_id,
        a.original_name,
        d.cognome,
        d.nome,
        d.email,
        d.username
    FROM bonus_docente_allegato a
    JOIN docente d ON d.id = a.docente_id
    WHERE $where
    ORDER BY d.cognome, d.nome, a.id
");

$result = [
    'ok' => true,
    'anno_scolastico_id' => $anno,
    'found' => count($rows),
    'shared' => 0,
    'skipped' => [],
    'errors' => [],
];

foreach ($rows as $row) {
    $id = intval($row['id']);
    $email = bonusShareExistingEmailIstituzionale($row);

    if ($email === '') {
        $result['skipped'][] = [
            'id' => $id,
            'original_name' => $row['original_name'],
            'reason' => 'mail istituzionale Buonarroti mancante',
        ];
        continue;
    }

    try {
        googleDriveShareFileWithUser((string)$row['drive_file_id'], $email, 'reader');
        $result['shared']++;
    } catch (Throwable $e) {
        $result['errors'][] = [
            'id' => $id,
            'original_name' => $row['original_name'],
            'email' => $email,
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
