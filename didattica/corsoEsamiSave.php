<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

function sqlv($val)
{
    if ($val === null) return "NULL";
    if (is_string($val)) {
        $trim = trim($val);
        if ($trim === '') return "NULL";
        return "'" . addslashes($trim) . "'";
    }
    return "'" . addslashes((string)$val) . "'";
}

function recalcFirmatoEsame($id_esame_data)
{
    $id_esame_data = intval($id_esame_data);
    if ($id_esame_data <= 0) return;

    $info = dbGetFirst("
        SELECT ed.id_corso, c.firma_policy
        FROM corso_esami_date ed
        INNER JOIN corso c ON c.id = ed.id_corso
        WHERE ed.id = $id_esame_data
        LIMIT 1
    ");
    if (!$info) return;

    $id_corso = intval($info['id_corso']);
    $policy = strtoupper(trim($info['firma_policy'] ?? 'ANY'));
    if ($policy !== 'ALL') $policy = 'ANY';

    if ($policy === 'ANY') {
        dbExec("
            UPDATE corso_esami_date
            SET firmato = CASE
                WHEN EXISTS(SELECT 1 FROM corso_esami_date_firme f WHERE f.id_esame_data = $id_esame_data) THEN 1
                ELSE 0
            END
            WHERE id = $id_esame_data
        ");
        return;
    }

    $nDoc = dbGetValue("SELECT COUNT(*) FROM corso_docenti WHERE id_corso = $id_corso");
    $nDoc = intval($nDoc);
    if ($nDoc <= 0) $nDoc = 1;

    dbExec("
        UPDATE corso_esami_date
        SET firmato = CASE
            WHEN (SELECT COUNT(*) FROM corso_esami_date_firme f WHERE f.id_esame_data = $id_esame_data) >= $nDoc THEN 1
            ELSE 0
        END
        WHERE id = $id_esame_data
    ");
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_POST['corso_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametro corso_id mancante']);
    exit;
}

$corso_id      = intval($_POST['corso_id']);
$id_esame_data = isset($_POST['id_esame_data']) ? $_POST['id_esame_data'] : null;

$argomenti         = $_POST['argomenti'] ?? '';
$data_inizio_esame = $_POST['data_inizio'] ?? null;
$data_fine_esame   = $_POST['data_fine'] ?? null;
$aula_esame        = $_POST['aula'] ?? null;
$force_segreteria = intval($_POST['force_segreteria'] ?? 0);
if ($force_segreteria === 1) {
    $is_segreteria = true;
}

// "firmato" significa: IO ho firmato (se docente)
$firmato_request   = isset($_POST['firmato']) ? intval($_POST['firmato']) : 0;

$studenti = $_POST['studenti'] ?? [];

// firme massivo (segreteria)
$firme_docenti = $_POST['firme_docenti'] ?? null;

/**
 * ✅ FIX ROBUSTEZZA:
 * jQuery può inviare firme_docenti come JSON-string (es. "[]") oppure come array.
 * Se è stringa, provo a decodificare.
 */
if (is_string($firme_docenti)) {
    $tmp = json_decode($firme_docenti, true);
    if (is_array($tmp)) $firme_docenti = $tmp;
}

/**
 * ✅ FIX extra:
 * Se arriva come struttura tipo firme_docenti[0][id_docente]... ma PHP non la interpreta bene,
 * qui normalizzo in array.
 */
if ($firme_docenti !== null && !is_array($firme_docenti)) {
    $firme_docenti = [];
}

// ruolo
$ruolo = strtolower(strval($__utente_ruolo ?? ''));
$is_segreteria = ($ruolo === 'segreteria-didattica' || $ruolo === 'dirigente' || $ruolo === 'admin');

// docente corrente
$id_docente = 0;
if (impersonaRuolo('docente')) {
    $id_docente = intval($__docente_id ?? 0);
}

try {

    // ==============================
    // 1) crea o aggiorna corso_esami_date
    // ==============================
    if (empty($id_esame_data) || intval($id_esame_data) <= 0) {

        $tentativo = dbGetValue("
            SELECT IFNULL(MAX(tentativo), 0) + 1
            FROM corso_esami_date
            WHERE id_corso = $corso_id
        ");

        dbExec("
            INSERT INTO corso_esami_date
                (id_corso, tentativo, data_inizio_esame, data_fine_esame, aula, firmato, created_at, updated_at)
            VALUES
                ($corso_id, $tentativo,
                 " . sqlv($data_inizio_esame) . ",
                 " . sqlv($data_fine_esame) . ",
                 " . sqlv($aula_esame) . ",
                 0,
                 NOW(), NOW())
        ");

        $id_esame_data = dblastId();

        // pre-creazione righe esiti SOLO per il primo tentativo (come già facevi)
        if (intval($tentativo) === 1) {
            dbExec("
                INSERT INTO corso_esiti
                    (id_corso, id_esame_data, id_studente, presente, tipo_prova, voto, argomenti,
                     assenza_giustificata, assenza_note,
                     inviato_registro, created_at, updated_at)
                SELECT
                    ci.id_corso,
                    $id_esame_data,
                    ci.id_studente,
                    0 AS presente,
                    NULL AS tipo_prova,
                    NULL AS voto,
                    NULL AS argomenti,
                    0 AS assenza_giustificata,
                    NULL AS assenza_note,
                    0 AS inviato_registro,
                    NOW(), NOW()
                FROM corso_iscritti ci
                WHERE ci.id_corso = $corso_id
            ");
        }
    } else {
        dbExec("
            UPDATE corso_esami_date
            SET data_inizio_esame = " . sqlv($data_inizio_esame) . ",
                data_fine_esame   = " . sqlv($data_fine_esame) . ",
                aula              = " . sqlv($aula_esame) . ",
                updated_at        = NOW()
            WHERE id = " . intval($id_esame_data) . "
              AND id_corso = $corso_id
        ");
    }

    $id_esame_data = intval($id_esame_data);

    // ==============================
    // 1b) FIRMA per-docente (DOCENTE)
    // ==============================
    // Docente: SOLO la mia firma, e NON aggiornare firmato_il se già presente
    if ($id_docente > 0) {
        if ($firmato_request === 1) {
            dbExec("
                INSERT INTO corso_esami_date_firme (id_esame_data, id_docente, firmato_il)
                SELECT $id_esame_data, $id_docente, NOW()
                WHERE NOT EXISTS (
                    SELECT 1 FROM corso_esami_date_firme
                    WHERE id_esame_data = $id_esame_data AND id_docente = $id_docente
                )
            ");
        } else {
            dbExec("
                DELETE FROM corso_esami_date_firme
                WHERE id_esame_data = $id_esame_data AND id_docente = $id_docente
            ");
        }
    }

    // ==============================
    // 1c) FIRME MASSIVE (SEGRETERIA)
    // ==============================
    // Segreteria: può spuntare firme docenti, ma NON deve aggiornare firmato_il se già firmato.
    // Inoltre: whitelist docenti del corso.
    if ($is_segreteria && is_array($firme_docenti)) {

        $docenti_ok = dbGetAll("SELECT id_docente FROM corso_docenti WHERE id_corso = $corso_id");
        if (!$docenti_ok) $docenti_ok = [];

        $allowed = [];
        foreach ($docenti_ok as $r) {
            $allowed[intval($r['id_docente'])] = 1;
        }

        // fallback legacy: se non c'è corso_docenti, consenti almeno il docente principale del corso
        if (count($allowed) === 0) {
            $doc_princ = intval(dbGetValue("SELECT id_docente FROM corso WHERE id = $corso_id LIMIT 1"));
            if ($doc_princ > 0) $allowed[$doc_princ] = 1;
        }

        foreach ($firme_docenti as $fd) {
            $did = intval($fd['id_docente'] ?? 0);
            $f   = intval($fd['firmato'] ?? 0);

            if ($did <= 0) continue;
            if (!isset($allowed[$did])) continue;

            if ($f === 1) {
                // inserisco solo se non esiste (NON aggiorno firmato_il)
                dbExec("
                    INSERT INTO corso_esami_date_firme (id_esame_data, id_docente, firmato_il)
                    SELECT $id_esame_data, $did, NOW()
                    WHERE NOT EXISTS (
                        SELECT 1 FROM corso_esami_date_firme
                        WHERE id_esame_data = $id_esame_data AND id_docente = $did
                    )
                ");
            } else {
                dbExec("
                    DELETE FROM corso_esami_date_firme
                    WHERE id_esame_data = $id_esame_data AND id_docente = $did
                ");
            }
        }
    }

    // ==============================
    // 1d) ricalcolo sintetico (ANY/ALL)
    // ==============================
    recalcFirmatoEsame($id_esame_data);

    // ==============================
    // 2) aggiorna/crea corso_esiti per quella sessione
    // ==============================
    foreach ($studenti as $stud) {
        $stud_id   = intval($stud['id_studente'] ?? 0);
        if ($stud_id <= 0) continue;

        $presente  = isset($stud['presente']) ? intval($stud['presente']) : 0;
        $tipologia = $stud['tipo'] ?? null;
        $voto      = ($stud['voto'] !== '' && $stud['voto'] !== null) ? floatval($stud['voto']) : null;

        $assenza_giust = isset($stud['assenza_giustificata']) ? intval($stud['assenza_giustificata']) : 0;
        $assenza_note  = $stud['assenza_note'] ?? null;

        if ($presente === 1) {
            $assenza_giust = 0;
            $assenza_note = null;
        } else {
            if ($assenza_giust !== 1) $assenza_note = null;
        }

        $rowEsito = dbGetFirst("
            SELECT id
            FROM corso_esiti
            WHERE id_corso = $corso_id
              AND id_studente = $stud_id
              AND id_esame_data = $id_esame_data
        ");

        if ($rowEsito) {
            dbExec("
                UPDATE corso_esiti
                SET presente = $presente,
                    tipo_prova = " . sqlv($tipologia) . ",
                    voto = " . ($voto !== null ? $voto : "NULL") . ",
                    argomenti = " . sqlv($argomenti) . ",
                    assenza_giustificata = " . intval($assenza_giust) . ",
                    assenza_note = " . sqlv($assenza_note) . ",
                    updated_at = NOW()
                WHERE id = " . intval($rowEsito['id']) . "
            ");
        } else {
            dbExec("
                INSERT INTO corso_esiti
                    (id_corso, id_esame_data, id_studente, presente, tipo_prova, voto, argomenti,
                     assenza_giustificata, assenza_note,
                     inviato_registro, created_at, updated_at)
                VALUES
                    ($corso_id, $id_esame_data, $stud_id, $presente,
                     " . sqlv($tipologia) . ",
                     " . ($voto !== null ? $voto : "NULL") . ",
                     " . sqlv($argomenti) . ",
                     " . intval($assenza_giust) . ",
                     " . sqlv($assenza_note) . ",
                     0, NOW(), NOW())
            ");
        }
    }

    echo json_encode(['success' => true, 'id_esame_data' => $id_esame_data], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
