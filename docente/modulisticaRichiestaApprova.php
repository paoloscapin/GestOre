<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../common/PHPMailer/PHPMailer.php';
require_once '../common/PHPMailer/Exception.php';

require_once '../common/checkSession.php';
// ruoloRichiesto('docente','dirigente','segreteria-docenti','segreteria-didattica');
require_once '../common/connect.php';
require_once '../common/header-common.php';
require_once '../docente/modulisticaProduciTabella.php';

function notifica($to, $toName, $titolo, $stato, $messaggio) {
	global $nomeCognomeDocente, $template;

	$mail = new PHPMailer(true);
	$mail->setFrom(getSettingsValue('local', 'emailNoReplyFrom', ''), 'no replay');
	$mail->addAddress($to, $toName);

	// subject
	$mail->Subject = $stato . $titolo;
	$mail->isHTML(TRUE);

	$mail->Body = '<html><body>';
	$mail->Body .= "<p>Il docente ".$nomeCognomeDocente." ha inviato il modulo ".$template['nome'];

	$mail->Body .= '<p>La richiesta '.$titolo.' &egrave; stata '.$stato.' in data '.date("d/m/Y").'</p>';
// nota: il messaggio in questo momento e' ancora vuoto perche' viene inserito in seguito, per cui lo lasciamo perdere per il momento
//	$mail->Body .= produciTabella();

	$mail->Body .= "</body></html>";
	$mail->AltBody = 'Richiesta '.$titolo.': '.$stato.' in data '.date("d/m/Y");

	// send the message
	if(!$mail->send()){
		warning('La notifica non ha funzionato: toName='.$toName.'to='.$to.' Mailer Error='. $mail->ErrorInfo);
	}
}

if(! isset($_GET)) {
	return;
}

// recupera la richiesta e lo uuid (necessario un secondo controllo perche' non ci sono limitazioni di ruolo o controlli di accesso alla pagina)
$richiesta_id = $_GET['richiesta_id'];
$uuid = $_GET['uuid'];
$richiesta = dbGetFirst("SELECT * FROM modulistica_richiesta WHERE id = $richiesta_id;");
$richiestaUuid = $richiesta['uuid'];
// controlla lo uuid
if ($uuid != $richiestaUuid) {
	warning("uuid errato per la richiesta id=$richiesta_id: uuid ricevuto=".$uuid);
	redirect("/error/unauthorized.php");
}

$approvata = $richiesta['approvata'];
$respinta = $richiesta['respinta'];
$annullata = $richiesta['annullata'];

$template_id = $richiesta['modulistica_template_id'];
$template = dbGetFirst("SELECT * FROM modulistica_template WHERE id = $template_id;");

$docente_id = $richiesta['docente_id'];
$docente = dbGetFirst("SELECT * FROM docente WHERE id = $docente_id;");

$nomeCognomeDocente = $docente['nome'] . ' ' . $docente['cognome'];
$emailDocente = $docente['email'];

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataInvio = utf8_encode( strftime("%d %B %Y", strtotime($richiesta['data_invio'])));
if ($richiesta['data_approvazione'] != null) {
	$dataApprovazione = utf8_encode( strftime("%d %B %Y", strtotime($richiesta['data_approvazione'])));
} else {
	$dataApprovazione = '';
}
setlocale(LC_TIME, $oldLocale);

$titolo = 'M-' . $richiesta_id . ' ' . $template['nome'] .' - ' . $nomeCognomeDocente;

