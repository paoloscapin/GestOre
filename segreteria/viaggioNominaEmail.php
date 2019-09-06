<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
$viaggio_id = $_POST["viaggio_id"];
$query = "
			SELECT
				viaggio.id AS viaggio_id,
				viaggio.protocollo AS viaggio_protocollo,
				viaggio.tipo_viaggio AS viaggio_tipo_viaggio,
				viaggio.data_nomina AS viaggio_data_nomina,
				viaggio.data_partenza AS viaggio_data_partenza,
				viaggio.data_rientro AS viaggio_data_rientro,
				viaggio.ora_partenza AS viaggio_ora_partenza,
				viaggio.ora_rientro AS viaggio_ora_rientro,
				viaggio.destinazione AS viaggio_destinazione,
				viaggio.classe AS viaggio_classe,
				viaggio.stato AS viaggio_stato,
				docente.email AS docente_email,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome
			FROM viaggio viaggio
			INNER JOIN docente docente
			ON viaggio.docente_id = docente.id
			WHERE viaggio.id = '$viaggio_id'";

if (!$result = mysqli_query($con, $query)) {
	exit(mysqli_error($con));
}

$response = array();
$row = array();
if(mysqli_num_rows($result) > 0) {
	$row = mysqli_fetch_assoc($result);
}
else {
	$response['status'] = 200;
	$response['message'] = "Data not found!";
}

$tipoViaggio = "";
if ($row['viaggio_tipo_viaggio'] === 'Uscita Formativa') {
	$tipoViaggio = "l'uscita formativa";
} else if ($row['viaggio_tipo_viaggio'] === 'Visita Guidata') {
	$tipoViaggio = "la visita guidata";
} else if ($row['viaggio_tipo_viaggio'] === 'Viaggio di Istruzione') {
	$tipoViaggio = "il viaggio d'istruzione";
}

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataNomina = utf8_encode( strftime("%d %B %Y", strtotime($row['viaggio_data_nomina'])));
$dataPartenza = utf8_encode( strftime("%d %B %Y", strtotime($row['viaggio_data_partenza'])));
$dataRientro = utf8_encode( strftime("%d %B %Y", strtotime($row['viaggio_data_rientro'])));
setlocale(LC_TIME, $oldLocale);

$to = $row['docente_email'];
$subject = 'Incarico '.$row['viaggio_tipo_viaggio'].' a '.$row['viaggio_destinazione'].' del '.$dataPartenza;
$sender = "noreply-gestionale@martinomartini.eu";

$headers = "From: $sender\n";
$headers .= "MIME-Version: 1.0\n";
$headers .= "Content-Type: text/html; charset=\"UTF-8\"\n";
$headers .= "Content-Transfer-Encoding: 8bit\n";
$headers .= "X-Mailer: PHP " . phpversion();

// Corpi del messaggio nei due formati testo e HTML
$text_msg = "Incarico";

$connection = 'http';
if ($__settings->system->https) {
    $connection = 'https';
}
$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/docente/viaggio.php';
$html_msg = '
<html><body>
Gentile '.$row['docente_nome'].' '.$row['docente_cognome'].'
<p>in data '.$dataNomina.' il Dirigente Scolastico le ha conferito l&rsquo;incarico di accompagnatore degli studenti durante '.$tipoViaggio.' a <b>'.$row['viaggio_destinazione'].'</b> del giorno <b>'.$dataPartenza.'</b></p>
<p>La preghiamo di confermare al pi&ugrave; presto la sua disponibilit&agrave; confermando sul sito di
<a href=\''.$url.'\'>accettare l&rsquo;incarico</a></p>
<p>Segreteria Marconi</p>
</body></html>
';

// Imposta il Return-Path (funziona solo su hosting Windows)
ini_set("sendmail_from", $sender);

// Invia il messaggio, il quinto parametro "-f$sender" imposta il Return-Path su hosting Linux
if (mail($to, $subject, $html_msg, $headers, "-f$sender")) {
	echo "email inviata correttamente a ".$row['docente_email'];
} else {
	echo "errore nell'invio della email";
}
?>