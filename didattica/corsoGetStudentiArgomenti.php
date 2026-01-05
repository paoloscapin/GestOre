<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id_data_corso = isset($_POST['data_id']) ? intval($_POST['data_id']) : 0;
if ($id_data_corso <= 0) {
    echo json_encode(["success" => false, "error" => "id_data_corso mancante"]);
    exit;
}

$corso_info = dbGetFirst("
    SELECT id_corso, firmato
    FROM corso_date
    WHERE id = $id_data_corso
");
if (!$corso_info) {
    echo json_encode(["success" => false, "error" => "Data corso non trovata"]);
    exit;
}

$id_corso = intval($corso_info['id_corso']);
$firmato_totale = isset($corso_info['firmato']) ? intval($corso_info['firmato']) : 0;

global $__anno_scolastico_corrente_id;
$id_anno_scolastico = intval($__anno_scolastico_corrente_id);

// studenti
$studenti = dbGetAll("
    SELECT s.id, CONCAT(s.cognome,' ',s.nome) AS nominativo, cl.classe AS classe
    FROM corso_iscritti ci
    INNER JOIN studente s ON s.id = ci.id_studente
    LEFT JOIN studente_frequenta sf ON sf.id_studente = s.id AND sf.id_anno_scolastico = $id_anno_scolastico
    LEFT JOIN classi cl ON cl.id = sf.id_classe
    WHERE ci.id_corso = $id_corso
    ORDER BY s.cognome, s.nome
");
if (!$studenti) $studenti = [];

// presenze
$presenze = dbGetAll("
    SELECT id_studente
    FROM corso_presenti
    WHERE id_data_corso = $id_data_corso
");
$mapPresenze = [];
if ($presenze) {
    foreach ($presenze as $p) $mapPresenze[intval($p['id_studente'])] = 1;
}
foreach ($studenti as &$s) {
    $sid = intval($s['id']);
    $s['presente'] = isset($mapPresenze[$sid]) ? 1 : 0;
}
unset($s);

// argomento
$arg = dbGetValue("
    SELECT argomento
    FROM corso_argomenti
    WHERE id_data_corso = $id_data_corso
");

// firme (righe presenti)
$firme = dbGetAll("
    SELECT f.id_docente, d.cognome, d.nome, f.firmato_il, f.minuti, f.note
    FROM corso_date_firme f
    INNER JOIN docente d ON d.id = f.id_docente
    WHERE f.id_data_corso = $id_data_corso
    ORDER BY f.firmato_il ASC
");
if (!$firme) $firme = [];

// firmato_me
$docente_id = $__docente_id;
$firmato_me = 0;
if ($docente_id > 0) {
    $cnt = dbGetValue("
        SELECT COUNT(*)
        FROM corso_date_firme
        WHERE id_data_corso = $id_data_corso AND id_docente = $docente_id
    ");
    $firmato_me = $cnt ? 1 : 0;
}

// ✅ docenti del corso + stato firma per quella data
$docenti_firme = dbGetAll("
    SELECT
        cdn.id_docente,
        d.cognome,
        d.nome,
        cdn.principale,
        CASE WHEN f.id_docente IS NULL THEN 0 ELSE 1 END AS firmato,
        f.firmato_il
    FROM corso_docenti cdn
    INNER JOIN docente d ON d.id = cdn.id_docente
    LEFT JOIN corso_date_firme f
           ON f.id_data_corso = $id_data_corso
          AND f.id_docente = cdn.id_docente
    WHERE cdn.id_corso = $id_corso
    ORDER BY cdn.principale DESC, d.cognome ASC, d.nome ASC
");
if (!$docenti_firme) $docenti_firme = [];

echo json_encode([
    "success" => true,
    "studenti" => $studenti,
    "argomento" => $arg,
    "firmato" => $firmato_totale,
    "firmato_me" => $firmato_me,
    "firme" => $firme,
    "docenti_firme" => $docenti_firme
], JSON_UNESCAPED_UNICODE);
