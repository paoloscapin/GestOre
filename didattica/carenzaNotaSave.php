<?php

require_once '../common/checkSession.php';

ruoloRichiesto('segreteria-didattica', 'docente');

function carenzaNotaCurrentDocenteId(): int
{
    global $__docente_id, $__username;

    $docenteId = intval($__docente_id ?? 0);
    if ($docenteId > 0) {
        return $docenteId;
    }

    $username = trim((string)($__username ?? ''));
    if ($username !== '') {
        return intval(dbGetValue("SELECT id FROM docente WHERE username = " . dbQ($username) . " LIMIT 1"));
    }

    return 0;
}

$id = intval($_POST['id'] ?? 0);
$nota = trim((string)($_POST['nota'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo 'Carenza non valida.';
    exit;
}

$row = dbGetFirst("SELECT id, id_docente, stato FROM carenze WHERE id = " . dbI($id) . " LIMIT 1");
if ($row == null) {
    http_response_code(404);
    echo 'Carenza non trovata.';
    exit;
}

$docenteId = carenzaNotaCurrentDocenteId();
$canEdit = haRuolo('dirigente') || haRuolo('segreteria-didattica');
if (!$canEdit) {
    $canEdit = intval($row['stato'] ?? 0) === 1
        && $docenteId > 0
        && intval($row['id_docente'] ?? 0) === $docenteId;
}

if (!$canEdit) {
    http_response_code(403);
    echo 'Non sei autorizzato a modificare la nota di questa carenza.';
    exit;
}

dbExec("UPDATE carenze SET nota_docente = " . dbQ($nota) . " WHERE id = " . dbI($id));
info("nota carenza aggiornata id=$id docente_id=$docenteId");
echo 'Nota aggiornata.';

