<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function nomeOra($ora) {
	if ($ora == 1) {
		return "prima ora (8:00 - 8:50)";
	} else if ($ora == 2) {
		return "seconda ora (8:50 - 9:40)";
	} else if ($ora == 3) {
		return "terza ora (9:40 - 10:30)";
	} else if ($ora == 4) {
		return "quarta ora (10:40 - 11:30)";
	} else if ($ora == 5) {
		return "quinta ora (11:30 - 12:20)";
	} else if ($ora == 6) {
		return "sesta ora (12:20 - 13:10)";
	} else if ($ora == 7) {
		return "settima ora (13:10 - 14:00)";
	} else if ($ora == 8) {
		return "ottava ora (14:00 - 14:50)";
	} else if ($ora == 9) {
		return "nona ora (14:50 - 15:40)";
	} else if ($ora == 10) {
		return "decima ora (15:40 - 16:30)";
	}
}

function dataFormattata($dataRichiesta) {
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$formattata = utf8_encode( strftime("%d %B %Y", strtotime($dataRichiesta)));
	setlocale(LC_TIME, $oldLocale);
    return $formattata;
}

function giornoSettimana($dataRichiesta) {
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$giorno = utf8_encode( strftime("%A", strtotime($dataRichiesta)));
	setlocale(LC_TIME, $oldLocale);
    return $giorno;
}

function giornoEData($dataRichiesta) {
    return giornoSettimana($dataRichiesta) . ', ' . dataFormattata($dataRichiesta);
}

function produciTabella() {
	return '';
}

function getStatoStyle() {
	$head='<style>
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
		.btn-waiting { background-color: #fc8d0f; }
		.btn-chiudi { background-color: #0abaf0; }
		.btn-pendente { background-color: #777777; }
		.btn-label { padding: 5px 12px; border-radius: 8px; margin: 10px 5px; }
		</style>';
	return $head;
}

function getEmailHead() {
	$head='<head>'.getStatoStyle().'</head>';
	return $head;
}

function getStatoMarker($stato) {
	$statoMarker = '';
	if ($stato == 'in attesa') {
		$statoMarker .= '<span class="label btn-pendente">in attesa</span>';
	} else if ($stato == 'approvata') {
        $statoMarker .= '<span class="label btn-approva">approvata</span>';
	} else if ($stato == 'annullata') {
        $statoMarker .= '<span class="label btn-respingi">annullata</span>';
	} else if ($stato == 'verbalizzata') {
        $statoMarker .= '<span class="label btn-waiting">verbalizzata</span>';
	} else if ($stato == 'chiusa') {
        $statoMarker .= '<span class="label btn-chiudi">chiusa</span>';
	}
	return $statoMarker;
}

?>
