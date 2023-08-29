<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$pagina = '';

require_once '../common/checkSession.php';
// require_once '../common/__Minuti.php';
require_once '../common/importi_load.php';
ruoloRichiesto('dirigente');
require_once '../common/dompdf/autoload.inc.php';

// controlla se deve gestire i minuti
 $__minuti = getSettingsValue('config','minuti', false);

 // include le functions di utilita'
 require_once '../common/__MinutiFunction.php';

use Dompdf\Dompdf;

if(! isset($_GET)) {
	return;
} else {
	$anno_id = $_GET['anno_id'];
	// controlla se e' richiesta la stampa
	if(isset($_GET['print'])) {
		$print = true;
	} else {
		$print = false;
	}
}

function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatNoZeroNoDecimal($value) {
    return ($value != 0) ? number_format($value,0) : ' ';
}

function formatDate($value) {

	$result = strftime("%d/%m/%Y", strtotime($value));
    return $result;
}

// il processo potrebbe esssere molto lungo, specialmente in fase di stampa
set_time_limit(0);

// tag
$accettato = '<td class="col-sd-1 text-center"><span style="color:green !important;font-weight:bold">&#10004;</span></td>';
$contestataMarker = '<span style="color:red !important;font-weight:bold">&#10008;</span>';
$accettataMarker = '<span style="color:green !important;font-weight:bold">&#10004;</span>';
$accettataMarker = '<span style="color:green !important;font-weight:bold">&#10004;</span>';

// Intestazione pagina
$dataContenuto = '';
$dataCopertina = '';
$dataConsuntivo = '';

// azzera i totali di istituto
$totaleAssegnatoIstuto = 0;
$totaleDiariaIstuto = 0;
$totaleAttivitaIstuto = 0;
$totaleClilIstuto = 0;
$totaleCorsiDiRecuperoExtraIstituto = 0;

