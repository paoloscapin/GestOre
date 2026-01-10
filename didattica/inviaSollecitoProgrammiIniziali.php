<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('studente', 'docente', 'dirigente', 'segreteria-docenti');

require_once '../common/vendor/autoload.php';
require_once '../common/send-mail.php';

$programId = isset($_POST['id']) ? (int)$_POST['id'] : -1;

if ($programId <= 0) {
    debug('invalid program id');
    exit;
}

// 1) Recupero dati programma iniziale + email docente
$query = "SELECT
            programmi_iniziali.id AS programma_id,
            programmi_iniziali.id_classe AS classe_id,
            programmi_iniziali.id_docente AS docente_id,
            programmi_iniziali.id_materia AS materia_id,
            programmi_iniziali.id_anno_scolastico AS anno_scolastico_id,
            programmi_iniziali.id_utente AS utente_id,
            programmi_iniziali.updated AS ultimo_agg,

            classi.classe AS classe_nome,
            materia.nome AS materia_nome,

            docente.nome AS docente_nome,
            docente.cognome AS docente_cognome,
            docente.email AS docente_email,

            utente.nome AS utente_nome,
            utente.cognome AS utente_cognome
        FROM programmi_iniziali
        INNER JOIN classi  ON programmi_iniziali.id_classe  = classi.id
        INNER JOIN materia ON programmi_iniziali.id_materia = materia.id
        INNER JOIN docente ON programmi_iniziali.id_docente = docente.id
        INNER JOIN utente  ON programmi_iniziali.id_utente  = utente.id
        WHERE programmi_iniziali.id_anno_scolastico = " . (int)$__anno_scolastico_corrente_id . "
          AND programmi_iniziali.id = " . (int)$programId;

$program = dbGetFirst($query);

if ($program === null) {
    debug('programma non presente');
    exit;
}

// 2) Verifica “programma vuoto”: nessun modulo
$check = "SELECT COUNT(*) FROM programmi_iniziali_moduli WHERE id_programma = " . (int)$programId;
$nModuli = (int)dbGetValue($check);

// Se vuoi inviare sollecito SOLO se è vuoto:
if ($nModuli > 0) {
    debug('sollecito non inviato perchè ci sono dei moduli presenti');
    exit;
}

// 3) Composizione mail
$materia_nome = $program['materia_nome'];
$docente = $program['docente_cognome'] . " " . $program['docente_nome'];
$classe = $program['classe_nome'];
$email_docente = $program['docente_email'];

if (!$email_docente) {
    debug('mail docente non presente');
    exit;
}

$templatePath = "../didattica/template_mail_sollecito.html";
$full_mail_body = file_get_contents($templatePath);

$full_mail_body = str_replace("{titolo}", "PROGRAMMI INIZIALI", $full_mail_body);
$full_mail_body = str_replace("{nome}", strtoupper($docente), $full_mail_body);
$full_mail_body = str_replace("{messaggio}", "hai ricevuto questa mail perchè hai inserito un programma iniziale ma risulta ancora vuoto", $full_mail_body);
$full_mail_body = str_replace("{classe}", $classe, $full_mail_body);
$full_mail_body = str_replace("{docente}", strtoupper($docente), $full_mail_body);
$full_mail_body = str_replace("{materia}", $materia_nome, $full_mail_body);
$full_mail_body = str_replace("{messaggio_finale}", "Si richiede di caricare il programma iniziale il prima possibile!", $full_mail_body);
$full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);

$to = $email_docente;
$toName = $docente;
$mailsubject = 'GestOre - Invio sollecito programmi iniziali - materia ' . $materia_nome;

// Allegati: nessuno
$filename = null;

info("Invio sollecito programma iniziale via mail al docente: " . $to . " " . $toName);
sendMail($to, $toName, $mailsubject, $full_mail_body, $filename);

echo 'sent';
