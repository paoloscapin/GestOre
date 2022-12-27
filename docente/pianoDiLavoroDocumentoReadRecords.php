<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(! isset($_GET)) {
	return;
} else {
	$piano_di_lavoro_id = $_GET['piano_di_lavoro_id'];
}

$query = "	SELECT
				piano_di_lavoro_contenuto.id AS piano_di_lavoro_contenuto_id,
				piano_di_lavoro_contenuto.titolo AS piano_di_lavoro_contenuto_titolo,
				piano_di_lavoro_contenuto.testo AS piano_di_lavoro_contenuto_testo,
				piano_di_lavoro_contenuto.posizione AS piano_di_lavoro_contenuto_posizione
			FROM piano_di_lavoro_contenuto
			WHERE piano_di_lavoro_id = $piano_di_lavoro_id
			ORDER BY piano_di_lavoro_contenuto.posizione ASC;";

$data = '';
$counter = 0;

$rowList = dbGetAll($query);
$numContenuti = count($rowList);

foreach($rowList as $row) {
	++$counter;
	$pianoDiLavoroContenutoId = $row['piano_di_lavoro_contenuto_id'];
	$piano_di_lavoro_contenuto_posizione = $row['piano_di_lavoro_contenuto_posizione'];
	$data .= '
<div class="panel panel-teal4">
<div class="panel-heading container-fluid">
<div class="row">
<div class="col-md-1"><strong>Modulo '.$row['piano_di_lavoro_contenuto_posizione'].'</strong></div>
<div class="col-md-10"><strong>'.$row['piano_di_lavoro_contenuto_titolo'].'</strong></div>
<div class="col-md-1 text-right"><div class="pull-right">
	<button class="btn btn-xs btn-teal4" onclick="pianoDiLavoroDocumentoRemove('.$pianoDiLavoroContenutoId.', '.$piano_di_lavoro_contenuto_posizione.')"><span class="glyphicon glyphicon-remove"></span>&nbsp;elimina</button>
	<button class="btn btn-xs btn-teal4" onclick="moveUp('.$piano_di_lavoro_contenuto_posizione.')" '. (($counter==1)?'disabled':'') .'><span class="glyphicon glyphicon-chevron-up"></span></button>
	<button class="btn btn-xs btn-teal4" onclick="moveDown('.$piano_di_lavoro_contenuto_posizione.')" '. (($counter==$numContenuti)?'disabled':'') .'><span class="glyphicon glyphicon-chevron-down"></span></button>
</div></div>
</div>
</div>
<div class="panel-body">
<div class="col-md-12">
'.$row['piano_di_lavoro_contenuto_testo'].'
</div>
</div>
<div class="panel-footer text-center">
<button onclick="pianoDiLavoroDocumentoGetDetails('.$pianoDiLavoroContenutoId.')" class="btn btn-xs btn-teal4"';
	// solo per disabilitare la modifica se non autorizzati, poi vedremo come calcolarlo
	if ($pianoDiLavoroContenutoId == -1) {
		$data .= ' disabled';
	}
	$data .= '><span class="glyphicon glyphicon-pencil"> Modifica</button>
</div>
</div>
';
}
echo $data;
?>
