<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

$type = trim((string)($_POST['type'] ?? ''));
$direction = trim((string)($_POST['direction'] ?? ''));
$id = intval($_POST['id'] ?? 0);

$result = ['ok' => false, 'message' => 'Richiesta non valida'];
$redirect = 'mastercom.php';

if ($type === 'student') {
    $redirect = 'mastercom_student_compare.php?id=' . $id;
    if ($direction === 'gestore_from_mastercom') {
        $result = mastercomAdminAlignGestoreStudentFromMastercom($id);
    } elseif ($direction === 'mastercom_from_gestore') {
        $result = mastercomAdminAlignMirrorStudentFromGestore($id);
    }
} elseif ($type === 'parent') {
    $redirect = 'mastercom_parent_compare.php?id=' . $id;
    if ($direction === 'gestore_from_mastercom') {
        $result = mastercomAdminAlignGestoreParentFromMastercom($id);
    } elseif ($direction === 'mastercom_from_gestore') {
        $result = mastercomAdminAlignMirrorParentFromGestore($id);
    }
}

header('Location: ' . $redirect . '&' . ($result['ok'] ? 'message=' : 'error=') . urlencode($result['message']));
exit;
