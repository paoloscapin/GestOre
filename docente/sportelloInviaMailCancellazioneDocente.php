<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */


 
require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-didattica','dirigente');

// recupero i dati della materia
$query = "SELECT nome FROM materia WHERE id = '$materia_id'";

$materia = dbGetValue($query);

// recupero i dati del docente
$query = "SELECT * FROM docente WHERE id = '$docente_id'";

$result = dbGetFirst($query);

$docente_cognome = $result['cognome'];
$docente_nome = $result['nome'];
$docente_email = $result['email'];

// inverto format data - giorno con mese
$data_array = explode("-", $data);
$data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

// preparo il testo della mail
$full_mail_body = file_get_contents("template_mail_cancella_docente_studenti.html");

$full_mail_body = str_replace("{titolo}","ANNULLAMENTO SPORTELLO",$full_mail_body);
$full_mail_body = str_replace("{nome}",strtoupper($docente_cognome) . " " . strtoupper($docente_nome),$full_mail_body);
$full_mail_body = str_replace("{messaggio}","hai ricevuto questa mail perchè hai cancellato il seguente sportello",$full_mail_body);
$full_mail_body = str_replace("{data}",$data,$full_mail_body);
$full_mail_body = str_replace("{ora}",$ora,$full_mail_body);
$full_mail_body = str_replace("{docente}",strtoupper($docente_cognome . " " . $docente_nome),$full_mail_body);
$full_mail_body = str_replace("{materia}",$materia,$full_mail_body);
$full_mail_body = str_replace("{aula}",$luogo,$full_mail_body);

$messaggio_finale = "A questo sportello non risultavano studenti iscritti";
$lista_studenti = "";

// recupero l'elenco degli studenti iscritti allo sportello
$query = "SELECT 
            studente.id AS studente_id,
            studente.cognome AS studente_cognome,
            studente.nome AS studente_nome,
            studente.classe AS studente_classe,
            studente.email AS studente_email,
            sportello_studente.sportello_id AS sportello_id
            FROM sportello_studente
            INNER JOIN studente
            ON studente.id = studente_id
            WHERE sportello_studente.sportello_id = $id";

$resultArray = dbGetAll($query);

if ($resultArray == null) 
{
	$resultArray = [];
}
else
{
    $lista_studenti="<ul>";
    $messaggio_finale = "A questo sportello risultavano iscritti i seguenti studenti:";
    foreach($resultArray as $row) 
    {
        $studente_cognome = $row['studente_cognome'];
        $studente_nome = $row['studente_nome'];
        $studente_classe = $row['studente_classe'];
        $lista_studenti .= "<li>" . $studente_classe. " - " . $studente_cognome . " " . $studente_nome . "</li>";
    }
    $lista_studenti .= "</ul>";
}

$full_mail_body = str_replace("{messaggio_finale}",$messaggio_finale,$full_mail_body);
$full_mail_body = str_replace("{elenco_studenti}",$lista_studenti,$full_mail_body);

$sender = $__settings->local->emailNoReplyFrom;
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
$headers .= "From: " . $sender . "\r\n";
$headers .= "Bcc: " . $__settings->local->emailSportelli . "\r\n"."X-Mailer: php";
$mailsubject = 'GestOre - Annullamento sportello ' . $materia;
mail($docente_email, $mailsubject, $full_mail_body ,  $headers, additional_params: "-f$sender");
info("inviata mail di cancellazione sportello come richiesto dal docente - " . $docente_cognome . " " . $docente_nome);
?>
