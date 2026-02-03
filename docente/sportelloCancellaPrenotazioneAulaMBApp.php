<?php
/**
 * sportelloCancellaPrenotazioneAulaMBApp.php
 *
 * DELETE fisico della prenotazione aula su MBApp associata ad uno sportello GestOre.
 *
 * LOGICA:
 * - Legge da GestOre DB sportello_mbapp_link (idAssenza, idCalendario)
 * - Su MBApp elimina: utilizza -> oralezione -> assenze
 * - Su GestOre elimina il link sportello_mbapp_link (anti-duplicazione)
 *
 * REGOLE DB:
 * - MBApp: usare SOLO mb_dbExec / mb_dbGetFirst / mb_dbGetValue
 * - GestOre: usare SOLO dbExec / dbGetFirst / dbGetValue
 */

require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';          // GestOre DB
require_once __DIR__ . '/../common/connectMBApp.php';     // MBApp DB + helpers mb_*

$TRACE = [];
function t_mbdel($msg) {
    global $TRACE;
    $TRACE[] = $msg;
    debug("[cancellaPrenotaAulaMBApp] " . $msg);
}

/* fatal catcher */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @error("[cancellaPrenotaAulaMBApp] FATAL: " . print_r($err, true));
    }
});

t_mbdel("START");

// 0) MBApp abilitata?
global $__settings;
$mbAppEnabled = !empty($__settings->config->MBApp); // come prenotaAula.php
t_mbdel("settings.config.MBApp=" . ($mbAppEnabled ? "true" : "false"));
if (!$mbAppEnabled) {
    t_mbdel("SKIP: MBApp disabilitata da config");
    return;
}

// 1) sanity: funzioni MBApp
if (!function_exists('mb_dbExec') || !function_exists('mb_dbGetFirst') || !function_exists('mb_dbGetValue')) {
    t_mbdel("ERRORE: Funzioni MBApp mancanti (mb_dbExec/mb_dbGetFirst/mb_dbGetValue). Controlla connectMBApp.php");
    return;
}

// 2) sportello id deve esistere (arriva da sportelloAggiorna.php come $id)
if (!isset($id) || intval($id) <= 0) {
    t_mbdel("SKIP: sportello id non valido (manca $id)");
    return;
}
$idSportello = intval($id);
t_mbdel("idSportello=$idSportello");

// 3) recupera link in GestOre
$idAssenza = 0;
$idCalendario = 0;

try {
    $qLink = "SELECT idAssenza, idCalendario
              FROM sportello_mbapp_link
              WHERE id_sportello = $idSportello
              LIMIT 1";
    t_mbdel("QUERY link => " . preg_replace('/\s+/', ' ', trim($qLink)));
    $linkRow = dbGetFirst($qLink);

    if (!$linkRow) {
        t_mbdel("SKIP: nessun link trovato in sportello_mbapp_link per id_sportello=$idSportello");
        return;
    }

    $idAssenza    = intval($linkRow['idAssenza'] ?? 0);
    $idCalendario = intval($linkRow['idCalendario'] ?? 0);

    t_mbdel("FOUND link: idAssenza=$idAssenza idCalendario=$idCalendario");

    if ($idAssenza <= 0 || $idCalendario <= 0) {
        t_mbdel("SKIP: link presente ma idAssenza/idCalendario non validi");
        return;
    }
} catch (Throwable $e) {
    t_mbdel("ERRORE lettura link: " . $e->getMessage());
    return;
}

// 4) DELETE su MBApp (ordine: utilizza -> oralezione -> assenze)
try {
    // Provo transazione (se MBApp supporta InnoDB è ok; se non supporta non fa danni)
    t_mbdel("MBApp START TRANSACTION");
    mb_dbExec("START TRANSACTION");

    // 4a) delete utilizza (dipende spesso da idCalendario/idAssenza)
    $qU = "DELETE FROM utilizza WHERE idCalendario = $idCalendario AND idAssenza = $idAssenza";
    t_mbdel("MBApp DELETE utilizza => " . $qU);
    mb_dbExec($qU);

    // 4b) delete oralezione (dipende spesso da idAssenza)
    $qO = "DELETE FROM oralezione WHERE id = $idCalendario AND idAssenza = $idAssenza";
    t_mbdel("MBApp DELETE oralezione => " . $qO);
    mb_dbExec($qO);

    // 4c) delete assenze
    $qA = "DELETE FROM assenze WHERE id = $idAssenza";
    t_mbdel("MBApp DELETE assenze => " . $qA);
    mb_dbExec($qA);

    t_mbdel("MBApp COMMIT");
    mb_dbExec("COMMIT");

    t_mbdel("MBApp DELETE completato (idAssenza=$idAssenza, idCalendario=$idCalendario)");
} catch (Throwable $e) {
    t_mbdel("ERRORE DELETE MBApp: " . $e->getMessage());
    // Se qualcosa fallisce, rollback e NON tocco il link per poter ritentare
    try {
        t_mbdel("MBApp ROLLBACK");
        mb_dbExec("ROLLBACK");
    } catch (Throwable $e2) {
        t_mbdel("ERRORE ROLLBACK MBApp: " . $e2->getMessage());
    }
    return;
}

// 5) DELETE link su GestOre (sportello_mbapp_link) — ora possiamo, perché MBApp è pulita
try {
    $qDelLink = "DELETE FROM sportello_mbapp_link WHERE id_sportello = $idSportello";
    t_mbdel("GestOre DELETE link => " . $qDelLink);
    dbExec($qDelLink);
    t_mbdel("GestOre link eliminato OK");
} catch (Throwable $e) {
    // MBApp è già pulita; se qui fallisce è “fastidioso” ma non blocca la cancellazione
    t_mbdel("ATTENZIONE: impossibile cancellare link sportello_mbapp_link: " . $e->getMessage());
}

t_mbdel("END");
return;
