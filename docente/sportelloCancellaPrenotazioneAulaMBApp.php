<?php
/**
 * sportelloCancellaPrenotazioneAulaMBApp.php
 *
 * DELETE fisico della prenotazione aula su MBApp associata ad uno sportello GestOre.
 *
 * LOGICA:
 * - Legge da GestOre DB sportello_mbapp_link (idAssenza, idCalendario)
 * - Su MBApp elimina: utilizza -> oralezione -> assenze   (ordine child -> parent)
 * - Su GestOre elimina il link sportello_mbapp_link (anti-duplicazione)
 *
 * NOTE IMPORTANTI (fix):
 * - Non richiede che idAssenza E idCalendario siano entrambi validi: se ne basta uno.
 * - WHERE robusti con OR per coprire casi in cui una tabella usa solo una delle due chiavi.
 * - Nomi colonne MBApp: per compatibilità prova sia idAssenza che IDassenza (utilizza spesso usa IDassenza).
 * - Mostra affected rows in TRACE così vedi subito se ha cancellato 0 righe.
 *
 * REGOLE DB:
 * - MBApp: usare SOLO mb_dbExec / mb_dbGetFirst / mb_dbGetValue / mb_dbAffectedRows
 * - GestOre: usare SOLO dbExec / dbGetFirst / dbGetValue
 */

require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';          // GestOre DB
require_once __DIR__ . '/../common/connectMBApp.php';     // MBApp DB + helpers mb_*

$TRACE = [];
function t_mbdel(string $msg): void {
    global $TRACE;
    $TRACE[] = $msg;
    // __Log.php: debug/info/error
    debug("[cancellaPrenotaAulaMBApp] " . $msg);
}

/* fatal catcher */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @error("[cancellaPrenotaAulaMBApp] FATAL: " . print_r($err, true));
    }
});

// helper: prova una delete e logga affected rows
function mb_try_delete(string $label, string $sql): int {
    t_mbdel("MBApp DELETE $label => " . preg_replace('/\s+/', ' ', trim($sql)));
    mb_dbExec($sql);
    $aff = function_exists('mb_dbAffectedRows') ? (int)mb_dbAffectedRows() : -1;
    t_mbdel("MBApp affected_rows $label = " . $aff);
    return $aff;
}

t_mbdel("START");

// 0) MBApp abilitata? (tollerante a diversi layout settings)
global $__settings;
$mbAppEnabled = false;

// casi tipici: $__settings->MBApp oppure $__settings->config->MBApp
if (isset($__settings->MBApp)) $mbAppEnabled = true;
if (isset($__settings->config) && isset($__settings->config->MBApp)) $mbAppEnabled = !empty($__settings->config->MBApp);

t_mbdel("MBAppEnabled=" . ($mbAppEnabled ? "true" : "false"));
if (!$mbAppEnabled) {
    t_mbdel("SKIP: MBApp disabilitata da config/settings");
    return;
}

// 1) sanity: funzioni MBApp
if (!function_exists('mb_dbExec') || !function_exists('mb_dbGetFirst') || !function_exists('mb_dbGetValue')) {
    t_mbdel("ERRORE: Funzioni MBApp mancanti (mb_dbExec/mb_dbGetFirst/mb_dbGetValue). Controlla connectMBApp.php");
    return;
}
if (!function_exists('mb_dbAffectedRows')) {
    t_mbdel("ATTENZIONE: mb_dbAffectedRows() non disponibile (ok, ma non vedrai affected_rows)");
}

// 2) sportello id deve esistere (arriva da sportelloAggiorna.php come $id)
if (!isset($id) || intval($id) <= 0) {
    t_mbdel("SKIP: sportello id non valido (manca \$id o <=0)");
    return;
}
$idSportello = intval($id);
t_mbdel("idSportello=$idSportello");

// 3) recupera link in GestOre
$idAssenza = 0;
$idCalendario = 0;

try {
    $qLink = "
        SELECT idAssenza, idCalendario
        FROM sportello_mbapp_link
        WHERE id_sportello = $idSportello
        LIMIT 1
    ";
    t_mbdel("GestOre QUERY link => " . preg_replace('/\s+/', ' ', trim($qLink)));
    $linkRow = dbGetFirst($qLink);

    if (!$linkRow) {
        t_mbdel("SKIP: nessun link trovato in sportello_mbapp_link per id_sportello=$idSportello");
        return;
    }

    $idAssenza    = intval($linkRow['idAssenza'] ?? 0);
    $idCalendario = intval($linkRow['idCalendario'] ?? 0);

    t_mbdel("FOUND link: idAssenza=$idAssenza idCalendario=$idCalendario");

    // ✅ fix: basta che ce ne sia UNO valido
    if ($idAssenza <= 0 && $idCalendario <= 0) {
        t_mbdel("SKIP: link presente ma idAssenza/idCalendario entrambi non validi");
        return;
    }
} catch (Throwable $e) {
    t_mbdel("ERRORE lettura link: " . $e->getMessage());
    return;
}

// 4) DELETE su MBApp (ordine: utilizza -> oralezione -> assenze)
try {
    t_mbdel("MBApp START TRANSACTION");
    mb_dbExec("START TRANSACTION");

    // costruiamo WHERE robusti
    $condUtil = [];
    if ($idCalendario > 0) $condUtil[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0) {
        // in MBApp su UTILIZZA spesso è IDassenza (maiuscolo). Proviamo entrambe per sicurezza.
        $condUtil[] = "IDassenza = $idAssenza";
        $condUtil[] = "idAssenza = $idAssenza";
    }
    $whereUtil = implode(" OR ", array_unique($condUtil));
    if ($whereUtil !== '') {
        mb_try_delete('utilizza', "DELETE FROM utilizza WHERE $whereUtil");
    } else {
        t_mbdel("MBApp SKIP delete utilizza: nessuna condizione valida");
    }

    $condOra = [];
    if ($idCalendario > 0) $condOra[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $condOra[] = "idAssenza = $idAssenza";
    $whereOra = implode(" OR ", $condOra);
    if ($whereOra !== '') {
        mb_try_delete('oralezione', "DELETE FROM oralezione WHERE $whereOra");
    } else {
        t_mbdel("MBApp SKIP delete oralezione: nessuna condizione valida");
    }

    // assenze: in MBApp di solito è idAssenza (non id). Se la tua tabella usa id come PK, cambialo qui.
    if ($idAssenza > 0) {
        mb_try_delete('assenze', "DELETE FROM assenze WHERE idAssenza = $idAssenza");
    } else {
        t_mbdel("MBApp SKIP delete assenze: idAssenza non disponibile");
    }

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
    t_mbdel("GestOre DELETE link => " . preg_replace('/\s+/', ' ', trim($qDelLink)));
    dbExec($qDelLink);
    t_mbdel("GestOre link eliminato OK");
} catch (Throwable $e) {
    // MBApp è già pulita; se qui fallisce è “fastidioso” ma non blocca la cancellazione
    t_mbdel("ATTENZIONE: impossibile cancellare link sportello_mbapp_link: " . $e->getMessage());
}

t_mbdel("END");
return;