// controlla se e' stata richiesta l'esecuzione di un comando
if(isset($_GET['comando'])) {
	$comando = $_GET['comando'];

	// controlla se eì stato passato un messaggio
	if(isset($_GET['messaggio'])) {
		$messaggio = $_GET['messaggio'];
	} else {
		$messaggio = "";
	}	

	// se va chiusa si chiude e basta
	if ($comando == 'chiudi') {
		dbExec('UPDATE modulistica_richiesta SET `chiusa` = 1, data_chiusura=now() WHERE id = '.$richiesta_id.';');
		info("chiusa la richiesta id=$richiesta_id richiesta=$titolo");
	}

	// se e' gia' in stato di approvata, respinta o annullata non prende altri comandi a parte chiudi
	else if (! $approvata && ! $respinta && ! $annullata) {
		if ($comando == 'approva') {
			dbExec('UPDATE modulistica_richiesta SET `approvata` = 1, `messaggio` = "'.escapeString($messaggio).'", data_approvazione=now() WHERE id = '.$richiesta_id.';');
			info("approvata la richiesta id=$richiesta_id richiesta=$titolo messaggio=$messaggio");
			notifica($emailDocente, $nomeCognomeDocente, $titolo, ' Approvata', $messaggio);
			notifica($template['email_to'], $template['email_to'], $titolo, ' Approvata', $messaggio);
		} else if ($comando == 'respingi') {
			dbExec('UPDATE modulistica_richiesta SET `respinta` = 1, `messaggio` = "'.escapeString($messaggio).'", data_approvazione=now() WHERE id = '.$richiesta_id.';');
			info("respinta la richiesta id=$richiesta_id richiesta=$titolo messaggio=$messaggio");
			notifica($emailDocente, $nomeCognomeDocente, $titolo, 'Respinta', $messaggio);
			notifica($template['email_to'], $template['email_to'], $titolo, 'Respinta', $messaggio);
		} else if ($comando == 'annulla') {
			dbExec('UPDATE modulistica_richiesta SET `annullata` = 1, `messaggio` = "'.escapeString($messaggio).'", data_approvazione=now() WHERE id = '.$richiesta_id.';');
			info("annullata la richiesta id=$richiesta_id richiesta=$titolo messaggio=$messaggio");
			notifica($emailDocente, $nomeCognomeDocente, $titolo, 'Annullata', $messaggio);
			notifica($template['email_to'], $template['email_to'], $titolo, 'Annullata', $messaggio);
		} else {
			warning('comando sconosciuto: comando=' . $comando . ": ignorato");
		}

		// eseguito il comando (o no) fa il redirect
		redirect('/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&uuid='.$uuid);
	}
}

