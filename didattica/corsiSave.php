<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

if (!getSettingsValue('config', 'corsi', false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Funzione corsi disabilitata']);
    exit;
}

$id         = intval($_POST['id'] ?? -1);
$materia_id = intval($_POST['materia_id'] ?? 0);
$titolo     = trim($_POST['titolo'] ?? '');
$in_itinere = intval($_POST['in_itinere'] ?? 0);
$carenze    = intval($_POST['carenze'] ?? 0);

// compat: docente principale (se arriva)
$docente_id = intval($_POST['docente_id'] ?? 0);

// docenti multipli
$docenti_multi = $_POST['docenti_multi'] ?? [];

// Se arriva come JSON-string
if (is_string($docenti_multi)) {
    $tmp = json_decode($docenti_multi, true);
    if (is_array($tmp)) $docenti_multi = $tmp;
}

// Se non è array -> forzo array vuoto
if (!is_array($docenti_multi)) $docenti_multi = [];

// normalizza (compatibile anche con PHP < 7.4: NO fn())
$docenti_multi = array_map('intval', $docenti_multi);
$docenti_multi = array_values(array_unique($docenti_multi));
$docenti_multi = array_values(array_filter($docenti_multi, function ($x) {
    return $x > 0;
}));

if (count($docenti_multi) === 0 && $docente_id > 0) {
    $docenti_multi = [$docente_id];
}

if ($materia_id <= 0 || $titolo === '' || count($docenti_multi) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametri mancanti o non validi']);
    exit;
}

// docente principale = primo
$docente_principale = intval($docenti_multi[0]);

// anno scolastico corrente
$anno_id = intval($__anno_scolastico_corrente_id);

// helper per errori SQL "umani"
function fail_sql($con, $msg) {
    http_response_code(500);
    $err = '';
    if ($con) $err = mysqli_error($con);
    echo json_encode([
        'success' => false,
        'error' => $msg . ($err ? " | SQL: " . $err : "")
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $titolo_sql = mysqli_real_escape_string($con, $titolo);

    if ($id > 0) {
        // update corso
        $sql = "
            UPDATE corso
            SET id_materia = $materia_id,
                id_docente = $docente_principale,
                id_anno_scolastico = $anno_id,
                titolo = '$titolo_sql',
                carenza = " . intval($carenze) . ",
                in_itinere = " . intval($in_itinere) . "
            WHERE id = $id
        ";
        dbExec($sql);
        $corso_id = $id;

    } else {
        // insert corso
        $sql = "
            INSERT INTO corso
                (id_materia, id_docente, id_anno_scolastico, titolo, carenza, carenza_sessione, in_itinere)
            VALUES
                ($materia_id, $docente_principale, $anno_id, '$titolo_sql', " . intval($carenze) . ", 1, " . intval($in_itinere) . ")
        ";
        dbExec($sql);
        $corso_id = dblastId();
        if (!$corso_id || intval($corso_id) <= 0) {
            fail_sql($con, "Impossibile ottenere ID del corso appena creato");
        }
    }

    // sync corso_docenti (serve tabella e colonna principale)
    dbExec("DELETE FROM corso_docenti WHERE id_corso = $corso_id");

    $pos = 0;
    foreach ($docenti_multi as $did) {
        $did = intval($did);
        if ($did <= 0) continue;

        $principale = ($pos === 0) ? 1 : 0;

        $sql = "
            INSERT INTO corso_docenti (id_corso, id_docente, principale)
            VALUES ($corso_id, $did, $principale)
        ";
        dbExec($sql);
        $pos++;
    }

    // compat: assicuro id_docente coerente
    dbExec("UPDATE corso SET id_docente = $docente_principale WHERE id = $corso_id");

    echo json_encode(['success' => true, 'corso_id' => intval($corso_id)], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // Throwable prende anche Error (es: parse error runtime, type error) su PHP 7+
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    // fallback vecchio (se Throwable non esiste)
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
