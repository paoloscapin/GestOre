<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$soloAttivi = $_GET["soloAttivi"];
$classeFiltroId = $_GET["classeFiltroId"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Cognome</th>
						<th class="text-center col-md-1">Nome</th>
						<th class="text-center col-md-1">Codice fiscale</th>
						<th class="text-center col-md-1">UserID MasterCom</th>
						<th class="text-center col-md-2">email</th>
						<th class="text-center col-md-3">Genitore di</th>
						<th class="text-center col-md-1">Relazione</th>
						<th class="text-center col-md-1">Attivo</th>
						<th class="text-center col-md-1"></th>
					</tr>
					</thead>';

$query = "SELECT * FROM genitori ";

if( $soloAttivi || $soloAttivi == 'true' ) {
 	$query .= " WHERE genitori.attivo = 1 ";
}

$query .= "ORDER BY genitori.cognome ASC, genitori.nome ASC";

foreach(dbGetAll($query) as $row) {

	$query = "SELECT * FROM genitori_studenti WHERE id_genitore = ".$row['id']." ORDER BY id_studente ASC";
	$genitoriStudenti = dbGetAll($query);

	if (count($genitoriStudenti) == 0) {
		// se il genitore non ha figli, non lo mostro
		continue;
	}

	$genitoriDi = '';
	$relazioni = '';

	foreach ($genitoriStudenti as $genitoreStudente) {
		$query2 = "SELECT * FROM studente WHERE id = ".$genitoreStudente['id_studente'];
		$studente = dbGetFirst($query2);
		if ($studente === null) {
			continue; // se lo studente non esiste, salto
		}

		$query2 = "SELECT * FROM studente_frequenta WHERE id_studente = ".$studente['id']." AND id_anno_scolastico = ".$__anno_scolastico_corrente_id;
		$frequenta = dbGetFirst($query2);
		if ($frequenta === null) {
			continue; // se lo studente non frequenta l'anno scolastico corrente, salto
		}
		$query2 = "SELECT * FROM classi WHERE id = ".$frequenta['id_classe'];
		if ($frequenta['id_classe'] == 0) {
			// se lo studente non ha una classe, salto
			continue;
		}
		$classe = dbGetFirst($query2);
		if ( $classeFiltroId && $classeFiltroId > 0 ) {
			if ($classe['id'] != $classeFiltroId) {	
				// se la classe non corrisponde al filtro, non lo mostro
				continue;	
			}
		}
		if ($genitoriDi != '') {
			$genitoriDi .= '<br>';
		}
		$genitoriDi .= $studente['cognome'] . ' ' . $studente['nome'] . ' (' . strtoupper($classe['classe']) . ')';

		if ($relazioni != '') {
			$relazioni .= '<br>';
		}
		$query2 = "SELECT relazione FROM genitori_relazioni WHERE id=".$genitoreStudente['id_relazione'];
		$relazione = dbGetValue($query2);
		$relazioni .= ucfirst($relazione);
		info("genitore id=".$row['id']." cognome=".$row['cognome']." nome=".$row['nome']." studente id=".$studente['id']." cognome=".$studente['cognome']." nome=".$studente['nome']." relazione=".$relazioni);

	}


	if ($genitoriDi == '') {
		// se il genitore non ha figli, non lo mostro
		continue;
	}

	$data .= '<tr>
	<td style="text-align:center">'.ucwords(strtolower($row['cognome'])).'</td>
	<td style="text-align:center">'.ucwords(strtolower($row['nome'])).'</td>
	<td style="text-align:center">'.strtoupper($row['codice_fiscale']).
	'</td>
	<td style="text-align:center">'.$row['username'].'</td>
	<td style="text-align:center">'.strtolower($row['email']).'</td>
	<td style="text-align:center">'.$genitoriDi.'</td>
	<td style="text-align:center">'.$relazioni.'</td>
	<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" ';
	if ($row['attivo']) {
		$data .= 'checked ';
	}
	$data .='</td>
		<td class="text-center">
		<button onclick="genitoreGetDetails('.$row['id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></span></button>
		<button onclick="genitoreDelete('.$row['id'].', \''.$row['cognome'].'\', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></span></button>
		<button onclick="genitoreImpersona('.$row['id'].', \''.$row['cognome'].'\', \''.$row['nome'].'\')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-pawn"></span></button>
		</td>
		</tr>';
	}

$data .= '</table></div>';

echo $data;
?>
