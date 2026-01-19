<?php

/**
 * prenotaAula.php
 * - INSERT su MBApp + invio mail
 * - EVITA DUPLICATI con tabella GestOre: sportello_mbapp_link
 * - Scrive link (idAssenza/idCalendario) in GestOre DB dopo commit
 *
 * REGOLE DB:
 * - MBApp: usare SOLO mb_dbExec / mb_dbGetFirst / mb_dbGetValue (NON dbExec/dbGetFirst)
 * - GestOre (link): usare dbExec / dbGetFirst
 */

require_once __DIR__ . '/checkSession.php';
require_once __DIR__ . '/connect.php';          // ✅ DB GestOre (sportello_mbapp_link)
require_once __DIR__ . '/connectMBApp.php';     // ✅ DB MBApp + helpers mb_*
require_once __DIR__ . '/send-mail.php';        // sendMail()

header('Content-Type: application/json; charset=utf-8');

$TRACE = [];
function t($msg)
{
    global $TRACE;
    $TRACE[] = $msg;
    debug("[prenotaAula] " . $msg);
}

function jsonOut($ok, $extra = [])
{
    global $TRACE;
    echo json_encode(array_merge(['ok' => (bool)$ok, 'trace' => $TRACE], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/* fatal catcher */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @error("[prenotaAula] FATAL: " . print_r($err, true));
        if (!headers_sent()) {
            echo json_encode([
                'ok' => false,
                'fatal' => true,
                'error' => $err['message'] ?? 'Fatal error',
                'where' => ($err['file'] ?? '') . ':' . ($err['line'] ?? ''),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
});

t("START");

/* =====================================================
   0) CONFIG (case-sensitive!)
===================================================== */
global $__settings;
$mbAppEnabled = !empty($__settings->config->MBApp); // <-- CORRETTO: MBApp
t("settings.config.MBApp=" . ($mbAppEnabled ? "true" : "false"));

if (!$mbAppEnabled) {
    jsonOut(true, [
        'skipped' => true,
        'msg' => 'MBApp disabilitata da config: nessuna prenotazione effettuata'
    ]);
}

/* =====================================================
   1) Sanity: helpers MBApp esistono?
===================================================== */
if (!function_exists('mb_dbExec') || !function_exists('mb_dbGetFirst') || !function_exists('mb_dbGetValue')) {
    jsonOut(false, ['error' => 'Funzioni MBApp mancanti (mb_dbExec / mb_dbGetFirst / mb_dbGetValue). Controlla connectMBApp.php']);
}

/* =====================================================
   2) Parametri richiesti
===================================================== */
t("POST keys=" . implode(',', array_keys($_POST)));

if (
    !isset($_POST['nroAula'], $_POST['dataInizio'], $_POST['oraInizio'], $_POST['oraFine']) ||
    trim((string)$_POST['nroAula']) === '' ||
    trim((string)$_POST['dataInizio']) === '' ||
    trim((string)$_POST['oraInizio']) === '' ||
    trim((string)$_POST['oraFine']) === ''
) {
    jsonOut(false, ['error' => 'Parametri mancanti: nroAula, dataInizio, oraInizio, oraFine']);
}

$nroAulaRaw    = trim((string)$_POST['nroAula']);
$dataInizioRaw = trim((string)$_POST['dataInizio']);
$oraInizioRaw  = trim((string)$_POST['oraInizio']);
$oraFineRaw    = trim((string)$_POST['oraFine']);

$motivoRaw   = (string)($_POST['motivo'] ?? "");
$dettagliRaw = (string)($_POST['dettagli'] ?? "");
$attivitaRaw = (string)($_POST['attivitaProgetto'] ?? "sportello");

t("RAW nroAula=$nroAulaRaw dataInizio=$dataInizioRaw oraInizio=$oraInizioRaw oraFine=$oraFineRaw");

/* =====================================================
   2b) ID sportello (serve per anti-duplicazione)
===================================================== */
$idSportello = isset($_POST['idSportello']) ? (int)$_POST['idSportello'] : 0;
t("idSportello=" . $idSportello);

/* =====================================================
   2c) ANTI-DUPLICAZIONE: se esiste link -> SKIP
   (GestOre DB)
===================================================== */
if ($idSportello > 0) {
    try {
        $qLink = "SELECT idAssenza, idCalendario
                  FROM sportello_mbapp_link
                  WHERE id_sportello = $idSportello
                  LIMIT 1";
        t("CHECK link (GestOre) => " . preg_replace('/\s+/', ' ', trim($qLink)));
        $linkRow = dbGetFirst($qLink);

        if ($linkRow && !empty($linkRow['idCalendario'])) {
            t("SKIP: link già presente (idCalendario=" . (int)$linkRow['idCalendario'] . ")");
            jsonOut(true, [
                'skip' => true,
                'msg' => 'Prenotazione MBApp già esistente per questo sportello',
                'idSportello' => $idSportello,
                'idAssenza' => (int)($linkRow['idAssenza'] ?? 0),
                'idCalendario' => (int)($linkRow['idCalendario'] ?? 0),
            ]);
        }
    } catch (Throwable $e) {
        // se qui fallisce, rischio duplicati: meglio BLOCCARE che fare danni
        t("CHECK link EX: " . $e->getMessage());
        jsonOut(false, [
            'error' => 'Impossibile verificare sportello_mbapp_link (anti-duplicazione). Blocco per sicurezza.',
            'detail' => $e->getMessage(),
            'idSportello' => $idSportello
        ]);
    }
} else {
    t("ATTENZIONE: idSportello non fornito -> impossibile prevenire duplicati via sportello_mbapp_link");
}

/* =====================================================
   3) Giorno (it)
===================================================== */
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
t("engDay=$engDay giorno=$giorno");

/* =====================================================
   4) docenti / username MBApp
   (query su MBApp => mb_dbGetValue)
===================================================== */
global $__username, $__docente_email;

$docenti = (string)($__username ?? '');
t("__username=$docenti");
t("__docente_email=" . (string)($__docente_email ?? ''));

$usernameMB = '';
try {
    $email1 = addslashes((string)($__docente_email ?? ''));
    $qUser = "SELECT username FROM utente WHERE email1 = '$email1' LIMIT 1";
    t("lookup usernameMB (MBApp) => " . preg_replace('/\s+/', ' ', trim($qUser)));
    $usernameMB = (string)(mb_dbGetValue($qUser) ?? '');
} catch (Throwable $e) {
    t("usernameMB lookup EX: " . $e->getMessage());
}

if (trim($usernameMB) === '') {
    $usernameMB = $docenti;
    t("usernameMB fallback=$usernameMB");
}

$stato = 'CONFERMATO';

/* =====================================================
   5) INSERT su MBApp (tutto via mb_dbExec)
===================================================== */
try {
    // NB: mb_dbExec/mb_dbGetFirst dovrebbero lavorare su $__conMBApp e gestire escaping base,
    // qui facciamo addslashes per sicurezza minima (coerente con stile legacy).
    $nroAula    = addslashes($nroAulaRaw);
    $dataInizio = addslashes($dataInizioRaw);
    $dataFine   = $dataInizio;
    $oraInizio  = addslashes($oraInizioRaw);
    $oraFine    = addslashes($oraFineRaw);

    $motivo   = addslashes($motivoRaw);
    $dettagli = addslashes($dettagliRaw);
    $attivita = addslashes($attivitaRaw);

    $docentiEsc  = addslashes($docenti);
    $userMBe     = addslashes($usernameMB);

    t("MBApp INSERT assenze");
    $sql1 = "
        INSERT INTO assenze (docenti, dataInizio, dataFine, oraInizio, oraFine, motivo, dettagli, stato)
        VALUES ('$docentiEsc', '$dataInizio', '$dataFine', '$oraInizio', '$oraFine', '$motivo', '$dettagli', '$stato')
    ";
    mb_dbExec($sql1);

    // recupera id assenza
    $assenzaId = (int)mb_dbGetValue("SELECT LAST_INSERT_ID()");
    t("assenzaId=$assenzaId");

    t("MBApp INSERT oralezione");
    $sql2 = "
        INSERT INTO oralezione (nroAula, dataGiorno, giorno, ora, attivitaProgetto, stato, idAssenza)
        VALUES ('$nroAula', '$dataInizio', '$giorno', '$oraInizio', '$attivita', '$stato', $assenzaId)
    ";
    mb_dbExec($sql2);

    $idCalendario = (int)mb_dbGetValue("SELECT LAST_INSERT_ID()");
    t("idCalendario=$idCalendario");

    t("MBApp INSERT utilizza");
    $sql3 = "
        INSERT INTO utilizza (idCalendario, username, idAssenza)
        VALUES ($idCalendario, '$userMBe', $assenzaId)
    ";
    mb_dbExec($sql3);

    /* =====================================================
       5b) Scrive link in GestOre (anti duplicazione futura)
    ===================================================== */
    $linkSaved = false;
    $linkErr = '';

    if ($idSportello > 0) {
        try {
            $qIns = "
                INSERT INTO sportello_mbapp_link (id_sportello, idAssenza, idCalendario, created_at, updated_at)
                VALUES ($idSportello, $assenzaId, $idCalendario, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    idAssenza = VALUES(idAssenza),
                    idCalendario = VALUES(idCalendario),
                    updated_at = NOW()
            ";
            t("LINK UPSERT (GestOre) => " . preg_replace('/\s+/', ' ', trim($qIns)));
            dbExec($qIns);
            $linkSaved = true;
        } catch (Throwable $e) {
            $linkErr = $e->getMessage();
            t("LINK EXCEPTION: $linkErr");
        }
    } else {
        t("LINK SKIPPED: idSportello=0");
        $linkSaved = false;
        $linkErr = 'idSportello=0 (manca parametro da JS)';
    }

    /* =====================================================
       6) MAIL (non blocca)
    ===================================================== */
    $mailSent = false;
    $mailErr  = '';
    try {
        $notifyTo = trim((string)($__settings->MBApp->emailPrenotazioniAule ?? ''));
        $notifyToName = trim((string)($__settings->MBApp->destPrenotazioniAule ?? ''));
        t("MAIL notifyTo=$notifyTo notifyToName=$notifyToName");

        $dataIT = ($ts !== false) ? date('d-m-y', $ts) : $dataInizioRaw;

        if ($notifyTo !== '' && function_exists('sendMail')) {
            $subject = "GestOre: prenotazione aula {$nroAulaRaw} — {$dataInizioRaw} {$oraInizioRaw}-{$oraFineRaw}";

            $aulaH = htmlspecialchars($nroAulaRaw, ENT_QUOTES, 'UTF-8');
            $dataH = htmlspecialchars($dataIT, ENT_QUOTES, 'UTF-8');
            $oraIH = htmlspecialchars($oraInizioRaw, ENT_QUOTES, 'UTF-8');
            $oraFH = htmlspecialchars($oraFineRaw, ENT_QUOTES, 'UTF-8');
            $docH  = htmlspecialchars($docenti, ENT_QUOTES, 'UTF-8');
            $attH  = htmlspecialchars((string)($_POST['attivitaProgetto'] ?? ''), ENT_QUOTES, 'UTF-8');
            $motH  = htmlspecialchars((string)($_POST['motivo'] ?? ''), ENT_QUOTES, 'UTF-8');
            $detH  = htmlspecialchars((string)($_POST['dettagli'] ?? ''), ENT_QUOTES, 'UTF-8');

            $body = '
            <div style="margin:0;padding:0;background:#f6f7fb;">
            <div style="max-width:720px;margin:0 auto;padding:24px;">
                <div style="background:#ffffff;border:1px solid #e6e8ef;border-radius:14px;overflow:hidden;">

                <!-- header -->
                <div style="padding:18px 22px;background:#0b57d0;color:#fff;">
                    <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
                    <div style="font-size:16px;opacity:.95;">GestOre</div>
                    <div style="font-size:22px;font-weight:700;line-height:1.2;margin-top:6px;">
                        Prenotazione aula confermata per sportello
                    </div>
                    </div>
                </div>

                <!-- content -->
                <div style="padding:22px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1f2937;">

                    <div style="display:inline-block;background:#eef2ff;border:1px solid #e0e7ff;color:#3730a3;
                                padding:6px 10px;border-radius:999px;font-weight:700;font-size:13px;margin-bottom:14px;">
                    Aula ' . $aulaH . '
                    </div>

                    <div style="font-size:15px;line-height:1.5;margin:0 0 14px 0;">
                    È stata registrata una prenotazione su <b>MBApp</b>.
                    </div>

                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
                        style="border-collapse:separate;border-spacing:0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;width:160px;color:#6b7280;font-size:13px;">Data</td>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;"><b>' . $dataH . '</b></td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;">Orario</td>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;"><b>' . $oraIH . '</b> – <b>' . $oraFH . '</b></td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;">Docente</td>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;">' . $docH . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;">Attività</td>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;">' . ($attH !== '' ? $attH : '<span style="color:#6b7280;">—</span>') . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;">Motivo</td>
                        <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;">' . ($motH !== '' ? $motH : '<span style="color:#6b7280;">—</span>') . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px;color:#6b7280;font-size:13px;">Dettagli</td>
                        <td style="padding:12px 14px;font-size:14px;">' . ($detH !== '' ? nl2br($detH) : '<span style="color:#6b7280;">—</span>') . '</td>
                    </tr>
                    </table>

                    <div style="margin-top:14px;padding:12px 14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;">
                    <div style="font-weight:700;color:#9a3412;margin-bottom:6px;">Riferimenti MBApp</div>
                    <div style="font-size:13px;color:#7c2d12;line-height:1.4;">
                        idAssenza: <b>' . (int)$assenzaId . '</b><br>
                        idCalendario: <b>' . (int)$idCalendario . '</b>
                    </div>
                    </div>

                </div>

                <!-- footer -->
                <div style="padding:14px 22px;background:#f9fafb;border-top:1px solid #e5e7eb;
                            font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#6b7280;font-size:12px;">
                    Notifica automatica da GestOre • Non rispondere a questa email
                </div>

                </div>
            </div>
            </div>
            ';


            sendMail($notifyTo, ($notifyToName !== '' ? $notifyToName : $notifyTo), $subject, $body);
            $mailSent = true;
        }
    } catch (Throwable $e) {
        $mailErr = $e->getMessage();
        t("MAIL EXCEPTION: $mailErr");
    }

    jsonOut(true, [
        'idSportello' => $idSportello,
        'assenzaId' => $assenzaId,
        'idCalendario' => $idCalendario,
        'linkSaved' => $linkSaved,
        'linkErr' => $linkErr,
        'mailSent' => $mailSent,
        'mailErr' => $mailErr,
        'msg' => "Prenotazione aula $nroAulaRaw confermata"
    ]);
} catch (Throwable $e) {
    t("EXCEPTION: " . $e->getMessage());
    jsonOut(false, ['error' => $e->getMessage()]);
}
