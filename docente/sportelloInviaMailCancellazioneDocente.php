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
require_once '../common/mail-ui.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

// ------------------------
// INPUT (da chiamata ajax)
// ------------------------
// mi aspetto questi campi (adegua se usi GET)
$id         = (int)($_POST['sportello_id'] ?? $_POST['id'] ?? 0);
$docente_id = (int)($_POST['docente_id'] ?? 0);
$materia_id = (int)($_POST['materia_id'] ?? 0);

$data       = (string)($_POST['data'] ?? '');      // es: 2026-02-01 oppure dd-mm-yyyy, lo normalizzo sotto
$ora        = (string)($_POST['ora'] ?? '');
$categoria  = (string)($_POST['categoria'] ?? 'sportello');
$luogo      = (string)($_POST['luogo'] ?? '');

if ($id <= 0 || $docente_id <= 0 || $materia_id <= 0) {
    http_response_code(400);
    echo "Parametri mancanti o non validi (sportello_id/docente_id/materia_id).";
    exit;
}

// ------------------------
// DATI MATERIA + DOCENTE
// ------------------------
$materia = (string)dbGetValue("SELECT nome FROM materia WHERE id = $materia_id");

$doc = dbGetFirst("SELECT cognome, nome, email FROM docente WHERE id = $docente_id");
$docente_cognome = (string)($doc['cognome'] ?? '');
$docente_nome    = (string)($doc['nome'] ?? '');
$docente_email   = (string)($doc['email'] ?? '');

if ($docente_email === '') {
    http_response_code(400);
    echo "Email docente non trovata.";
    exit;
}

$to     = $docente_email;
$toName = trim($docente_nome . ' ' . $docente_cognome);

// ------------------------
// NORMALIZZA DATA in dd/mm/yyyy
// ------------------------
$dataIt = $data;
if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
    $p = explode('-', substr($data, 0, 10));
    $dataIt = $p[2] . '/' . $p[1] . '/' . $p[0];
} elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $data)) {
    // già dd-mm-yyyy
    $p = explode('-', $data);
    $dataIt = $p[0] . '/' . $p[1] . '/' . $p[2];
}

// ------------------------
// STUDENTI ISCRITTI
// ------------------------
$q = "
    SELECT
        st.id     AS studente_id,
        st.cognome AS studente_cognome,
        st.nome    AS studente_nome,
        ss.argomento AS sportello_argomento,
        c.classe   AS studente_classe
    FROM sportello_studente ss
    INNER JOIN studente st ON st.id = ss.studente_id
    INNER JOIN studente_frequenta sf
        ON sf.id_studente = st.id
       AND sf.id_anno_scolastico = $__anno_scolastico_corrente_id
    INNER JOIN classi c ON c.id = sf.id_classe
    WHERE ss.sportello_id = $id
      AND ss.iscritto = 1
    ORDER BY c.classe, st.cognome, st.nome
";

$studRows = dbGetAll($q);
if (!$studRows) $studRows = [];

$numero_iscritti = count($studRows);

// ------------------------
// EMAIL (grafica nuova)
// ------------------------
$title = "ANNULLAMENTO ATTIVITÀ<br>" . strtoupper($categoria);
$intro = "Notifica automatica: hai cancellato l’attività indicata sotto.";

$content = '
  <div style="margin:0 0 12px 0;">
    ' . badge('ANNULLATO', '#fee2e2', '#7f1d1d') . '
    ' . ($numero_iscritti > 0 ? ' ' . badge($numero_iscritti . ' student' . ($numero_iscritti === 1 ? 'e' : 'i') . ' iscritt' . ($numero_iscritti === 1 ? 'o' : 'i'), '#fff7ed', '#9a3412') : '') . '
  </div>

  <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
      ' . kvRow('Attività', $categoria) . '
      ' . kvRow('Materia', $materia) . '
      ' . kvRow('Data', $dataIt) . '
      ' . kvRow('Ora', $ora) . '
      ' . kvRow('Aula', ($luogo !== '' ? $luogo : '—')) . '
      ' . kvRow('ID Sportello', (string)$id) . '
    </table>
  </div>

  <div style="font-size:13.5px;line-height:1.55;color:#374151;">
    ' . ($numero_iscritti > 0
        ? 'A questa attività risultavano iscritti i seguenti studenti:'
        : 'A questa attività non risultavano studenti iscritti.'
    ) . '
  </div>

  ' . ($numero_iscritti > 0 ? studentiTableHtml($studRows) : '') . '
';

$footer = "Messaggio automatico da GestOre – annullamento attività.";
$body   = mailWrap($title, $toName, $intro, $content, $footer,'annullamento');

$subject = "GestOre - Annullamento attività ($categoria) - $materia - $dataIt $ora";

info("Invio mail cancellazione al docente: $to ($toName) sportello_id=$id iscritti=$numero_iscritti");
sendMail($to, $toName, $subject, $body);

info("Inviata mail di cancellazione sportello come richiesto dal docente - $docente_cognome $docente_nome");
echo "OK";
