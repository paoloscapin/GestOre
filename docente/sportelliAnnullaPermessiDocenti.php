<?php
/**
 * sportelliAnnullaPermessiDocenti.php
 *
 * CRON (ore 08:00):
 * - prende gli sportelli ATTIVI di OGGI con studenti iscritti
 * - legge assenze MBApp con motivo PERMESSO / PERMESSO BREVE che coprono oggi (data + fascia oraria)
 * - se un docente risulta in permesso durante l'orario dello sportello:
 *      - ANNULLA sportello in GestOre
 *      - invia mail a docente + studenti (DRY-RUN: mostra cosa invierebbe)
 *      - cancella prenotazione aula su MBApp (DRY-RUN: mostra le query)
 *
 * IMPORTANTE: in questa versione è tutto in DRY-RUN (nessuna modifica reale).
 */

require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/send-mail.php';
require_once __DIR__ . '/../common/mail-ui.php';

$mbappEnabled = false;
if (file_exists(__DIR__ . '/../common/connectMBApp.php')) {
    require_once __DIR__ . '/../common/connectMBApp.php';
    $mbappEnabled = function_exists('mb_dbExec') && function_exists('mb_dbGetAll') && function_exists('mb_dbGetFirst') && function_exists('mb_dbGetValue');
}

/** =========================
 * CONFIG
 * ========================= */
define('DRY_RUN', false); // <-- metti false quando vuoi eseguire davvero
date_default_timezone_set('Europe/Rome');

/** =========================
 * LOG CRON (fallback-safe)
 * ========================= */
function cron_info(string $msg): void {
    if (function_exists('infocron')) { infocron($msg); return; }
    if (function_exists('info')) { info('[CRON] ' . $msg); }
}
function cron_warn(string $msg): void {
    if (function_exists('warningcron')) { warningcron($msg); return; }
    if (function_exists('warning')) { warning('[CRON] ' . $msg); }
}
function cron_err(string $msg): void {
    if (function_exists('errorcron')) { errorcron($msg); return; }
    if (function_exists('error')) { error('[CRON] ' . $msg); }
}

/** =========================
 * OUTPUT helper
 * ========================= */
function out(string $title, string $body = ''): void {
    $isCli = (php_sapi_name() === 'cli');
    if ($isCli) {
        echo "\n=== $title ===\n";
        if ($body !== '') echo $body . "\n";
        return;
    }
    echo "<h3 style='margin:18px 0 6px 0;font-family:Arial,sans-serif;'>".htmlspecialchars($title)."</h3>";
    if ($body !== '') {
        echo "<pre style='padding:10px;border:1px solid #ddd;border-radius:8px;background:#fafafa;white-space:pre-wrap;'>"
            . htmlspecialchars($body) . "</pre>";
    }
}

/** =========================
 * SAFE Exec wrappers (GestOre / MBApp)
 * - in DRY_RUN stampano e NON eseguono
 * ========================= */
function exec_gestore(string $sql, string $why): array {
    cron_info("GESTORE: $why");
    out("GESTORE: $why", $sql);

    if (DRY_RUN) {
        return ['ok' => true, 'dry_run' => true, 'affected' => null];
    }

    global $__con;
    debug($sql);
    if (!mysqli_query($__con, $sql)) {
        $err = mysqli_error($__con);
        cron_err("GESTORE SQL ERROR: $err");
        out("GESTORE SQL ERROR", $err . "\n\nSQL:\n" . $sql);
        return ['ok' => false, 'error' => $err];
    }
    return ['ok' => true, 'dry_run' => false, 'affected' => mysqli_affected_rows($__con)];
}

function exec_mbapp(string $sql, string $why): array {
    cron_info("MBAPP: $why");
    out("MBAPP: $why", $sql);

    if (DRY_RUN) {
        return ['ok' => true, 'dry_run' => true, 'affected' => null];
    }

    global $__conMBApp;
    debug($sql);
    if (!mysqli_query($__conMBApp, $sql)) {
        $err = mysqli_error($__conMBApp);
        cron_err("MBAPP SQL ERROR: $err");
        out("MBAPP SQL ERROR", $err . "\n\nSQL:\n" . $sql);
        return ['ok' => false, 'error' => $err];
    }
    return ['ok' => true, 'dry_run' => false, 'affected' => mysqli_affected_rows($__conMBApp)];
}

