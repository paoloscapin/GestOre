<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$soloValidi = $_GET["soloValidi"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
            <tr>
                <th class="text-center col-md-9">Modulo</th>
                <th class="text-center col-md-1">Valido</th>
                <th class="text-center col-md-1">Modifica</th>
                <th class="text-center col-md-1"></th>
            </tr>';

$query = "	SELECT modulistica_template.id AS local_id, modulistica_template.* FROM modulistica_template ";
if( $soloValidi ) {
	$query .= "WHERE modulistica_template.valido is true ";
}

$query .= "ORDER BY valido DESC, nome ASC;";

foreach(dbGetAll($query) as $row) {
    $statoMarker = '';
    if (! $row['valido']) {
		$statoMarker = '<span class="label label-danger">disattivato</span>';
	} else {
        $statoMarker = '<span class="label label-success">si</span>';
    }

    $data .= '
            <tr>
                <td>'.$row['nome'].'</td>
                <td class="text-center">'.$statoMarker.'</td>
                <td class="text-center">
                    <button onclick="modulisticaOpenTemplate('.$row['local_id'].')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-file">&nbsp;Contenuto</span></button>
                </td>
                <td>
                    <button onclick="modulisticaGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
                    <button onclick="modulisticaDelete('.$row['local_id'].', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
                </td>
            </tr>';
}

$data .= '
        </table></div>';
echo $data;
?>
