<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */



require_once '../common/checkSession.php';
require_once '../common/send-mail.php';
ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

// recupero i dati della materia
$query = "SELECT nome FROM materia WHERE id = '$materia_id'";

$materia = dbGetValue($query);

// recupero i dati del docente
$query = "SELECT * FROM docente WHERE id = '$docente_id'";

$result = dbGetFirst($query);

$docente_cognome = $result['cognome'];
$docente_nome = $result['nome'];
$docente_email = $result['email'];

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
$full_mail_body = "";

if ($resultArray == null) {
    // preparo il testo della mail
    $full_mail_body = file_get_contents("../docente/template_mail_cancella_docente.html");
    $messaggio_finale = "A questa attività non risultavano studenti iscritti";
    $resultArray = [];
} else {
    // preparo il testo della mail
    $full_mail_body = file_get_contents("../docente/template_mail_cancella_docente_studenti.html");
    $data_html = '<tr>';
    $messaggio_finale = "A questa attività che è stata cancellata erano iscritti i seguenti studenti:";
    foreach ($resultArray as $row) {
        $studente_cognome = $row['studente_cognome'];
        $studente_nome = $row['studente_nome'];
        $studente_classe = $row['studente_classe'];

        $row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
        <p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
        $row_html = str_replace("VALORE", $studente_classe, $row_html);
        $data_html .= $row_html;

        $row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
        <p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
        $row_html = str_replace("VALORE", $studente_cognome, $row_html);
        $data_html .= $row_html;

        $row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
        <p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
        $row_html = str_replace("VALORE", $studente_nome, $row_html);
        $data_html .= $row_html;
    }

    $data_html .= '</tr>';
}

// inverto format data - giorno con mese
$data_array = explode("-", $data);
$data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

$full_mail_body = str_replace("{titolo}", "ANNULLAMENTO ATTIVITA'<br>" . strtoupper($categoria), $full_mail_body);
$full_mail_body = str_replace("{nome}", strtoupper($docente_cognome) . " " . strtoupper($docente_nome), $full_mail_body);
$full_mail_body = str_replace("{messaggio}", "hai ricevuto questa mail perchè hai cancellato la seguente attività</p><h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($categoria) . "</center></b></h3>", $full_mail_body);
$full_mail_body = str_replace("{data}", $data, $full_mail_body);
$full_mail_body = str_replace("{ora}", $ora, $full_mail_body);
$full_mail_body = str_replace("{docente}", strtoupper($docente_cognome . " " . $docente_nome), $full_mail_body);
$full_mail_body = str_replace("{materia}", $materia, $full_mail_body);
$full_mail_body = str_replace("{aula}", $luogo, $full_mail_body);
$full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);

$full_mail_body = str_replace("{messaggio_finale}", $messaggio_finale, $full_mail_body);

if ($resultArray != null) 
{
    $full_mail_body = str_replace("{codice_html_tabella}", $data_html, $full_mail_body);
}

$to = $docente_email;
$toName = $docente_nome . " " . $docente_cognome;
info("Invio mail al docente: ".$to." ".$toName);
echo "Invio mail al docente: ".$to." ".$toName."\n";
$mailsubject = 'GestOre - Annullamento attività ' . $categoria . ' - materia' . $materia;
sendMail($to,$toName,$mailsubject,$full_mail_body);

info("inviata mail di cancellazione sportello come richiesto dal docente - " . $docente_cognome . " " . $docente_nome);

?>