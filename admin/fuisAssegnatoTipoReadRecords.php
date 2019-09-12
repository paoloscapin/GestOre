<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
            <tr>
                <th>Nome</th>
                <th>Modifica</th>
            </tr>';

$query = "	SELECT
				fuis_assegnato_tipo.id AS local_id,
				fuis_assegnato_tipo.*
			FROM fuis_assegnato_tipo
			";

$query .= "order by nome";

foreach(dbGetAll($query) as $row) {
    $data .= '
            <tr>
                <td>'.$row['nome'].'</td>';
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

