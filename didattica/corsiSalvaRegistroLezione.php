<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('docente', 'admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function getDocenteIdCorrente($con) {
    global $__docente_id, $__username;
    if (isset($__docente_id) && intval($__docente_id) > 0) return intval($__docente_id);

    $u = mysqli_real_escape_string($con, strval($__username ?? ''));
    if ($u !== '') {
        $row = dbGetFirst("SELECT id FROM docente WHERE username='$u' LIMIT 1");
        if ($row && isset($row['id'])) return intval($row['id']);
    }
    return 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Metodo non consentito"]);
    exit;
}

$data_id   = isset($_POST['data_id']) ? intval($_POST['data_id']) : 0;
$corso_id  = isset($_POST['corso_id']) ? intval($_POST['corso_id']) : 0;
$argomenti = isset($_POST['argomenti']) ? trim($_POST['argomenti']) : "";
$presenze  = $_POST['presenze'] ?? [];
$firmato   = isset($_POST['firmato']) ? intval($_POST['firmato']) : 0;

// nuove firme lato segreteria
$firme_docenti = $_POST['firme_docenti'] ?? null;

if (is_string($presenze)) $presenze = json_decode($presenze, true);
if (!is_array($presenze)) $presenze = [];

if (is_string($firme_docenti)) {
    $tmp = json_decode($firme_docenti, true);
    if (is_array($tmp)) $firme_docenti = $tmp;
}
if ($firme_docenti !== null && !is_array($firme_docenti)) $firme_docenti = null;

if (!$data_id || !$corso_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Parametri mancanti"]);
    exit;
}

$argomenti_sql = mysqli_real_escape_string($con, $argomenti);

try {
    // =========================
    // 1) Presenze
    // =========================
    dbExec("DELETE FROM corso_presenti WHERE id_data_corso = $data_id");

    foreach ($presenze as $presenza) {
        $id_studente = isset($presenza['id_studente']) ? intval($presenza['id_studente']) : 0;
        $presente = isset($presenza['presente']) && intval($presenza['presente']) ? 1 : 0;
        if ($id_studente > 0 && $presente) {
            dbExec("INSERT INTO corso_presenti (id_data_corso, id_studente) VALUES ($data_id, $id_studente)");
        }
    }

    // =========================
    // 2) Argomenti
    // =========================
    $existing = dbGetFirst("SELECT 1 FROM corso_argomenti WHERE id_data_corso = $data_id");
    if ($existing) {
        dbExec("UPDATE corso_argomenti SET argomento = '$argomenti_sql' WHERE id_data_corso = $data_id");
    } else {
        dbExec("INSERT INTO corso_argomenti (id_data_corso, argomento) VALUES ($data_id, '$argomenti_sql')");
    }

    // =========================
    // 3) Firme
    // =========================
    $isSegreteria = haRuolo('segreteria-didattica') || haRuolo('dirigente') || haRuolo('admin');

    if ($isSegreteria && $firme_docenti !== null) {
        // modalità segreteria: riscrivo firme da lista checkbox
        dbExec("DELETE FROM corso_date_firme WHERE id_data_corso = $data_id");

        foreach ($firme_docenti as $r) {
            $did = intval($r['id_docente'] ?? 0);
            $chk = intval($r['firmato'] ?? 0);
            if ($did > 0 && $chk === 1) {
                dbExec("
                    INSERT INTO corso_date_firme (id_data_corso, id_docente, firmato_il, minuti, note)
                    VALUES ($data_id, $did, NOW(), NULL, NULL)
                ");
            }
        }
    } else {
        // modalità docente: firmo/annullo solo la MIA firma
        $docente_id = getDocenteIdCorrente($con);
        if ($docente_id <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Docente non identificato"]);
            exit;
        }

        if ($firmato === 1) {
            $esiste = dbGetFirst("
                SELECT 1
                FROM corso_date_firme
                WHERE id_data_corso = $data_id AND id_docente = $docente_id
                LIMIT 1
            ");
            if ($esiste) {
                dbExec("
                    UPDATE corso_date_firme
                    SET firmato_il = NOW()
                    WHERE id_data_corso = $data_id AND id_docente = $docente_id
                ");
            } else {
                dbExec("
                    INSERT INTO corso_date_firme (id_data_corso, id_docente, firmato_il, minuti, note)
                    VALUES ($data_id, $docente_id, NOW(), NULL, NULL)
                ");
            }
        } else {
            dbExec("
                DELETE FROM corso_date_firme
                WHERE id_data_corso = $data_id AND id_docente = $docente_id
            ");
        }
    }

    // aggiorno flag totale su corso_date
    $nFirme = dbGetValue("SELECT COUNT(*) FROM corso_date_firme WHERE id_data_corso = $data_id");
    $nFirme = $nFirme ? intval($nFirme) : 0;
    $flagFirmato = ($nFirme > 0) ? 1 : 0;

    dbExec("UPDATE corso_date SET firmato = $flagFirmato WHERE id = $data_id");

    echo json_encode(["success" => true, "id" => $corso_id], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
