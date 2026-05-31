<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/**
 * sportelloSave.php (didattica)
 * - UPDATE se id>0
 * - INSERT se id==0
 * - SYNC MBApp quando lo sportello diventa "eligible" (attivo=1, cancellato=0, aula non vuota)
 *   - se link esiste => UPDATE prenotazione MBApp esistente (in-place)
 *   - se link non esiste => CREATE prenotazione MBApp + link
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';        // GestOre
ruoloRichiesto('segreteria-didattica');

header('Content-Type: application/json; charset=utf-8');

function jsonOut($ok, $extra = [])
{
    echo json_encode(array_merge(['ok' => (bool)$ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// DEBUG: log POST completo
info("sportelloSave.php START POST=" . json_encode($_POST, JSON_UNESCAPED_UNICODE));

/**
 * Converte d/m/YYYY o d-m-YYYY in Y-m-d.
 * Se già Y-m-d ritorna com'è.
 */
function normDateYmd(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';

    // già YYYY-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

    // dd/mm/YYYY o dd-mm-YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
        $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $y = $m[3];
        return $y . '-' . $mo . '-' . $d;
    }

    // fallback (prova strtotime)
    $ts = strtotime($s);
    if ($ts) return date('Y-m-d', $ts);

    return $s; // ultimo fallback
}

function sportelloNextOraSlot(string $ora): string
{
    $orari = ["07:50","08:40","09:30","10:30","11:20","12:10","13:00","13:50","14:40","15:30","16:20","17:10","18:00","18:50","19:40","20:30","21:30","22:20"];
    $idx = array_search(trim($ora), $orari, true);
    return ($idx !== false && isset($orari[$idx + 1])) ? $orari[$idx + 1] : '';
}

