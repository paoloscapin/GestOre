<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$soloAttivi = $_GET["soloAttivi"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
            <tr>
                <th class="text-center col-md-8">Nome</th>
                <th class="text-center col-md-2">Citrix</th>
                <th class="text-center col-md-1">Attivo</th>
                <th class="text-center col-md-1">Modifica</th>
            </tr>';

$query = "	SELECT
				fuis_assegnato_tipo.id AS local_id,
				fuis_assegnato_tipo.*
			FROM fuis_assegnato_tipo
			";
if( $soloAttivi ) {
	$query .= "WHERE fuis_assegnato_tipo.attivo is true ";
}

$query .= "ORDER BY attivo DESC, nome ASC";

foreach(dbGetAll($query) as $row) {
    $statoMarker = '';
    if (! $row['attivo']) {
		$statoMarker = '<span class="label label-danger">disattivato</span>';
	}

    $data .= '
            <tr>
                <td>'.$row['nome'].'</td>
                <td>'.$row['codice_citrix'].'</td>
                <td class="text-center">'.$statoMarker.'</td>'
            ;
	$data .='
                <td>
                    <button onclick="fuisAssegnatoTipoGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
                    <button onclick="fuisAssegnatoTipoDelete('.$row['local_id'].', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
                </td>
            </tr>';
}

$data .= '
        </table></div>';
echo $data;
?>
