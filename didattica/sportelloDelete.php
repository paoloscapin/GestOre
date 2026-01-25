<?php
/**
 * sportelloDelete.php (didattica)
 * - elimina fisicamente record sportello su GestOre
 * - se esiste link, elimina fisicamente la prenotazione su MBApp (utilizza, oralezione, assenze)
 * - elimina il link sportello_mbapp_link
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';          // ✅ DB GestOre
require_once '../common/connectMBApp.php';     // ✅ DB MBApp

ruoloRichiesto('segreteria-didattica');

header('Content-Type: application/json; charset=utf-8');

function jsonOut($ok, $extra = [])
{
    echo json_encode(array_merge(['ok' => (bool)$ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

info("sportelloDelete.php START POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE));

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonOut(false, ['error' => 'ID non valido']);
}

// default per output (evita notice)
$mbappAssenzaId = 0;
$mbappCalendarioId = 0;

try {
    /* =========================================================
       1) Cerca link GestOre -> MBApp
    ========================================================= */
    $result = dbGetFirst("
        SELECT idAssenza, idCalendario
        FROM sportello_mbapp_link
        WHERE id_sportello = $id
        LIMIT 1
    ");

    if ($result) {
        $mbappAssenzaId = (int)($result['idAssenza'] ?? 0);
        $mbappCalendarioId = (int)($result['idCalendario'] ?? 0);

        // ======================================================
        // 1a) DELETE su MBApp (case esatti come hai indicato)
        // ======================================================

        // UTILIZZA: IDassenza (con D maiuscola)
        if ($mbappCalendarioId > 0 || $mbappAssenzaId > 0) {
            $mbquery = "
                DELETE
                FROM utilizza
                WHERE idCalendario = $mbappCalendarioId
                  AND IDassenza   = $mbappAssenzaId
            ";
            mb_dbExec($mbquery);
            info("MBApp: DELETE utilizza idCalendario=$mbappCalendarioId IDassenza=$mbappAssenzaId affected=" . (int)mb_dbAffectedRows());

            // ORALEZIONE: IdAssenza
            $mbquery = "
                DELETE
                FROM oralezione
                WHERE idCalendario = $mbappCalendarioId
                  AND IdAssenza    = $mbappAssenzaId
            ";
            mb_dbExec($mbquery);
            info("MBApp: DELETE oralezione idCalendario=$mbappCalendarioId IdAssenza=$mbappAssenzaId affected=" . (int)mb_dbAffectedRows());

            // ASSENZE: IdAssenza
            if ($mbappAssenzaId > 0) {
                $mbquery = "
                    DELETE
                    FROM assenze
                    WHERE IdAssenza = $mbappAssenzaId
                    LIMIT 1
                ";
                mb_dbExec($mbquery);
                info("MBApp: DELETE assenze IdAssenza=$mbappAssenzaId affected=" . (int)mb_dbAffectedRows());
            }
        }

        // ======================================================
        // 1b) Cancella link in GestOre
        // ======================================================
        dbExec("
            DELETE
            FROM sportello_mbapp_link
            WHERE id_sportello = $id
            LIMIT 1
        ");
        info("GestOre: DELETE sportello_mbapp_link id_sportello=$id");
    } else {
        info("sportelloDelete.php: nessun link MBApp per id_sportello=$id");
    }

    /* =========================================================
       2) Elimina record sportello su GestOre (hard delete)
       (se hai tabelle collegate, cancella prima quelle)
    ========================================================= */

    // Se esiste questa tabella, va bene; se non esiste, commentala.
    // (Molto probabile in GestOre che ci sia)
    dbExec("DELETE FROM sportello_studente WHERE sportello_id = $id");
    info("GestOre: DELETE sportello_studente sportello_id=$id");

    // Elimina sportello
    dbExec("DELETE FROM sportello WHERE id = $id LIMIT 1");
    info("GestOre: DELETE sportello id=$id");

    jsonOut(true, [
        'id' => $id,
        'mbapp' => [
            'idAssenza' => $mbappAssenzaId,
            'idCalendario' => $mbappCalendarioId
        ],
        'msg' => 'Sportello eliminato (GestOre) e prenotazione MBApp rimossa se presente'
    ]);
} catch (Throwable $e) {
    warning("sportelloDelete.php ERROR id=$id err=" . $e->getMessage());
    jsonOut(false, [
        'id' => $id,
        'error' => $e->getMessage(),
        'mbapp' => [
            'idAssenza' => $mbappAssenzaId,
            'idCalendario' => $mbappCalendarioId
        ],
    ]);
}
