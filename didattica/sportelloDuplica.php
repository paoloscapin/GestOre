<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$sportello_id = intval($_POST['sportello_id'] ?? 0);
if ($sportello_id <= 0) {
    echo json_encode(["ok" => false, "msg" => "Parametro non valido"]);
    exit;
}

$anno_corrente = intval($__anno_scolastico_corrente_id);

$orig = dbGetFirst("
    SELECT *
    FROM sportello
    WHERE id = $sportello_id
      AND anno_scolastico_id = $anno_corrente
");

if (!$orig) {
    echo json_encode(["ok" => false, "msg" => "Sportello non trovato"]);
    exit;
}

dbExec("START TRANSACTION");

try {
    // Copia “1:1” dei campi più importanti + reset firmato/cancellato
    // Nota: categoria e classe possono essere sia testo che id (nel tuo progetto ci sono entrambe),
    // quindi copiamo entrambi dove presenti.
    $data          = addslashes($orig['data'] ?? '');
    $ora           = addslashes($orig['ora'] ?? '');
    $docente_id    = intval($orig['docente_id'] ?? 0);
    $materia_id    = intval($orig['materia_id'] ?? 0);
    $categoria     = addslashes($orig['categoria'] ?? '');
    $numero_ore    = addslashes($orig['numero_ore'] ?? '0');
    $argomento     = addslashes($orig['argomento'] ?? '');
    $luogo         = addslashes($orig['luogo'] ?? '');
    $max_iscr      = intval($orig['max_iscrizioni'] ?? 0);

    $online        = intval($orig['online'] ?? 0);
    $clil          = intval($orig['clil'] ?? 0);
    $orientamento  = intval($orig['orientamento'] ?? 0);

    // classe “testo” e/o classe_id
    $classe        = addslashes($orig['classe'] ?? '');
    $classe_id     = intval($orig['classe_id'] ?? 0);

    // Inserimento nuovo sportello
    $q = "
        INSERT INTO sportello
            (data, ora, docente_id, materia_id, categoria,
             numero_ore, argomento, luogo,
             classe, classe_id,
             firmato, cancellato,
             max_iscrizioni, online, clil, orientamento,
             anno_scolastico_id)
        VALUES
            ('$data', '$ora', $docente_id, $materia_id, '$categoria',
             '$numero_ore', '$argomento', '$luogo',
             '$classe', $classe_id,
             0, 0,
             $max_iscr, $online, $clil, $orientamento,
             $anno_corrente)
    ";
    dbExec($q);

    $new_id = intval(dblastId());
    if ($new_id <= 0) {
        dbExec("ROLLBACK");
        echo json_encode(["ok" => false, "msg" => "Errore creazione sportello duplicato"]);
        exit;
    }

    dbExec("COMMIT");
    echo json_encode(["ok" => true, "new_sportello_id" => $new_id]);
    exit;

} catch (Exception $e) {
    try { dbExec("ROLLBACK"); } catch (Exception $e2) {}
    echo json_encode(["ok" => false, "msg" => "Errore duplicazione: " . $e->getMessage()]);
    exit;
}
