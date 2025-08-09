<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
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
						<th class="text-center col-md-2">Cognome</th>
						<th class="text-center col-md-2">Nome</th>
						<th class="text-center col-md-1">Codice fiscale</th>
						<th class="text-center col-md-1">UserID MasterCom</th>
						<th class="text-center col-md-2">email</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Anno</th>
						<th class="text-center col-md-1">Attivo</th>
						<th class="text-center col-md-1"></th>
					</tr>
					</thead>';

$query = "SELECT * FROM studente ";

if( $soloAttivi || $soloAttivi == 'true' ) {
 	$query .= " WHERE studente.attivo = 1 ";
}

$query .= "ORDER BY studente.cognome ASC, studente.nome ASC";

foreach(dbGetAll($query) as $row) {

	$query2 = "SELECT * FROM studente_frequenta WHERE id_studente = ".$row['id']." ORDER BY id_anno_scolastico DESC LIMIT 1";
	$studente = dbGetFirst($query2);
	if ($studente === null) {
		// se lo studente non ha mai frequentato, non lo mostro
		continue;
	}

	$query2 = "SELECT * FROM classi WHERE id = ".$studente['id_classe'];
	$classe = dbGetFirst($query2);

	if ( $classeFiltroId && $classeFiltroId > 0 ) {
	if ($studente['id_classe'] != $classeFiltroId){
		// se la classe non corrisponde al filtro, non lo mostro
		continue;	
	}
   }

	$query2 = "SELECT * FROM anno_scolastico WHERE id = ".$studente['id_anno_scolastico'];
	$anno = dbGetFirst($query2);

	$data .= '<tr>
	<td style="text-align:center">'.ucwords(strtolower($row['cognome'])).'</td>
	<td style="text-align:center">'.ucwords(strtolower($row['nome'])).'</td>
	<td style="text-align:center">'.strtoupper($row['codice_fiscale']).'</td>
	<td style="text-align:center">'.$row['username'].'</td>
	<td style="text-align:center">'.strtolower($row['email']).'</td>
	<td style="text-align:center">'.strtoupper($classe['classe']).'</td>
	<td style="text-align:center">'.$anno['anno'].'</td>
	<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" ';
	if ($row['attivo']) {
		$data .= 'checked ';
	}
	$data .='</td>
		<td class="text-center">
		<button onclick="studenteGetDetails('.$row['id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="studenteDelete('.$row['id'].', \''.$row['cognome'].'\', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		<button onclick="studenteImpersona('.$row['id'].', \''.$row['cognome'].'\', \''.$row['nome'].'\')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-pawn"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';

echo $data;
?>
