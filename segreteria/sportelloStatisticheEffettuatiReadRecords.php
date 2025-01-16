<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$categoria_filtro_id = $_GET["categoria_filtro_id"];
$con_sportelli = $_GET["con_sportelli"];
$con_sportelli_fatti = $_GET["con_sportelli_fatti"];
$senza_sportelli = $_GET["senza_sportelli"];
$solo_passati = $_GET["soloPassati"];
$solo_futuri = $_GET["soloFuturi"];

$categoria_selezionata = "";

if ($categoria_filtro_id != 0) {
	$dbcat = dbGetFirst("SELECT nome FROM sportello_categoria WHERE id = " . $categoria_filtro_id);
	$categoria_selezionata = $dbcat['nome'];
}

// totali
$totaleOreFatte = 0;
$totaleSportelliFatti = 0;
$totaleOreSaltate = 0;
$totaleSportelliSaltati = 0;

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-12" colspan="8">Statistiche sportelli</th>
					</tr>
					<tr>
						<th class="text-center col-md-1">Cognome</th>
						<th class="text-center col-md-1">Nome</th>
						<th class="text-center col-md-1">Sportelli Programmati</th>
						<th class="text-center col-md-1">Sportelli Effettuati</th>
						<th class="text-center col-md-1">Ore Totali Programmate</th>
						<th class="text-center col-md-1">Ore Fatte</th>
					</tr>
					</thead>';
$outputcsv = array();
array_push($outputcsv,"Cognome,Nome,SportelliProgrammati,SportelliEffettuati,Ore Totali Programmate,Ore Fatte\n");

$tutti_docenti = dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome ASC");
foreach ($tutti_docenti as $docente) {
	$id_docente = $docente['id'];
	$docente_cognome = $docente['cognome'];
	$docente_nome = $docente['nome'];

	// elenco sportelli docente non cancellati
	$query = "SELECT id,COUNT(id) AS numero, COUNT(CASE firmato WHEN '1' THEN 1 ELSE NULL END) AS nfatti, COUNT(numero_ore) AS ore_totali, COUNT(CASE firmato WHEN '1' THEN numero_ore ELSE NULL END) AS ore_fatte FROM sportello
	WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id AND NOT sportello.cancellato AND sportello.docente_ID = $id_docente";

	if ($solo_passati == 1)
	{
		$query .= " AND sportello.data <= CURRENT_DATE()";
	}
	else
	if ($solo_futuri == 1)
	{
		$query .= " AND sportello.data > CURRENT_DATE()";
	}
	if ($categoria_filtro_id != 0) {
		$query .= " AND sportello.categoria = '" . $categoria_selezionata . "'";
	}
	if ($con_sportelli == 1) {
		$query .= " HAVING COUNT(id) > 0 ";
	} else
		if ($con_sportelli_fatti == 1) {
			$query .= " HAVING COUNT(CASE firmato WHEN '1' THEN 1 ELSE NULL END) > 0 ";
		} else
			if ($senza_sportelli == 1) {
				$query .= " HAVING COUNT(id) = 0 ";
			}
	debug($query);
	$result = dbGetFirst($query);

	if ($result != null) {
		$data .= '<tr>' .
			'<td class="text-center">' . $docente_cognome . '</td>' .
			'<td class="text-center">' . $docente_nome . '</td>' .
			'<td class="text-center">' . $result['numero'] . '</td>' .
			'<td class="text-center">' . $result['nfatti'] . '</td>' .
			'<td class="text-center">' . $result['ore_totali'] . '</td>' .
			'<td class="text-center">' . $result['ore_fatte'] . '</td>' .
			'</tr>';
		array_push($outputcsv,$docente_cognome.",".$docente_nome.",".$result['numero'].",".$result['nfatti'].",".$result['ore_totali'].",".$result['ore_fatte']."\n");
	}
}

$file = fopen("sportelli_export.csv","w");

foreach ($outputcsv as $line) {
	fwrite($file, $line);
}
  
  fclose($file);

$data .= '</table></div>';

echo $data;
?>