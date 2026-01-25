<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/**
 * mbappSyncSportello.php (common)
 *
 * Scopo:
 * - Sync di UNA prenotazione MBApp associata ad uno sportello GestOre.
 * - Se esiste link (sportello_mbapp_link): aggiorna la prenotazione ESISTENTE su MBApp (UPDATE)
 *   modificando data/ora/aula/oraFine/giorno (e opzionalmente testi se preserve_text_fields=false).
 * - Se NON esiste link: crea prenotazione su MBApp + crea link in sportello_mbapp_link.
 *
 * Vincoli colonne MBApp (come da tuo schema):
 * - assenze PK: idAssenza
 * - oralezione PK: idCalendario
 * - oralezione ha campo idAssenza (attenzione: nelle tue note a volte "IdAssenza", ma usiamo PK dove possibile)
 * - utilizza ha campo IDassenza (D maiuscola)
 */

require_once __DIR__ . '/connect.php';        // GestOre
require_once __DIR__ . '/connectMBApp.php';   // MBApp: mb_dbExec / mb_dbGetFirst / mb_dbGetValue / mb_dbAffectedRows

/* -------------------------------------------------------
   Helpers
------------------------------------------------------- */
function mbapp_giorno_it(string $dataYmd): string
{
    $giorni = [
        'Sunday'    => 'domenica',
        'Monday'    => 'lunedi',
        'Tuesday'   => 'martedi',
        'Wednesday' => 'mercoledi',
        'Thursday'  => 'giovedi',
        'Friday'    => 'venerdi',
        'Saturday'  => 'sabato'
    ];
    $ts = strtotime($dataYmd);
    $eng = $ts ? date('l', $ts) : '';
    return $giorni[$eng] ?? '';
}

/**
 * NB: in GestOre "numero_ore" = 1 o 2 slot da 50 minuti (coerente con le tue logiche precedenti).
 */
function mbapp_calc_ora_fine(string $oraInizio, int $numero_ore = 1): string
{
    $numero_ore = max(1, (int)$numero_ore);
    $dt = DateTime::createFromFormat('H:i', trim($oraInizio));
    if (!$dt) return trim($oraInizio);
    $dt->modify('+' . (50 * $numero_ore) . ' minutes');
    return $dt->format('H:i');
}

function mbapp_username_from_docente_id(int $docente_id): string
{
    $docente_id = (int)$docente_id;
    if ($docente_id <= 0) return '';

    // email docente da GestOre
    $email = (string)dbGetValue("SELECT email FROM docente WHERE id=$docente_id LIMIT 1");
    $email = trim($email);
    if ($email === '') return '';

    $emailEsc = addslashes($email);
    return trim((string)(mb_dbGetValue("SELECT username FROM utente WHERE email1='$emailEsc' LIMIT 1") ?? ''));
}

/* -------------------------------------------------------
   API principale
------------------------------------------------------- */
/**
 * @param int   $sportello_id  ID sportello GestOre
 * @param array $p             payload:
 *   - data (Y-m-d)
 *   - ora (H:i)
 *   - luogo (nroAula)
 *   - numero_ore (1/2)
 *   - docente_id (opzionale ma consigliato)
 *   - docenti (testo)         usato solo in CREATE o se preserve_text_fields=false
 *   - motivo (testo)          usato solo in CREATE o se preserve_text_fields=false
 *   - dettagli (testo)        usato solo in CREATE o se preserve_text_fields=false
 *   - attivitaProgetto (testo) usato solo in CREATE o se preserve_text_fields=false
 *   - preserve_text_fields (bool) default true
 *
 * @return array ['ok'=>bool,'action'=>..., 'msg'=>..., 'idAssenza'=>..., 'idCalendario'=>...]
 */
