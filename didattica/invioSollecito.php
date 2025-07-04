<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */


require_once '../common/checkSession.php';
ruoloRichiesto('studente', 'docente', 'dirigente', 'segreteria-docenti');
// program.php (in testa al file, prima di qualsiasi uso di mPDF)
require_once '../common/vendor/autoload.php';
require_once '../common/send-mail.php';
// 1) PARAMETRI POST
$programId = isset($_POST['id']) ? (int) $_POST['id'] : -1;

// 2) RECUPERO DATI PROGRAMMA
$query = "	SELECT
			    programmi_svolti.id AS programma_id,
				programmi_svolti.id_classe AS classe_id,
				programmi_svolti.id_docente AS docente_id,
				programmi_svolti.id_materia AS materia_id,
				programmi_svolti.id_anno_scolastico AS anno_scolastico_id,
				programmi_svolti.id_utente AS utente_id,
				programmi_svolti.updated AS ultimo_agg,
                classi.id,
                classi.classe AS classe_nome,
                materia.id,
                materia.nome AS materia_nome,
				docente.id,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome,
        docente.email AS docente_email,
				utente.id,
				utente.nome AS utente_nome,
				utente.cognome AS utente_cognome
			FROM programmi_svolti
			INNER JOIN classi classi
			ON programmi_svolti.id_classe = classi.id
			INNER JOIN materia materia
			ON programmi_svolti.id_materia = materia.id
			INNER JOIN docente docente
			ON programmi_svolti.id_docente = docente.id
			INNER JOIN utente utente
			ON programmi_svolti.id_utente = utente.id
			WHERE programmi_svolti.id_anno_scolastico=" . $__anno_scolastico_corrente_id . " AND programmi_svolti.id = " . $programId;

$program = dbGetFirst($query);

if ($program != null)
{
// 3) RECUPERO DATI
$materia_nome = $program['materia_nome'];
$docente = $program['docente_cognome'] . " " . $program['docente_nome'];
$classe = $program['classe_nome'];
$email_docente = $program['docente_email'];

    $full_mail_body = file_get_contents("../didattica/template_mail_sollecito.html");

    $full_mail_body = str_replace("{titolo}","PROGRAMMI SVOLTI",$full_mail_body);
    $full_mail_body = str_replace("{nome}",strtoupper($docente),$full_mail_body);
    $full_mail_body = str_replace("{messaggio}","hai ricevuto questa mail perchè hai inserito un programma svolto ma risulta ancora vuoto",$full_mail_body);
    $full_mail_body = str_replace("{classe}",$classe,$full_mail_body);
    $full_mail_body = str_replace("{docente}",strtoupper($docente),$full_mail_body);
    $full_mail_body = str_replace("{materia}",$materia_nome,$full_mail_body);
    $full_mail_body = str_replace("{messaggio_finale}",'Si richiede di caricare il programma svolto il prima possibile!',$full_mail_body);
    $full_mail_body = str_replace("{nome_istituto}",$__settings->local->nomeIstituto,$full_mail_body);
  
    $to = $email_docente;
    $toName = $docente;
		info("Invio sollecito programma svolto via mail al docente: ".$to." ".$toName);
    $mailsubject = 'GestOre - Invio sollecito programmi svolti - materia '. $materia_nome;
    sendMail($to,$toName,$mailsubject,$full_mail_body,$filename);
   	echo 'sent';
    }
?>