// se arriva qui non ha eseguito comandi per cui può visualizzare lo stato della richiesta
$listaEtichette = [];
$listaTipi = [];
$listaValoriSelezionabili = [];
$listaValori = [];
foreach(dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $template_id ORDER BY posizione;") as $campo) {
    $etichetta = $campo['etichetta'];
	$listaEtichette[] = $etichetta;
	$listaValoriSelezionabili[] = $campo['lista_valori'];
	$listaTipi[] = $campo['tipo'];

	$template_campo_id = $campo['id'];
	$richiesta_campo = dbGetFirst("SELECT * FROM modulistica_richiesta_campo WHERE modulistica_richiesta_id = $richiesta_id AND modulistica_template_campo_id = $template_campo_id;");
	$listaValori[] = $richiesta_campo['valore'];
}
?>
<html><head><style>
	#campi { font-family: Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%; }
	#campi td, #campi th { border: 1px solid #ddd; padding: 6px; }
	#campi tr:nth-child(even) { background-color: #f2f2f2; }
	#campi tr:hover { background-color: #ddd; }
	#campi th { padding-top: 6px; padding-bottom: 6px; text-align: left; background-color: #04AA6D; color: white; }
    .col1 { width: 25%; }
    .col2 { width: 75%; }
	.tick { margin-left: 0.65cm; text-indent: -0.65cm; }
	.btn-ar { color: #fff; padding: 10px 15px; margin: 20px 15px 10px 15px;
		background-image: radial-gradient(93% 87% at 87% 89%, rgba(0, 0, 0, 0.23) 0%, transparent 86.18%), radial-gradient(66% 66% at 26% 20%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 69.79%, rgba(255, 255, 255, 0) 100%);
		box-shadow: inset -3px -3px 9px rgba(255, 255, 255, 0.25), inset 0px 3px 9px rgba(255, 255, 255, 0.3), inset 0px 1px 1px rgba(255, 255, 255, 0.6), inset 0px -8px 36px rgba(0, 0, 0, 0.3), inset 0px 1px 5px rgba(255, 255, 255, 0.6), 2px 19px 31px rgba(0, 0, 0, 0.2);
		border-radius: 12px; font-weight: bold; font-size: 14px; border: 0; user-select: none; -webkit-user-select: none; touch-action: manipulation; cursor: pointer; }
		.btn-approva { background-color: #1CAF43; }
		.btn-respingi { background-color: #F1003C; }
		.btn-waiting { background-color: #f2e829; }
		.btn-chiudi { background-color: #0ae4f0; }
		.btn-pendente { background-color: #777777; }
		.btn-label { padding: 5px 12px; border-radius: 8px; margin: 10px 5px; }
	</style>
	<title><?php echo $titolo; ?></title>
	</head><body>
<?php

$chekboxChecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;" checked></b> ';
$chekboxUnchecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;"></b> ';
$radioChecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;" checked></b> ';
$radioUnchecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;"></b> ';

$data = '<h2 style="text-align: center">'.$titolo.'</h2>';

// controlla se e' respinta o annullata e in quel caso richiede che ci sia un messaggio se non e' gia' presente
if ($template['approva'] && ($richiesta['respinta'] || $richiesta['annullata']) && strlen($richiesta['messaggio']) == 0) {
	echo('<script type="text/javascript" src="../common/jquery-3.3.1-dist/jquery-3.3.1.min.js"></script>');
	echo('<script type="text/javascript" src="../common/bootbox-4.4.0/js/bootbox.min.js"></script>');
	echo('<script>');
	echo('messaggio = prompt("Inserisci la motivazione");');
	echo('$.post("../common/recordUpdate.php", {table: "modulistica_richiesta", id: '.$richiesta_id.', nome: "messaggio", valore: messaggio}, function (data, status) {location.reload();});');
	echo('</script>');
	echo("</body></html>");
	return;
}

// se e' gia' stata approvata o respinta, scrive lo stato e non lascia modificare
if ($template['approva']) {
	if ($richiesta['approvata']) {
		$data .= '<h3 style="text-align: center">La richiesta &egrave; stata <span class="btn-ar btn-approva btn-label">APPROVATA</span> in data '.$dataApprovazione.'</h3>';
	} else if ($richiesta['respinta']) {
		$data .= '<h3 style="text-align: center">La richiesta &egrave; stata <span class="btn-ar btn-respingi btn-label">RESPINTA</span> in data '.$dataApprovazione.'</h3>';
		$data .= '<h3 style="text-align: center">Motivazione: '.$richiesta['messaggio'].'</h3>';
	} else if ($richiesta['annullata']) {
		$data .= '<h3 style="text-align: center">La richiesta &egrave; stata <span class="btn-ar btn-respingi btn-label">ANNULLATA</span> in data '.$dataApprovazione.'</h3>';
		$data .= '<h3 style="text-align: center">Motivazione: '.$richiesta['messaggio'].'</h3>';
	} else {
		$data .= '<h3 style="text-align: center">La richiesta non &egrave; ancora stata approvata</h3>';
		if ($template['approva']) {
			$data .= '<div class="form-group" style="text-align: center"><button class="btn-ar btn-approva" onclick="location.href=\''.$__http_base_link.'/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&uuid='.$uuid.'&comando=approva\'">Approva</button>
				<button class="btn-ar btn-respingi" onclick="location.href=\''.$__http_base_link.'/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&uuid='.$uuid.'&comando=respingi\'">Respingi</button></div>';
		}
	}
}

$data .= "<p>I campi del modulo sono riportati qui di seguito:</p>";

$data .= produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili);

$data .= "</body></html>";

echo $data;
?>