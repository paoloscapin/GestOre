<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

// non abbiamo una sessione per cui calcola l'id dell'anno scolastico
$anno_scolastico_corrente_id = (int)dbGetValue("SELECT anno_scolastico_id FROM anno_scolastico_corrente");

// per prima cosa determina quale è la data da controllare
$daysInAdvance = getSettingsValue('sportelli', 'chiusuraIscrizioniGiorni', '1');
$dateToCheck = date('Y-m-d', strtotime(' + ' . (int)$daysInAdvance . ' days'));

// controlla la data dell'ultimo controllo effettuato
$lastCheckDate = (string)dbGetValue("SELECT ultimo_controllo_sportelli FROM config");

// se si tratta della stessa data non deve fare nulla
if ($lastCheckDate === $dateToCheck) {
    debug('controllo sportello già effettuato per la data ' . $dateToCheck);
    return;
}

info('inizio controllo sportello per la data ' . $dateToCheck);

$query = "
    SELECT
        sportello.id AS sportello_id,
        sportello.data AS sportello_data,
        sportello.ora AS sportello_ora,
        sportello.numero_ore AS sportello_numero_ore,
        sportello.luogo AS sportello_luogo,
        sportello.classe AS sportello_classe,
        sportello.argomento AS sportello_argomento,
        sportello.firmato AS sportello_firmato,
        sportello.online AS sportello_online,
        sportello.cancellato AS sportello_cancellato,
        materia.nome AS materia_nome,
        docente.cognome AS docente_cognome,
        docente.nome AS docente_nome,
        docente.email AS docente_email,
        (
            SELECT COUNT(*)
            FROM sportello_studente
            WHERE sportello_studente.sportello_id = sportello.id
        ) AS numero_studenti
    FROM sportello sportello
    INNER JOIN docente docente ON sportello.docente_id = docente.id
    INNER JOIN materia materia ON sportello.materia_id = materia.id
    WHERE sportello.anno_scolastico_id = $anno_scolastico_corrente_id
      AND NOT sportello.cancellato
      AND sportello.data = '$dateToCheck'
";

$sportelli = dbGetAll($query);
if (!$sportelli) $sportelli = [];

foreach ($sportelli as $sportello) {

    $sportello_id = (int)$sportello['sportello_id'];

    $nomeCognome = trim(($sportello['docente_nome'] ?? '') . ' ' . ($sportello['docente_cognome'] ?? ''));
    $sportelloMateria = (string)($sportello['materia_nome'] ?? '');
    $sportelloData = (string)($sportello['sportello_data'] ?? '');
    $sportelloOra = (string)($sportello['sportello_ora'] ?? '');
    $sportelloArgomento = (string)($sportello['sportello_argomento'] ?? '');
    $sportelloNumeroOre = (int)($sportello['sportello_numero_ore'] ?? 0);
    $sportelloNumeroStudenti = (int)($sportello['numero_studenti'] ?? 0);

    $oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
    $dateString = utf8_encode(strftime("%A, %d %B %Y", strtotime($sportelloData)));
    setlocale(LC_TIME, $oldLocale);

    debug('sportelloData=' . $sportelloData);
    debug('dateString=' . $dateString);

    $dicituraSportello = "sportello di " . $sportelloMateria . ' di ' . $dateString . ' alle ' . $sportelloOra . ' (durata ' . $sportelloNumeroOre . ' ore)';

    $html_msg = "
    <html>
    <head>
    <style>
    #student {
      font-family: Arial, Helvetica, sans-serif;
      border-collapse: collapse;
      width: 100%;
    }
    #student td, #student th {
      border: 1px solid #ddd;
      padding: 6px;
    }
    #student tr:nth-child(even){background-color: #f2f2f2;}
    #student tr:hover {background-color: #ddd;}
    #student th {
      padding-top: 6px;
      padding-bottom: 6px;
      text-align: left;
      background-color: #04AA6D;
      color: white;
    }
    </style>
    </head>
    <body>
    <p>Sportello: $dateString - $sportelloOra (durata $sportelloNumeroOre ore)<br>
    Docente: $nomeCognome<br>
    Materia: $sportelloMateria</p>
    <hr>
    <p>Argomento: <strong>$sportelloArgomento</strong></p>
    <p>Studenti: ($sportelloNumeroStudenti):</br>
    <table id=\"student\">
      <tr><th>Studente</th><th>Classe</th><th>Argomento</th></tr>
    ";

    // ✅ FIX studenti: classe da studente_frequenta -> classi, argomento da sportello_studente
    $qStud = "
        SELECT
            studente.cognome,
            studente.nome,
            sportello_studente.argomento AS argomento,
            sportello_studente.note AS note,
            (
                SELECT classi.classe
                FROM classi
                WHERE id = (
                    SELECT sf.id_classe
                    FROM studente_frequenta sf
                    WHERE sf.id_studente = studente.id
                      AND sf.id_anno_scolastico = $anno_scolastico_corrente_id
                    LIMIT 1
                )
            ) AS classe_attuale
        FROM sportello_studente
        INNER JOIN studente ON sportello_studente.studente_id = studente.id
        WHERE sportello_studente.sportello_id = $sportello_id
    ";

    $studenti = dbGetAll($qStud);
    if (!$studenti) $studenti = [];

    foreach ($studenti as $st) {
        $stClasse = (string)($st['classe_attuale'] ?? '');
        $stArg = trim((string)($st['argomento'] ?? ''));
        if ($stArg === '') $stiente = trim((string)($st['note'] ?? ''));
        if ($stArg === '') $stArg = '-';

        $html_msg .= "<tr><td>" . $st['cognome'] . " " . $st['nome'] . "</td><td>" . $stClasse . "</td><td>" . htmlspecialchars($stArg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>";
    }

    $html_msg .= "</table></p></body></html>";

    // invia la email al docente
    $to = (string)($sportello['docente_email'] ?? '');
    if ($to === '') continue;

    if ($sportelloNumeroStudenti > 0) {
        $subject = "Conferma $dicituraSportello";
    } else {
        $subject = "Annullato $dicituraSportello";
        $html_msg = "<html><body><p><strong>Annullamento Sportello</strong></p><p>Gentile $nomeCognome, lo sportello $dicituraSportello viene annullato perché non risultano studenti iscritti</p></body></html>";
    }

    $sender = $__settings->local->emailNoReplyFrom;
    $headers = "From: $sender\n";
    $headers .= "MIME-Version: 1.0\n";
    $headers .= "Content-Type: text/html; charset=\"UTF-8\"\n";
    $headers .= "Content-Transfer-Encoding: 8bit\n";
    $headers .= "X-Mailer: PHP " . phpversion();

    ini_set("sendmail_from", $sender);

    if (mail($to, $subject, $html_msg, $headers, "-f$sender")) {
        info("email inviata correttamente a " . $to . " oggetto: " . $subject);
    } else {
        warning("errore nell'invio della email a " . $to . " oggetto: " . $subject);
    }
}

// aggiornamento ultimo controllo
dbExec("UPDATE config SET ultimo_controllo_sportelli = '$dateToCheck'");
info('terminato controllo sportello per la data ' . $dateToCheck);
