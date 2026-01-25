<?php
/**
 * sportelloRimettiBozza.php (didattica)
 * - mette lo sportello in BOZZA: docente_id=0, luogo='', attivo=0
 * - se esiste link su sportello_mbapp_link:
 *    - cancella prenotazione MBApp (utilizza -> oralezione -> assenze)
 *    - cancella il link
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';          // GestOre
require_once '../common/connectMBApp.php';     // MBApp (mb_dbExec, mb_dbGetFirst, mb_dbGetValue, mb_dbAffectedRows)

ruoloRichiesto('segreteria-didattica');

header('Content-Type: application/json; charset=utf-8');

function jsonOut($ok, $extra = []) {
    echo json_encode(array_merge(['ok' => (bool)$ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

info("sportelloRimettiBozza.php START POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE));

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonOut(false, ['error' => 'ID non valido']);
}

// output safe
$mbappAssenzaId = 0;
$mbappCalendarioId = 0;

try {
    // 1) prendo sportello (per log e per capire se esiste)
    $s = dbGetFirst("
        SELECT id, docente_id, luogo, attivo, cancellato, data, ora
        FROM sportello
        WHERE id = $id
        LIMIT 1
    ");

    if (!$s) {
        jsonOut(false, ['error' => 'Sportello non trovato']);
    }

    // 2) cerco link MBApp
    $link = dbGetFirst("
        SELECT idAssenza, idCalendario
        FROM sportello_mbapp_link
        WHERE id_sportello = $id
        LIMIT 1
    ");

    $mb = [
        'ok' => true,
        'action' => 'skip',
        'msg' => 'Nessun link MBApp'
    ];

    if ($link && !empty($link['idAssenza']) && !empty($link['idCalendario'])) {
        $mbappAssenzaId = (int)$link['idAssenza'];
        $mbappCalendarioId = (int)$link['idCalendario'];

        info("sportelloRimettiBozza MBApp DELETE start id=$id idAssenza=$mbappAssenzaId idCalendario=$mbappCalendarioId");

        // NB: in utilizza la colonna è IDassenza (D maiuscola)
        $qU = "
            DELETE FROM utilizza
            WHERE idCalendario = $mbappCalendarioId
              AND IDassenza   = $mbappAssenzaId
        ";
        mb_dbExec($qU);
        $affU = (int)mb_dbAffectedRows();
        info("MBApp: DELETE utilizza affected=$affU idCalendario=$mbappCalendarioId IDassenza=$mbappAssenzaId");

        // in oralezione usiamo PK idCalendario (più robusto)
        $qO = "
            DELETE FROM oralezione
            WHERE idCalendario = $mbappCalendarioId
            LIMIT 1
        ";
        mb_dbExec($qO);
        $affO = (int)mb_dbAffectedRows();
        info("MBApp: DELETE oralezione affected=$affO idCalendario=$mbappCalendarioId");

        // assenze per PK idAssenza
        $qA = "
            DELETE FROM assenze
            WHERE idAssenza = $mbappAssenzaId
            LIMIT 1
        ";
        mb_dbExec($qA);
        $affA = (int)mb_dbAffectedRows();
        info("MBApp: DELETE assenze affected=$affA idAssenza=$mbappAssenzaId");

        // elimino link su GestOre
        dbExec("
            DELETE FROM sportello_mbapp_link
            WHERE id_sportello = $id
            LIMIT 1
        ");
        info("GestOre: DELETE sportello_mbapp_link id_sportello=$id");

        $mb = [
            'ok' => true,
            'action' => 'delete',
            'msg' => "MBApp eliminata (utilizza:$affU, oralezione:$affO, assenze:$affA) + link rimosso",
            'idAssenza' => $mbappAssenzaId,
            'idCalendario' => $mbappCalendarioId,
            'affected' => ['utilizza' => $affU, 'oralezione' => $affO, 'assenze' => $affA]
        ];
    }

    // 3) metto in bozza su GestOre (docente_id=0, luogo='', attivo=0)
    // Nota: NON tocchiamo cancellato: resta quello che è (di solito 0 in didattica).
    dbExec("
        UPDATE sportello
        SET docente_id = 0,
            luogo = '',
            attivo = 0
        WHERE id = $id
        LIMIT 1
    ");
    info("GestOre: UPDATE sportello -> BOZZA id=$id (docente_id=0, luogo='', attivo=0)");

    jsonOut(true, [
        'id' => $id,
        'mbapp' => $mb,
        'msg' => 'Sportello rimesso in bozza'
    ]);

} catch (Throwable $e) {
    warning("sportelloRimettiBozza.php ERROR id=$id err=" . $e->getMessage());
    jsonOut(false, [
        'id' => $id,
        'error' => $e->getMessage(),
        'mbapp' => [
            'idAssenza' => $mbappAssenzaId,
            'idCalendario' => $mbappCalendarioId
        ]
    ]);
}