// cicla i docenti
foreach(dbGetAll("SELECT docente.id AS docente_id, docente.* FROM docente ORDER BY docente.cognome ASC, docente.nome ASC;") as $docente) {
	if ($anno_id == $__anno_scolastico_corrente_id && $docente['attivo'] == 0) {
		debug('Salto il docente '.$docente['cognome'] . ' ' . $docente['nome'].' non attivo');
		continue;
	}

	// anche se non lo salto, controllo se effettivamente ci sta qualcosa di significativo
	$significativo = false;
	$data = '';
	$docente_id = $docente['docente_id'];
	$totaleAssegnatoDocente = 0;
	$totaleDiariaDocente = 0;
	$totaleAttivitaDocente = 0;
	$totaleClilDocente = 0;
	$totaleCorsiDiRecuperoExtraDocente = 0;
	
    // eventuale messaggio per chiarire la compensazione effettuata
    $messaggio = "";
    $messaggioEccesso = "";

	$data .= '<h2 style="page-break-before: always;text-align: center;">'.$docente['cognome'] . ' ' . $docente['nome'].'</h2>';
	$data .= '';

	// fuis assegnato
	$assegnatoList = dbGetAll("SELECT * FROM fuis_assegnato INNER JOIN fuis_assegnato_tipo ON fuis_assegnato.fuis_assegnato_tipo_id=fuis_assegnato_tipo.id WHERE fuis_assegnato.docente_id = $docente_id AND fuis_assegnato.anno_scolastico_id = $anno_id;");
	if (!empty($assegnatoList)) {
		$data .= '<h4 style="text-align: center;background-color: #f1e6b2 !important;"><strong>FUIS Assegnato</strong></h4>';
		$data .= '<table><thead><tr><th class="col-sd-11">Tipo</th><th class="text-center col-sd-1">Importo</th></tr></thead><tbody>';
		foreach($assegnatoList as $assegnato) {
			$data .= '<tr><td>'.$assegnato['nome'].'</td><td class="text-right funzionale">'.$assegnato['importo'].'</td></tr>';
			$totaleAssegnatoDocente = $totaleAssegnatoDocente + $assegnato['importo'];
		}
		$data .= '</tbody><tfoot>';
		$data .='<tr><td colspan="1" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . formatNoZero($totaleAssegnatoDocente) . '</strong></td></tr>';
		$data .='</tfoot></table>';
		$data .= '<hr>';
		$significativo = true;
	}

	// diarie viaggio (non le ore)
	$diariaList = dbGetAll("SELECT * FROM fuis_viaggio_diaria INNER JOIN viaggio ON fuis_viaggio_diaria.viaggio_id = viaggio.id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $anno_id ORDER BY data_partenza ASC;");
	if (!empty($diariaList)) {
		$data .= '<h4 style="text-align: center;background-color: #fcaebb !important;"><strong>Diaria Viaggi</strong></h4>';
		$data .= '<table><thead><tr><th class="col-sd-6 text-left">Destinazione</th><th class="col-sd-4 text-left">Classe</th><th class="col-sd-1 text-center">Data</th><th class="text-center col-sd-1">Importo</th></tr></thead><tbody>';
		foreach($diariaList as $diaria) {
			$data .= '<tr><td>'.$diaria['destinazione'].'</td><td>'.$diaria['classe'].'</td><td class="text-center">'.formatDate($diaria['data_partenza']).'</td><td class="text-right funzionale">'.$diaria['importo'].'</td></tr>';
			$totaleDiariaDocente = $totaleDiariaDocente + $diaria['importo'];
		}
		$data .= '</tbody><tfoot>';
		$data .='<tr><td colspan="3" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . formatNoZero($totaleDiariaDocente) . '</strong></td></tr>';
		$data .='</tfoot></table>';
		$data .= '<hr>';
		$significativo = true;
	}

	// attivita'
	$data .= '<h4 style="text-align: center;background-color: #9be3bf !important;"><strong>Attività</strong></h4>';

	// Inserite da docente
	$data .= '<h4 style="text-align: center;background-color: #97D3CF !important;">Inserite da docente</h4>';
	// fuis: carica prima le ore dovute, previste e fatte
	$dovute = dbGetFirst("SELECT * FROM ore_dovute WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_id;");
    $dovuteFunzionali = $dovute['ore_70_funzionali'];
    $dovuteConStudenti = $dovute['ore_70_con_studenti'] + $dovute['ore_40_con_studenti'];
    $dovuteSostituzioni = $dovute['ore_40_sostituzioni_di_ufficio'];

    // le previste funzionali e con studenti
    $previsteFunzionali = dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore)) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE ore_previste_attivita.docente_id = $docente_id AND ore_previste_attivita.anno_scolastico_id = $anno_id AND ore_previste_tipo_attivita.categoria='funzionali'");
    $previsteConStudenti = dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore)) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE ore_previste_attivita.docente_id = $docente_id AND ore_previste_attivita.anno_scolastico_id = $anno_id AND ore_previste_tipo_attivita.categoria='con studenti'");

	$fatte = dbGetFirst("SELECT * FROM ore_fatte WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_id;");

	// inizializza le ore dovute
	$oreDovute = array("funzionali"=>($dovute['ore_70_funzionali']),"con studenti"=>($dovute['ore_40_con_studenti'] + $dovute['ore_70_con_studenti']),"sostituzioni"=>($dovute['ore_40_sostituzioni_di_ufficio']),"aggiornamento"=>($dovute['ore_40_aggiornamento']));

	// tabella delle ore fatte
	$oreFatte = array_fill_keys(array('funzionali', 'con studenti', 'sostituzioni', 'aggiornamento', 'clil_funzionali', 'clil_con_studenti', 'corsiDiRecuperoPagamentoExtra'), 0);

	// le sostituzioni sono tutte registrate qui
	$oreFatte['sostituzioni'] = $fatte['ore_40_sostituzioni_di_ufficio'];

	// tabella attività fatte:
	$data .= '<table>';
	$data .= '<thead><tr><th class="col-sd-1 text-left">Tipo</th><th class="col-sd-2 text-left">Nome</th><th class="col-sd-7 text-left">Dettaglio</th><th class="col-sd-1 text-center">Data</th><th class="col-sd-1 text-center">Ore</th></tr></thead><tbody>';

	$query = "SELECT ore_fatte_attivita.ore AS ore_attivita, ore_fatte_attivita.*, ore_previste_tipo_attivita.*, registro_attivita.*, ore_fatte_attivita_commento.* FROM `ore_fatte_attivita`
	INNER JOIN ore_previste_tipo_attivita ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
	LEFT JOIN registro_attivita registro_attivita ON registro_attivita.ore_fatte_attivita_id = ore_fatte_attivita.id
	LEFT JOIN ore_fatte_attivita_commento on ore_fatte_attivita_commento.ore_fatte_attivita_id = ore_fatte_attivita.id
	WHERE ore_fatte_attivita.docente_id = $docente_id AND ore_fatte_attivita.anno_scolastico_id = $anno_id
	ORDER BY ore_fatte_attivita.data DESC, ore_fatte_attivita.ora_inizio;";
	foreach(dbGetAll($query) as $attivita) {
		$significativo = true;
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
		$data .='</tr>';

		// se non contestata, la aggiunge alle ore fatte
		if ($attivita['contestata'] != 1) {
			if (array_key_exists($attivita['categoria'], $oreFatte)) {
				$oreFatte[$attivita['categoria']] += $attivita['ore_attivita'];
			} else {
				warning('categoria non trovata nome='.$attivita['categoria'].' ore='.$ore_con_minuti. 'docente='.$docente['cognome'] . ' ' . $docente['nome']);
			}
		}
	}

	$data .= '</tbody></table>';
	$data .= '<hr>';

	// sportelli
	$query = "	SELECT
		sportello.id AS sportello_id, sportello.*, materia.*,
		(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.presente) AS numero_presenti,
		(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.iscritto) AS numero_iscritti
		FROM sportello sportello INNER JOIN materia materia ON sportello.materia_id = materia.id
		WHERE sportello.docente_id = $docente_id AND sportello.anno_scolastico_id = $anno_id AND sportello.firmato = true AND sportello.cancellato = false
		ORDER BY sportello.data ASC;";

	$sportelliList = dbGetAll($query);
	if (!empty($sportelliList)) {
		$data .= '<h4 style="text-align: center;background-color: #FFD290 !important;">Sportelli</h4>';
		$data .= '<table>';
		$data .= '<thead><tr><th class="col-sd-2 text-left">Categoria</th><th class="col-sd-2 text-left">Materia</th><th class="col-sd-4 text-left">Note</th><th class="col-sd-2 text-center">Studenti</th><th class="col-sd-1 text-center">Data</th><th class="text-center col-sd-1">Ora</th></tr></thead><tbody>';
		foreach(dbGetAll($query) as $sportello) {
	
			$onlineMarker = (empty($sportello['online'])) ? '' : '<span class=\'label label-danger\'>online</span>';

			$ore_con_minuti = oreToDisplay($sportello['numero_ore']);
			$data .= '<tr><td>'.$sportello['categoria'].'</td>';
			$data .= '<td>'.$sportello['nome'].'</td>';
			$data .= '<td>'.$onlineMarker.$sportello['note'].'</td>';
			$data .= '<td class="text-center">'.$sportello['numero_presenti'].' di '.$sportello['numero_iscritti'].' iscritti</td>';
			$data .= '<td class="text-center">'.strftime("%d/%m/%Y", strtotime($sportello['data'])).'</td>';
			$data .= '<td class="text-center">'.$ore_con_minuti.'</td>';
			$data .='</tr>';
			
			$oreFatte['con studenti'] += $sportello['numero_ore'];
		}
	
		$data .= '</tbody></table>';
		$data .= '<hr>';
	}

	// attribuite
	$query = "	SELECT ore_previste_attivita.ore AS ore_attivita, ore_previste_attivita.*, ore_previste_tipo_attivita.* FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		WHERE ore_previste_attivita.anno_scolastico_id = $anno_id AND ore_previste_attivita.docente_id = $docente_id AND ore_previste_tipo_attivita.inserito_da_docente = false AND ore_previste_tipo_attivita.previsto_da_docente = false
		ORDER BY ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC";
	$attribuiteList = dbGetAll($query);
	if (!empty($attribuiteList)) {
		$data .= '<h4 style="text-align: center;background-color: #CCF3DD !important;">Attribuite</h4>';
		$data .= '<table>';
		$data .= '<thead><tr><th class="col-sd-2 text-left">Tipo</th><th class="col-sd-4 text-left">Nome</th><th class="col-sd-5 text-left">Dettaglio</th><th class="col-sd-1 text-center">Ore</th></tr></thead><tbody>';
		foreach($attribuiteList as $attribuite) {
			$ore_con_minuti = oreToDisplay($attribuite['ore_attivita']);
			$data .= '<tr><td>'.$attribuite['categoria'].'</td><td>'.$attribuite['nome'].'</td><td>'.$attribuite['dettaglio'].'</td><td class="text-center">'.$attribuite['ore'].'</td></tr>';
			// la aggiunge (attribuite non sono mai contestate)
			$oreFatte[$attribuite['categoria']] += $attribuite['ore_attivita'];
		}			
		$data .= '</tbody></table>';
		$significativo = true;
	}

	// gruppi di lavoro (non clil)
	$query = "SELECT * FROM gruppo_incontro_partecipazione
			INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
			INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
			WHERE gruppo_incontro_partecipazione.docente_id = $docente_id
			AND gruppo_incontro_partecipazione.ha_partecipato = true
			AND gruppo.anno_scolastico_id = $anno_id
			AND gruppo_incontro.effettuato = true
			AND gruppo.dipartimento = false
			AND gruppo.clil = false;";

	$gruppoList = dbGetAll($query);
	if (!empty($gruppoList)) {
		$data .= '<h4 style="text-align: center;background-color: #93DAFA !important;">Gruppi</h4>';
		$data .= '<table>';
		$data .= '<thead><tr><th class="col-sd-3 text-left">Gruppo</th><th class="col-sd-7 text-left">Ordine del Giorno</th><th class="col-sd-1 text-center">Data</th><th class="col-sd-1 text-center">Ore</th></tr></thead><tbody>';
		foreach($gruppoList as $gruppo) {
			$data .= '<tr><td>'.$gruppo['nome'].'</td><td>'.$gruppo['ordine_del_giorno'].'</td><td class="col-sd-1 text-center">'.formatDate($gruppo['data']).'</td><td class="col-sd-1 text-center">'.$gruppo['ore'].'</td></tr>';
			// i gruppi di lavoro sono sempre ore funzionali
			$oreFatte['funzionali'] += $gruppo['ore'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
		$significativo = true;
	}
	
	// le ore dei viaggi (che vanno con gli studenti)
	$viaggioList = dbGetAll("SELECT * FROM viaggio_ore_recuperate INNER JOIN viaggio ON viaggio_ore_recuperate.viaggio_id = viaggio.id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $anno_id ORDER BY data_partenza ASC;");
	if (!empty($viaggioList)) {
		$data .= '<h4 style="text-align: center;background-color: #FFE3DA !important;">Viaggi: ore recuperate</h4>';
		$data .= '<table>';
		$data .= '<thead><tr><th class="col-sd-6">Viaggio: destinazione</th><th class="col-sd-4">Classe</th><th class="text-center col-sd-1">Data</th><th class="text-center col-sd-1">Ore</th></tr></thead><tbody>';
		foreach($viaggioList as $viaggio) {
			$data .= '<tr><td>'.$viaggio['destinazione'].'</td><td>'.$viaggio['classe'].'</td><td class="col-sd-1 text-center">'.formatDate($viaggio['data_partenza']).'</td><td class="col-sd-1 text-center">'.$viaggio['ore'].'</td></tr>';
			// viaggi sono sempre con studenti
			$oreFatte['con studenti'] += $viaggio['ore'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
		$significativo = true;
	}

	// eventuali corsi di recupero inizio anno o in itinere
	$corsoDiRecuperoList = dbGetAll("SELECT corso_di_recupero.codice as corso_di_recupero_codice, corso_di_recupero.*, materia.* FROM corso_di_recupero INNER JOIN materia ON corso_di_recupero.materia_id = materia.id WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_id AND ore_recuperate > 0;");
	if (!empty($corsoDiRecuperoList)) {
		$data .= '<h4 style="text-align: center;background-color: #B9E6FB !important;">Corsi di recupero</h4>';
		$data .= '<table>';
		$data .= '<thead><tr><th class="col-sd-5 text-left">Codice</th><th class="col-sd-6 text-left">Materia</th><th class="text-center col-sd-1">Ore</th></tr></thead><tbody>';
		foreach($corsoDiRecuperoList as $corsoDiRecupero) {
			$data .= '<tr><td>'.$corsoDiRecupero['corso_di_recupero_codice'].'</td><td>'.$corsoDiRecupero['nome'].'</td><td class="col-sd-1 text-center">'.$corsoDiRecupero['ore_recuperate'].'</td></tr>';
			// corsi di recupero sono sempre con studenti
			$oreFatte['con studenti'] += $corsoDiRecupero['ore_recuperate'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
		$significativo = true;
	}

	// calcoli dei totali ore:
	$ore_funzionali = $oreFatte['funzionali'] - $dovuteFunzionali;
	$ore_con_studenti = $oreFatte['con studenti'] - $dovuteConStudenti;

	// aggiunge o toglie eventuali sostituzioni (sono sempre ore con studenti)
	$ore_sostituzioni = $oreFatte['sostituzioni'] - $dovuteSostituzioni;
	// ma se configurato per non sottrarre le sostituzioni, ignora questa parte se sono dovute dal docente (mette a 0)
	if (! getSettingsValue('fuis','rimuovi_sostituzioni_non_fatte', true)) {
		if ($ore_sostituzioni < 0) {
			$ore_sostituzioni = 0;
		}
	}
	$ore_con_studenti = $ore_con_studenti + $ore_sostituzioni;

	// se si possono compensare in ore quelle mancanti funzionali con quelle fatte in piu' con studenti lo aggiorna ora
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
		if ($ore_funzionali < 0 && $ore_con_studenti > 0) {
			$daSpostare = -$ore_funzionali;
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if ($ore_con_studenti < $daSpostare) {
				$daSpostare = $ore_con_studenti;
			}
			$ore_con_studenti = $ore_con_studenti - $daSpostare;
			$ore_funzionali = $ore_funzionali + $daSpostare;
            $messaggio = $messaggio . "Spostate " . $daSpostare ." ore con studenti per coprire " . $daSpostare ." ore funzionali mancanti. ";
		}
	}

	// se si possono compensare in ore quelle mancanti con studenti con quelle fatte in piu' funzionali lo aggiorna ora
	if (getSettingsValue('fuis','accetta_funzionali_per_con_studenti', false)) {
		if ($ore_con_studenti < 0 && $ore_funzionali > 0) {
			$daSpostare = -$ore_con_studenti;
			// se non ce ne sono abbastanza funzionali, sposta tutte quelle che ci sono
			if ($ore_funzionali < $daSpostare) {
				$daSpostare = $ore_funzionali;
			}
			$ore_funzionali = $ore_funzionali - $daSpostare;
            $ore_con_studenti = $ore_con_studenti + $daSpostare;
            $messaggio = $messaggio . "Spostate " . $daSpostare ." ore funzionali per coprire " . $daSpostare ." ore con studenti mancanti. ";
		}
    }

    // possibile controllo se le ore fatte eccedono le previsioni
	if (getSettingsValue('fuis','rimuovi_fatte_eccedenti_previsione', false)) {
        $pagabiliFunzionali = max($previsteFunzionali - $dovuteFunzionali,0);
        $pagabiliConStudenti = max($previsteConStudenti - $dovuteConStudenti,0);
        if ($ore_funzionali > 0 && $ore_funzionali > $pagabiliFunzionali) {
            $bilancioDifferenzaFunzionali = $ore_funzionali - $pagabiliFunzionali;
            $ore_funzionali = $pagabiliFunzionali;
            $messaggioEccesso = $messaggioEccesso . $bilancioDifferenzaFunzionali . " ore funzionali non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $ore_funzionali .".";
        }
        if ($ore_con_studenti > 0 && $ore_con_studenti > $pagabiliConStudenti) {
            $bilancioDifferenzaConStudenti = $ore_con_studenti - $pagabiliConStudenti;
            $ore_con_studenti = $pagabiliConStudenti;
            if ( ! empty($messaggioEccesso)) {
                $messaggioEccesso = $messaggioEccesso . "</br>";
            }
            $messaggioEccesso = $messaggioEccesso . $bilancioDifferenzaConStudenti . " ore con studenti non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $ore_con_studenti .". ";
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
		$fuis_sostituzioni_importo = max($fuis_sostituzioni_importo, 0);
	}

	// totale per fuis attivita'
	$totaleAttivitaDocente = $fuis_sostituzioni_importo + $fuis_funzionale_importo + $fuis_con_studenti_importo;

	// ma nessuno deve dare soldi indietro alla scuola
	if ($totaleAttivitaDocente < 0) {
	    $totaleAttivitaDocente = 0;
	}

	// scrive le ore come sono prima di calcolare l'importo
	$data .= '<h4 style="text-align: center;;background-color: #FFA98F !important;">Totale Ore attività</h4>';
	$data .= '<table>';
	$data .= '<thead><tr><th class="col-sd-2 text-left">Tipo</th><th class="col-sd-3 text-center">Dovute</th><th class="col-sd-3 text-center">Fatte</th><th class="col-sd-3 text-center">Bilancio</th><th class="col-sd-1 text-center">Importo</th></tr></thead><tbody>';
	$data .= '<tr><td class="col-sd-2 text-left">sostituzioni</td><td class="col-sd-3 text-center">'.$dovuteSostituzioni . '</td><td class="col-sd-3 text-center">' . $oreFatte['sostituzioni'] . '</td><td class="col-sd-3 text-center">' . $ore_sostituzioni . '</td><td class="col-sd-1 text-right">' . number_format($fuis_sostituzioni_importo,2) . '</td></tr>';
	$data .= '<tr><td class="col-sd-2 text-left">funzionali</td><td class="col-sd-3 text-center">'.$dovuteFunzionali . '</td><td class="col-sd-3 text-center">' . $oreFatte['funzionali'] . '</td><td class="col-sd-3 text-center">' . $ore_funzionali . '</td><td class="col-sd-1 text-right">' . number_format($fuis_funzionale_importo,2) . '</td></tr>';
	$data .= '<tr><td class="col-sd-2 text-left">con studenti</td><td class="col-sd-3 text-center">'.$dovuteConStudenti . '</td><td class="col-sd-3 text-center">' . $oreFatte['con studenti'] . '</td><td class="col-sd-3 text-center">' . $ore_con_studenti . '</td><td class="col-sd-1 text-right">' . number_format($fuis_con_studenti_importo,2) . '</td></tr>';
	$data .= '</tbody><tfoot>';
	// inserisce nel footer eventuali messaggi di compensazione
	if ( ! empty($messaggio)) {
		$data .='<tr><td colspan="1" class="text-left"><strong>Attenzione:</strong></td><td class="text-left" colspan="4"><strong>' . $messaggio . '</strong></td></tr>';
	}
	if ( ! empty($messaggioEccesso)) {
		$data .='<tr><td colspan="1" class="text-left"><strong>Attenzione:</strong></td><td class="text-left" colspan="4"><strong>' . $messaggioEccesso . '</strong></td></tr>';
	}
	$data .='<tr><td colspan="4" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . number_format($totaleAttivitaDocente,2) . '</strong></td></tr>';
	$data .='</tfoot></table>';
	$data .= '<hr>';

	// eventuali corsi di recupero inizio anno con pagamento extra
	$corsoDiRecuperoList = dbGetAll("SELECT corso_di_recupero.codice as corso_di_recupero_codice, corso_di_recupero.*, materia.* FROM corso_di_recupero INNER JOIN materia ON corso_di_recupero.materia_id = materia.id WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_id AND ore_pagamento_extra > 0;");
	if (!empty($corsoDiRecuperoList)) {
		$data .= '<h4 style="text-align: center;background-color: #B9E6FB !important;">Corsi di recupero: pagamento extra</h4>';
		$data .= '<table>';
		$data .= '<thead><tr><th class="col-sd-4 text-left">Codice</th><th class="col-sd-6 text-left">Materia</th><th class="text-center col-sd-1">Ore</th></tr></thead><tbody>';
		foreach($corsoDiRecuperoList as $corsoDiRecupero) {
			$data .= '<tr><td>'.$corsoDiRecupero['corso_di_recupero_codice'].'</td><td>'.$corsoDiRecupero['nome'].'</td><td class="col-sd-1 text-center">'.$corsoDiRecupero['ore_pagamento_extra'].'</td></tr>';
			// corsi di recupero sono sempre con studenti
			$oreFatte['corsiDiRecuperoPagamentoExtra'] += $corsoDiRecupero['ore_pagamento_extra'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
		$significativo = true;

		$totaleCorsiDiRecuperoExtraDocente = $oreFatte['corsiDiRecuperoPagamentoExtra'] * $__importi['importo_ore_corsi_di_recupero'];
	}

	// CLIL attivita'
	$fuis_clil_funzionale_importo = 0;
	$fuis_clil_con_studenti_importo = 0;
	$totaleClilDocente = 0;

	$clilPrevisteFunzionali=dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id WHERE anno_scolastico_id = $anno_id AND docente_id = $docente_id AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'funzionali';");
    $clilPrevisteConStudenti=dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id WHERE anno_scolastico_id = $anno_id AND docente_id = $docente_id AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'con studenti';");

	$query = "SELECT * FROM ore_fatte_attivita_clil
		LEFT JOIN registro_attivita_clil registro_attivita_clil ON registro_attivita_clil.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
		LEFT JOIN ore_fatte_attivita_clil_commento ON ore_fatte_attivita_clil_commento.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
		WHERE ore_fatte_attivita_clil.anno_scolastico_id = $anno_id AND ore_fatte_attivita_clil.docente_id = $docente_id ORDER BY ore_fatte_attivita_clil.data DESC, ore_fatte_attivita_clil.ora_inizio;";

	$clilList = dbGetAll($query);
	if (!empty($clilList)) {
		$data .= '<h4 style="text-align: center;background-color: #8bd3e6 !important;">CLIL</h4>';
		$data .= '<h4 style="text-align: center;">Ore CLIL Richieste</h4>';
		$data .= '<table><thead><tr><th class="col-sd-2 text-left">Tipo</th><th class="col-sd-8 text-left">Dettaglio</th><th class="text-center col-sd-1">Data</th><th class="text-center col-sd-1">Ore</th></tr></thead><tbody>';
		foreach($clilList as $clil) {
			$categoria = ($clil['con_studenti'])? 'con studenti' : 'funzionali';
			$ore_con_minuti = oreToDisplay($clil['ore']);

			$data .= '<tr><td>'.$categoria.'</td><td>'.$clil['dettaglio'];
			// $data .='</br><strong>Registro: </strong>'.$clil['descrizione'].'';
			if ($clil['contestata'] == 1) {
				$data .='</br><span style="color:red !important;font-weight:bold"><strong  style="color:red !important">'.$clil['commento'].'</strong></span>';
			}
			$data .= '</br>'.$clil['ore_fatte_attivita_clil_id'];
			$data .= '</td>';
	
			// data e ora solo per quelle inserite da docente
			$ore_con_minuti = oreToDisplay($clil['ore']);
			$data .='<td class="text-center">'.formatDate($clil['data']).'</td><td class="text-center">'.$ore_con_minuti.'</td>';
	
			// contestata?
			$marker = ($clil['contestata'] == 1)? $contestataMarker : $accettataMarker;
			$data .='</tr>';
	
			// se non contestata, la aggiunge alle ore fatte
			if ($clil['contestata'] != 1) {
				$oreFatte[($clil['con_studenti'])? 'clil_con_studenti' : 'clil_funzionali'] += $clil['ore'];
			}
		}

		// anche i gruppi di lavoro clil entrano nel clil funzionale (ma solo nelle ore fatte, dove il responsabile ha inserito la partecipazione)
		$query = "SELECT * FROM gruppo_incontro_partecipazione
				INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
				INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
				WHERE gruppo_incontro_partecipazione.docente_id = $docente_id
				AND gruppo_incontro_partecipazione.ha_partecipato = true
				AND gruppo.anno_scolastico_id = $anno_id
				AND gruppo_incontro.effettuato = true
				AND gruppo.dipartimento = false
				AND gruppo.clil = true;";

		foreach(dbGetAll($query) as $gruppo) {
			$data .= '<tr><td>gruppo clil</td><td>'.$gruppo['ordine_del_giorno'].'</td>';
			$data .='<td class="text-center">'.formatDate($gruppo['data']).'</td><td class="text-center">'.$gruppo['ore'].'</td>';
			// gruppi sono sempre funzionali
			$oreFatte['clil_funzionali'] += $gruppo['ore'];
		}

		$data .= '</tbody></table>';

		$fuis_clil_funzionale_importo = $oreFatte['clil_funzionali'] * $__settings->importi->oreFunzionali;
		$fuis_clil_con_studenti_importo = $oreFatte['clil_con_studenti'] * $__settings->importi->oreConStudenti;
		$totaleClilDocente = $fuis_clil_funzionale_importo + $fuis_clil_con_studenti_importo;
	

		// scrive le ore come sono prima di calcolare l'importo
		$data .= '<h4 style="text-align: center;">Totale Ore CLIL</h4>';
		$data .= '<table>';
		$data .= '<thead><tr><th class="col-sd-8 text-left">Tipo</th><th class="col-sd-3 text-center">Ore</th><th class="col-sd-1 text-center">Importo</th></tr></thead><tbody>';
		$data .= '<tr><td class="col-sd-8 text-left">clil funzionali</td><td class="col-sd-3 text-center">' . $oreFatte['clil_funzionali'] . '</td><td class="col-sd-1 text-right">' . number_format($fuis_clil_funzionale_importo,2) . '</td></tr>';
		$data .= '<tr><td class="col-sd-8 text-left">clil con studenti</td><td class="col-sd-3 text-center">'.$oreFatte['clil_con_studenti'] . '</td><td class="col-sd-1 text-right">' . number_format($fuis_clil_con_studenti_importo,2) . '</td></tr>';
		$data .= '</tbody><tfoot>';
		$data .='<tr><td colspan="2" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . number_format($totaleClilDocente,2) . '</strong></td></tr>';
		$data .='</tfoot></table>';
		$data .= '<hr>';
		$significativo = true;
	}

	// aggiorna i totali di istituto
	$totaleAssegnatoIstuto = $totaleAssegnatoIstuto + $totaleAssegnatoDocente;
	$totaleDiariaIstuto = $totaleDiariaIstuto + $totaleDiariaDocente;
	$totaleAttivitaIstuto = $totaleAttivitaIstuto + $totaleAttivitaDocente;
	$totaleClilIstuto = $totaleClilIstuto + $totaleClilDocente;
	$totaleCorsiDiRecuperoExtraIstituto = $totaleCorsiDiRecuperoExtraIstituto + $totaleCorsiDiRecuperoExtraDocente;

	// se ha trovato qualcosa di significativo, include il docente nello storico
	if ($significativo) {
		$dataContenuto = $dataContenuto . $data;
	}
}

// serve il nome dell'anno scolastico
$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");

// stampa i totali di istituto
$dataConsuntivo .= '<hr style="page-break-before: always;">';
$dataConsuntivo .= '<h2 style="text-align: center; padding-top: 3cm; padding-bottom: 2cm;">Totale FUIS anno scolastico '.$nome_anno_scolastico.'</h2>';
$dataConsuntivo .= '<table class="table table-bordered table-striped table-green">';

$dataConsuntivo .= '<thead><tr><th class="col-sd-11 text-left">Tipo</th><th class="col-sd-1 text-center">Importo</th></tr></thead><tbody>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale Diaria Viaggi</td><td class="col-sd-1 text-right">' . number_format($totaleDiariaIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale FUIS Assegnato</td><td class="col-sd-1 text-right">' . number_format($totaleAssegnatoIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale FUIS Attività</td><td class="col-sd-1 text-right">' . number_format($totaleAttivitaIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale FUIS CLIL</td><td class="col-sd-1 text-right">' . number_format($totaleClilIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale Corsi di Recepero Extra</td><td class="col-sd-1 text-right">' . number_format($totaleCorsiDiRecuperoExtraIstituto,2) . '</td></tr>';
$dataConsuntivo .= '</tbody></table>';
$dataConsuntivo .= '<hr>';

// prima pagina
$dataCopertina .= '<h2 style="text-align: center; padding-bottom: 1cm;"><img style="text-align: center;" alt="" src="data:image/png;base64,'. base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'")).'" title=""></h2>';
$dataCopertina .= '<h3 style="text-align: center; padding-bottom: 3cm;">'.getSettingsValue('local','nomeIstituto', '').'</h3>';
$dataCopertina .= '<h2 style="text-align: center;">FUIS Docenti anno scolastico '.$nome_anno_scolastico.'</h2>';

// titolo
$title = 'Storico FUIS ' . $nome_anno_scolastico.' - '.getSettingsValue('local','nomeIstituto', '');

// adesso viene il momento di produrre la pagina o il pdf
$pagina .= '<html>
<head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css">
';

$pagina .= '<title>' .$title . '</title>';
$pagina .='
<meta content="text/html; charset=UTF-8" http-equiv="content-type">
<style>
	h1,h2,h3,h4,h5 { color: #0e2c50; font-family: Helvetica, Sans-Serif; }
	.unita_titolo { display:inline-block; vertical-align: middle; }
	.nome { text-transform:uppercase; color: #0e2c50; font-family: Helvetica, Sans-Serif; display: block; font-weight: bold; font-size: .83em; }
	body { max-width: 800px; }
	@media print {
		.noprint {
			visibility: hidden;
		}
	}

	 @page {
		@bottom-left {
			content: counter(page) " of " counter(pages);
		}
	}

	.label {
        box-sizing: border-box;
    	padding: 0.2em 0.6em 0.2em;
    	border-radius: 0.25em;
        font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
        font-weight: 700;
        font-size: 75%;
        vertical-align: baseline;
        color: white;
    }
	.label-success {
        background-color: #5cb85c;
    }
	.label-info {
        background-color: #5bc0de;
    }
	.label-warning {
        background-color: #eea236;
    }
	.label-danger {
        background-color: #d9534f;
    }
    .icon-play{
        background-image : url("../img/pdf-256.png");
        background-size: cover;
        display: inline-block;
        height: 24px;
        width: 24px;
    }

	.btn_print {
        box-sizing: border-box;
    	padding: 0.2em 0.6em 0.2em;
    	border-radius: 0.25em;
        font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
        font-weight: 700;
        font-size: 75%;
        vertical-align: center;
		background-color: #4c3635;
        color: white;
		align-items: center;
		display: inline-flex;
    }

	table {
		font-family: Helvetica, Sans-Serif;
		font-size: .75em;
		width: 100%;
	}

	table, td, th {
		border: 1px solid black;
		border-collapse: collapse;
		padding: 5px;
	}
	  
	.text-left {
		text-align: left;
	}
	.text-right {
		text-align: right;
	}
	.text-center {
		text-align: center;
	}

	.col-sd-1 {
		width: 8%;
	}
	.col-sd-2 {
		width: 17%;
	}
	.col-sd-3 {
		width: 25%;
	}
	.col-sd-4 {
		width: 33%;
	}
	.col-sd-5 {
		width: 42%;
	}
	.col-sd-6 {
		width: 50%;
	}
	.col-sd-7 {
		width: 58%;
	}
	.col-sd-8 {
		width: 67%;
	}
	.col-sd-9 {
		width: 75%;
	}
	.col-sd-11 {
		width: 92%;
	}

</style>';

// lo script non deve entrare nel pdf
if (! $print) {
	$pagina .='
	<script type="text/javascript">
	window.onload = (event) => {
		var printBtn = document.querySelector(".btn_print");
		printBtn.onclick = function(event) {
			event.preventDefault();
			window.location.search += "&print=true";
		}
	};
	</script>';
}

// chiude l'intestazione
$pagina .='
	</head>
	<body>';

// bottone di print solo se in visualizzazione
if (! $print) {
	$pagina .='
		<div class="text-center noprint" style="text-align: center;padding: 50px;">
		<button onclick="storicoBonusSavePdf('.$anno_id.')" class="btn btn-orange4 btn-xs btn_print"><i class="icon-play"></i>&nbsp;Scarica il pdf</button>
		</div>';
}

// il resto deve entrare in entrambi i casi, pagina o pdf: copertina, consuntivo, poi tutto il resto
$pagina .= $dataCopertina;
$pagina .= $dataConsuntivo;
$pagina .= $dataContenuto;

// chiude la pagina
$pagina .= '</body></html>';

// decide se visualizzarla o inviarla a pdf
if (! $print) {
	echo $pagina;
} else {
	// aumenta il limite di memoria visto che ne potrebbe usare molta
	ini_set('memory_limit','1024M');

	$dompdf = new Dompdf();
	$dompdf->loadHtml($pagina);
 
	// configura i parametri
	$dompdf->setPaper('A4', 'portrait');
	
	// Render html in pdf
	$dompdf->render();

	// produce il nome del file
	$pdfFileName = "$title.pdf";

	// richiesta di invio di email
	if ($print) {
		// invia il pdf al browser che fa partire il download in automatico
		$dompdf->stream($pdfFileName);
	}
}

?>