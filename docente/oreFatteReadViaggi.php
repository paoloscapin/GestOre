<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$modificabile = $__config->getOre_fatte_aperto();

$docente_id = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	$docente_id = $_POST['docente_id'];
	$modificabile = false;
}

$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-7 text-left">Destinazione</th>
							<th class="col-md-1 text-center">Data</th>
							<th class="col-md-1 text-center">Ore</th>
							<th class="col-md-3 text-center"></th>
						</tr></thead><tbody>';

$query = "	SELECT
					viaggio_ore_recuperate.id AS viaggio_ore_recuperate_id,
					viaggio_ore_recuperate.ore AS viaggio_ore_recuperate_ore,
					viaggio.destinazione AS viaggio_destinazione,
					viaggio.data_partenza AS viaggio_data_partenza

				FROM viaggio_ore_recuperate viaggio_ore_recuperate
				INNER JOIN viaggio viaggio
				ON viaggio_ore_recuperate.viaggio_id = viaggio.id
				WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND viaggio.docente_id = $docente_id
				ORDER BY
					viaggio.data_partenza DESC
				"
				;
				
if (!$result = mysqli_query($con, $query)) {
    exit(mysqli_error($con));
}

// if query results contains rows then fetch those rows
if(mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        //			console_log_data("docente=", $row);
        $data .= '<tr>
                <td>'.$row['viaggio_destinazione'].'</td>
    			<td class="text-center">'.strftime("%d/%m/%Y", strtotime($row['viaggio_data_partenza'])).'</td>
    			<td class="text-center">'.$row['viaggio_ore_recuperate_ore'].'</td>
                ';
        
        $data .='
                <td></td>
                </tr>';
    }
} else {
    // records now found
    $data .= '<tr><td colspan="5">Nessun viaggio</td></tr>';
}

$data .= '</tbody></table></div>';

echo $data;
	

?>
