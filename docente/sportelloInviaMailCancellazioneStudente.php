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
  ruoloRichiesto('docente','segreteria-didattica','dirigente');

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
}
else
{
  foreach($resultArray as $row) 
  {
    $studente_cognome = $row['studente_cognome'];
    $studente_nome = $row['studente_nome'];
    $studente_email = $row['studente_email'];

    $full_mail_body = file_get_contents("template_mail_cancella_studente.html");

    $full_mail_body = str_replace("{titolo}","ANNULLAMENTO ATTIVITA'<br>".strtoupper($categoria),$full_mail_body);
    $full_mail_body = str_replace("{nome}",strtoupper($studente_cognome) . " " . strtoupper($studente_nome),$full_mail_body);
    $full_mail_body = str_replace("{messaggio}","hai ricevuto questa mail perchè il docente ha cancellato la seguente attività a cui eri iscritto</p><h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($categoria) . "</center></b></h3><p style='font-size: 14px; line-height: 140%;'> Puoi prenotarti ad una della altre attività disponibili",$full_mail_body);
    $full_mail_body = str_replace("{data}",$data,$full_mail_body);
    $full_mail_body = str_replace("{ora}",$ora,$full_mail_body);
    $full_mail_body = str_replace("{docente}",strtoupper($docente_cognome . " " . $docente_nome),$full_mail_body);
    $full_mail_body = str_replace("{materia}",$materia,$full_mail_body);
    $full_mail_body = str_replace("{aula}",$luogo,$full_mail_body);
    $full_mail_body = str_replace("{nome_istituto}",$__settings->local->nomeIstituto,$full_mail_body);
    
    $sender = $__settings->local->emailNoReplyFrom;
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    $headers .= "From: " . $sender . "\r\n";
    $headers .= "Bcc: " . $__settings->local->emailSportelli . "\r\n"."X-Mailer: php";
    $mailsubject = 'GestOre - Annullamento attività ' . $categoria . ' - '. $materia;
    mail($studente_email, $mailsubject, $full_mail_body ,  $headers, additional_params: "-f$sender");
    info("mail di cancellazione dello sportello da parte del docente inviata allo studente - " . $studente_cognome . " " . $studente_nome);
  }
}
?>
  