function mbapp_sync_sportello(int $sportello_id, array $p): array
{
    $sportello_id = (int)$sportello_id;
    if ($sportello_id <= 0) {
        return ['ok' => false, 'action' => 'error', 'msg' => 'sportello_id non valido'];
    }

    // sanity helpers MBApp
    if (!function_exists('mb_dbExec') || !function_exists('mb_dbGetFirst') || !function_exists('mb_dbGetValue')) {
        return ['ok' => false, 'action' => 'error', 'msg' => 'Helpers MBApp mancanti (connectMBApp.php)'];
    }

    $data = trim((string)($p['data'] ?? ''));
    $ora  = trim((string)($p['ora'] ?? ''));
    $aula = trim((string)($p['luogo'] ?? ''));
    $nOre = (int)($p['numero_ore'] ?? 1);
    if ($nOre < 1) $nOre = 1;
    if ($nOre > 2) $nOre = 2;

    if ($data === '' || $ora === '' || $aula === '') {
        return ['ok' => false, 'action' => 'skip', 'msg' => 'Dati insufficienti (data/ora/luogo)'];
    }

    $oraFine = mbapp_calc_ora_fine($ora, $nOre);
    $giorno  = mbapp_giorno_it($data);

    $preserve = true;
    if (array_key_exists('preserve_text_fields', $p)) {
        $preserve = (bool)$p['preserve_text_fields'];
    }

    // testi (usati in CREATE o in UPDATE se preserve=false)
    $docentiTxt = (string)($p['docenti'] ?? '');
    $motivoTxt  = (string)($p['motivo'] ?? '');
    $dettTxt    = (string)($p['dettagli'] ?? '');
    $attivitaTxt = (string)($p['attivitaProgetto'] ?? '');

    $docentiEsc = addslashes($docentiTxt);
    $motivoEsc  = addslashes($motivoTxt);
    $dettEsc    = addslashes($dettTxt);
    $attivitaEsc = addslashes($attivitaTxt);

    $dataEsc = addslashes($data);
    $oraEsc  = addslashes($ora);
    $oraFineEsc = addslashes($oraFine);
    $giornoEsc  = addslashes($giorno);
    $aulaEsc    = addslashes($aula);

    // 1) verifica link
    $link = dbGetFirst("
        SELECT idAssenza, idCalendario
        FROM sportello_mbapp_link
        WHERE id_sportello = $sportello_id
        LIMIT 1
    ");

    if ($link && !empty($link['idAssenza']) && !empty($link['idCalendario'])) {
        // UPDATE (sposta prenotazione esistente, NON delete/reinsert)
        $idAss = (int)$link['idAssenza'];
        $idCal = (int)$link['idCalendario'];

        // oralezione: aggiorno per PK idCalendario (più robusto di qualunque case su idAssenza)
        $sqlO = "
            UPDATE oralezione
            SET
                nroAula    = '$aulaEsc',
                dataGiorno = '$dataEsc',
                giorno     = '$giornoEsc',
                ora        = '$oraEsc'
            WHERE idCalendario = $idCal
            LIMIT 1
        ";
        mb_dbExec($sqlO);
        $affO = (int)mb_dbAffectedRows();

        // assenze: aggiorno per PK idAssenza
        $sqlA_set = "
            dataInizio = '$dataEsc',
            dataFine   = '$dataEsc',
            oraInizio  = '$oraEsc',
            oraFine    = '$oraFineEsc'
        ";

        if (!$preserve) {
            // opzionale: aggiorna anche testi
            $sqlA_set .= ",
            docenti  = '$docentiEsc',
            motivo   = '$motivoEsc',
            dettagli = '$dettEsc'
            ";
        }

        $sqlA = "
            UPDATE assenze
            SET $sqlA_set
            WHERE idAssenza = $idAss
            LIMIT 1
        ";
        mb_dbExec($sqlA);
        $affA = (int)mb_dbAffectedRows();

        return [
            'ok' => true,
            'action' => 'update',
            'msg' => "MBApp aggiornata (oralezione:$affO, assenze:$affA) preserve_text_fields=" . ($preserve ? 'true' : 'false'),
            'idAssenza' => $idAss,
            'idCalendario' => $idCal
        ];
    }

    // 2) CREATE (non esiste link)
    $stato = 'CONFERMATO';

    // assenze
    mb_dbExec("
        INSERT INTO assenze (docenti, dataInizio, dataFine, oraInizio, oraFine, motivo, dettagli, stato)
        VALUES ('$docentiEsc', '$dataEsc', '$dataEsc', '$oraEsc', '$oraFineEsc', '$motivoEsc', '$dettEsc', '$stato')
    ");
    $idAssenza = (int)mb_dbGetValue("SELECT LAST_INSERT_ID()");

    if ($idAssenza <= 0) {
        return ['ok' => false, 'action' => 'create', 'msg' => 'INSERT assenze fallito (idAssenza=0)'];
    }

    // oralezione
    mb_dbExec("
        INSERT INTO oralezione (nroAula, dataGiorno, giorno, ora, attivitaProgetto, stato, idAssenza)
        VALUES ('$aulaEsc', '$dataEsc', '$giornoEsc', '$oraEsc', '$attivitaEsc', '$stato', $idAssenza)
    ");
    $idCalendario = (int)mb_dbGetValue("SELECT LAST_INSERT_ID()");

    if ($idCalendario <= 0) {
        return ['ok' => false, 'action' => 'create', 'msg' => 'INSERT oralezione fallito (idCalendario=0)', 'idAssenza' => $idAssenza];
    }

    // utilizza (se ho docente_id posso agganciare username MBApp)
    $docente_id = (int)($p['docente_id'] ?? 0);
    $usernameMB = $docente_id > 0 ? mbapp_username_from_docente_id($docente_id) : '';
    if ($usernameMB !== '') {
        $uEsc = addslashes($usernameMB);
        // NB: in utilizza la colonna è IDassenza (come mi hai detto)
        mb_dbExec("INSERT INTO utilizza (idCalendario, username, IDassenza) VALUES ($idCalendario, '$uEsc', $idAssenza)");
    }

    // link su GestOre
    dbExec("
        INSERT INTO sportello_mbapp_link (id_sportello, idAssenza, idCalendario, created_at, updated_at)
        VALUES ($sportello_id, $idAssenza, $idCalendario, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            idAssenza = VALUES(idAssenza),
            idCalendario = VALUES(idCalendario),
            updated_at = NOW()
    ");

    return [
        'ok' => true,
        'action' => 'create',
        'msg' => 'MBApp creata + link salvato',
        'idAssenza' => $idAssenza,
        'idCalendario' => $idCalendario
    ];
}
