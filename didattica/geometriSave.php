<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function geometri_fail($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function geometri_array($value)
{
    if (is_array($value)) return $value;
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return $decoded;
    }
    return [];
}

function geometri_dt($value)
{
    $value = trim((string)$value);
    if ($value === '') return null;
    try {
        return (new DateTime($value, new DateTimeZone('Europe/Rome')))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        geometri_fail('Data non valida');
    }
}

function geometri_exec($query)
{
    global $__con;

    dbDebug($query);
    if (!mysqli_query($__con, $query)) {
        geometri_fail('Errore SQL: ' . mysqli_error($__con), 500);
    }
}

function geometri_table_exists($table)
{
    $table = dbEscape($table);
    return intval(dbGetValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'")) > 0;
}

function geometri_column_exists($table, $column)
{
    $table = dbEscape($table);
    $column = dbEscape($column);
    return intval(dbGetValue("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = '$column'")) > 0;
}

$id = intval($_POST['id'] ?? -1);
$anno_id = intval($_POST['anno_id'] ?? 0);
$id_esame = intval($_POST['id_esame'] ?? 0);
$data = geometri_dt($_POST['data_inizio'] ?? '');
$stato = trim((string)($_POST['stato'] ?? 'bozza'));
$note = trim((string)($_POST['note'] ?? ''));

$classi = array_values(array_filter(array_unique(array_map('intval', geometri_array($_POST['classi'] ?? [])))));
$studenti_recupero = array_values(array_filter(array_unique(array_map('intval', geometri_array($_POST['studenti_recupero'] ?? [])))));
$docenti = array_values(array_filter(array_unique(array_map('intval', geometri_array($_POST['docenti'] ?? [])))));
$esterni = array_values(array_filter(array_unique(array_map('intval', geometri_array($_POST['esterni'] ?? [])))));

if ($anno_id <= 0 || $id_esame <= 0 || !$data || (count($classi) === 0 && count($studenti_recupero) === 0)) {
    geometri_fail('Compila esame, anno, data e almeno una classe o uno studente di recupero');
}

if (count($studenti_recupero) > 0 && !geometri_table_exists('geometri_sessioni_studenti')) {
    geometri_fail('Tabella geometri_sessioni_studenti mancante: eseguire la migrazione DB per gli studenti di recupero', 500);
}

if (!in_array($stato, ['bozza', 'programmata', 'chiusa'], true)) {
    $stato = 'bozza';
}

$existsEsame = intval(dbGetValue("SELECT COUNT(*) FROM geometri_esami WHERE id=" . dbI($id_esame)));
if ($existsEsame <= 0) geometri_fail('Esame non valido');

$hasAula = geometri_column_exists('geometri_sessioni', 'aula');

if ($id > 0) {
    $aulaSet = $hasAula ? "aula=NULL," : "";
    geometri_exec("
        UPDATE geometri_sessioni
        SET id_esame=" . dbI($id_esame) . ",
            id_anno_scolastico=" . dbI($anno_id) . ",
            data=" . dbQ($data) . ",
            $aulaSet
            note=" . dbQ($note) . ",
            stato=" . dbQ($stato) . "
        WHERE id=" . dbI($id) . "
    ");
    $sessione_id = $id;
} else {
    if ($hasAula) {
        geometri_exec("
            INSERT INTO geometri_sessioni
                (id_esame, id_anno_scolastico, data, aula, note, stato)
            VALUES
                (" . dbI($id_esame) . ", " . dbI($anno_id) . ", " . dbQ($data) . ", NULL, " . dbQ($note) . ", " . dbQ($stato) . ")
        ");
    } else {
        geometri_exec("
            INSERT INTO geometri_sessioni
                (id_esame, id_anno_scolastico, data, note, stato)
            VALUES
                (" . dbI($id_esame) . ", " . dbI($anno_id) . ", " . dbQ($data) . ", " . dbQ($note) . ", " . dbQ($stato) . ")
        ");
    }
    $sessione_id = intval(dblastId());
}

if ($sessione_id <= 0) {
    geometri_fail('Impossibile salvare la sessione', 500);
}

geometri_exec("DELETE FROM geometri_sessioni_classi WHERE id_sessione=" . dbI($sessione_id));
foreach ($classi as $id_classe) {
    if ($id_classe <= 0) continue;
    geometri_exec("INSERT INTO geometri_sessioni_classi (id_sessione, id_classe) VALUES (" . dbI($sessione_id) . ", " . dbI($id_classe) . ")");
}

if (geometri_table_exists('geometri_sessioni_studenti')) {
    geometri_exec("DELETE FROM geometri_sessioni_studenti WHERE id_sessione=" . dbI($sessione_id));
    foreach ($studenti_recupero as $id_studente) {
        if ($id_studente <= 0) continue;
        geometri_exec("INSERT INTO geometri_sessioni_studenti (id_sessione, id_studente) VALUES (" . dbI($sessione_id) . ", " . dbI($id_studente) . ")");
    }
}

geometri_exec("DELETE FROM geometri_sessioni_docenti WHERE id_sessione=" . dbI($sessione_id));
foreach ($docenti as $id_docente) {
    if ($id_docente <= 0) continue;
    geometri_exec("INSERT INTO geometri_sessioni_docenti (id_sessione, id_docente) VALUES (" . dbI($sessione_id) . ", " . dbI($id_docente) . ")");
}

geometri_exec("DELETE FROM geometri_sessioni_esterni WHERE id_sessione=" . dbI($sessione_id));
foreach ($esterni as $id_utente) {
    if ($id_utente <= 0) continue;
    geometri_exec("INSERT INTO geometri_sessioni_esterni (id_sessione, id_utente) VALUES (" . dbI($sessione_id) . ", " . dbI($id_utente) . ")");
}

echo json_encode(['success' => true, 'id' => $sessione_id], JSON_UNESCAPED_UNICODE);
