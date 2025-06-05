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

$programma_id = $_GET["programma_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Ordine</th>
						<th class="text-center col-md-4">Titolo</th>
						<th class="text-center col-md-2">Ultimo aggiornamento</th>
						<th class="text-center col-md-2">Azioni</th>
					</tr>
					</thead>';

					$query = "	SELECT
					programma_moduli.id AS modulo_id,
					programma_moduli.id_programma AS programma_id,
					programma_moduli.ordine AS modulo_ordine,
					programma_moduli.nome AS modulo_nome,
					programma_moduli.updated AS modulo_updated
				FROM programma_moduli
				WHERE programma_moduli.id_programma=$programma_id ";
	
	$query .= "ORDER BY programma_moduli.ordine ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach ($resultArray as $row) {
	{
		$idmodulo = $row['modulo_id'];
		$id_programma = $row['programma_id'];
		$ordine = $row['modulo_ordine'];
		$titolo = $row['modulo_nome'];
		$updated = $row['modulo_updated'];
		$data .= '<tr>
		<td align="center">' . $ordine . '</td>
		<td align="center">' . $titolo . '</td>
		<td align="center">' . $updated . '</td>
		';
		$data .= '
		<td class="text-center">';

		if (haRuolo('docente'))
		{
			$data .= '
			<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></button>
			';
		}
		if ((haRuolo('dirigente'))||(haRuolo('segreteria-didattica')))
		{
			$data .= '
			<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="moduloDelete(' . $idmodulo . ',\'' . $id_programma . '\',\'' . $titolo . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></button>
			';
		}
		$data .= '
		</td>
		</tr>';
	}
}

$data .= '</table></div>';

echo $data;
?>