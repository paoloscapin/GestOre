<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('esterno', 'docente', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametro mancante']);
    exit;
}

$row = dbGetFirst("
    SELECT *
    FROM geometri_sessioni
    WHERE id = " . dbI($id) . "
    LIMIT 1
");

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Sessione non trovata']);
    exit;
}

$ruolo_eff = $__utente_ruolo ?? '';
if (impersonaRuolo('docente')) $ruolo_eff = 'docente';
if (impersonaRuolo('esterno')) $ruolo_eff = 'esterno';

if ($ruolo_eff === 'docente') {
    $docente_id = intval($__docente_id ?? 0);
    $ok = $docente_id > 0 ? intval(dbGetValue("SELECT COUNT(*) FROM geometri_sessioni_docenti WHERE id_sessione=" . dbI($id) . " AND id_docente=" . dbI($docente_id))) : 0;
    if ($ok <= 0) {
        echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
        exit;
    }
}

if ($ruolo_eff === 'esterno') {
    $utente_id = intval($__utente_id ?? 0);
    $ok = $utente_id > 0 ? intval(dbGetValue("SELECT COUNT(*) FROM geometri_sessioni_esterni WHERE id_sessione=" . dbI($id) . " AND id_utente=" . dbI($utente_id))) : 0;
    if ($ok <= 0) {
        echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
        exit;
    }
}

function g_local_dt($value)
{
    if (!$value) return '';
    try {
        return (new DateTime((string)$value))->format('Y-m-d\TH:i');
    } catch (Exception $e) {
        return '';
    }
}

function g_table_exists($table)
{
    $table = dbEscape($table);
    return intval(dbGetValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'")) > 0;
}

$row['data_inizio_local'] = g_local_dt($row['data'] ?? '');

$classi = dbGetAllValues("SELECT id_classe FROM geometri_sessioni_classi WHERE id_sessione=" . dbI($id) . " ORDER BY id_classe ASC");
$studenti_recupero = g_table_exists('geometri_sessioni_studenti')
    ? dbGetAllValues("SELECT id_studente FROM geometri_sessioni_studenti WHERE id_sessione=" . dbI($id) . " ORDER BY id_studente ASC")
    : [];
$docenti = dbGetAllValues("SELECT id_docente FROM geometri_sessioni_docenti WHERE id_sessione=" . dbI($id) . " ORDER BY id_docente ASC");
$esterni = dbGetAllValues("SELECT id_utente FROM geometri_sessioni_esterni WHERE id_sessione=" . dbI($id) . " ORDER BY id_utente ASC");

echo json_encode([
    'success' => true,
    'sessione' => $row,
    'classi' => array_map('intval', $classi ?: []),
    'studenti_recupero' => array_map('intval', $studenti_recupero ?: []),
    'docenti' => array_map('intval', $docenti ?: []),
    'esterni' => array_map('intval', $esterni ?: []),
], JSON_UNESCAPED_UNICODE);
