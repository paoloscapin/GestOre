<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__Minuti.php';

$modificabile = $__config->getOre_fatte_aperto();

$docenteId = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	$docenteId = $_POST['docente_id'];
	$modificabile = false;
}

$totale = 0;
$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-11 text-left">Data</th>
							<th class="col-md-2 text-center">Ore</th>
						</tr></thead><tbody>';

$query = "SELECT data, ora FROM sostituzione_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId ORDER BY data DESC;";
foreach(dbGetAll($query) as $row) {
    $data .= '<tr>
        <td>'.strftime("%d/%m/%Y", strtotime($row['data'])).'</td>
        <td class="text-center">'.$row['ora'].'</td>
        ';
	$totale += $row['ora'];
}

$data .= '</tbody>
<tfoot><tr><td class="text-right"><strong>Totale:</strong></td><td class="text-center"><strong>'.$totale.'</strong></td></tr></tfoot>
</table></div>';

echo $data;
	

?>
