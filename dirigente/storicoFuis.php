<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html>
<head>

<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-notify.php';
require_once '../common/__Minuti.php';
ruoloRichiesto('dirigente');

if(! isset($_GET)) {
	return;
} else {
	$anno_id = $_GET['anno_id'];
}
$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");
echo '<title>Storico FUIS ' . $nome_anno_scolastico.' - '.getSettingsValue('local','nomeIstituto', '') . '</title>';

?>

</head>

<body >

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<?php

function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatDate($value) {
	$dateFormatter = '%e %b %Y';
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
		$dateFormatter = '%#d %b %Y';
	}
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$result = utf8_encode(strftime($dateFormatter, strtotime($value)));
	setlocale(LC_TIME, $oldLocale);

    return $result;
}

// tag
$accettato = '<td class="col-md-1 text-center"><span style="color:green !important;font-weight:bold">&#10004;</span></td>';
$contestataMarker = '<span style="color:red !important;font-weight:bold">&#10008;</span>';
$accettataMarker = '<span style="color:green !important;font-weight:bold">&#10004;</span>';

// Intestazione pagina
$data = '';
$data = $data . '<h2 style="">FUIS Docenti anno scolastico '.$nome_anno_scolastico.' - '.getSettingsValue('local','nomeIstituto', '').'</h2>';

