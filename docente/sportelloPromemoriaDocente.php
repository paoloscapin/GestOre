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
require_once '../common/connectMBApp.php';
require_once '../common/send-mail.php';

// Se disponibile: connessione MBApp (per cancellare prenotazioni)
$mbappEnabled = false;
if (file_exists(__DIR__ . '/../common/connectMBApp.php')) {
  require_once __DIR__ . '/../common/connectMBApp.php';
  $mbappEnabled = function_exists('mb_dbExec');
}

// anno scolastico
$__anno_scolastico = dbGetFirst("SELECT * FROM anno_scolastico_corrente");
$__anno_scolastico_corrente_id = (int)($__anno_scolastico['anno_scolastico_id'] ?? 0);

// -------------------------------
// Helpers: HTML mail (grafica)
// -------------------------------
function mailWrap($titleHtml, $toName, $introHtml, $contentHtml, $footerHtml = ''): string
{
  $brand = "#1f5e3b";   // verde “istituto”
  $bg    = "#f3f6f8";
  $card  = "#ffffff";
  $txt   = "#1f2937";
  $muted = "#6b7280";
  $border = "#e5e7eb";

  $toNameSafe = htmlspecialchars((string)$toName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

  return '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>' . strip_tags($titleHtml) . '</title>
</head>
<body style="margin:0;background:' . $bg . ';font-family:Lato, Arial, Helvetica, sans-serif;color:' . $txt . ';">

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:' . $bg . ';padding:24px 10px;">
    <tr>
      <td align="center">

        <table role="presentation" width="720" cellspacing="0" cellpadding="0" style="max-width:720px;width:100%;">
          <tr>
            <td style="padding:0 0 12px 0;">
              <div style="background:' . $brand . ';color:#fff;padding:18px 20px;border-radius:14px 14px 0 0;
                          font-size:18px;font-weight:800;letter-spacing:.3px;line-height:1.2;">
                ' . $titleHtml . '
              </div>
            </td>
          </tr>

          <tr>
            <td style="background:' . $card . ';border:1px solid ' . $border . ';
                       border-top:none;border-radius:0 0 14px 14px; padding:18px 20px;">

              <div style="font-size:14px;line-height:1.55;color:' . $txt . ';margin-bottom:10px;">
                <div style="font-weight:800;font-size:15px;">' . $toNameSafe . '</div>
                <div style="color:' . $muted . ';font-size:13px;">' . $introHtml . '</div>
              </div>

              <div style="height:1px;background:' . $border . ';margin:14px 0;"></div>

              ' . $contentHtml . '

              ' . ($footerHtml ? '<div style="height:1px;background:' . $border . ';margin:14px 0;"></div>
              <div style="font-size:12.5px;line-height:1.55;color:' . $muted . ';">' . $footerHtml . '</div>' : '') . '

            </td>
          </tr>

          <tr>
            <td style="padding:14px 4px 0 4px;text-align:center;color:' . $muted . ';font-size:11.5px;line-height:1.4;">
              Messaggio automatico da GestOre – ' . htmlspecialchars((string)date('d/m/Y H:i'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>';
}

function badge($text, $bg, $color = "#111827"): string
{
  $t = htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  return '<span style="display:inline-block;padding:6px 10px;border-radius:999px;background:' . $bg . ';
                  color:' . $color . ';font-weight:800;font-size:12px;letter-spacing:.2px;">' . $t . '</span>';
}

function kvRow($k, $v): string
{
  $k = htmlspecialchars((string)$k, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $v = htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  return '
      <tr>
        <td style="padding:8px 10px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12.5px;width:34%;">' . $k . '</td>
        <td style="padding:8px 10px;border-top:1px solid #e5e7eb;font-weight:800;font-size:13.5px;">' . $v . '</td>
      </tr>';
}

function mailMbappCancelHtml(string $aula, string $dataIt, string $ora, int $sportello_id, string $categoria, string $materia): string
{
  $title = "ANNULLAMENTO PRENOTAZIONE AULA (MBApp)";
  $intro = "Notifica automatica: la prenotazione aula è stata annullata perché lo sportello è stato annullato.";

  $content = '
      <div style="margin:0 0 12px 0;">
        ' . badge('PRENOTAZIONE ANNULLATA', '#fee2e2', '#7f1d1d') . '
      </div>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          ' . kvRow('Aula', ($aula !== '' ? $aula : '—')) . '
          ' . kvRow('Data', $dataIt) . '
          ' . kvRow('Ora', $ora) . '
          ' . kvRow('Motivo', 'Sportello annullato (0 iscritti)') . '
          ' . kvRow('ID Sportello', (string)$sportello_id) . '
          ' . kvRow('Attività', $categoria) . '
          ' . kvRow('Materia', $materia) . '
        </table>
      </div>

      <div style="font-size:13.5px;line-height:1.55;color:#374151;">
        Non è richiesta alcuna azione. Questa email serve solo come tracciamento della modifica.
      </div>
    ';

  $footer = "Messaggio automatico: gestione prenotazioni aule (MBApp).";

  // riuso lo stesso wrapper grafico
  return mailWrap($title, $__GLOBALS['__settings']->MBApp->destPrenotazioniAule ?? 'Prenotazioni aule', $intro, $content, $footer);
}

function studentiTableHtml(array $rows): string
{
  if (!$rows) return '';

  $thead = '
    <tr>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Classe</th>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Cognome</th>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Nome</th>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Argomento</th>
    </tr>';

  $body = '';
  foreach ($rows as $r) {
    $cls = htmlspecialchars((string)($r['studente_classe'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $cog = htmlspecialchars((string)($r['studente_cognome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $nom = htmlspecialchars((string)($r['studente_nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $arg = htmlspecialchars((string)($r['sportello_argomento'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $body .= '
        <tr>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . $cls . '</td>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . $cog . '</td>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . $nom . '</td>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#374151;">' . $arg . '</td>
        </tr>';
  }

  return '
    <div style="margin-top:14px;">
      <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Elenco studenti iscritti</div>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <thead style="background:#f8fafc;">' . $thead . '</thead>
        <tbody>' . $body . '</tbody>
      </table>
    </div>';
}

// -------------------------------
// Helpers: MBApp delete booking by sportello
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

  // --- COUNT prima (per debug reale) ---
  if ($idCalendario > 0 || $idAssenza > 0) {
    $condU = [];
    if ($idCalendario > 0) $condU[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $condU[] = "IDassenza = $idAssenza";
    $whereU = implode(" OR ", $condU);

    $condO = [];
    if ($idCalendario > 0) $condO[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $condO[] = "idAssenza = $idAssenza";
    $whereO = implode(" OR ", $condO);

    $out['before']['utilizza']  = (int)mb_dbGetValue("SELECT COUNT(*) FROM utilizza  WHERE $whereU");
    $out['before']['oralezione'] = (int)mb_dbGetValue("SELECT COUNT(*) FROM oralezione WHERE $whereO");
    if ($idAssenza > 0) {
      $out['before']['assenze']   = (int)mb_dbGetValue("SELECT COUNT(*) FROM assenze WHERE idAssenza = $idAssenza");
    }
  }

  // --- DELETE (ordine corretto: child -> parent) ---

  // 1) UTILIZZA: per idCalendario OR IDassenza
  if ($idCalendario > 0 || $idAssenza > 0) {
    $cond = [];
    if ($idCalendario > 0) $cond[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $cond[] = "IDassenza = $idAssenza";
    $where = implode(" OR ", $cond);

    mb_dbExec("DELETE FROM utilizza WHERE $where");
    $out['deleted']['utilizza'] = (int)mb_dbAffectedRows();
  }

  // 2) ORALEZIONE: per idCalendario OR idAssenza
  if ($idCalendario > 0 || $idAssenza > 0) {
    $cond = [];
    if ($idCalendario > 0) $cond[] = "idCalendario = $idCalendario";
    if ($idAssenza > 0)    $cond[] = "idAssenza = $idAssenza";
    $where = implode(" OR ", $cond);

    mb_dbExec("DELETE FROM oralezione WHERE $where");
    $out['deleted']['oralezione'] = (int)mb_dbAffectedRows();
  }

  // 3) ASSENZE: per idAssenza
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

  $sportello_id   = (int)$row['sportello_id'];
  $ora            = (string)$row['sportello_ora'];
  $dataYmd        = (string)$row['sportello_data'];
  $docente_id     = (int)$row['sportello_docente_id'];
  $docente_nome   = trim(($row['docente_cognome'] ?? '') . ' ' . ($row['docente_nome'] ?? ''));
  $docente_email  = (string)$row['docente_email'];
  $materia        = (string)$row['sportello_materia'];
  $categoria      = (string)$row['sportello_categoria'];
  $luogo          = (string)$row['sportello_luogo'];

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

    $body = mailWrap($title, $toName, $intro, $content, $footer);

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
              Potrai riprogrammarlo (in particolare cambiando data/ora e altri dettagli) e poi salvarlo nuovamente.
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
    $mbInfo = ['ok' => true, 'msg' => 'mbapp not enabled'];
    if ($mbappEnabled) {
      $link = dbGetFirst("
    SELECT idAssenza, idCalendario
    FROM sportello_mbapp_link
    WHERE id_sportello = $sportello_id
    LIMIT 1");

      $mbInfo = ['ok' => true, 'msg' => 'no link'];
      if ($mbappEnabled && $link) {
        $idAss = (int)($link['idAssenza'] ?? 0);
        $idCal = (int)($link['idCalendario'] ?? 0);

        $mbInfo = mbapp_delete_by_link($idAss, $idCal);
        info("CRON MBApp delete sportello_id=$sportello_id link=($idAss,$idCal) res=" . json_encode($mbInfo, JSON_UNESCAPED_UNICODE));

        // ✅ Cancella il link SOLO se ho davvero eliminato qualcosa oppure se non esisteva più
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

          // ✅ invio notifica a prenotazioni aule MBApp (se configurata)
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

          // ✅ cancello link GestOre
          dbExec("DELETE FROM sportello_mbapp_link WHERE id_sportello = $sportello_id LIMIT 1");
          info("CRON delete link sportello_mbapp_link id_sportello=$sportello_id");
        } else {
          warning("CRON: NON cancello il link perché MBApp non ha eliminato nulla (ID forse non combaciano) sportello_id=$sportello_id");
        }
      }
    }

    // -------------------------------
    // 2) Aggiunto sportello in BOZZA per lo sportello annullato
    //    - data = data + 14 giorni
    //    - attivo = 0
    //    - luogo = '' (svuoto aula)
    //    - cancellato resta 0
    // -------------------------------
    dbExec("
            UPDATE sportello
            SET
                attivo = 1,
                luogo = ''
            WHERE id = $sportello_id
            LIMIT 1
        ");
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
    LIMIT 1");

    info("CRON sportello annullato - aggiunto nuovo sportello in BOZZA");
  } // fine gestione 0 iscritti
} // fine ciclo sportelli di domani

dbexec("UPDATE sportello s
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
  AND DATE(s.data) <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)");

// riallinea le date degli sportelli in BOZZA per evitare di accumulare sportelli in date passate
info("CRON: riallineamento date sportelli in BOZZA completato");

echo "OK cron promemoria sportelli\n";
