<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

// -------------------------------
// Helpers: HTML mail (grafica)
// -------------------------------
function mailWrap($titleHtml, $toName, $introHtml, $contentHtml, $footerHtml = ''): string
{
    $brand  = "#1f5e3b";
    $bg     = "#f3f6f8";
    $card   = "#ffffff";
    $txt    = "#1f2937";
    $muted  = "#6b7280";
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

// -------------------------------
// DATI necessari (assunti già disponibili nel contesto che include questo file):
// $materia_id, $id (sportello_id), $categoria, $data, $ora, $docente_cognome, $docente_nome, $luogo
// -------------------------------

// recupero i dati della materia
$materia = dbGetValue("SELECT nome FROM materia WHERE id = '" . addslashes($materia_id) . "'");

// elenco studenti iscritti allo sportello
$query = "SELECT 
            st.id      AS studente_id,
            st.cognome AS studente_cognome,
            st.nome    AS studente_nome,
            st.email   AS studente_email
          FROM sportello_studente ss
          INNER JOIN studente st ON st.id = ss.studente_id
          WHERE ss.sportello_id = " . (int)$id;

$resultArray = dbGetAll($query);
if (!$resultArray) $resultArray = [];

// Data in formato IT (se arriva yyyy-mm-dd)
$dataIt = (string)$data;
if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dataIt)) {
    $p = explode('-', substr($dataIt, 0, 10));
    $dataIt = $p[2] . '/' . $p[1] . '/' . $p[0];
}

foreach ($resultArray as $row) {

    $studente_id      = (int)$row['studente_id'];
    $studente_cognome = (string)$row['studente_cognome'];
    $studente_nome    = (string)$row['studente_nome'];
    $studente_email   = (string)$row['studente_email'];

    // ✅ BUGFIX: genitori dello studente della riga, NON $__studente_id
    $genitori = dbGetAll("
        SELECT g.cognome, g.nome, g.email
        FROM genitori g
        INNER JOIN genitori_studenti gs ON gs.id_genitore = g.id
        WHERE g.attivo = 1
          AND gs.id_studente = $studente_id
    ");
    if (!$genitori) $genitori = [];

    $email_genitori = "";
    $nominativo_genitori = "";
    foreach ($genitori as $genitore) {
        $mailG = trim((string)($genitore['email'] ?? ''));
        if ($mailG === '') continue;

        if ($email_genitori !== "") {
            $email_genitori .= ", ";
            $nominativo_genitori .= ", ";
        }
        $email_genitori .= $mailG;
        $nominativo_genitori .= trim((string)$genitore['cognome'] . " " . (string)$genitore['nome']);
    }

    // -------------------------------
    // Nuova mail HTML
    // -------------------------------
    $title = "ANNULLAMENTO ATTIVITÀ";
    $intro = "Notifica: il docente ha cancellato l’attività a cui eri iscritto.";

    $content = '
      <div style="margin:0 0 12px 0;">
        ' . badge('ATTIVITÀ ANNULLATA', '#fee2e2', '#7f1d1d') . '
      </div>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          ' . kvRow('Attività', strtoupper((string)$categoria)) . '
          ' . kvRow('Materia', (string)$materia) . '
          ' . kvRow('Data', $dataIt) . '
          ' . kvRow('Ora', (string)$ora) . '
          ' . kvRow('Aula', ((string)$luogo !== '' ? (string)$luogo : '—')) . '
          ' . kvRow('Docente', strtoupper(trim((string)$docente_cognome . ' ' . (string)$docente_nome))) . '
          ' . kvRow('ID Sportello', (string)(int)$id) . '
        </table>
      </div>

      <div style="font-size:13.5px;line-height:1.55;color:#374151;">
        Puoi prenotarti a una delle altre attività disponibili in <b>GestOre</b>.
      </div>
    ';

    $footer = "Se hai dubbi, contatta il docente o consulta GestOre.";
    $body = mailWrap($title, strtoupper($studente_cognome) . " " . strtoupper($studente_nome), $intro, $content, $footer);

    $to = $studente_email;
    $toName = $studente_nome . " " . $studente_cognome;
    $subject = 'GestOre - Annullamento attività ' . $categoria . ' - materia ' . $materia;

    info("Invio mail annullamento allo studente: $to ($toName) sportello_id=" . (int)$id);

    if ($email_genitori !== "") {
        sendMailCC($to, $toName, $email_genitori, $nominativo_genitori, $subject, $body);
        info("Mail annullamento inviata anche ai genitori: $email_genitori");
    } else {
        sendMail($to, $toName, $subject, $body);
    }
}

?>