// cicla i docenti
foreach(dbGetAll("SELECT docente.id AS docente_id, docente.*, ore_dovute.* FROM docente INNER JOIN ore_dovute ON ore_dovute.docente_id=docente.id WHERE ore_dovute.anno_scolastico_id=$anno_id AND ore_dovute.ore_40_totale>0 ORDER BY docente.cognome ASC, docente.nome ASC;") as $docente) {
	$docente_id = $docente['docente_id'];
	$totaleAssegnatoDocente = 0;
	$totaleDiariaDocente = 0;
	$totaleAttivitaDocente = 0;
	$totaleClilDocente = 0;
	
	$data .= '<h2 style="page-break-before: always;text-align: center;">'.$docente['cognome'] . ' ' . $docente['nome'].'</h2>';
	$data .= '';

	// fuis assegnato
	$assegnatoList = dbGetAll("SELECT * FROM fuis_assegnato INNER JOIN fuis_assegnato_tipo ON fuis_assegnato.fuis_assegnato_tipo_id=fuis_assegnato_tipo.id WHERE fuis_assegnato.docente_id = $docente_id AND fuis_assegnato.anno_scolastico_id = $anno_id;");
	if (!empty($assegnatoList)) {
		$data .= '<h4 style="background-color: #f1e6b2 !important;">FUIS Assegnato</h4>';
		$data .= '<table class="table table-bordered table-striped table-green"><thead><tr><th class="col-sm-11">Tipo</th><th class="text-center col-sm-1">Importo</th></tr></thead><tbody>';
		foreach($assegnatoList as $assegnato) {
			$data .= '<tr><td>'.$assegnato['nome'].'</td><td class="text-right funzionale">'.$assegnato['importo'].'</td></tr>';
			$totaleAssegnatoDocente = $totaleAssegnatoDocente + $assegnato['importo'];
		}
		$data .= '</tbody><tfooter>';
		$data .='<tr><td colspan="1" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . formatNoZero($totaleAssegnatoDocente) . '</strong></td></tr>';
		$data .='</tfooter></table>';
		$data .= '<hr>';
	}

	// diarie viaggio (non le ore)
	$diariaList = dbGetAll("SELECT * FROM fuis_viaggio_diaria INNER JOIN viaggio ON fuis_viaggio_diaria.viaggio_id = viaggio.id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $anno_id ORDER BY data_partenza ASC;");
	if (!empty($diariaList)) {
		$data .= '<h4 style="background-color: #fcaebb !important;">FUIS Diaria Viaggi</h4>';
		$data .= '<table class="table table-bordered table-striped table-green"><thead><tr><th class="col-sm-6">Destinazione</th><th class="col-sm-4">Classe</th><th class="col-md-1 text-center">Data</th><th class="text-center col-sm-1">Importo</th></tr></thead><tbody>';
		foreach($diariaList as $diaria) {
			$data .= '<tr><td>'.$diaria['destinazione'].'</td><td>'.$diaria['classe'].'</td><td class="text-center">'.formatDate($diaria['data_partenza']).'</td><td class="text-right funzionale">'.$diaria['importo'].'</td></tr>';
			$totaleDiariaDocente = $totaleDiariaDocente + $diaria['importo'];
		}
		$data .= '</tbody><tfooter>';
		$data .='<tr><td colspan="3" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . formatNoZero($totaleDiariaDocente) . '</strong></td></tr>';
		$data .='</tfooter></table>';
		$data .= '<hr>';
	}

	// attivita'
	$data .= '<h4 style="background-color: #9be3bf !important;">Attività</h4>';

	// Inserite da docente
	$data .= '<h4 style="text-align: center;">Inserite da docente</h4>';
	// fuis: carica prima le ore dovute, previste e fatte
	$dovute = dbGetFirst("SELECT * FROM ore_dovute WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_id;");
	$previste = dbGetFirst("SELECT * FROM ore_previste WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_id;");
	$fatte = dbGetFirst("SELECT * FROM ore_fatte WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_id;");

	// inizializza le ore dovute
	$oreDovute = array("funzionali"=>($dovute['ore_70_funzionali']),"con studenti"=>($dovute['ore_40_con_studenti'] + $dovute['ore_70_con_studenti']),"sostituzioni"=>($dovute['ore_40_sostituzioni_di_ufficio']),"aggiornamento"=>($dovute['ore_40_aggiornamento']));

	// tabella delle ore fatte
	$oreFatte = array_fill_keys(array('funzionali', 'con studenti', 'sostituzioni', 'aggiornamento', 'clil_funzionali', 'clil_con_studenti'), 0);

	// le sostituzioni sono tutte registrate qui
	$oreFatte['sostituzioni'] = $fatte['ore_40_sostituzioni_di_ufficio'];

	// tabella attività fatte:
	$data .= '<table class="table table-bordered table-striped table-green">';
	$data .= '<thead><tr><th class="col-md-1 text-left">Tipo</th><th class="col-md-2 text-left">Nome</th><th class="col-md-6 text-left">Dettaglio</th><th class="col-md-1 text-center">Data</th><th class="col-md-1 text-center">Ore</th><th class="col-md-1 text-center">Stato</th></tr></thead><tbody>';

	$query = "SELECT ore_fatte_attivita.ore AS ore_attivita, ore_fatte_attivita.*, ore_previste_tipo_attivita.*, registro_attivita.*, ore_fatte_attivita_commento.* FROM `ore_fatte_attivita`
	INNER JOIN ore_previste_tipo_attivita ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
	LEFT JOIN registro_attivita registro_attivita ON registro_attivita.ore_fatte_attivita_id = ore_fatte_attivita.id
	LEFT JOIN ore_fatte_attivita_commento on ore_fatte_attivita_commento.ore_fatte_attivita_id = ore_fatte_attivita.id
	WHERE ore_fatte_attivita.docente_id = $docente_id AND ore_fatte_attivita.anno_scolastico_id = $anno_id
	ORDER BY ore_fatte_attivita.data DESC, ore_fatte_attivita.ora_inizio;";
	foreach(dbGetAll($query) as $attivita) {
		$data .= '<tr><td>'.$attivita['categoria'].'</td><td>'.$attivita['nome'].'</td><td>'.$attivita['dettaglio'];
		if (!empty($attivita['descrizione'])) {
			$data .='</br>'.$attivita['descrizione'].'';
		}
		if ($attivita['contestata'] == 1) {
			$data .='</br><span style="color:red !important;font-weight:bold"><strong  style="color:red !important">'.$attivita['commento'].'</strong></span>';
		}
		$data .='</td>';

		// data e ora solo per quelle inserite da docente
		$ore_con_minuti = oreToDisplay($attivita['ore_attivita']);
		if ($attivita['inserito_da_docente']) {
			$data .='<td class="text-center">'.formatDate($attivita['data']).'</td><td class="text-center">'.$ore_con_minuti.'</td>';
		} else {
			$data .='<td class="text-center">'.'</td><td class="text-center">'.$ore_con_minuti.'</td>';
		}

		// contestata?
		$marker = ($attivita['contestata'] == 1)? $contestataMarker : $accettataMarker;
		$data .= '<td class="col-md-1 text-center">'.$marker.'</td>';
		$data .='</tr>';

		// se non contestata, la aggiunge alle ore fatte
		if ($attivita['contestata'] != 1) {
			$oreFatte[$attivita['categoria']] += $attivita['ore_attivita'];
		}
	}

	$data .= '</tbody></table>';
	$data .= '<hr>';

	// attribuite
	$query = "	SELECT ore_previste_attivita.ore AS ore_attivita, ore_previste_attivita.*, ore_previste_tipo_attivita.* FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		WHERE ore_previste_attivita.anno_scolastico_id = $anno_id AND ore_previste_attivita.docente_id = $docente_id AND ore_previste_tipo_attivita.inserito_da_docente = false AND ore_previste_tipo_attivita.previsto_da_docente = false
		ORDER BY ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC";
	$attribuiteList = dbGetAll($query);
	if (!empty($attribuiteList)) {
		$data .= '<h4 style="text-align: center;">Attribuite</h4>';
		$data .= '<table class="table table-bordered table-striped table-green">';
		$data .= '<thead><tr><th class="col-md-1 text-left">Tipo</th><th class="col-md-3 text-left">Nome</th><th class="col-md-6 text-left">Dettaglio</th><th class="col-md-1 text-center">Ore</th><th class="col-md-1 text-center">Stato</th></tr></thead><tbody>';
		foreach(dbGetAll($query) as $attribuite) {
			$ore_con_minuti = oreToDisplay($attribuite['ore_attivita']);
			$data .= '<tr><td>'.$attribuite['categoria'].'</td><td>'.$attribuite['nome'].'</td><td>'.$attribuite['dettaglio'].'</td><td class="text-center">'.$attribuite['ore'].'</td>'.$accettato.'</tr>';
			// la aggiunge (attribuite non sono mai contestate)
			$oreFatte[$attribuite['categoria']] += $attribuite['ore_attivita'];
		}			
		$data .= '</tbody></table>';
	}

	// gruppi di lavoro
	$query = "SELECT * FROM gruppo_incontro_partecipazione
			INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
			INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
			WHERE gruppo_incontro_partecipazione.docente_id = $docente_id
			AND gruppo_incontro_partecipazione.ha_partecipato = true
			AND gruppo.anno_scolastico_id = $anno_id
			AND gruppo_incontro.effettuato = true
			AND gruppo.dipartimento = false";

	$gruppoList = dbGetAll($query);
	if (!empty($gruppoList)) {
		$data .= '<h4 style="text-align: center;">Gruppi</h4>';
		$data .= '<table class="table table-bordered table-striped table-green">';
		$data .= '<thead><tr><th class="col-md-3 text-left">Gruppo</th><th class="col-md-6 text-left">Ordine del Giorno</th><th class="col-md-1 text-center">Data</th><th class="col-md-1 text-center">Ore</th><th class="col-md-1 text-center">Stato</th></tr></thead><tbody>';
		foreach(dbGetAll($query) as $gruppo) {
			$data .= '<tr><td>'.$gruppo['nome'].'</td><td>'.$gruppo['ordine_del_giorno'].'</td><td class="col-md-1 text-center">'.formatDate($gruppo['data']).'</td><td class="col-md-1 text-center">'.$gruppo['ore'].'</td>'.$accettato.'</tr>';
			// i gruppi di lavoro sono sempre ore funzionali
			$oreFatte['funzionali'] += $gruppo['ore'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
	}
	
	// infine le ore dei viaggi (che vanno con gli studenti)
	$viaggioList = dbGetAll("SELECT * FROM viaggio_ore_recuperate INNER JOIN viaggio ON viaggio_ore_recuperate.viaggio_id = viaggio.id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $anno_id ORDER BY data_partenza ASC;");
	if (!empty($viaggioList)) {
		$data .= '<h4 style="text-align: center;">Viaggi: ore recuperate</h4>';
		$data .= '<table class="table table-bordered table-striped table-green">';
		$data .= '<thead><tr><th class="col-sm-6">Viaggio: destinazione</th><th class="col-sm-3">Classe</th><th class="text-center col-sm-1">Data</th><th class="text-center col-sm-1">Ore</th><th class="col-md-1 text-center">Stato</th></tr></thead><tbody>';
		foreach($viaggioList as $viaggio) {
			$data .= '<tr><td>'.$viaggio['destinazione'].'</td><td>'.$viaggio['classe'].'</td><td class="col-md-1 text-center">'.formatDate($viaggio['data_partenza']).'</td><td class="col-md-1 text-center">'.$viaggio['ore'].'</td>'.$accettato.'</tr>';
			// viaggi sono sempre con studenti
			$oreFatte['con studenti'] += $viaggio['ore'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
	}

	// calcoli dei totali ore:
	$ore_sostituzioni = $oreFatte['sostituzioni'] - $oreDovute['sostituzioni'];
	// ma se configurato per non sottrarre le sostituzioni, ignora questa parte se sono dovute dal docente (mette a 0)
	if (! getSettingsValue('fuis','rimuovi_sostituzioni_non_fatte', true)) {
		if ($ore_sostituzioni < 0) {
			$ore_sostituzioni = 0;
		}
	}
	$ore_funzionali = $oreFatte['funzionali'] - $oreDovute['funzionali'];
	$ore_con_studenti = $oreFatte['con studenti'] - $oreDovute['con studenti'];

	// se si possono compensare in ore quelle mancanti funzionali con quelle fatte in piu' con studenti lo aggiorna ora
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
		if ($ore_funzionali < 0) {
			$daSpostare = -$ore_funzionali;
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if ($ore_con_studenti < $daSpostare) {
				$daSpostare = $ore_con_studenti;
			}
			$ore_con_studenti = $ore_con_studenti - $daSpostare;
			$ore_funzionali = $ore_funzionali + $daSpostare;
		}
	}

	// NB: non deve accadere che manchino delle ore con studenti: in quel caso il DS assegnerebbe altre attivita' o Disposizioni
	//     In caso siano rimaste in negativo ore con studenti la cosa viene qui ignorata, visto che in ogni caso il fuis non puo' diventare negativo
	$fuis_funzionale_importo = $ore_funzionali * $__settings->importi->oreFunzionali;
	$fuis_con_studenti_importo = $ore_con_studenti * $__settings->importi->oreConStudenti;
	$fuis_sostituzioni_importo = $ore_sostituzioni * $__settings->importi->oreConStudenti;

	// se non configurato per compensare, i valori negativi devono essere azzerati (se ce ne sono...)
	if (!getSettingsValue('fuis','compensa_in_valore', false)) {
		$fuis_funzionale_importo = max($fuis_funzionale_importo, 0);
		$fuis_con_studenti_importo = max($fuis_con_studenti_importo, 0);
		$fuis_sostituzioni_importo = max($fuis_sostituzioni_importo, 0);
	}

	// totale per fuis attivita'
	$totale_importo = $fuis_sostituzioni_importo + $fuis_funzionale_importo + $fuis_con_studenti_importo;

	// ma nessuno deve dare soldi indietro alla scuola
	if ($totale_importo < 0) {
	    $totale_importo = 0;
	}

	// scrive le ore come sono prima di calcolare l'importo
	$data .= '<h4 style="text-align: center;">Totale Ore attività</h4>';
	$data .= '<table class="table table-bordered table-striped table-green">';
	$data .= '<thead><tr><th class="col-md-2 text-left">Tipo</th><th class="col-md-3 text-center">Dovute</th><th class="col-md-3 text-center">Fatte</th><th class="col-md-3 text-center">Bilancio</th><th class="col-md-1 text-center">Importo</th></tr></thead><tbody>';
	$data .= '<tr><td class="col-md-2 text-left">sostituzioni</td><td class="col-md-3 text-center">'.$oreDovute['sostituzioni'] . '</td><td class="col-md-3 text-center">' . $oreFatte['sostituzioni'] . '</td><td class="col-md-3 text-center">' . $ore_sostituzioni . '</td><td class="col-md-1 text-right">' . number_format($fuis_sostituzioni_importo,2) . '</td></tr>';
	$data .= '<tr><td class="col-md-2 text-left">funzionali</td><td class="col-md-3 text-center">'.$oreDovute['funzionali'] . '</td><td class="col-md-3 text-center">' . $oreFatte['funzionali'] . '</td><td class="col-md-3 text-center">' . $ore_funzionali . '</td><td class="col-md-1 text-right">' . number_format($fuis_funzionale_importo,2) . '</td></tr>';
	$data .= '<tr><td class="col-md-2 text-left">con studenti</td><td class="col-md-3 text-center">'.$oreDovute['con studenti'] . '</td><td class="col-md-3 text-center">' . $oreFatte['con studenti'] . '</td><td class="col-md-3 text-center">' . $ore_con_studenti . '</td><td class="col-md-1 text-right">' . number_format($fuis_con_studenti_importo,2) . '</td></tr>';
	$data .= '</tbody><tfooter>';
	$data .='<tr><td colspan="4" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . number_format($totale_importo,2) . '</strong></td></tr>';
	$data .='</tfooter></table>';
	$data .= '<hr>';

	// CLIL attivita'
	$query = "SELECT * FROM ore_fatte_attivita_clil
		LEFT JOIN registro_attivita_clil registro_attivita_clil ON registro_attivita_clil.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
		LEFT JOIN ore_fatte_attivita_clil_commento ON ore_fatte_attivita_clil_commento.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
		WHERE ore_fatte_attivita_clil.anno_scolastico_id = $anno_id AND ore_fatte_attivita_clil.docente_id = $docente_id ORDER BY ore_fatte_attivita_clil.data DESC, ore_fatte_attivita_clil.ora_inizio;";

	$clilList = dbGetAll($query);
	if (!empty($clilList)) {
		$data .= '<h4 style="background-color: #8bd3e6 !important;">CLIL</h4>';
		$data .= '<h4 style="text-align: center;">Ore CLIL Richieste</h4>';
		$data .= '<table class="table table-bordered table-striped table-green"><thead><tr><th class="col-sm-1">Tipo</th><th class="col-sm-6">Dettaglio</th><th class="text-center col-sm-1">Data</th><th class="text-center col-sm-1">Ore</th><th class="text-center col-sm-1">Stato</th></tr></thead><tbody>';
		foreach($clilList as $clil) {
			$categoria = ($clil['con_studenti'])? 'con studenti' : 'funzionali';
			$ore_con_minuti = oreToDisplay($clil['ore']);

			$data .= '<tr><td>'.$categoria.'</td><td><strong>Dettaglio: </strong>'.$clil['dettaglio'];
			$data .='</br><strong>Registro: </strong>'.$clil['descrizione'].'';
			if ($clil['contestata'] == 1) {
				$data .='</br><span style="color:red !important;font-weight:bold"><strong  style="color:red !important">'.$clil['commento'].'</strong></span>';
			}
			$data .= '</br>'.$clil['ore_fatte_attivita_clil_id'];
			$data .= '</td>';
	
			// data e ora solo per quelle inserite da docente
			$ore_con_minuti = oreToDisplay($clil['ore']);
			$data .='<td class="text-center">'.($clil['data']).'</td><td class="text-center">'.$ore_con_minuti.'</td>';
	
			// contestata?
			$marker = ($clil['contestata'] == 1)? $contestataMarker : $accettataMarker;
			$data .= '<td class="col-md-1 text-center">'.$marker.'</td>';
			$data .='</tr>';
	
			// se non contestata, la aggiunge alle ore fatte
			if ($clil['contestata'] != 1) {
				$oreFatte[($clil['con_studenti'])? 'clil_con_studenti' : 'clil_funzionali'] += $clil['ore'];
			}
		}
		$data .= '</tbody></table>';

		$fuis_clil_funzionale_importo = $oreFatte['clil_funzionali'] * $__settings->importi->oreFunzionali;
		$fuis_clil_con_studenti_importo = $oreFatte['clil_con_studenti'] * $__settings->importi->oreConStudenti;
		$fuis_clil_totale = $fuis_clil_funzionale_importo + $fuis_clil_con_studenti_importo;
	

		// scrive le ore come sono prima di calcolare l'importo
		$data .= '<h4 style="text-align: center;">Totale Ore CLIL</h4>';
		$data .= '<table class="table table-bordered table-striped table-green">';
		$data .= '<thead><tr><th class="col-md-8 text-left">Tipo</th><th class="col-md-3 text-center">Ore</th><th class="col-md-1 text-center">Importo</th></tr></thead><tbody>';
		$data .= '<tr><td class="col-md-8 text-left">clil funzionali</td><td class="col-md-3 text-center">' . $oreFatte['clil_funzionali'] . '</td><td class="col-md-1 text-right">' . number_format($fuis_clil_funzionale_importo,2) . '</td></tr>';
		$data .= '<tr><td class="col-md-8 text-left">clil con studenti</td><td class="col-md-3 text-center">'.$oreFatte['clil_con_studenti'] . '</td><td class="col-md-1 text-right">' . number_format($fuis_clil_con_studenti_importo,2) . '</td></tr>';
		$data .= '</tbody><tfooter>';
		$data .='<tr><td colspan="2" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . number_format($fuis_clil_totale,2) . '</strong></td></tr>';
		$data .='</tfooter></table>';
		$data .= '<hr>';
	}
}

echo $data;
?>

</body>
</html>
