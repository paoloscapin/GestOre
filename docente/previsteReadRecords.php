<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__Minuti.php';

function writeOre($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.oreToDisplay($originali).' </s>&ensp;<span class="text-danger"><strong> '.oreToDisplay($attuali).' </strong></span>';
}

// default opera sul docente connesso e agisce come docente
$docente_id = $__docente_id;
$operatore = 'docente';

$modificabile = $__config->getOre_previsioni_aperto();

if(isset($_POST['operatore']) && $_POST['operatore'] == 'dirigente') {
	// se vuoi fare il dirigente, devi essere dirigente
	ruoloRichiesto('dirigente');
	// agisci quindi come dirigente
	$operatore = 'dirigente';
	// il dirigente può sempre fare modifiche
	$modificabile = true;
	// devi leggere il timestamp dell'ultimo controllo effettuato
	$ultimo_controllo = $_POST['ultimo_controllo'];
}
debug('modificabile='.$modificabile);
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<tr>
							<th>Tipo</th>
							<th>Nome</th>
							<th>Dettaglio</th>
							<th class="col-md-1 text-center">ore</th>
							<th></th>
						</tr>';

$query = "	SELECT
					ore_previste_attivita.id AS ore_previste_attivita_id,
					ore_previste_attivita.ore AS ore_previste_attivita_ore,
					ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
					ore_previste_attivita.ultima_modifica AS ore_previste_attivita_ultima_modifica,
					ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
					ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
					ore_previste_tipo_attivita.inserito_da_docente AS ore_previste_tipo_attivita_inserito_da_docente,
					ore_previste_tipo_attivita.previsto_da_docente AS ore_previste_tipo_attivita_previsto_da_docente,
					ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome,
                    ore_previste_attivita_commento.commento AS ore_previste_attivita_commento_commento,
                    ore_previste_attivita_commento.ore_originali AS ore_previste_attivita_commento_ore_originali

				FROM ore_previste_attivita ore_previste_attivita
				INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
				ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
                LEFT JOIN ore_previste_attivita_commento
                on ore_previste_attivita_commento.ore_previste_attivita_id = ore_previste_attivita.id
				WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND ore_previste_attivita.docente_id = $docente_id
				AND ore_previste_tipo_attivita.previsto_da_docente = true
				ORDER BY
				ore_previste_tipo_attivita.previsto_da_docente DESC,
				ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC;"
				;

$parteModificabile = true;
foreach(dbGetAll($query) as $row) {
	// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
	$marker = '';
	if ($operatore == 'dirigente') {
		if ($row['ore_previste_attivita_ultima_modifica'] > $ultimo_controllo) {
			$marker = '&nbsp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>&ensp;';
		}
	}

	// inserisce una riga rossa nella tabella quando iniziano le attività che non posso modificare
	if ($parteModificabile && ! $row['ore_previste_tipo_attivita_previsto_da_docente']) {
		$parteModificabile = false;
		$data .= '<tr style="border-top:2px solid red">';
	} else {
		$data .= '<tr>';
	}

	$data .= '<td class="col-md-1">'.$row['ore_previste_tipo_attivita_categoria'].'</td><td class="col-md-3">'.$row['ore_previste_tipo_attivita_nome'].'</td><td>'.$marker.$row['ore_previste_attivita_dettaglio'];
	if ($row['ore_previste_attivita_commento_commento'] != null && !empty(trim($row['ore_previste_attivita_commento_commento'], " "))) {
		$data .='</br><span class="text-danger"><strong>'.$row['ore_previste_attivita_commento_commento'].'</strong></span>';
	}
	$data .='</td>';

	$data .= '<td class="col-md-1 text-center">'.writeOre($row['ore_previste_attivita_ore'], $row['ore_previste_attivita_commento_ore_originali']).'</td>';

	$data .='<td class="col-md-1 text-center">';
	// si possono modificare solo le righe previste da docente: se dirigente lo script non cancella ma propone di mettere le ore a zero
	if ($row['ore_previste_tipo_attivita_previsto_da_docente']) {
		if ($modificabile) {
			$data .='
			<button onclick="previstaModifica('.$row['ore_previste_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="previstaDelete('.$row['ore_previste_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		';
		}
	}
	$data .='</td></tr>';
}

$data .= '</table></div>';

echo $data;
?>