/** =========================
 * Helpers: ora/overlap
 * ========================= */
function hhmm(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    // accetta "07:50:00" -> "07:50"
    if (preg_match('/^\d{2}:\d{2}/', $s)) return substr($s, 0, 5);
    return $s;
}

function timeOverlap(string $startA, string $endA, string $startB, string $endB): bool {
    // [startA, endA) interseca [startB, endB)
    // se manca end -> consideriamo 1 slot (start + 1 min) per evitare false negative
    $startA = hhmm($startA); $endA = hhmm($endA);
    $startB = hhmm($startB); $endB = hhmm($endB);

    if ($startA === '' || $startB === '') return false;

    if ($endA === '') $endA = $startA;
    if ($endB === '') $endB = $startB;

    return ($startA < $endB) && ($startB < $endA);
}

/** =========================
 * MBApp: risolvi docente da tabella utente (OBBLIGATORIO)
 * ========================= */
function mbapp_getUtenteByUsername(string $username): ?array {
    $u = trim($username);
    if ($u === '') return null;

    // NB: richiesta dell’utente -> usare mb_dbGetFirst
    $uEsc = mysqli_real_escape_string($GLOBALS['__conMBApp'], $u);

    $sql = "SELECT username, cognome, nome, email1, email2, tipo
            FROM utente
            WHERE username = '$uEsc'
            LIMIT 1";

    $row = mb_dbGetFirst($sql);
    return $row ?: null;
}

/** =========================
 * MBApp: carica permessi di oggi (PERMESSO / PERMESSO BREVE)
 * ========================= */
function mbapp_loadPermessiOggi(string $oggiYmd): array {
    // carichiamo tutte le assenze che includono oggi, motivo permesso
    // poi filtriamo/indiciamo per docente
    $oggiEsc = mysqli_real_escape_string($GLOBALS['__conMBApp'], $oggiYmd);

    $sql = "
        SELECT
            idAssenza, motivo, dettagli, docenti,
            dataInizio, dataFine,
            oraInizio, oraFine,
            oraInizioReale, oraFineReale
        FROM assenze
        WHERE (UPPER(motivo) LIKE 'PERMESSO%' OR UPPER(dettagli) LIKE 'PERMESSO%')
          AND DATE(dataInizio) <= DATE('$oggiEsc')
          AND DATE(dataFine)   >= DATE('$oggiEsc')
    ";

    cron_info("Carico permessi MBApp per oggi=$oggiYmd");
    out("MBAPP: SELECT permessi di oggi", $sql);

    $rows = mb_dbGetAll($sql);
    if (!$rows) $rows = [];

    // index: permessiByDocente[username] = [ {start,end, idAssenza, ...}, ... ]
    $permessiByDocente = [];

    foreach ($rows as $a) {
        $docStr = trim((string)($a['docenti'] ?? ''));
        if ($docStr === '') continue;

        // docenti nel formato: "nome.cognome, nome2.cognome2"
        $docList = array_filter(array_map('trim', explode(',', $docStr)));

        $oraDa = hhmm((string)($a['oraInizioReale'] ?? ''));
        if ($oraDa === '') $oraDa = hhmm((string)($a['oraInizio'] ?? ''));

        $oraA  = hhmm((string)($a['oraFineReale'] ?? ''));
        if ($oraA === '') $oraA = hhmm((string)($a['oraFine'] ?? ''));

        // se è PERMESSO (giorno intero) spesso non ha fascia -> gestiamo come "tutto il giorno"
        $hasTime = ($oraDa !== '' && $oraA !== '');
        if (!$hasTime) {
            $oraDa = "00:00";
            $oraA  = "23:59";
        }

        foreach ($docList as $u) {
            $permessiByDocente[$u][] = [
                'idAssenza' => (int)($a['idAssenza'] ?? 0),
                'motivo'    => (string)($a['motivo'] ?? ''),
                'dettagli'  => (string)($a['dettagli'] ?? ''),
                'oraDa'     => $oraDa,
                'oraA'      => $oraA,
                'rawDocenti'=> $docStr,
            ];
        }
    }

    return $permessiByDocente;
}

/** =========================
 * MBApp delete booking by link (idAssenza / idCalendario)
 * - DRY_RUN: mostra query
 * ========================= */
