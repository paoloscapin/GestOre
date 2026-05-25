<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');
ruoloRichiesto('esterno', 'docente', 'segreteria-didattica', 'dirigente');

function geometri_esiti_fail($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function geometri_esiti_exec($query)
{
    global $__con;
    dbDebug($query);
    if (!mysqli_query($__con, $query)) {
        geometri_esiti_fail('Errore SQL: ' . mysqli_error($__con), 500);
    }
}

function geometri_esiti_table_exists($table)
{
    $table = dbEscape($table);
    return intval(dbGetValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'")) > 0;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) geometri_esiti_fail('Sessione non valida');

$sessione = dbGetFirst("SELECT * FROM geometri_sessioni WHERE id=" . dbI($id) . " LIMIT 1");
if (!$sessione) geometri_esiti_fail('Sessione non trovata', 404);

$ruolo_eff = $__utente_ruolo ?? '';
if (impersonaRuolo('docente')) $ruolo_eff = 'docente';
if (impersonaRuolo('esterno')) $ruolo_eff = 'esterno';

if ($ruolo_eff === 'docente') {
    $docente_id = intval($__docente_id ?? 0);
    $ok = $docente_id > 0 ? intval(dbGetValue("SELECT COUNT(*) FROM geometri_sessioni_docenti WHERE id_sessione=" . dbI($id) . " AND id_docente=" . dbI($docente_id))) : 0;
    if ($ok <= 0) geometri_esiti_fail('Non sei abilitato agli esiti di questa sessione', 403);
} elseif ($ruolo_eff === 'esterno') {
    $utente_id = intval($__utente_id ?? 0);
    $ok = $utente_id > 0 ? intval(dbGetValue("SELECT COUNT(*) FROM geometri_sessioni_esterni WHERE id_sessione=" . dbI($id) . " AND id_utente=" . dbI($utente_id))) : 0;
    if ($ok <= 0) geometri_esiti_fail('Non sei abilitato agli esiti di questa sessione', 403);
}

$raw = $_POST['esiti'] ?? '[]';
$rows = json_decode((string)$raw, true);
if (!is_array($rows)) geometri_esiti_fail('Dati esiti non validi');

$validEsiti = ['da_valutare', 'superato', 'non_superato', 'assente', 'ritirato'];
$registrato_ruolo = dbQ($ruolo_eff);
$registrato_id = dbI($__utente_id ?? 0);
$saved = 0;

foreach ($rows as $row) {
    if (!is_array($row)) continue;

    $id_studente = intval($row['id_studente'] ?? 0);
    if ($id_studente <= 0) continue;

    $allowedSql = "
        SELECT COUNT(*)
        FROM (
            SELECT sf.id_studente
            FROM geometri_sessioni_classi sc
            INNER JOIN studente_frequenta sf
                ON sf.id_classe = sc.id_classe
               AND sf.id_anno_scolastico = " . dbI($sessione['id_anno_scolastico']) . "
            WHERE sc.id_sessione = " . dbI($id) . "
    ";
    if (geometri_esiti_table_exists('geometri_sessioni_studenti')) {
        $allowedSql .= "
            UNION
            SELECT ss.id_studente
            FROM geometri_sessioni_studenti ss
            WHERE ss.id_sessione = " . dbI($id) . "
        ";
    }
    $allowedSql .= "
        ) allowed_students
        WHERE allowed_students.id_studente = " . dbI($id_studente) . "
    ";

    $allowed = intval(dbGetValue($allowedSql));
    if ($allowed <= 0) continue;

    $presente = intval($row['presente'] ?? 0) === 1 ? 1 : 0;
    $esito = trim((string)($row['esito'] ?? 'da_valutare'));
    if (!in_array($esito, $validEsiti, true)) $esito = 'da_valutare';
    if ($presente === 0 && $esito === 'da_valutare') $esito = 'assente';

    $note = dbQ($row['note'] ?? null);

    geometri_esiti_exec("
        INSERT INTO geometri_esiti
            (id_sessione, id_studente, presente, esito, voto, note, registrato_da_ruolo, registrato_da_id, registrato_il)
        VALUES
            (" . dbI($id) . ", " . dbI($id_studente) . ", " . dbI($presente) . ", " . dbQ($esito) . ", NULL, $note, $registrato_ruolo, $registrato_id, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            presente = VALUES(presente),
            esito = VALUES(esito),
            voto = VALUES(voto),
            note = VALUES(note),
            registrato_da_ruolo = VALUES(registrato_da_ruolo),
            registrato_da_id = VALUES(registrato_da_id),
            registrato_il = CURRENT_TIMESTAMP
    ");
    $saved++;
}

echo json_encode(['success' => true, 'saved' => $saved], JSON_UNESCAPED_UNICODE);
