<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';

$gruppo_id = $_GET["gruppo_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th>Data</th>
						<th>Ora</th>
						<th>Stato</th>
						<th class="text-center">-</th>
					</tr>
					</thead>';

$query = "	SELECT * from gruppo_incontro WHERE gruppo_id = $gruppo_id order by data DESC;";

foreach(dbGetAll($query) as $gruppo_incontro) {
    $oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
    $dataIncontro = utf8_encode( strftime("%d %B %Y", strtotime($gruppo_incontro['data'])));
    setlocale(LC_TIME, $oldLocale);

    $statoMarker = ($gruppo_incontro['effettuato'] == 1) ? '<span class="label label-success">effettuato</span>' : '<span class="label label-primary">pendente</span>';

    $data .= '<tr>
    <td>'.$dataIncontro.'</td>
    <td>'.$gruppo_incontro['ora'].'</td>
    <td>'.$statoMarker.'</td>
    ';
    $data .='
        <td class="text-center">
            <button onclick="gruppoIncontroGetDetails('.$gruppo_incontro['id'].', \''.$gruppo_incontro['gruppo_id'].'\', \''.$gruppo_incontro['data'].'\', \''.$gruppo_incontro['ora'].'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
            <button onclick="gruppoIncontroDelete('.$gruppo_incontro['id'].', \''.$gruppo_incontro['gruppo_id'].'\', \''.$gruppo_incontro['data'].'\', \''.$gruppo_incontro['ora'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
        </td>
        </tr>';
}
$data .= '</table></div>';

echo $data;
?>
