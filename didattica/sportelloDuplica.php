<?php
/**
 * sportelloDuplica.php (didattica)
 * - Duplica uno sportello su GestOre
 * - Nel nuovo sportello: luogo='' (aula vuota), docente_id=0, attivo=0 (bozza), firmato=0, cancellato=0
 * - NON crea prenotazioni su MBApp e NON scrive sportello_mbapp_link
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function jsonOut($ok, $extra = [])
{
    echo json_encode(array_merge(['ok' => (bool)$ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$sportello_id = (int)($_POST['sportello_id'] ?? 0);
if ($sportello_id <= 0) {
    jsonOut(false, ["msg" => "Parametro non valido"]);
}

$anno_corrente = (int)$__anno_scolastico_corrente_id;

// escape minimale (stile legacy progetto)
function escGO($s) { return addslashes((string)$s); }

// legge sportello origine
$orig = dbGetFirst("
    SELECT *
    FROM sportello
    WHERE id = $sportello_id
      AND anno_scolastico_id = $anno_corrente
    LIMIT 1
");

if (!$orig) {
    jsonOut(false, ["msg" => "Sportello non trovato"]);
}

dbExec("START TRANSACTION");

try {
    $data         = escGO($orig['data'] ?? '');
    $ora          = escGO($orig['ora'] ?? '');
    $docente_id   = escGO($orig['docente_id'] ?? 0);

    // IMPORTANTI: nel duplicato NON vogliamo docente, NON vogliamo aula, NON vogliamo attivo
    $luogo        = ""; // aula vuota
    $attivo       = 0; 
    $firmato      = 0;
    $cancellato   = 0;

    $materia_id   = (int)($orig['materia_id'] ?? 0);
    $categoria    = escGO($orig['categoria'] ?? '');

    $numero_ore   = (int)($orig['numero_ore'] ?? 1);
    if ($numero_ore <= 0) $numero_ore = 1;

    $argomento    = escGO($orig['argomento'] ?? '');
    $max_iscr     = (int)($orig['max_iscrizioni'] ?? 0);

    $online       = (int)($orig['online'] ?? 0);
    $clil         = (int)($orig['clil'] ?? 0);
    $orientamento = (int)($orig['orientamento'] ?? 0);

    $classe       = escGO($orig['classe'] ?? '');
    $classe_id    = (int)($orig['classe_id'] ?? 0);

    $note         = escGO($orig['note'] ?? '');

    $luogoEsc = escGO($luogo);

    // duplica GestOre (senza MBApp)
    $q = "
        INSERT INTO sportello
            (data, ora, docente_id, materia_id, categoria,
             numero_ore, argomento, luogo,
             classe, classe_id,
             firmato, cancellato,
             attivo,
             max_iscrizioni, online, clil, orientamento,
             note,
             anno_scolastico_id)
        VALUES
            ('$data', '$ora', $docente_id, $materia_id, '$categoria',
             $numero_ore, '$argomento', '$luogoEsc',
             '$classe', $classe_id,
             $firmato, $cancellato,
             $attivo,
             $max_iscr, $online, $clil, $orientamento,
             '$note',
             $anno_corrente)
    ";
    dbExec($q);

    $new_id = (int)dblastId();
    if ($new_id <= 0) {
        dbExec("ROLLBACK");
        jsonOut(false, ["msg" => "Errore creazione sportello duplicato"]);
    }

    // Per sicurezza: NON deve esistere link MBApp sul duplicato
    dbExec("DELETE FROM sportello_mbapp_link WHERE id_sportello = $new_id");

    dbExec("COMMIT");

    jsonOut(true, [
        "new_sportello_id" => $new_id,
        "msg" => "Sportello duplicato in bozza (aula vuota, nessun link MBApp)"
    ]);

} catch (Throwable $e) {
    try { dbExec("ROLLBACK"); } catch (Throwable $e2) {}
    jsonOut(false, ["msg" => "Errore duplicazione: " . $e->getMessage()]);
}
