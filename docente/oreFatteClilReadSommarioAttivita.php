<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__i18n.php';

require_once '../common/connect.php';

$docente_id = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	$docente_id = $_POST['docente_id'];
}

$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-10 text-left">Tipo</th>
							<th class="col-md-2 text-center">Ore</th>
						</tr></thead><tbody>';

$query = "
    SELECT SUM(ore_fatte_attivita_clil.ore)
    FROM ore_fatte_attivita_clil
    WHERE
        ore_fatte_attivita_clil.docente_id = $docente_id
    AND
        ore_fatte_attivita_clil.anno_scolastico_id = $__anno_scolastico_corrente_id
    AND
        ore_fatte_attivita_clil.contestata is not true
    AND
        ore_fatte_attivita_clil.con_studenti = 1
    ";
$ore = dbGetValue($query);
if (!empty($ore)) {
    $data .= '<tr>
		<td>Con Studenti</td>
		<td>'.$ore.'</td>
		</tr>
		';
}

$query = "
    SELECT SUM(ore_fatte_attivita_clil.ore)
    FROM ore_fatte_attivita_clil
    WHERE
        ore_fatte_attivita_clil.docente_id = $docente_id
    AND
        ore_fatte_attivita_clil.anno_scolastico_id = $__anno_scolastico_corrente_id
    AND
        ore_fatte_attivita_clil.con_studenti = 0
    ";
$ore = dbGetValue($query);
if (!empty($ore)) {
    $data .= '<tr>
		<td>'.__("Funzionali").'</td>
		<td>'.$ore.'</td>
		</tr>
		';
}

$data .= '</tbody>';

$data .= '</table>
';
$data .= '</div>';

echo $data;

?>
