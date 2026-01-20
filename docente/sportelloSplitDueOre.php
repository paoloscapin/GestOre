<?php
/**
 * sportelloSplitDueOre.php
 * - Riceve payload come sportelloAggiorna, ma con numero_ore=2
 * - Trasforma uno sportello in 2 sportelli contigui da 1 ora
 * - Verifica aula libera per due ore consecutive su MBApp (oralezione)
 * - Inserisce 2 prenotazioni MBApp + 2 link su sportello_mbapp_link
 * - Salva categoria TESTO su sportello.categoria (lookup da sportello_categoria.id)
 */

require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';          // DB GestOre: dbGetFirst/dbGetValue/dbExec (+ $__con se mysqli)
require_once __DIR__ . '/../common/connectMBApp.php';     // DB MBApp: $__conMBApp + mb_dbGetValue/mb_dbGetFirst/mb_dbExec

header('Content-Type: application/json; charset=utf-8');

$TRACE = [];
function t($msg) {
    global $TRACE;
    $TRACE[] = $msg;
    if (function_exists('debug')) debug("[sportelloSplitDueOre] " . $msg);
}
function out($ok, $extra = []) {
    global $TRACE;
    echo json_encode(array_merge(['ok' => (bool)$ok, 'trace' => $TRACE], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================
   Utils ORARI (slot contigui)
========================================================= */
const ORARI = ["07:50","08:40","09:30","10:30","11:20","12:10","13:00","13:50","14:40","15:30","16:20","17:10","18:00","18:50","19:40","20:30","21:30","22:20"];

function nextOraSlot(string $ora): string {
    $i = array_search($ora, ORARI, true);
    if ($i === false) return '';
    $j = $i + 1;
    if (!isset(ORARI[$j])) return '';
    return ORARI[$j];
}

function escMB($s) {
    global $__conMBApp;
    return mysqli_real_escape_string($__conMBApp, (string)$s);
}

/* =========================================================
   Escaping GestOre (usa conn mysqli se disponibile)
========================================================= */
function escGO($s) {
    global $__con;
    if (isset($__con) && ($__con instanceof mysqli)) {
        return mysqli_real_escape_string($__con, (string)$s);
    }
    return addslashes((string)$s);
}

/* =========================================================
   0) Input + basic validation
========================================================= */
t("START");
if (!isset($_POST['id'])) out(false, ['error' => 'Parametro id mancante']);

$idSportello = (int)$_POST['id'];
if ($idSportello <= 0) out(false, ['error' => 'id non valido']);

$data = trim((string)($_POST['data'] ?? ''));          // YYYY-MM-DD
$ora1 = trim((string)($_POST['ora'] ?? ''));           // HH:MM
$numero_ore = (int)($_POST['numero_ore'] ?? 0);
$luogo = trim((string)($_POST['luogo'] ?? ''));

if ($data === '' || $ora1 === '') out(false, ['error' => 'data/ora mancanti']);
if ($numero_ore !== 2) out(false, ['error' => 'Questo endpoint gestisce solo numero_ore=2']);
if ($luogo === '') out(false, ['error' => 'Per lo split a 2 ore devi selezionare un’aula']);

$ora2 = nextOraSlot($ora1);
if ($ora2 === '') out(false, ['error' => "Ora non valida o non esiste slot successivo per: $ora1"]);

t("idSportello=$idSportello data=$data ora1=$ora1 ora2=$ora2 luogo=$luogo");

/* =========================================================
   1) Recupero sportello originale (GestOre DB)
========================================================= */
$sp = dbGetFirst("SELECT * FROM sportello WHERE id = $idSportello LIMIT 1");
if (!$sp) out(false, ['error' => 'Sportello non trovato']);

/* Vincolo: docente loggato proprietario (se già assegnato) */
global $__docente_id;
$docenteLogged = isset($__docente_id) ? (int)$__docente_id : 0;
if ($docenteLogged > 0) {
    $docenteSportello = (int)($sp['docente_id'] ?? 0);
    if ($docenteSportello > 0 && $docenteSportello !== $docenteLogged) {
        out(false, ['error' => 'Non puoi modificare/splittare uno sportello che non è tuo']);
    }
}

/* =========================================================
   2) Lookup categoria TESTO da categoria_id
========================================================= */
$categoria_id = (int)($_POST['categoria_id'] ?? 0);
if ($categoria_id <= 0) out(false, ['error' => 'categoria_id mancante o non valido']);

$rowCat = dbGetFirst("SELECT nome FROM sportello_categoria WHERE id = $categoria_id LIMIT 1");
$categoria_nome = trim((string)($rowCat['nome'] ?? ''));
if ($categoria_nome === '') out(false, ['error' => "Categoria non trovata (id=$categoria_id)"]);

t("categoria_id=$categoria_id categoria_nome=$categoria_nome");

/* =========================================================
   3) Altri campi (GestOre DB)
   ✅ FIX CLASSE: mai lookup su tabelle classi/classe
   ✅ Se POST['classe'] è vuoto -> mantieni valore originale dello sportello
========================================================= */
$materia_id = (int)($_POST['materia_id'] ?? ($sp['materia_id'] ?? 0));
$argomento = (string)($_POST['argomento'] ?? '');
$max_iscrizioni = (int)($_POST['max_iscrizioni'] ?? ($sp['max_iscrizioni'] ?? 0));
$cancellato = (int)($_POST['cancellato'] ?? 0);
$firmato = (int)($_POST['firmato'] ?? 0);

$classe_post = trim((string)($_POST['classe'] ?? ''));         // legacy text (può arrivare vuota!)
$classe_orig = trim((string)($sp['classe'] ?? ''));            // legacy text originale
$classe = ($classe_post !== '') ? $classe_post : $classe_orig; // ✅ mai vuota se esisteva

// classe_id: se arriva lo prendo, altrimenti tengo quello originale
$classe_id = (int)($_POST['classe_id'] ?? 0);
if ($classe_id <= 0) $classe_id = (int)($sp['classe_id'] ?? 0);

$online = (int)($_POST['online'] ?? 0);
$clil = (int)($_POST['clil'] ?? 0);
$orientamento = (int)($_POST['orientamento'] ?? 0);

t("classe_post='$classe_post' classe_orig='$classe_orig' => classe_usata='$classe' classe_id=$classe_id");

/* Agganci vari dallo sportello originale */
$anno_scolastico_id = (int)($sp['anno_scolastico_id'] ?? 0);
$docente_id = (int)($sp['docente_id'] ?? 0);
if ($docenteLogged > 0) $docente_id = $docenteLogged;

/* =========================================================
   4) Verifica disponibilità aula su MBApp per 2 slot contigui
========================================================= */
global $__settings, $__conMBApp;
$mbAppEnabled = !empty($__settings->config->MBApp);
t("MBApp enabled=" . ($mbAppEnabled ? "true" : "false"));

if ($mbAppEnabled) {
    if (!($__conMBApp instanceof mysqli)) out(false, ['error' => 'Connessione MBApp non disponibile']);

    $aulaMB = escMB($luogo);
    $dataMB = escMB($data);
    $ora1MB = escMB($ora1);
    $ora2MB = escMB($ora2);

    $qBusy = "
        SELECT COUNT(*)
        FROM oralezione
        WHERE nroAula = '$aulaMB'
          AND dataGiorno = '$dataMB'
          AND ora IN ('$ora1MB', '$ora2MB')
    ";
    $cnt = (int)mb_dbGetValue($qBusy);
    t("MBApp busy count=$cnt");

    if ($cnt > 0) {
        out(false, ['error' => "Aula $luogo NON libera per due ore consecutive ($ora1 e $ora2)"]);
    }
}

/* =========================================================
   5) Update sportello originale + insert secondo sportello
   ✅ Transazione GestOre (update+insert atomici)
========================================================= */
try {
    // sanitizzazione stringhe (GestOre)
    $categoria_nome_sql = escGO($categoria_nome);
    $argomento_sql      = escGO($argomento);
    $luogo_sql          = escGO($luogo);
    $classe_sql         = escGO($classe);

    // transazione GestOre (se conn mysqli esiste)
    global $__con;
    $useTxGO = (isset($__con) && ($__con instanceof mysqli));
    if ($useTxGO) {
        t("BEGIN TX GestOre");
        if (!mysqli_begin_transaction($__con)) {
            t("BEGIN TX GestOre FAIL: " . mysqli_error($__con));
            $useTxGO = false; // continuo senza tx
        }
    }

    // 5a) aggiorna sportello originale: ora1, numero_ore=1, attivo=1
    $qUp = "
        UPDATE sportello
        SET
            data = '$data',
            ora = '$ora1',
            numero_ore = 1,
            argomento = '$argomento_sql',
            luogo = '$luogo_sql',
            classe = '$classe_sql',
            classe_id = " . (int)$classe_id . ",
            categoria = '$categoria_nome_sql',
            materia_id = " . (int)$materia_id . ",
            docente_id = " . (int)$docente_id . ",
            max_iscrizioni = " . (int)$max_iscrizioni . ",
            cancellato = " . (int)$cancellato . ",
            firmato = " . (int)$firmato . ",
            attivo = 1,
            online = " . (int)$online . ",
            clil = " . (int)$clil . ",
            orientamento = " . (int)$orientamento . "
        WHERE id = $idSportello
        LIMIT 1
    ";
    t("UPDATE original => " . preg_replace('/\s+/', ' ', trim($qUp)));
    dbExec($qUp);

    // 5b) crea secondo sportello: ora2, numero_ore=1, attivo=1
    $qIns = "
        INSERT INTO sportello
        (data, ora, numero_ore, argomento, luogo, classe, categoria, clil, orientamento, max_iscrizioni, firmato, cancellato, attivo, online, note, anno_scolastico_id, materia_id, docente_id, classe_id)
        VALUES
        (
            '$data',
            '$ora2',
            1,
            '$argomento_sql',
            '$luogo_sql',
            '$classe_sql',
            '$categoria_nome_sql',
            " . (int)$clil . ",
            " . (int)$orientamento . ",
            " . (int)$max_iscrizioni . ",
            0,
            0,
            1,
            " . (int)$online . ",
            'Creato automaticamente da split 2 ore (sportello $idSportello)',
            " . (int)$anno_scolastico_id . ",
            " . (int)$materia_id . ",
            " . (int)$docente_id . ",
            " . (int)$classe_id . "
        )
    ";
    t("INSERT second => " . preg_replace('/\s+/', ' ', trim($qIns)));
    dbExec($qIns);

    // id del secondo sportello (robusto)
    $idSportello2 = 0;
    if ($useTxGO) {
        $idSportello2 = (int)mysqli_insert_id($__con);
    }
    if ($idSportello2 <= 0) {
        // fallback (se dbExec usa la stessa connessione)
        $idSportello2 = (int)dbGetValue("SELECT LAST_INSERT_ID()");
    }
    if ($idSportello2 <= 0) throw new Exception("Impossibile ottenere id del secondo sportello");

    t("idSportello2=$idSportello2");

    if ($useTxGO) {
        t("COMMIT TX GestOre");
        if (!mysqli_commit($__con)) {
            throw new Exception("Commit GestOre fallito: " . mysqli_error($__con));
        }
    }

    /* =========================================================
       6) Prenotazioni MBApp (2 x 1 ora) + link su sportello_mbapp_link
    ========================================================== */
    $prenotazioni = [];

    if ($mbAppEnabled) {
        $materiaNome = (string)dbGetValue("SELECT nome FROM materia WHERE id = " . (int)$materia_id . " LIMIT 1");
        if ($materiaNome === '') $materiaNome = 'SPORTELLO';

        $attivita = "SPORTELLO " . $materiaNome;
        $motivo = "IMPEGNO IN ISTITUTO";
        $dettagli = "SPORTELLO " . $materiaNome;

        $prenotazioni[] = prenotaMbappUnaOra($idSportello,  $luogo, $data, $ora1, $attivita, $motivo, $dettagli);
        $prenotazioni[] = prenotaMbappUnaOra($idSportello2, $luogo, $data, $ora2, $attivita, $motivo, $dettagli);

        foreach ($prenotazioni as $p) {
            if (empty($p['ok']) && empty($p['skip'])) {
                out(false, [
                    'error' => 'Sportelli creati ma prenotazione MBApp fallita',
                    'prenotazioni' => $prenotazioni,
                    'idSportello' => $idSportello,
                    'idSportello2' => $idSportello2
                ]);
            }
        }
    }

    out(true, [
        'msg' => 'Split completato',
        'idSportello' => $idSportello,
        'idSportello2' => $idSportello2,
        'ora1' => $ora1,
        'ora2' => $ora2,
        'prenotazioni' => $prenotazioni
    ]);

} catch (Throwable $e) {
    global $__con;
    if (isset($__con) && ($__con instanceof mysqli)) {
        @mysqli_rollback($__con);
    }
    t("EXCEPTION: " . $e->getMessage());
    out(false, ['error' => $e->getMessage()]);
}

/* =========================================================
   Funzione: prenota 1 ora su MBApp e salva link su GestOre
========================================================= */
function prenotaMbappUnaOra(int $idSportello, string $nroAulaRaw, string $dataInizioRaw, string $oraInizioRaw, string $attivitaProgettoRaw, string $motivoRaw, string $dettagliRaw): array
{
    t("prenotaMbappUnaOra sportello=$idSportello aula=$nroAulaRaw data=$dataInizioRaw ora=$oraInizioRaw");

    // anti-duplicazione: se link esiste già, skip
    $link = dbGetFirst("SELECT idAssenza, idCalendario FROM sportello_mbapp_link WHERE id_sportello = $idSportello LIMIT 1");
    if ($link && !empty($link['idCalendario'])) {
        t("SKIP prenota: link già presente idCalendario=" . (int)$link['idCalendario']);
        return [
            'ok' => true,
            'skip' => true,
            'idSportello' => $idSportello,
            'idAssenza' => (int)($link['idAssenza'] ?? 0),
            'idCalendario' => (int)($link['idCalendario'] ?? 0),
        ];
    }

    global $__conMBApp, $__username, $__docente_email;

    $oraFineRaw = nextOraSlot($oraInizioRaw);
    if ($oraFineRaw === '') {
        return ['ok' => false, 'error' => "Non esiste oraFine per slot $oraInizioRaw", 'idSportello' => $idSportello];
    }

    $giorni = [
        'Sunday'    => 'domenica',
        'Monday'    => 'lunedi',
        'Tuesday'   => 'martedi',
        'Wednesday' => 'mercoledi',
        'Thursday'  => 'giovedi',
        'Friday'    => 'venerdi',
        'Saturday'  => 'sabato'
    ];
    $ts = strtotime($dataInizioRaw);
    $engDay = $ts ? date('l', $ts) : '';
    $giorno = $giorni[$engDay] ?? '';

    $docenti = (string)($__username ?? '');
    $usernameMB = '';
    try {
        $qUser = "SELECT username FROM utente WHERE email1 = '" . escMB((string)($__docente_email ?? '')) . "' LIMIT 1";
        $tmp = mb_dbGetValue($qUser);
        $usernameMB = (string)($tmp ?? '');
    } catch (Throwable $e) { }
    if (trim($usernameMB) === '') $usernameMB = $docenti;

    $nroAula = escMB($nroAulaRaw);
    $dataInizio = escMB($dataInizioRaw);
    $dataFine = $dataInizio;
    $oraInizio = escMB($oraInizioRaw);
    $oraFine = escMB($oraFineRaw);
    $motivo = escMB($motivoRaw);
    $dettagli = escMB($dettagliRaw);
    $attivitaProgetto = escMB($attivitaProgettoRaw);

    $docentiEsc = escMB($docenti);
    $usernameMBe = escMB($usernameMB);
    $stato = 'CONFERMATO';

    try {
        if (!mysqli_begin_transaction($__conMBApp)) {
            throw new Exception("MBApp begin_transaction fallita: " . mysqli_error($__conMBApp));
        }

        $sql1 = "
            INSERT INTO assenze (docenti, dataInizio, dataFine, oraInizio, oraFine, motivo, dettagli, stato)
            VALUES ('$docentiEsc', '$dataInizio', '$dataFine', '$oraInizio', '$oraFine', '$motivo', '$dettagli', '$stato')
        ";
        if (!mysqli_query($__conMBApp, $sql1)) throw new Exception("Insert assenze fallita: " . mysqli_error($__conMBApp));
        $idAssenza = (int)mysqli_insert_id($__conMBApp);

        $sql2 = "
            INSERT INTO oralezione (nroAula, dataGiorno, giorno, ora, attivitaProgetto, stato, idAssenza)
            VALUES ('$nroAula', '$dataInizio', '$giorno', '$oraInizio', '$attivitaProgetto', '$stato', $idAssenza)
        ";
        if (!mysqli_query($__conMBApp, $sql2)) throw new Exception("Insert oralezione fallita: " . mysqli_error($__conMBApp));
        $idCalendario = (int)mysqli_insert_id($__conMBApp);

        $sql3 = "
            INSERT INTO utilizza (idCalendario, username, idAssenza)
            VALUES ($idCalendario, '$usernameMBe', $idAssenza)
        ";
        if (!mysqli_query($__conMBApp, $sql3)) throw new Exception("Insert utilizza fallita: " . mysqli_error($__conMBApp));

        if (!mysqli_commit($__conMBApp)) {
            throw new Exception("MBApp commit fallito: " . mysqli_error($__conMBApp));
        }

        $qIns = "
            INSERT INTO sportello_mbapp_link (id_sportello, idAssenza, idCalendario, created_at, updated_at)
            VALUES ($idSportello, $idAssenza, $idCalendario, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                idAssenza = VALUES(idAssenza),
                idCalendario = VALUES(idCalendario),
                updated_at = NOW()
        ";
        dbExec($qIns);

        return [
            'ok' => true,
            'idSportello' => $idSportello,
            'idAssenza' => $idAssenza,
            'idCalendario' => $idCalendario,
            'data' => $dataInizioRaw,
            'oraInizio' => $oraInizioRaw,
            'oraFine' => $oraFineRaw,
            'aula' => $nroAulaRaw
        ];

    } catch (Throwable $e) {
        @mysqli_rollback($__conMBApp);
        return ['ok' => false, 'idSportello' => $idSportello, 'error' => $e->getMessage()];
    }
}
