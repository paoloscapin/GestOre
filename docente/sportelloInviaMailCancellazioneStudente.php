<!--
/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
-->

<?php

require_once '../common/checkSession.php';
require_once '../common/send-mail.php';
ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

// recupero i dati della materia
$query = "SELECT nome FROM materia WHERE id = '$materia_id'";

$materia = dbGetValue($query);

// recupero l'elenco degli studenti iscritti allo sportello
$query = "SELECT 
            studente.id AS studente_id,
            studente.cognome AS studente_cognome,
            studente.nome AS studente_nome,
            studente.email AS studente_email,
            sportello_studente.sportello_id AS sportello_id
            FROM sportello_studente
            INNER JOIN studente
            ON studente.id = studente_id
            WHERE sportello_studente.sportello_id = $id";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
  $resultArray = [];
} else {
  foreach ($resultArray as $row) {
    $studente_cognome = $row['studente_cognome'];
    $studente_nome = $row['studente_nome'];
    $studente_email = $row['studente_email'];

    $genitori = dbGetAll("SELECT cognome,nome,email from genitori g
                          INNER JOIN genitori_studenti gs ON gs.id_studente = " . $__studente_id. "
                          WHERE g.attivo=1 AND gs.id_genitore = g.id");
    $email_genitori = "";
    $nominativo_genitori = "";
    
    foreach ($genitori as $genitore) {
      if ($email_genitori != "") {
        $email_genitori = $email_genitori . ", ";
        $nominativo_genitori = $nominativo_genitori . ", ";
      }
      $email_genitori = $email_genitori . $genitore['email'];
      $nominativo_genitori = $nominativo_genitori . $genitore['cognome'] . " " . $genitore['nome'];
    }

    $full_mail_body = file_get_contents("../docente/template_mail_cancella_studente.html");

    $full_mail_body = str_replace("{titolo}", "ANNULLAMENTO ATTIVITA'<br>" . strtoupper($categoria), $full_mail_body);
    $full_mail_body = str_replace("{nome}", strtoupper($studente_cognome) . " " . strtoupper($studente_nome), $full_mail_body);
    $full_mail_body = str_replace("{messaggio}", "hai ricevuto questa mail perchè il docente ha cancellato la seguente attività a cui eri iscritto</p><h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($categoria) . "</center></b></h3><p style='font-size: 14px; line-height: 140%;'> Puoi prenotarti ad una della altre attività disponibili", $full_mail_body);
    $full_mail_body = str_replace("{data}", $data, $full_mail_body);
    $full_mail_body = str_replace("{ora}", $ora, $full_mail_body);
    $full_mail_body = str_replace("{docente}", strtoupper($docente_cognome . " " . $docente_nome), $full_mail_body);
    $full_mail_body = str_replace("{materia}", $materia, $full_mail_body);
    $full_mail_body = str_replace("{aula}", $luogo, $full_mail_body);
    $full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);

    $to = $studente_email;
    $toCC = $email_genitori;
    $toCCName = $nominativo_genitori;
    $toName = $studente_nome . " " . $studente_cognome;
    info("Invio mail allo studente: " . $to . " " . $toName);
    echo "Invio mail al docente: " . $to . " " . $toName . "\n";

    if ($toCC != "") {
      $full_mail_body = str_replace("{messaggio}", "hai ricevuto questa mail perchè il docente ha cancellato la seguente attività a cui eri iscritto</p><h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($categoria) . "</center></b></h3><p style='font-size: 14px; line-height: 140%;'> Puoi prenotarti ad una della altre attività disponibili", $full_mail_body);
      $mailsubject = 'GestOre - Annullamento attività ' . $categoria . ' - materia ' . $materia;
      sendMailCC($to, $toName, $toCC, $toCCName, $mailsubject, $full_mail_body);

      info("mail di cancellazione dello sportello da parte del docente inviata anche al genitore - " . $studente_cognome . " " . $studente_nome);
    } else {
      $full_mail_body = str_replace("{messaggio}", "hai ricevuto questa mail perchè il docente ha cancellato la seguente attività a cui eri iscritto</p><h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($categoria) . "</center></b></h3><p style='font-size: 14px; line-height: 140%;'> Puoi prenotarti ad una della altre attività disponibili", $full_mail_body);
      $mailsubject = 'GestOre - Annullamento attività ' . $categoria . ' - materia ' . $materia;
      sendMail($to, $toName, $mailsubject, $full_mail_body);
    }
    info("mail di cancellazione dello sportello da parte del docente inviata allo studente - " . $studente_cognome . " " . $studente_nome);
  }
}
?>