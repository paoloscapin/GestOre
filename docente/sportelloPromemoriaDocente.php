<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+
 *
 * CRON (ore 14): sportelli del giorno dopo
 * - se ci sono iscritti: invia promemoria al docente con lista studenti
 * - se non ci sono iscritti:
 *    ✅ lo sportello ORIGINALE resta in GestOre (attivo=1) ma:
 *       - viene tolta la prenotazione MBApp (best effort)
 *       - viene svuotata l'aula (luogo = '')
 *       - (docente_id NON viene toccato)
 *    ✅ viene creato un NUOVO sportello gemello in BOZZA (+14 giorni):
 *       - attivo=0, docente_id=0, luogo=''
 */

require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';

// -------------------------------
// MBApp connection (optional)
// -------------------------------
$mbappEnabled = false;
if (file_exists(__DIR__ . '/../common/connectMBApp.php')) {
    require_once __DIR__ . '/../common/connectMBApp.php';
    $mbappEnabled = function_exists('mb_dbExec') && function_exists('mb_dbGetValue') && function_exists('mb_dbAffectedRows');
}

// -------------------------------
// SAFE helpers (NO exit)
// -------------------------------
function dbExecSafe(string $query): array
{
    global $__con;
    debug($query);
    $ok = mysqli_query($__con, $query);
    if (!$ok) {
        $err = mysqli_error($__con);
        error('dbExecSafe ERROR: ' . $err . ' QUERY=' . $query);
        return ['ok' => false, 'error' => $err, 'affected' => 0];
    }
    return ['ok' => true, 'affected' => mysqli_affected_rows($__con)];
}

function dbGetValueSafe(string $query)
{
    global $__con;
    debug($query);
    $res = mysqli_query($__con, $query);
    if (!$res) {
        $err = mysqli_error($__con);
        error('dbGetValueSafe ERROR: ' . $err . ' QUERY=' . $query);
        return null;
    }
    $row = mysqli_fetch_array($res, MYSQLI_NUM);
    return is_array($row) ? $row[0] : null;
}

function dbGetFirstSafe(string $query): ?array
{
    global $__con;
    debug($query);
    $res = mysqli_query($__con, $query);
    if (!$res) {
        $err = mysqli_error($__con);
        error('dbGetFirstSafe ERROR: ' . $err . ' QUERY=' . $query);
        return null;
    }
    if (mysqli_num_rows($res) <= 0) return null;
    return mysqli_fetch_assoc($res);
}