function mbapp_delete_by_link_dry(int $idAssenza, int $idCalendario): void {
    $idAssenza = (int)$idAssenza;
    $idCalendario = (int)$idCalendario;

    if ($idAssenza <= 0 && $idCalendario <= 0) {
        cron_warn("MBApp delete skip: idAssenza/idCalendario vuoti");
        return;
    }

    $condU = [];
    if ($idCalendario > 0) $condU[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $condU[] = "IDassenza = $idAssenza";
    $whereU = implode(" OR ", $condU);

    $condO = [];
    if ($idCalendario > 0) $condO[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $condO[] = "idAssenza = $idAssenza";
    $whereO = implode(" OR ", $condO);

    exec_mbapp("DELETE FROM utilizza  WHERE $whereU", "CANCELLA utilizza (prenotazione aula) per link ($idAssenza,$idCalendario)");
    exec_mbapp("DELETE FROM oralezione WHERE $whereO", "CANCELLA oralezione (prenotazione aula) per link ($idAssenza,$idCalendario)");
    if ($idAssenza > 0) {
        exec_mbapp("DELETE FROM assenze WHERE idAssenza = $idAssenza", "CANCELLA assenze (prenotazione aula) idAssenza=$idAssenza");
    }
}

/** =========================
 * MAIL (DRY)
 * ========================= */
function sendMailDry(string $to, string $toName, string $subject, string $htmlBody, string $why): void {
    cron_info("MAIL: $why -> $to ($toName) subj=$subject");
    out("MAIL: $why", "TO: $to ($toName)\nSUBJECT: $subject\n\n[Body HTML omesso qui per brevità - se vuoi lo stampo completo]");
    if (!DRY_RUN) {
        sendMail($to, $toName, $subject, $htmlBody);
    }
}

/** =========================
 * START
 * ========================= */
$oggi = date('Y-m-d');
cron_info("Avvio sportelliAnnullaPermessiDocenti - oggi=$oggi - DRY_RUN=" . (DRY_RUN ? '1' : '0'));
out("START", "oggi=$oggi\nDRY_RUN=" . (DRY_RUN ? '1' : '0'));

if (!$mbappEnabled) {
    cron_err("MBApp non disponibile: connectMBApp.php mancante o funzioni MBApp non caricate.");
    out("ERRORE", "MBApp non disponibile: connectMBApp.php mancante o non valido.");
    exit;
}

/** 1) sportelli ATTIVI di oggi con studenti iscritti */
$sqlSportelli = "
    SELECT
        s.id, s.data, s.ora, s.luogo, s.categoria, s.materia_id, s.docente_id, s.attivo, s.cancellato,
        m.nome AS materia_nome,
        d.cognome AS docente_cognome, d.nome AS docente_nome, d.email AS docente_email
    FROM sportello s
    INNER JOIN materia m ON m.id = s.materia_id
    INNER JOIN docente d ON d.id = s.docente_id
    WHERE DATE(s.data) = DATE('$oggi')
      AND s.attivo = 1
      AND (s.cancellato IS NULL OR s.cancellato = 0)
      AND EXISTS (
          SELECT 1
          FROM sportello_studente ss
          WHERE ss.sportello_id = s.id
            AND ss.iscritto = 1
      )
    ORDER BY s.ora, s.id
";

cron_info("Carico sportelli attivi di oggi con iscritti");
out("GESTORE: SELECT sportelli di oggi con iscritti", $sqlSportelli);

$sportelli = dbGetAll($sqlSportelli);
if (!$sportelli) $sportelli = [];

out("Sportelli trovati", "count=" . count($sportelli));

if (!count($sportelli)) {
    cron_info("Nessuno sportello da controllare oggi.");
    out("FINE", "Nessuno sportello da controllare.");
    exit;
}

/** 2) permessi MBApp di oggi indicizzati per docente username (nome.cognome) */
$permessiByDocente = mbapp_loadPermessiOggi($oggi);
out("Permessi MBApp indicizzati", "docenti con permesso oggi: " . count($permessiByDocente));

/** 3) per ogni sportello: verifica conflitto */
foreach ($sportelli as $s) {

    $sportello_id = (int)$s['id'];
    $ora = hhmm((string)$s['ora']);
    $luogo = (string)($s['luogo'] ?? '');
    $categoria = (string)($s['categoria'] ?? '');
    $materia = (string)($s['materia_nome'] ?? '');
    $docenteEmail = (string)($s['docente_email'] ?? '');
    $docenteNome  = trim((string)($s['docente_cognome'] ?? '') . ' ' . (string)($s['docente_nome'] ?? ''));

    // sportello: consideriamo slot di 1 ora -> ora fine = ora successiva se vuoi (qui default: 1h simbolica 60 min non calcolabile su griglia ORARI)
    // Per confronto overlap usiamo: [ora, ora+01:00). Se tu hai griglia ORARI fissa, posso usare l’array ORARI come in sportello.js.
    $oraFine = $ora;
    // fallback semplice: se ora=07:50 -> fine=08:40 (ma serve lista ORARI scuola). Per ora metto +60 min:
    if (preg_match('/^\d{2}:\d{2}$/', $ora)) {
        $t = DateTime::createFromFormat('H:i', $ora);
        if ($t) {
            $t->modify('+60 minutes');
            $oraFine = $t->format('H:i');
        }
    }

    cron_info("Controllo sportello_id=$sportello_id ora=$ora-$oraFine docente=$docenteNome materia=$materia luogo=$luogo");

    /**
     * Qui serve il docente in formato username (nome.cognome) da MBApp:
     * - lo prendiamo da assenze.docenti quando c'è permesso.
     * - ma lo sportello ha docente_id (tabella docente GestOre), non username MBApp.
     *
     * Quindi: verifichiamo conflitto se tra i docenti in permesso c'è un docente che "matcha" questo docente.
     * Matching robusto:
     * - proviamo a costruire username "nome.cognome" da docente GestOre (nome/cognome), normalizzato.
     * - e lo cerchiamo in $permessiByDocente.
     */
    $guessUsername = '';
    if ($docenteNome !== '') {
        $nome = strtolower(trim((string)$s['docente_nome']));
        $cogn = strtolower(trim((string)$s['docente_cognome']));
        $nome = preg_replace('/\s+/', '', $nome);
        $cogn = preg_replace('/\s+/', '', $cogn);
        $guessUsername = $nome . '.' . $cogn; // nome.cognome
    }

    $conflitti = [];
    if ($guessUsername !== '' && isset($permessiByDocente[$guessUsername])) {
        foreach ($permessiByDocente[$guessUsername] as $p) {
            if (timeOverlap($ora, $oraFine, $p['oraDa'], $p['oraA'])) {
                $conflitti[] = $p;
            }
        }
    }

    if (!count($conflitti)) {
        cron_info("OK nessun conflitto per sportello_id=$sportello_id (username guess=$guessUsername)");
        continue;
    }

    // Conflitto trovato
    $p0 = $conflitti[0];
    cron_warn("CONFLITTO: sportello_id=$sportello_id docente=$guessUsername permesso {$p0['oraDa']}-{$p0['oraA']} motivo={$p0['motivo']}");

    out("CONFLITTO sportello_id=$sportello_id", json_encode([
        'sportello' => [
            'id' => $sportello_id,
            'ora' => $ora,
            'oraFine' => $oraFine,
            'materia' => $materia,
            'luogo' => $luogo,
            'docente' => $docenteNome,
            'username_guess' => $guessUsername,
        ],
        'permesso' => $p0,
        'conflitti_count' => count($conflitti)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    /** 3.1) risolvi docente in MBApp.utente (richiesta esplicita) */
    $uRow = mbapp_getUtenteByUsername($guessUsername);
    if ($uRow) {
        out("MBAPP utente trovato", json_encode($uRow, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    } else {
        cron_warn("MBApp utente NON trovato per username=$guessUsername (proseguo comunque con annullamento sportello)");
        out("MBAPP utente NON trovato", $guessUsername);
    }

    /** 3.2) annulla sportello in GestOre */
    $whyUpd = "ANNULLA sportello per permesso docente (sportello_id=$sportello_id)";
    $sqlUpd = "
        UPDATE sportello
        SET cancellato = 1,
            attivo = 1,
            luogo = ''
        WHERE id = $sportello_id
        LIMIT 1
    ";

    exec_gestore($sqlUpd, $whyUpd);

    /** 3.3) cancella prenotazione MBApp via link sportello_mbapp_link (GestOre) */
    $sqlLink = "
        SELECT idAssenza, idCalendario
        FROM sportello_mbapp_link
        WHERE id_sportello = $sportello_id
        LIMIT 1
    ";
    cron_info("Cerco link MBApp per sportello_id=$sportello_id");
    out("GESTORE: SELECT link MBApp", $sqlLink);

    $link = dbGetFirst($sqlLink);
    if ($link) {
        $idAss = (int)($link['idAssenza'] ?? 0);
        $idCal = (int)($link['idCalendario'] ?? 0);

        mbapp_delete_by_link_dry($idAss, $idCal);

        // opzionale: cancella il link per evitare retry/duplicati
        exec_gestore(
            "DELETE FROM sportello_mbapp_link WHERE id_sportello = $sportello_id LIMIT 1",
            "Rimuovi link sportello_mbapp_link (dopo annullamento sportello_id=$sportello_id)"
        );
    } else {
        cron_warn("Nessun link MBApp trovato in sportello_mbapp_link per sportello_id=$sportello_id");
        out("MBAPP LINK", "Nessun link trovato per sportello_id=$sportello_id");
    }

    /** 3.4) Mail a docente + studenti (DRY) */
    $dataIt = date('d/m/Y', strtotime($oggi));
    $subject = "GestOre - Sportello annullato per assenza del docente - $materia - $dataIt $ora";
    $title = "ANNULLAMENTO SPORTELLO";
    $intro = "Lo sportello previsto per oggi è stato annullato automaticamente perché il docente risulta <b>ASSENTE</b> nell’orario previsto.";

    $content = '
        <div style="margin:0 0 12px 0;">
            ' . badge('ANNULLATO', '#fee2e2', '#7f1d1d') . '
        </div>
        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                ' . kvRow('Materia', $materia) . '
                ' . kvRow('Data', $dataIt) . '
                ' . kvRow('Ora', $ora) . '
                ' . kvRow('Aula', ($luogo !== '' ? $luogo : '—')) . '
                ' . kvRow('Motivo', 'Assenza docente') . '
                ' . kvRow('ID Sportello', (string)$sportello_id) . '
            </table>
        </div>
    ';
    $footer = "Messaggio automatico da GestOre.";

    // docente
    if ($docenteEmail !== '') {
        $bodyDoc = mailWrap($title, $docenteNome ?: 'Docente', $intro, $content, $footer, 'annullamento');
        sendMailDry($docenteEmail, $docenteNome ?: 'Docente', $subject, $bodyDoc, "Annullamento sportello al DOCENTE (sportello_id=$sportello_id)");
    } else {
        cron_warn("Docente senza email (sportello_id=$sportello_id) -> mail docente non inviata");
        out("MAIL DOCENTE SKIP", "Docente senza email in tabella docente (GestOre).");
    }

    // studenti: recupero iscritti + email (adatta i campi se i tuoi nomi sono diversi)
    $sqlStud = "
        SELECT st.nome, st.cognome, st.email
        FROM sportello_studente ss
        JOIN studente st ON st.id = ss.studente_id
        WHERE ss.sportello_id = $sportello_id
          AND ss.iscritto = 1
        ORDER BY st.cognome, st.nome
    ";
    cron_info("Carico studenti iscritti per invio mail (sportello_id=$sportello_id)");
    out("GESTORE: SELECT studenti iscritti", $sqlStud);

    $stud = dbGetAll($sqlStud);
    if (!$stud) $stud = [];

    foreach ($stud as $st) {
        $email = trim((string)($st['email'] ?? ''));
        $nome  = trim((string)($st['cognome'] ?? '') . ' ' . (string)($st['nome'] ?? ''));

        if ($email === '') {
            cron_warn("Studente senza email -> skip ($nome) sportello_id=$sportello_id");
            continue;
        }

        $bodyStud = mailWrap($title, $nome ?: 'Studente', $intro, $content, $footer, 'annullamento');
        sendMailDry($email, $nome ?: 'Studente', $subject, $bodyStud, "Annullamento sportello allo STUDENTE (sportello_id=$sportello_id)");
    }
}

/** END */
cron_info("Fine sportelliAnnullaPermessiDocenti - DRY_RUN=" . (DRY_RUN ? '1' : '0'));
out("FINE", "Esecuzione completata.\nDRY_RUN=" . (DRY_RUN ? '1' : '0'));
