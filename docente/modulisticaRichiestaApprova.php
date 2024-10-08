<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','dirigente','segreteria-docenti','segreteria-didattica');
require_once '../common/connect.php';
require_once '../common/header-common.php';

if(! isset($_GET)) {
	return;
}

// recupera la richiesta
$richiesta_id = $_GET['richiesta_id'];
$richiesta = dbGetFirst("SELECT * FROM modulistica_richiesta WHERE id = $richiesta_id;");
$approvata = $richiesta['approvata'];
$respinta = $richiesta['respinta'];
$annullata = $richiesta['annullata'];
$richiestaUuid = $richiesta['uuid'];

$template_id = $richiesta['modulistica_template_id'];
$template = dbGetFirst("SELECT * FROM modulistica_template WHERE id = $template_id;");

$docente_id = $richiesta['docente_id'];
$docente = dbGetFirst("SELECT * FROM docente WHERE id = $docente_id;");

$nomeCognomeDocente = $docente['nome'] . ' ' . $docente['cognome'];

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataInvio = utf8_encode( strftime("%d %B %Y", strtotime($richiesta['data_invio'])));
if ($richiesta['data_approvazione'] != null) {
	$dataApprovazione = utf8_encode( strftime("%d %B %Y", strtotime($richiesta['data_approvazione'])));
} else {
	$dataApprovazione = '';
}
setlocale(LC_TIME, $oldLocale);

$titolo = $template['nome'] .' - ' . $nomeCognomeDocente . ' - ' . $dataInvio;

// se e' gia' in stato di approvata, respinta o annullata non prende altri comandi
if (! $approvata && ! $respinta && ! $annullata) {
	if(isset($_GET['comando'])) {
		$comando = $_GET['comando'];
		$uuid = $_GET['uuid'];

		// controlla lo uuid
		if ($uuid != $richiestaUuid) {
			warning("uuid errato per la richiesta id=$richiesta_id richiesta=$titolo: uuid ricevuto=".$uuid);
			redirect("/error/unauthorized.php");
		}
	
		// se c'e' un comando, lo esegue controllando che ci sia il corretto uuid
		if ($comando == 'approva') {
			dbExec("UPDATE modulistica_richiesta SET `approvata` = 1 WHERE id = $richiesta_id;");
			info("approvata la richiesta id=$richiesta_id richiesta=$titolo");
			redirect('/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id);
		} else if ($comando == 'respingi') {
			// todo: chiede la ragione per inserirla nelle motivazioni
			$mesaggio = "La richiesta viene respinta";
			dbExec('UPDATE modulistica_richiesta SET `respinta` = 1, `messaggio_respinta` = "'.escapeString($mesaggio).'" WHERE id = '.$richiesta_id.';');
			info("respinta la richiesta id=$richiesta_id richiesta=$titolo messaggio=$messaggio");
			redirect('/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id);
		} else {
			warning('comando sconosciuto: comando=' . $comando . ": ignorato");
		}
	}
}

$listaEtichette = [];
$listaValori = [];
foreach(dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $template_id;") as $campo) {
    $etichetta = $campo['etichetta'];
	$listaEtichette[] = $etichetta;

	$template_campo_id = $campo['id'];
	$richiesta_campo = dbGetFirst("SELECT * FROM modulistica_richiesta_campo WHERE modulistica_richiesta_id = $richiesta_id AND modulistica_template_campo_id = $template_campo_id;");
	$listaValori[] = $richiesta_campo['valore'];
}

?>
<html>
	<head>
	<style>
	#campi {
	  font-family: Arial, Helvetica, sans-serif;
	  border-collapse: collapse;
	  width: 100%;
	}
	#campi td, #campi th {
	  border: 1px solid #ddd;
	  padding: 6px;
	}
	#campi tr:nth-child(even){background-color: #f2f2f2;}
	#campi tr:hover {background-color: #ddd;}
	#campi th {
	  padding-top: 6px;
	  padding-bottom: 6px;
	  text-align: left;
	  background-color: #04AA6D;
	  color: white;
	}
    .col1 { width: 25%; }
    .col2 { width: 75%; }

	.btn-ar {
		color: #fff;
		padding: 15px 25px;
		margin: 20px 25px 10px 25px;
		background-image: radial-gradient(93% 87% at 87% 89%, rgba(0, 0, 0, 0.23) 0%, transparent 86.18%), radial-gradient(66% 66% at 26% 20%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 69.79%, rgba(255, 255, 255, 0) 100%);
		box-shadow: inset -3px -3px 9px rgba(255, 255, 255, 0.25), inset 0px 3px 9px rgba(255, 255, 255, 0.3), inset 0px 1px 1px rgba(255, 255, 255, 0.6), inset 0px -8px 36px rgba(0, 0, 0, 0.3), inset 0px 1px 5px rgba(255, 255, 255, 0.6), 2px 19px 31px rgba(0, 0, 0, 0.2);
		border-radius: 14px;
		font-weight: bold;
		font-size: 16px;
		border: 0;
		user-select: none;
		-webkit-user-select: none;
		touch-action: manipulation;
		cursor: pointer;
	}
	.btn-approva {
		background-color: #1CAF43;
	}
	.btn-respingi {
		background-color: #F1003C;
	}

	</style>
	<title><?php echo $titolo; ?></title>
	</head>
	<body>
<?php

$data = '<h2 style="text-align: center">'.$titolo.'</h2>';

// se e' gia' stata approvata o respinta, scrive lo stato e non lascia modificare
if ($template['approva']) {
	if ($richiesta['approvata']) {
		$data .= '<h3 style="text-align: center">La richiesta &egrave; stata <b>APPROVATA</b> in data '.$dataApprovazione.'</h3>';
	} else if ($richiesta['respinta']) {
		$data .= '<h3 style="text-align: center">La richiesta &egrave; stata <b>RESPINTA</b> in data '.$dataApprovazione.'</h3>';
		$data .= "<p>con la seguente motivazione: ".$richiesta['messaggio_respinta']."</p>";
	} else if ($richiesta['annullata']) {
		$data .= '<h3 style="text-align: center">La richiesta &egrave; stata <b>ANNULLATA</b> in data '.$dataApprovazione.'</h3>';
	} else {
		$data .= '<h3 style="text-align: center">La richiesta non &egrave; ancora stata approvata</h3>';
		if ($template['approva']) {
			$data .= '<div class="form-group" style="text-align: center"><button class="btn-ar btn-approva" onclick="location.href=\'http://localhost/GestOre/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&comando=approva\'">Approva</button>
				<button class="btn-ar btn-respingi" onclick="location.href=\'http://localhost/GestOre/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&comando=respingi\'">Respingi</button></div>';
		}
	}
}

$data .= "<p>I campi del modulo sono riportati qui di seguito:</p>";

$data .= '<table id="campi"><tr><th>nome</th><th>valore</th><tr>';

for ($i = 0; $i < count($listaEtichette); $i++) {
    $campo = $listaEtichette[$i];
    $valore = escapeString($listaValori[$i]);
    $data .= '<tr><td class="col1">'.$campo.'</td><td class="col2">'.$valore.'</td><tr>';
}
$data .= '</table id="campi"></p>';

$data .= "</body></html>";

echo $data;
?>