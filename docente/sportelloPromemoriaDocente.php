<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+
 *
 * CRON (ore 14): sportelli del giorno dopo
 * - se ci sono iscritti: invia promemoria al docente con lista studenti
 * - se non ci sono iscritti: invia annullamento + rimette in BOZZA mantenendo docente_id
 *   + cancella prenotazione MBApp se presente + svuota aula
 */

require_once '../common/connect.php';
require_once '../common/send-mail.php';

// ✅ UI comune (mailWrap, badge, kvRow, studentiTableHtml, mailMbappCancelHtml)
require_once '../common/mail-ui.php';

// Se disponibile: connessione MBApp (per cancellare prenotazioni)
$mbappEnabled = false;
if (file_exists(__DIR__ . '/../common/connectMBApp.php')) {
    require_once __DIR__ . '/../common/connectMBApp.php';
    $mbappEnabled = function_exists('mb_dbExec') && function_exists('mb_dbGetValue') && function_exists('mb_dbAffectedRows');
}

// anno scolastico
$__anno_scolastico = dbGetFirst("SELECT * FROM anno_scolastico_corrente");
$__anno_scolastico_corrente_id = (int)($__anno_scolastico['anno_scolastico_id'] ?? 0);

// -------------------------------
// Helpers: MBApp delete booking by sportello (rimane qui se non è nel mail-ui)
// -------------------------------
function mbapp_delete_by_link(int $idAssenza, int $idCalendario): array
{
    $idAssenza    = (int)$idAssenza;
    $idCalendario = (int)$idCalendario;

    $out = [
        'ok' => true,
        'before' => ['utilizza' => 0, 'oralezione' => 0, 'assenze' => 0],
        'deleted' => ['utilizza' => 0, 'oralezione' => 0, 'assenze' => 0],
        'msg' => ''
    ];

    if (!function_exists('mb_dbExec') || !function_exists('mb_dbGetValue') || !function_exists('mb_dbAffectedRows')) {
        return ['ok' => false, 'msg' => 'MBApp helpers mancanti (connectMBApp.php non caricato?)'];
    }

    if ($idAssenza <= 0 && $idCalendario <= 0) {
        $out['msg'] = 'skip: idAssenza e idCalendario vuoti';
        return $out;
    }

    // COUNT prima (debug)
    if ($idCalendario > 0 || $idAssenza > 0) {
        $condU = [];
        if ($idCalendario > 0) $condU[] = "idCalendario = $idCalendario";
        if ($idAssenza > 0)    $condU[] = "IDassenza = $idAssenza";
        $whereU = implode(" OR ", $condU);

        $condO = [];
        if ($idCalendario > 0) $condO[] = "idCalendario = $idCalendario";
        if ($idAssenza > 0)    $condO[] = "idAssenza = $idAssenza";
        $whereO = implode(" OR ", $condO);

        $out['before']['utilizza']   = (int)mb_dbGetValue("SELECT COUNT(*) FROM utilizza  WHERE $whereU");
        $out['before']['oralezione'] = (int)mb_dbGetValue("SELECT COUNT(*) FROM oralezione WHERE $whereO");
        if ($idAssenza > 0) {
            $out['before']['assenze'] = (int)mb_dbGetValue("SELECT COUNT(*) FROM assenze WHERE idAssenza = $idAssenza");
        }
    }

    // DELETE (ordine child -> parent)

    // 1) UTILIZZA
    if ($idCalendario > 0 || $idAssenza > 0) {
        $cond = [];
        if ($idCalendario > 0) $cond[] = "idCalendario = $idCalendario";
        if ($idAssenza > 0)    $cond[] = "IDassenza = $idAssenza";
        $where = implode(" OR ", $cond);

        mb_dbExec("DELETE FROM utilizza WHERE $where");
        $out['deleted']['utilizza'] = (int)mb_dbAffectedRows();
    }

    // 2) ORALEZIONE
    if ($idCalendario > 0 || $idAssenza > 0) {
        $cond = [];
        if ($idCalendario > 0) $cond[] = "idCalendario = $idCalendario";
        if ($idAssenza > 0)    $cond[] = "idAssenza = $idAssenza";
        $where = implode(" OR ", $cond);

        mb_dbExec("DELETE FROM oralezione WHERE $where");
        $out['deleted']['oralezione'] = (int)mb_dbAffectedRows();
    }

    // 3) ASSENZE
    if ($idAssenza > 0) {
        mb_dbExec("DELETE FROM assenze WHERE idAssenza = $idAssenza");
        $out['deleted']['assenze'] = (int)mb_dbAffectedRows();
    }

    $out['msg'] = "MBApp delete done";
    return $out;
}

// -------------------------------
// Query sportelli di domani
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

