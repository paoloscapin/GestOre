<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html>
<head>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');
?>
	<title>Bonus Docente Selezione</title>
</head>

<body >
<?php
require_once '../common/header-docente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<?php
// <button onclick="saveBonusSelection()" class="btn btn-info"><span class="glyphicon glyphicon-cog"></span></button>

// prima mi faccio una lista di id che questo docente ha attivato:
$bonus_id_array = [];
$adesione_id_array = [];
$query = "SELECT id, bonus_id from bonus_docente WHERE bonus_docente.docente_id = $__docente_id AND bonus_docente.anno_scolastico_id = $__anno_scolastico_corrente_id";
$res0 = dbGetAll($query);
foreach($res0 as $bonus_docente) {
    $bonus_id_array[] = $bonus_docente['bonus_id'];
    $adesione_id_array[] = $bonus_docente['id'];
    debug($bonus_docente['bonus_id']);
}

// scandisco la lista delle aree e di ciascuna faccio un panel
$data = '';
$query = "SELECT * from bonus_area WHERE valido = true ORDER BY codice;";
$resultArray = dbGetAll($query);
foreach($resultArray as $bonus_area) {
    $data .= '
        <div class="panel panel-lima4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1">
                        <span class="glyphicon glyphicon-list-alt"></span>&ensp;'.$bonus_area['codice'].'
                    </div>
                    <div class="col-md-10 text-left">
                        <span style="white-space: pre-line">'.$bonus_area['descrizione'].'</span>
                    </div>
                    <div class="col-md-1 text-right">
                    </div>
        		</div>
        	</div>
            <div class="panel-body">
    ';
    
    // scandisco la lista degli indicatori e per ciascuno inizio una table
    $query = 'SELECT * from bonus_indicatore WHERE valido = true AND bonus_area_id = '.$bonus_area['id'].' ORDER BY codice;';
    $res2 = dbGetAll($query);
    foreach($res2 as $bonus_indicatore) {
        $data .= '
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-12">
            			<div class="table-wrapper">
            				<table class="table table-bordered table-striped table-green" id="bonus_selection_table">
            					<thead>
            						<tr>
                                    <th></th><th></th><th colspan="5" class="text-left"><span style="white-space: pre-line">'.$bonus_indicatore['codice'].' - '.$bonus_indicatore['descrizione'].'</span></th>
                                    </tr>
            						<tr>
                						<th class="text-center col-md-1">bonus_id</th>
                						<th class="text-center col-md-1">adesione_id</th>
                						<th class="text-center col-md-1">Codice</th>
                						<th class="text-center col-md-4">Descrittore</th>
                						<th class="text-center col-md-5">Evidenze</th>
                						<th class="text-center col-md-1">Valore</th>
                						<th class="text-center col-md-1"></th>
            						</tr>
            					</thead>
            					<tbody>
            ';
        
        // ciascun bonus di questo indicatore va in una riga table
        $query = 'SELECT * from bonus WHERE valido = true AND bonus_indicatore_id = '.$bonus_indicatore['id'].' ORDER BY codice;';
        $res3 = dbGetAll($query);
        foreach($res3 as $bonus) {
            $bonus_id = $bonus['id'];
            $data .= '
                                    <tr>
                                        <td>'.$bonus_id.'</td>
    	   ';
            // se sta nell'array deve avere un corrispondente id dell'adesione
            if (in_array($bonus_id, $bonus_id_array)) {
                $pos = array_search($bonus_id, $bonus_id_array, true);
                $adesione_id = $adesione_id_array[$pos];
                $data .= '
                                        <td>'.$adesione_id.'</td>
        	   ';
            } else {
                $data .= '
                                        <td>-1</td>
        	   ';
            }
            $data .= '
                                        <td class="text-center">'.$bonus['codice'].'</td>
                                        <td class="text-left"><span style="white-space: pre-line">'.$bonus['descrittori'].'</span></td>
                                        <td class="text-left"><span style="white-space: pre-line">'.$bonus['evidenze'].'</span></td>
                                        <td class="text-center">'.$bonus['valore_previsto'].'</td>
    	   ';
            
            
            $data .= '<td class="text-center"><input type="checkbox" data-toggle="toggle" data-onstyle="primary" id="richiesto" ';
            if (in_array($bonus_id, $bonus_id_array)) {
                $data .= 'checked ';
            }
            $data .= '></td>
					</tr>
					';
        }
        $data .= '
                					</tbody>
                				</table>
                	        </div>
                        </div>
                    </div>
        ';
    }
    $data .= '
                </div>
                </div>
    	   ';
}
echo $data;
?>
</div>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptBonus.js"></script>

</body>
</html>