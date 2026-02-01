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

// ✅ UI comune (mailWrap, badge, kvRow, studentiTableHtml, ecc.)
require_once '../common/mail-ui.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

// -------------------------------
// DATI necessari (assunti già disponibili nel contesto che include questo file):
// $materia_id, $id (sportello_id), $categoria, $data, $ora, $docente_cognome, $docente_nome, $luogo
// -------------------------------

// recupero i dati della materia
$materia = dbGetValue("SELECT nome FROM materia WHERE id = '" . addslashes((string)$materia_id) . "'");

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

    $studente_id      = (int)($row['studente_id'] ?? 0);
    $studente_cognome = (string)($row['studente_cognome'] ?? '');
    $studente_nome    = (string)($row['studente_nome'] ?? '');
    $studente_email   = (string)($row['studente_email'] ?? '');

    if ($studente_id <= 0 || $studente_email === '') {
        warning("sportelloInviaMailCancellazioneStudente: studente_id/email non validi per sportello_id=" . (int)$id);
        continue;
    }

    // ✅ BUGFIX: genitori dello studente della riga
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
        $nominativo_genitori .= trim((string)($genitore['cognome'] ?? '') . " " . (string)($genitore['nome'] ?? ''));
    }

    // -------------------------------
    // Nuova mail HTML (mail-ui)
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
          ' . kvRow('Data', (string)$dataIt) . '
          ' . kvRow('Ora', (string)$ora) . '
          ' . kvRow('Aula', ((string)$luogo !== '' ? (string)$luogo : '—')) . '
          ' . kvRow('Docente', strtoupper(trim((string)$docente_cognome . " " . (string)$docente_nome))) . '
          ' . kvRow('ID Sportello', (string)(int)$id) . '
        </table>
      </div>

      <div style="font-size:13.5px;line-height:1.55;color:#374151;">
        Puoi prenotarti a una delle altre attività disponibili in <b>GestOre</b>.
      </div>
    ';

    $footer = "Se hai dubbi, contatta il docente o consulta GestOre.";

    $body = mailWrap($title, strtoupper($studente_cognome) . " " . strtoupper($studente_nome), $intro, $content, $footer, 'annullamento');
 
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