$result = dbGetAll($query);
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

    // count iscritti
    $numero_iscritti = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id = $sportello_id AND iscritto = 1");
    info("CRON sportello_id=$sportello_id data=$dataIt ora=$ora iscritti=$numero_iscritti docente_id=$docente_id");

    $to = $docente_email;
    $toName = $docente_nome;

    if ($numero_iscritti > 0) {

        // studenti
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
        $studRows = dbGetAll($qStud);
        if (!$studRows) $studRows = [];

        // Mail content (UI da mail-ui.php)
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

        $body = mailWrap($title, $toName, $intro, $content, $footer,'docente');
        $subject = "GestOre - Promemoria sportello ($categoria) - $materia - $dataIt $ora";

        info("CRON invio PROMEMORIA sportello_id=$sportello_id a $to ($toName)");
        sendMail($to, $toName, $subject, $body);

    } else {

        // 0 iscritti: annullo + rimetto in bozza mantenendo docente_id
        $title = 'ANNULLAMENTO SPORTELLO';
        $intro = 'Attività annullata automaticamente per mancanza di iscritti.';

        $content = '
            <div style="margin:0 0 12px 0;">
                ' . badge('ANNULLATO – 0 iscritti', '#fee2e2', '#7f1d1d') . '
            </div>

            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
                <div style="font-weight:900;font-size:14px;margin-bottom:6px;color:#9a3412;">
                    Lo sportello verrà riportato in <b>BOZZA</b>
                </div>
                <div style="font-size:13.5px;line-height:1.55;color:#7c2d12;">
                    Potrai riprogrammarlo (cambiando data/ora e altri dettagli) e poi salvarlo nuovamente.
                </div>
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

        $footer = 'Nota: la prenotazione aula (MBApp) verrà rimossa se presente.';

        $body = mailWrap($title, $toName, $intro, $content, $footer);
        $subject = "GestOre - Annullamento sportello ($categoria) - $materia - $dataIt $ora";

        info("CRON invio ANNULLAMENTO sportello_id=$sportello_id a $to ($toName)");
        sendMail($to, $toName, $subject, $body);

        // -------------------------------
        // 1) Cancella prenotazione MBApp + link (se esiste)
        // -------------------------------
        if ($mbappEnabled) {

            $link = dbGetFirst("
                SELECT idAssenza, idCalendario
                FROM sportello_mbapp_link
                WHERE id_sportello = $sportello_id
                LIMIT 1
            ");

            if ($link) {
                $idAss = (int)($link['idAssenza'] ?? 0);
                $idCal = (int)($link['idCalendario'] ?? 0);

                $mbInfo = mbapp_delete_by_link($idAss, $idCal);
                info("CRON MBApp delete sportello_id=$sportello_id link=($idAss,$idCal) res=" . json_encode($mbInfo, JSON_UNESCAPED_UNICODE));

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

                if ($deletedSomething || $wasAlreadyGone) {

                    // notifica MBApp prenotazioni aule (se configurata)
                    $mbTo = trim((string)($__settings->MBApp->emailPrenotazioniAule ?? ''));
                    $mbToName = trim((string)($__settings->MBApp->destPrenotazioniAule ?? 'Prenotazioni aule'));

                    if ($mbTo !== '') {
                        $mbBody = mailMbappCancelHtml(
                            (string)$luogo,
                            (string)$dataIt,
                            (string)$ora,
                            (int)$sportello_id,
                            (string)$categoria,
                            (string)$materia
                        );

                        $mbSubject = "MBApp - Prenotazione aula annullata: Aula $luogo - $dataIt $ora (sportello $sportello_id)";
                        info("CRON invio NOTIFICA MBApp prenotazioni aule sportello_id=$sportello_id a $mbTo ($mbToName)");
                        sendMail($mbTo, $mbToName, $mbSubject, $mbBody);
                    } else {
                        info("CRON: emailPrenotazioniAule non configurata, skip notifica MBApp");
                    }

                    // cancello link GestOre
                    dbExec("DELETE FROM sportello_mbapp_link WHERE id_sportello = $sportello_id LIMIT 1");
                    info("CRON delete link sportello_mbapp_link id_sportello=$sportello_id");
                } else {
                    warning("CRON: NON cancello il link perché MBApp non ha eliminato nulla sportello_id=$sportello_id");
                }
            }
        }

        // -------------------------------
        // 2) Tieni lo sportello come storico degli sportelli andati deserti
        // -------------------------------
        dbExec("
            UPDATE sportello
            SET
                attivo = 1,
                luogo = ''
            WHERE id = $sportello_id
            LIMIT 1
        ");

        // 3) Crea un nuovo sportello BOZZA uguale, data +14 giorni, docente_id=0, luogo=''
        dbExec("
            INSERT INTO sportello (
                data,
                ora,
                numero_ore,
                argomento,
                luogo,
                classe,
                classe_id,
                categoria,
                materia_id,
                docente_id,
                max_iscrizioni,
                firmato,
                cancellato,
                online,
                clil,
                orientamento,
                attivo,
                anno_scolastico_id
            )
            SELECT
                DATE_ADD(data, INTERVAL 14 DAY) AS data,
                ora,
                numero_ore,
                argomento,
                ''              AS luogo,
                classe,
                classe_id,
                categoria,
                materia_id,
                0               AS docente_id,
                max_iscrizioni,
                0               AS firmato,
                0               AS cancellato,
                online,
                clil,
                orientamento,
                0               AS attivo,
                anno_scolastico_id
            FROM sportello
            WHERE id = $sportello_id
            LIMIT 1
        ");

        info("CRON sportello annullato - rimesso in BOZZA + aggiunto nuovo sportello in BOZZA (+14gg)");
    }
}

// -------------------------------
// riallinea le date degli sportelli in BOZZA (se <= domani)
// -------------------------------
dbExec("
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