function sportelloMbappIdsForSportelli(array $sportelloIds): array
{
    $ids = array_values(array_filter(array_map('intval', $sportelloIds), function ($id) {
        return $id > 0;
    }));
    if (empty($ids)) {
        return [];
    }

    $values = dbGetAllValues("
        SELECT idCalendario
        FROM sportello_mbapp_link
        WHERE id_sportello IN (" . implode(',', $ids) . ")
          AND idCalendario IS NOT NULL
          AND idCalendario <> 0
    ") ?: [];

    return array_values(array_filter(array_map('intval', $values), function ($id) {
        return $id > 0;
    }));
}

function sportelloEnsureAulaLiberaDueOre(string $data, string $ora1, string $ora2, string $luogo, array $excludeSportelloIds = []): void
{
    global $__settings, $__conMBApp;

    if (empty($__settings->config->MBApp) || trim($luogo) === '') {
        return;
    }

    require_once __DIR__ . '/../common/connectMBApp.php';

    if (!($__conMBApp instanceof mysqli)) {
        throw new Exception('Connessione MBApp non disponibile per verificare le aule.');
    }

    $excludeCalendari = sportelloMbappIdsForSportelli($excludeSportelloIds);
    $excludeSql = '';
    if (!empty($excludeCalendari)) {
        $excludeSql = ' AND idCalendario NOT IN (' . implode(',', $excludeCalendari) . ')';
    }

    $aula = mysqli_real_escape_string($__conMBApp, $luogo);
    $dataEsc = mysqli_real_escape_string($__conMBApp, $data);
    $ora1Esc = mysqli_real_escape_string($__conMBApp, $ora1);
    $ora2Esc = mysqli_real_escape_string($__conMBApp, $ora2);

    $busy = (int)mb_dbGetValue("
        SELECT COUNT(*)
        FROM oralezione
        WHERE nroAula = '$aula'
          AND dataGiorno = '$dataEsc'
          AND ora IN ('$ora1Esc', '$ora2Esc')
          $excludeSql
    ");

    if ($busy > 0) {
        throw new Exception("Aula $luogo non libera per due ore consecutive ($ora1 e $ora2).");
    }
}

function sportelloFindSecondaOraId(int $sportelloId, ?array $old, string $newData, string $newOra2, string $categoria): int
{
    if ($sportelloId <= 0) {
        return 0;
    }

    $noteLike = "%sportello $sportelloId)%";
    $id = (int)dbGetValue("
        SELECT id
        FROM sportello
        WHERE id <> " . dbI($sportelloId) . "
          AND note LIKE " . dbQ($noteLike) . "
          AND cancellato = 0
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($id > 0) {
        return $id;
    }

    if ($old) {
        $oldOra2 = sportelloNextOraSlot((string)($old['ora'] ?? ''));
        if ($oldOra2 !== '') {
            $id = (int)dbGetValue("
                SELECT id
                FROM sportello
                WHERE id <> " . dbI($sportelloId) . "
                  AND data = " . dbQ($old['data'] ?? '') . "
                  AND ora = " . dbQ($oldOra2) . "
                  AND docente_id = " . dbI($old['docente_id'] ?? 0) . "
                  AND materia_id = " . dbI($old['materia_id'] ?? 0) . "
                  AND categoria = " . dbQ($old['categoria'] ?? '') . "
                  AND luogo = " . dbQ($old['luogo'] ?? '') . "
                  AND cancellato = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            if ($id > 0) {
                return $id;
            }
        }
    }

    return 0;
}

function sportelloSyncMbappUnaOra(int $sportelloId, string $data, string $ora, string $luogo, int $docenteId, int $materiaId, string $categoria, string $argomento): array
{
    if ($sportelloId <= 0 || trim($luogo) === '' || $docenteId <= 0) {
        return ['ok' => true, 'action' => 'skip', 'msg' => 'Non eligible (bozza/no aula/no docente)'];
    }

    require_once __DIR__ . '/../common/mbappSyncSportello.php';

    $materiaNome = trim((string)dbGetValue("SELECT nome FROM materia WHERE id=" . dbI($materiaId) . " LIMIT 1"));
    $docenteNome = trim((string)dbGetValue("SELECT username FROM docente WHERE id=" . dbI($docenteId) . " LIMIT 1"));
    if ($materiaNome === '') $materiaNome = 'DIDATTICO';
    if ($docenteNome === '') $docenteNome = 'Segreteria didattica';

    return mbapp_sync_sportello($sportelloId, [
        'data' => $data,
        'ora' => $ora,
        'luogo' => $luogo,
        'numero_ore' => 1,
        'docente_id' => $docenteId,
        'docenti' => $docenteNome,
        'motivo' => 'IMPEGNO IN ISTITUTO',
        'dettagli' => trim($categoria . (trim($argomento) !== '' ? ' - ' . trim($argomento) : '')),
        'attivitaProgetto' => 'SPORTELLO ' . trim($materiaNome),
        'preserve_text_fields' => true
    ]);
}

$id = (int)($_POST['id'] ?? 0);
$old = null;

// Input (valide sia per INSERT che UPDATE)
$data_raw     = trim((string)($_POST['data'] ?? ''));
$data         = normDateYmd($data_raw);

$ora          = trim((string)($_POST['ora'] ?? ''));

$materia_id   = (int)($_POST['materia_id'] ?? 0);
$categoria_id = (int)($_POST['categoria_id'] ?? 0);

$numero_ore   = (int)($_POST['numero_ore'] ?? 0);
if ($numero_ore < 1) $numero_ore = 1;
if ($numero_ore > 2) $numero_ore = 2;

$argomento      = escapePost('argomento');
$max_iscrizioni = (int)($_POST['max_iscrizioni'] ?? 0);

$cancellato   = (int)($_POST['cancellato'] ?? 0);
$firmato      = (int)($_POST['firmato'] ?? 0);
$online       = (int)($_POST['online'] ?? 0);
$clil         = (int)($_POST['clil'] ?? 0);
$orientamento = (int)($_POST['orientamento'] ?? 0);

$docente_id = (int)($_POST['docente_id'] ?? 0);

// luogo
$luogo_raw = trim((string)($_POST['luogo'] ?? ''));
$luogo     = escapePost('luogo');

// attivo coerente col tuo JS
$attivo = ($luogo_raw === '' || $docente_id <= 0) ? 0 : 1;

// Categoria TESTO
if ($categoria_id > 0) {
    $categoria = (string)dbGetValue("SELECT nome FROM sportello_categoria WHERE id=" . (int)$categoria_id . " LIMIT 1");
    if (trim($categoria) === '') $categoria = "sportello didattico";
} else {
    $categoria = "sportello didattico";
}
$categoria_raw = $categoria;
$categoria = addslashes($categoria);

// Classe legacy testo + classe_id
$classe_id = (int)($_POST['classe_id'] ?? 0);
$classe    = trim((string)($_POST['classe'] ?? ''));

if ($classe === '' && isset($_POST['cclasse'])) {
    $classe = trim((string)$_POST['cclasse']);
}
if ($classe_id > 0) {
    $tmp = dbGetValue("SELECT nome FROM classe WHERE id = $classe_id LIMIT 1");
    $tmp = trim((string)$tmp);
    if ($tmp !== '') $classe = $tmp;
}
$classe = addslashes($classe);

// Studenti lists (solo per UPDATE id>0 in pratica, ma li gestiamo comunque)
$studentiDaModificareIdList = json_decode($_POST['studentiDaModificareIdList'] ?? '[]', true);
$studentiDaCancellareIdList = json_decode($_POST['studentiDaCancellareIdList'] ?? '[]', true);
if (!is_array($studentiDaModificareIdList)) $studentiDaModificareIdList = [];
if (!is_array($studentiDaCancellareIdList)) $studentiDaCancellareIdList = [];

$mbapp = ['ok' => false, 'action' => 'skip', 'msg' => 'not executed'];

try {

    /* ==========================================================
       RAMO UPDATE
    ========================================================== */
    if ($id > 0) {

        $old = dbGetFirst("
            SELECT data, ora, luogo, numero_ore, cancellato, attivo, docente_id, materia_id, categoria, argomento
            FROM sportello
            WHERE id = $id
            LIMIT 1
        ");

        if (!$old) {
            jsonOut(false, ['error' => 'Sportello non trovato']);
        }

        // DEBUG old/new
        info("sportelloSave UPDATE id=$id old=" . json_encode($old, JSON_UNESCAPED_UNICODE));
        info("sportelloSave NEW data=$data ora=$ora luogo_raw=$luogo_raw attivo=$attivo cancellato=$cancellato docente_id=$docente_id materia_id=$materia_id categoria_id=$categoria_id numero_ore=$numero_ore");

        $numero_ore_salvato = ($numero_ore === 2) ? 1 : $numero_ore;
        $secondOra = '';
        $secondSportelloId = 0;
        if ($numero_ore === 2 && !$cancellato && $attivo === 1) {
            $secondOra = sportelloNextOraSlot($ora);
            if ($secondOra === '') {
                jsonOut(false, ['error' => "Non esiste uno slot successivo a $ora per creare lo sportello da 2 ore."]);
            }
            $secondSportelloId = sportelloFindSecondaOraId($id, $old, $data, $secondOra, $categoria_raw);
            sportelloEnsureAulaLiberaDueOre($data, $ora, $secondOra, $luogo_raw, [$id, $secondSportelloId]);
        }

        // UPDATE sportello
        $query = "
            UPDATE sportello
            SET
                data = '$data',
                ora = '$ora',
                attivo = $attivo,
                docente_id = $docente_id,
                materia_id = $materia_id,
                categoria = '$categoria',
                numero_ore = $numero_ore_salvato,
                argomento = '$argomento',
                luogo = '$luogo',
                classe = '$classe',
                classe_id = $classe_id,
                max_iscrizioni = $max_iscrizioni,
                cancellato = $cancellato,
                firmato = $firmato,
                online = $online,
                clil = $clil,
                orientamento = $orientamento
            WHERE id = $id
            LIMIT 1
        ";
        dbExec($query);

        info("aggiornato sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id categoria=$categoria numero_ore=$numero_ore_salvato argomento=$argomento luogo=$luogo_raw classe=$classe classe_id=$classe_id max_iscrizioni=$max_iscrizioni online=$online clil=$clil orientamento=$orientamento attivo=$attivo cancellato=$cancellato");

        if ($numero_ore === 2 && !$cancellato && $attivo === 1) {
            if ($secondSportelloId > 0) {
                dbExec("
                    UPDATE sportello
                    SET
                        data = '$data',
                        ora = '$secondOra',
                        attivo = $attivo,
                        docente_id = $docente_id,
                        materia_id = $materia_id,
                        categoria = '$categoria',
                        numero_ore = 1,
                        argomento = '$argomento',
                        luogo = '$luogo',
                        classe = '$classe',
                        classe_id = $classe_id,
                        max_iscrizioni = $max_iscrizioni,
                        cancellato = $cancellato,
                        firmato = 0,
                        online = $online,
                        clil = $clil,
                        orientamento = $orientamento
                    WHERE id = $secondSportelloId
                    LIMIT 1
                ");
                info("aggiornata seconda ora sportello id=$secondSportelloId da sportello id=$id ora=$secondOra");
            } else {
                dbExec("
                    INSERT INTO sportello
                        (data, ora, docente_id, materia_id, categoria,
                         numero_ore, argomento, luogo,
                         classe, classe_id,
                         max_iscrizioni, online, clil, orientamento,
                         attivo, cancellato, firmato,
                         note, anno_scolastico_id)
                    VALUES
                        ('$data', '$secondOra', $docente_id, $materia_id, '$categoria',
                         1, '$argomento', '$luogo',
                         '$classe', $classe_id,
                         $max_iscrizioni, $online, $clil, $orientamento,
                         $attivo, 0, 0,
                         'Creato automaticamente da sportello admin 2 ore (sportello $id)',
                         " . (int)$__anno_scolastico_corrente_id . ")
                ");
                $secondSportelloId = (int)dblastId();
                info("creata seconda ora sportello id=$secondSportelloId da sportello id=$id ora=$secondOra");
            }
        }

        // ===== SYNC MBApp (come già avevi) =====
        try {
            require_once __DIR__ . '/../common/mbappSyncSportello.php';

            $oldData  = (string)($old['data'] ?? '');
            $oldOra   = (string)($old['ora'] ?? '');
            $oldLuogo = trim((string)($old['luogo'] ?? ''));
            $oldOre   = (int)($old['numero_ore'] ?? 1);

            $newLuogo = $luogo_raw;
            $changed = ($oldData !== $data) || ($oldOra !== $ora) || ($oldLuogo !== $newLuogo) || ($oldOre !== $numero_ore_salvato);

            $eligible = (!$cancellato && (int)$attivo === 1 && trim($newLuogo) !== '');

            debug("[sportelloSave] MBApp eligible=" . ($eligible ? 1 : 0) .
                  " changed=" . ($changed ? 1 : 0) .
                  " old=($oldData $oldOra $oldLuogo ore:$oldOre) new=($data $ora $newLuogo ore:$numero_ore_salvato)");

            if ($eligible) {

                $materiaNome = trim((string)dbGetValue("SELECT nome FROM materia WHERE id=" . (int)$materia_id . " LIMIT 1"));
                $docenteNome = trim((string)dbGetValue("SELECT username FROM docente WHERE id=" . (int)$docente_id . " LIMIT 1"));

                if ($materiaNome === '') $materiaNome = 'DIDATTICO';
                if ($docenteNome === '') $docenteNome = 'Segreteria didattica';

                $titoloSportello = "SPORTELLO " . trim($materiaNome);
                $dettagliTxt = "SPORTELLO " . trim($materiaNome);

                $mbapp = mbapp_sync_sportello((int)$id, [
                    'data' => $data,
                    'ora' => $ora,
                    'luogo' => $newLuogo,
                    'numero_ore' => 1,

                    'docente_id' => (int)$docente_id,
                    'docenti' => $docenteNome,
                    'motivo' => "IMPEGNO IN ISTITUTO",
                    'dettagli' => $dettagliTxt,
                    'attivitaProgetto' => $titoloSportello,

                    'preserve_text_fields' => true
                ]);

                if (!empty($mbapp['ok'])) {
                    info("MBApp sync OK: " . ($mbapp['action'] ?? '') . " - " . ($mbapp['msg'] ?? ''));
                } else {
                    warning("MBApp sync FAIL: " . ($mbapp['action'] ?? '') . " - " . ($mbapp['msg'] ?? ''));
                }
            } else {
                $mbapp = ['ok' => true, 'action' => 'skip', 'msg' => 'Non eligible (bozza/cancellato/no aula/no docente)'];
                debug("[sportelloSave] MBApp sync SKIP: non eligible");
            }

            if ($numero_ore === 2 && $secondSportelloId > 0 && !$cancellato && (int)$attivo === 1) {
                $mbappSecond = sportelloSyncMbappUnaOra($secondSportelloId, $data, $secondOra, $luogo_raw, $docente_id, $materia_id, $categoria_raw, $argomento);
                $mbapp['seconda_ora'] = $mbappSecond;
            }

        } catch (Throwable $e) {
            $mbapp = ['ok' => false, 'action' => 'exception', 'msg' => $e->getMessage()];
            warning("MBApp sync exception: " . $e->getMessage());
        }

        // Post-save studenti + cancellazione (lato docente)
        $lastCancellato = (int)($old['cancellato'] ?? 0);
        if ($cancellato && $lastCancellato !== 1) {
            info("invio mail di cancellazione al docente");
            require "../docente/sportelloInviaMailCancellazioneDocente.php";

            info("invio mail di cancellazione agli studenti iscritti allo sportello");
            require "../docente/sportelloInviaMailCancellazioneStudente.php";

            info("cancello prenotazione MBApp sportello id=$id");
            require "../docente/sportelloCancellaPrenotazioneAulaMBApp.php";

            info("cancellazione sportello id=$id: iscrizioni studenti mantenute come storico");
        }

        if ($cancellato) {
            info("sportello cancellato id=$id: salto modifiche/cancellazioni manuali iscrizioni");
        } else {
            $oldDataForMail = (string)($old['data'] ?? '');
            $oldOraForMail = (string)($old['ora'] ?? '');
            $oldLuogoForMail = trim((string)($old['luogo'] ?? ''));
            if ($oldDataForMail !== $data || $oldOraForMail !== $ora || $oldLuogoForMail !== $luogo_raw) {
                $cntIscritti = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id = " . (int)$id);
                if ($cntIscritti > 0) {
                    $sportello_update_old = [
                        'data' => $oldDataForMail,
                        'ora' => $oldOraForMail,
                        'luogo' => $oldLuogoForMail
                    ];
                    info("sportelloSave: invio mail aggiornamento studenti sportello id=$id iscritti=$cntIscritti");
                    require "../docente/sportelloInviaMailAggiornamentoStudente.php";
                }
            }

            info("numero studenti da modificare: " . count($studentiDaModificareIdList));
            foreach ($studentiDaModificareIdList as $ssid) {
                $ssid = (int)$ssid;
                if ($ssid <= 0) continue;
                dbExec("UPDATE sportello_studente SET presente = IF(presente, 0, 1) WHERE id = $ssid");
                info("aggiornato studente iscritto con sportello_studente.id=$ssid");
            }

            info("numero studenti da cancellare: " . count($studentiDaCancellareIdList));
            foreach ($studentiDaCancellareIdList as $ssid) {
                $ssid = (int)$ssid;
                if ($ssid <= 0) continue;
                dbExec("DELETE FROM sportello_studente WHERE id = $ssid");
                info("cancellato studente dallo sportello con sportello_studente.id=$ssid");
            }
        }

        info("sportelloSave.php END UPDATE id=$id");

        jsonOut(true, [
            'id' => $id,
            'second_id' => $secondSportelloId ?? 0,
            'attivo' => $attivo,
            'cancellato' => $cancellato,
            'mbapp' => $mbapp
        ]);
    }

    /* ==========================================================
       RAMO INSERT (NUOVO SPORTELLO)
    ========================================================== */

    // id == 0 => INSERT
    info("sportelloSave INSERT NEW data=$data ora=$ora luogo_raw=$luogo_raw attivo=$attivo cancellato=$cancellato docente_id=$docente_id materia_id=$materia_id categoria_id=$categoria_id numero_ore=$numero_ore");

    // anno scolastico corrente (già disponibile nel tuo progetto)
    $anno = (int)$__anno_scolastico_corrente_id;
    $numero_ore_salvato = ($numero_ore === 2) ? 1 : $numero_ore;
    $secondOra = '';

    if ($numero_ore === 2 && !$cancellato && $attivo === 1) {
        $secondOra = sportelloNextOraSlot($ora);
        if ($secondOra === '') {
            jsonOut(false, ['error' => "Non esiste uno slot successivo a $ora per creare lo sportello da 2 ore."]);
        }
        sportelloEnsureAulaLiberaDueOre($data, $ora, $secondOra, $luogo_raw, []);
    }

    // Inserisco sportello (cancellato di default 0; firmato 0)
    $qIns = "
        INSERT INTO sportello
            (data, ora, docente_id, materia_id, categoria,
             numero_ore, argomento, luogo,
             classe, classe_id,
             max_iscrizioni, online, clil, orientamento,
             attivo, cancellato, firmato,
             anno_scolastico_id)
        VALUES
            ('$data', '$ora', $docente_id, $materia_id, '$categoria',
             $numero_ore_salvato, '$argomento', '$luogo',
             '$classe', $classe_id,
             $max_iscrizioni, $online, $clil, $orientamento,
             $attivo, 0, 0,
             $anno)
    ";
    dbExec($qIns);

    $newId = (int)dblastId();
    if ($newId <= 0) {
        jsonOut(false, ['error' => 'Errore creazione nuovo sportello']);
    }

    info("sportelloSave INSERT OK newId=$newId");

    $secondSportelloId = 0;
    if ($numero_ore === 2 && !$cancellato && $attivo === 1) {
        dbExec("
            INSERT INTO sportello
                (data, ora, docente_id, materia_id, categoria,
                 numero_ore, argomento, luogo,
                 classe, classe_id,
                 max_iscrizioni, online, clil, orientamento,
                 attivo, cancellato, firmato,
                 note, anno_scolastico_id)
            VALUES
                ('$data', '$secondOra', $docente_id, $materia_id, '$categoria',
                 1, '$argomento', '$luogo',
                 '$classe', $classe_id,
                 $max_iscrizioni, $online, $clil, $orientamento,
                 $attivo, 0, 0,
                 'Creato automaticamente da sportello admin 2 ore (sportello $newId)',
                 $anno)
        ");
        $secondSportelloId = (int)dblastId();
        info("sportelloSave INSERT seconda ora OK secondSportelloId=$secondSportelloId ora=$secondOra da newId=$newId");
    }

    // SYNC MBApp solo se eligible
    try {
        require_once __DIR__ . '/../common/mbappSyncSportello.php';

        $eligible = (!$cancellato && (int)$attivo === 1 && trim($luogo_raw) !== '');
        debug("[sportelloSave INSERT] MBApp eligible=" . ($eligible ? 1 : 0));

        if ($eligible) {
            $materiaNome = trim((string)dbGetValue("SELECT nome FROM materia WHERE id=" . (int)$materia_id . " LIMIT 1"));
            $docenteNome = trim((string)dbGetValue("SELECT username FROM docente WHERE id=" . (int)$docente_id . " LIMIT 1"));

            if ($materiaNome === '') $materiaNome = 'SPORTELLO DIDATTICO';
            if ($docenteNome === '') $docenteNome = 'Segreteria didattica';

            $titoloSportello = "SPORTELLO " . trim($materiaNome);
            $dettagliTxt = trim(($categoria ?? '') . (trim($argomento) !== '' ? " - " . trim($argomento) : ''));

            // preserve_text_fields non ha senso in create, ma lo lasciamo true
            $mbapp = mbapp_sync_sportello($newId, [
                'data' => $data,
                'ora' => $ora,
                'luogo' => $luogo_raw,
                'numero_ore' => 1,

                'docente_id' => (int)$docente_id,
                'docenti' => $docenteNome,
                'motivo' => "IMPEGNO IN ISTITUTO",
                'dettagli' => $dettagliTxt,
                'attivitaProgetto' => $titoloSportello,

                'preserve_text_fields' => true
            ]);

            if (!empty($mbapp['ok'])) {
                info("MBApp sync (INSERT) OK: " . ($mbapp['action'] ?? '') . " - " . ($mbapp['msg'] ?? ''));
            } else {
                warning("MBApp sync (INSERT) FAIL: " . ($mbapp['action'] ?? '') . " - " . ($mbapp['msg'] ?? ''));
            }

            if ($numero_ore === 2 && $secondSportelloId > 0) {
                $mbappSecond = sportelloSyncMbappUnaOra($secondSportelloId, $data, $secondOra, $luogo_raw, $docente_id, $materia_id, $categoria_raw, $argomento);
                $mbapp['seconda_ora'] = $mbappSecond;
            }
        } else {
            $mbapp = ['ok' => true, 'action' => 'skip', 'msg' => 'Creato in bozza (no MBApp)'];
        }

    } catch (Throwable $e) {
        $mbapp = ['ok' => false, 'action' => 'exception', 'msg' => $e->getMessage()];
        warning("MBApp sync (INSERT) exception: " . $e->getMessage());
    }

    info("sportelloSave.php END INSERT newId=$newId");

    jsonOut(true, [
        'id' => $newId,
        'second_id' => $secondSportelloId,
        'attivo' => $attivo,
        'cancellato' => 0,
        'mbapp' => $mbapp
    ]);

} catch (Throwable $e) {
    warning("sportelloSave.php ERROR err=" . $e->getMessage());
    jsonOut(false, [
        'error' => $e->getMessage(),
        'mbapp' => $mbapp
    ]);
}