function dbGetAllSafe(string $query): array
{
    global $__con;
    debug($query);
    $res = mysqli_query($__con, $query);
    if (!$res) {
        $err = mysqli_error($__con);
        error('dbGetAllSafe ERROR: ' . $err . ' QUERY=' . $query);
        return [];
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    return is_array($rows) ? $rows : [];
}

// ---- MBApp SAFE ----
function mb_dbExecSafe(string $query): array
{
    global $__conMBApp;
    if (!isset($__conMBApp) || !$__conMBApp) {
        return ['ok' => false, 'error' => 'MBApp connection missing', 'affected' => 0];
    }
    debug($query);
    $ok = mysqli_query($__conMBApp, $query);
    if (!$ok) {
        $err = mysqli_error($__conMBApp);
        error('mb_dbExecSafe ERROR: ' . $err . ' QUERY=' . $query);
        return ['ok' => false, 'error' => $err, 'affected' => 0];
    }
    return ['ok' => true, 'affected' => mysqli_affected_rows($__conMBApp)];
}

function mb_dbGetValueSafe(string $query)
{
    global $__conMBApp;
    if (!isset($__conMBApp) || !$__conMBApp) return null;
    debug($query);
    $res = mysqli_query($__conMBApp, $query);
    if (!$res) {
        $err = mysqli_error($__conMBApp);
        error('mb_dbGetValueSafe ERROR: ' . $err . ' QUERY=' . $query);
        return null;
    }
    $row = mysqli_fetch_array($res, MYSQLI_NUM);
    return is_array($row) ? $row[0] : null;
}

// -------------------------------
// anno scolastico
// -------------------------------
$__anno_scolastico = dbGetFirstSafe("SELECT * FROM anno_scolastico_corrente");
$__anno_scolastico_corrente_id = (int)($__anno_scolastico['anno_scolastico_id'] ?? 0);

// -------------------------------
// MBApp delete booking by link (SAFE, uses MBApp queries only)
// -------------------------------
function mbapp_delete_by_link_safe(int $idAssenza, int $idCalendario): array
{
    $idAssenza = (int)$idAssenza;
    $idCalendario = (int)$idCalendario;

    $out = [
        'ok' => true,
        'before' => ['utilizza' => 0, 'oralezione' => 0, 'assenze' => 0],
        'deleted' => ['utilizza' => 0, 'oralezione' => 0, 'assenze' => 0],
        'errors' => [],
        'msg' => ''
    ];

    if ($idAssenza <= 0 && $idCalendario <= 0) {
        $out['msg'] = 'skip: idAssenza e idCalendario vuoti';
        return $out;
    }

    // where conditions
    $condU = [];
    if ($idCalendario > 0) $condU[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $condU[] = "IDassenza = $idAssenza";
    $whereU = implode(" OR ", $condU);

    $condO = [];
    if ($idCalendario > 0) $condO[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $condO[] = "idAssenza = $idAssenza";
    $whereO = implode(" OR ", $condO);

    // BEFORE counts (MBApp DB!)
    if ($whereU !== '') $out['before']['utilizza'] = (int)(mb_dbGetValueSafe("SELECT COUNT(*) FROM utilizza WHERE $whereU") ?? 0);
    if ($whereO !== '') $out['before']['oralezione'] = (int)(mb_dbGetValueSafe("SELECT COUNT(*) FROM oralezione WHERE $whereO") ?? 0);
    if ($idAssenza > 0) $out['before']['assenze'] = (int)(mb_dbGetValueSafe("SELECT COUNT(*) FROM assenze WHERE idAssenza = $idAssenza") ?? 0);

    // DELETE in order: utilizza -> oralezione -> assenze
    if ($whereU !== '') {
        $r = mb_dbExecSafe("DELETE FROM utilizza WHERE $whereU");
        if (!$r['ok']) $out['errors'][] = 'utilizza: ' . ($r['error'] ?? 'unknown');
        $out['deleted']['utilizza'] = (int)($r['affected'] ?? 0);
    }

    if ($whereO !== '') {
        $r = mb_dbExecSafe("DELETE FROM oralezione WHERE $whereO");
        if (!$r['ok']) $out['errors'][] = 'oralezione: ' . ($r['error'] ?? 'unknown');
        $out['deleted']['oralezione'] = (int)($r['affected'] ?? 0);
    }

    if ($idAssenza > 0) {
        $r = mb_dbExecSafe("DELETE FROM assenze WHERE idAssenza = $idAssenza");
        if (!$r['ok']) $out['errors'][] = 'assenze: ' . ($r['error'] ?? 'unknown');
        $out['deleted']['assenze'] = (int)($r['affected'] ?? 0);
    }

    $out['ok'] = (count($out['errors']) === 0);
    $out['msg'] = $out['ok'] ? 'MBApp delete done' : 'MBApp delete completed with errors';
    return $out;
}

// -------------------------------
// Query sportelli di domani (GestOre DB)
// -------------------------------
$query = "
    SELECT
        s.id         AS sportello_id,
        s.ora        AS sportello_ora,
        s.data       AS sportello_data,
        s.cancellato AS sportello_cancellato,
        s.attivo     AS sportello_attivo,
        s.docente_id AS sportello_docente_id,
        s.materia_id AS sportello_materia_id,
        s.categoria  AS sportello_categoria,
        s.luogo      AS sportello_luogo,

        d.cognome AS docente_cognome,
        d.nome    AS docente_nome,
        d.email   AS docente_email,

        m.nome AS sportello_materia
    FROM sportello s
    INNER JOIN docente d ON d.id = s.docente_id
    INNER JOIN materia m ON m.id = s.materia_id
    WHERE DATE(s.data) = DATE(CURDATE() + INTERVAL 1 DAY)
      AND NOT s.cancellato
      AND s.attivo = 1
";

$result = dbGetAllSafe($query);
if (!$result) {
    info("CRON promemoria: nessun sportello programmato per domani");
    echo "nessun sportello programmato per domani<br>";
    $result = [];
}

foreach ($result as $row) {

    $sportello_id  = (int)$row['sportello_id'];
    $ora           = (string)$row['sportello_ora'];
    $dataYmd       = (string)$row['sportello_data'];
    $docente_id    = (int)$row['sportello_docente_id'];
    $docente_nome  = trim(($row['docente_cognome'] ?? '') . ' ' . ($row['docente_nome'] ?? ''));
    $docente_email = (string)$row['docente_email'];
    $materia       = (string)$row['sportello_materia'];
    $categoria     = (string)$row['sportello_categoria'];
    $luogo         = (string)$row['sportello_luogo'];

    // dd/mm/yyyy
    $dataIt = $dataYmd;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dataYmd)) {
        $p = explode('-', substr($dataYmd, 0, 10));
        $dataIt = $p[2] . '/' . $p[1] . '/' . $p[0];
    }

    // count iscritti (GestOre DB)
    $numero_iscritti = (int)(dbGetValueSafe("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id = $sportello_id AND iscritto = 1") ?? 0);
    info("CRON sportello_id=$sportello_id data=$dataIt ora=$ora iscritti=$numero_iscritti docente_id=$docente_id");

    $to = $docente_email;
    $toName = $docente_nome;

    if ($numero_iscritti > 0) {

        // studenti (GestOre DB)
        $qStud = "
            SELECT
                st.cognome AS studente_cognome,
                st.nome    AS studente_nome,
                c.classe   AS studente_classe,
                ss.argomento AS sportello_argomento
            FROM sportello_studente ss
            INNER JOIN studente st ON st.id = ss.studente_id
            INNER JOIN studente_frequenta sf
                ON sf.id_studente = st.id
               AND sf.id_anno_scolastico = $__anno_scolastico_corrente_id
            INNER JOIN classi c ON c.id = sf.id_classe
            WHERE ss.sportello_id = $sportello_id
              AND ss.iscritto = 1
            ORDER BY c.classe, st.cognome, st.nome
        ";
        $studRows = dbGetAllSafe($qStud);

        // Mail content
        $title = 'PROMEMORIA SPORTELLO DIDATTICO';
        $intro = 'Promemoria per l’attività programmata per <b>domani</b>.';

        $content = '
            <div style="margin:0 0 12px 0;">
                ' . badge('CONFERMATO – ' . $numero_iscritti . ' iscritt' . ($numero_iscritti === 1 ? 'o' : 'i'), '#dcfce7', '#14532d') . '
            </div>

            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                    ' . kvRow('Attività', $categoria) . '
                    ' . kvRow('Materia', $materia) . '
                    ' . kvRow('Data', $dataIt) . '
                    ' . kvRow('Ora', $ora) . '
                    ' . kvRow('Aula', ($luogo !== '' ? $luogo : '—')) . '
                    ' . kvRow('ID Sportello', (string)$sportello_id) . '
                </table>
            </div>

            <div style="font-size:13.5px;line-height:1.55;color:#374151;">
                Di seguito l’elenco degli studenti iscritti.
            </div>

            ' . studentiTableHtml($studRows) . '
        ';

        $footer = 'Se hai bisogno di modifiche, accedi a <b>GestOre</b>.';

        $body = mailWrap($title, $toName, $intro, $content, $footer, 'docente');
        $subject = "GestOre - Promemoria sportello ($categoria) - $materia - $dataIt $ora";

        info("CRON invio PROMEMORIA sportello_id=$sportello_id a $to ($toName)");
        sendMail($to, $toName, $subject, $body);

    } else {

        // -------------------------------
        // 0 iscritti
        // -------------------------------
        $title = 'ANNULLAMENTO SPORTELLO';
        $intro = 'Attività annullata automaticamente per mancanza di iscritti.';

        $content = '
            <div style="margin:0 0 12px 0;">
                ' . badge('ANNULLATO – 0 iscritti', '#fee2e2', '#7f1d1d') . '
            </div>

            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                    ' . kvRow('Attività', $categoria) . '
                    ' . kvRow('Materia', $materia) . '
                    ' . kvRow('Data', $dataIt) . '
                    ' . kvRow('Ora', $ora) . '
                    ' . kvRow('Aula', ($luogo !== '' ? $luogo : '—')) . '
                    ' . kvRow('ID Sportello', (string)$sportello_id) . '
                </table>
            </div>
        ';

        $footer = 'Nota: la prenotazione aula (MBApp) verrà rimossa se presente. Verrà creato un nuovo sportello gemello in BOZZA (+14 giorni).';

        $body = mailWrap($title, $toName, $intro, $content, $footer, 'annullamento');
        $subject = "GestOre - Annullamento sportello ($categoria) - $materia - $dataIt $ora";

        info("CRON invio ANNULLAMENTO sportello_id=$sportello_id a $to ($toName)");
        sendMail($to, $toName, $subject, $body);

        // 1) Cancella prenotazione MBApp + link (GestOre link table)
        if ($mbappEnabled) {

            $link = dbGetFirstSafe("
                SELECT idAssenza, idCalendario
                FROM sportello_mbapp_link
                WHERE id_sportello = $sportello_id
                LIMIT 1
            ");

            if ($link) {
                $idAss = (int)($link['idAssenza'] ?? 0);
                $idCal = (int)($link['idCalendario'] ?? 0);

                $mbInfo = mbapp_delete_by_link_safe($idAss, $idCal);
                info("CRON MBApp delete SAFE sportello_id=$sportello_id link=($idAss,$idCal) res=" . json_encode($mbInfo, JSON_UNESCAPED_UNICODE));

                $deletedSomething = (
                    ($mbInfo['deleted']['utilizza'] ?? 0) > 0 ||
                    ($mbInfo['deleted']['oralezione'] ?? 0) > 0 ||
                    ($mbInfo['deleted']['assenze'] ?? 0) > 0
                );
                $wasAlreadyGone = (
                    ($mbInfo['before']['utilizza'] ?? 0) == 0 &&
                    ($mbInfo['before']['oralezione'] ?? 0) == 0 &&
                    ($mbInfo['before']['assenze'] ?? 0) == 0
                );

                // ✅ se MBApp ha cancellato o era già vuoto => elimina link GestOre
                if ($deletedSomething || $wasAlreadyGone) {
                    dbExecSafe("DELETE FROM sportello_mbapp_link WHERE id_sportello = $sportello_id LIMIT 1");
                    info("CRON delete link sportello_mbapp_link id_sportello=$sportello_id");
                } else {
                    warning("CRON: MBApp non ha eliminato nulla e non era già vuoto: tengo il link (sportello_id=$sportello_id)");
                }
            } else {
                info("CRON: nessun link MBApp per sportello_id=$sportello_id");
            }
        } else {
            info("CRON: MBApp non disponibile (connectMBApp.php assente o funzioni mancanti)");
        }

        // 2) Sportello originale RESTA ATTIVO (attivo=1) ma svuoto aula
        dbExecSafe("
            UPDATE sportello
            SET luogo = ''
            WHERE id = $sportello_id
            LIMIT 1
        ");
        info("CRON: sportello_id=$sportello_id lasciato ATTIVO, aula rimossa");

        // 3) Gemello in BOZZA (+14 gg), docente_id=0, luogo='', attivo=0 (anti-duplicato)
        $targetDate = dbGetValueSafe("SELECT DATE_ADD(DATE(data), INTERVAL 14 DAY) FROM sportello WHERE id = $sportello_id LIMIT 1");
        $targetDate = $targetDate ? (string)$targetDate : '';

        if ($targetDate !== '') {

            $exists = (int)(dbGetValueSafe("
                SELECT COUNT(*)
                FROM sportello s2
                JOIN sportello s1 ON s1.id = $sportello_id
                WHERE DATE(s2.data) = DATE('$targetDate')
                  AND s2.ora = s1.ora
                  AND s2.materia_id = s1.materia_id
                  AND s2.categoria = s1.categoria
                  AND (
                        (s2.classe_id = s1.classe_id AND s1.classe_id <> 0)
                     OR (s2.classe = s1.classe AND (s1.classe_id = 0 OR s1.classe_id IS NULL))
                  )
                  AND s2.attivo = 0
                  AND s2.docente_id = 0
                  AND (s2.luogo IS NULL OR s2.luogo = '')
                  AND NOT s2.cancellato
                LIMIT 1
            ") ?? 0);

            if ($exists > 0) {
                info("CRON: gemello già presente in BOZZA per sportello_id=$sportello_id (data=$targetDate) -> skip insert");
            } else {
                $ins = dbExecSafe("
                    INSERT INTO sportello (
                        data, ora, numero_ore, argomento, luogo,
                        classe, classe_id, categoria, materia_id,
                        docente_id, max_iscrizioni,
                        firmato, cancellato, online, clil, orientamento,
                        attivo, anno_scolastico_id
                    )
                    SELECT
                        DATE_ADD(DATE(data), INTERVAL 14 DAY) AS data,
                        ora,
                        numero_ore,
                        argomento,
                        '' AS luogo,
                        classe,
                        classe_id,
                        categoria,
                        materia_id,
                        0 AS docente_id,
                        max_iscrizioni,
                        0 AS firmato,
                        0 AS cancellato,
                        online,
                        clil,
                        orientamento,
                        0 AS attivo,
                        anno_scolastico_id
                    FROM sportello
                    WHERE id = $sportello_id
                    LIMIT 1
                ");

                if ($ins['ok']) {
                    info("CRON: creato gemello BOZZA (+14gg) sportello_id=$sportello_id (data=$targetDate)");
                } else {
                    warning("CRON: errore inserimento gemello BOZZA sportello_id=$sportello_id err=" . ($ins['error'] ?? ''));
                }
            }
        } else {
            warning("CRON: impossibile calcolare targetDate per sportello_id=$sportello_id, gemello non creato");
        }
    }
}

// -------------------------------
// riallinea le date degli sportelli in BOZZA (se <= domani) - SAFE
// -------------------------------
dbExecSafe("
    UPDATE sportello s
    SET s.data =
        DATE_ADD(
            DATE_ADD(DATE(s.data), INTERVAL 14 DAY),
            INTERVAL
                CASE
                    WHEN DATE_ADD(DATE(s.data), INTERVAL 14 DAY) >= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        THEN 0
                    ELSE
                        (CEIL(
                            DATEDIFF(
                                DATE_ADD(CURDATE(), INTERVAL 7 DAY),
                                DATE_ADD(DATE(s.data), INTERVAL 14 DAY)
                            ) / 7
                        ) * 7)
                END
            DAY
        )
    WHERE s.attivo = 0
      AND DATE(s.data) <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
");

info("CRON: riallineamento date sportelli in BOZZA completato");
echo "OK cron promemoria sportelli\n";
