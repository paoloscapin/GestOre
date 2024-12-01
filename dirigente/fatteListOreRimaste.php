<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
?>

<!DOCTYPE html>
<html>
<head>
	<title>Ore Fatte</title>
<?php

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
//require_once '../common/_include_bootstrap-select.php';
require_once '../common/__Minuti.php';
require_once '../common/importi_load.php';
ruoloRichiesto('dirigente');

function trasformaFloatInStringa($oreFloat) {
    return $oreFloat;
}

function getHtmlNumAndPrevisteVisual($value, $total) {
    $warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
    $okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';
    
    $numString = ($value >= 10) ? trasformaFloatInStringa($value) : '&ensp;' . trasformaFloatInStringa($value);
    $numString = '';
	$diff = $total - $value;
	if ($diff > 0) {
		$numString .= '&ensp;<span class="label label-warning">- ' . trasformaFloatInStringa($diff) . '</span>';
	} else if ($diff < 0) {
			$numString .= '&ensp;<span class="label label-danger">+ ' . trasformaFloatInStringa(-$diff) . '</span>';
	} else {
		$numString .= $okSymbol;
	}
	return '&emsp;' . $numString;
}

function getHtmlNumAndFatteVisual($value, $total) {
    $warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
    $okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';
    
    $numString = ($value >= 10) ? trasformaFloatInStringa($value) : '&ensp;' . trasformaFloatInStringa($value);
    $numString = '';
	$diff = $total - $value;
	if ($diff > 0) {
		$numString .= '&ensp;<span class="label label-warning">- ' . trasformaFloatInStringa($diff) . '</span>';
	} else if ($diff < 0) {
			$numString .= '&ensp;<span class="label label-danger">+ ' . trasformaFloatInStringa(-$diff) . '</span>';
	} else {
		$numString .= $okSymbol;
	}
	return '&emsp;' . $numString;
}

?>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<!-- _utiljs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/_util.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<!-- Custom JS file moved to the end -->

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-teal4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-2">
			<span class="glyphicon glyphicon-folder-close"></span>&emsp;<strong>Ore Fatte</strong>
		</div>
		<div class="col-md-3 text-center" id="totale_fatte">
		</div>
		<div class="col-md-3 text-center" id="totale_fatte_clil">
		</div>
		<div class="col-md-3 text-center" id="totale_fatte_corsi_di_recupero">
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
            <button onclick="refreshPagina()" class="btn btn-xs btn-teal4"><span class="glyphicon glyphicon-refresh"></span></button>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
    <div class="col-md-12">
    <div class="table-wrapper"><table id="fatte_docenti_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-2">Docente</th>
            <th class="text-center col-md-1">Previste Funzionali</th>
            <th class="text-center col-md-1">Previste con Studenti</th>
            <th class="text-center col-md-1">Fatte Funzionali</th>
            <th class="text-center col-md-1">Fatte con Studenti</th>
            <th class="text-center col-md-1">Fatte totale</th>
		</tr>
    </thead>
    <tbody>

<?php
require_once '../docente/oreFatteAggiorna.php';

$fuis_totale_fatto = 0;
$fuis_totale_fatto_clil = 0;
$fuis_totale_corsi_di_recupero = 0;
foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY cognome,nome;") as $docente) {
    $docenteId = $docente['id'];
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];

    $ultimo_controllo = dbGetValue("SELECT ultimo_controllo FROM ore_fatte WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId;");
    $fuisFatto = oreFatteAggiorna(true, $docenteId, 'dirigente', $ultimo_controllo, true);

    $dovute_con_studenti_totale = $fuisFatto['oreConStudentiDovute'];
    $previste_con_studenti_totale = $fuisFatto['oreConStudentiPreviste'];
    $fatte_con_studenti_totale = $fuisFatto['oreConStudenti'];

    $ore_dovute_ore_70_funzionali = $fuisFatto['oreFunzionaliDovute'];
    $ore_previste_ore_70_funzionali = $fuisFatto['oreFunzionaliPreviste'];
    $ore_fatte_ore_70_funzionali = $fuisFatto['oreFunzionali'];

    $openTabMode = getSettingsValue('interfaccia','apriDocenteInNuovoTab', false) ? '_blank' : '_self';
    $marker = '';

    echo '<tr>';
    echo '<td><a href="../docente/attivita.php?docente_id='.$docenteId.'" target="'.$openTabMode.'">&ensp;'.$docenteCognomeNome.' '.$marker.' </a></td>';

    echo '<td class="text-center">'. getHtmlNumAndPrevisteVisual($ore_previste_ore_70_funzionali,$ore_dovute_ore_70_funzionali) . '</td>';
    echo '<td class="text-center">'. getHtmlNumAndPrevisteVisual($previste_con_studenti_totale,$dovute_con_studenti_totale) . '</td>';
    echo '<td class="text-center">'. getHtmlNumAndFatteVisual($ore_fatte_ore_70_funzionali,$ore_dovute_ore_70_funzionali) . '</td>';
    echo '<td class="text-center">'. getHtmlNumAndPrevisteVisual($fatte_con_studenti_totale,$dovute_con_studenti_totale) . '</td>';

    echo '<td class="text-center">'. getHtmlNumAndPrevisteVisual($fatte_con_studenti_totale + $ore_fatte_ore_70_funzionali,$dovute_con_studenti_totale + $ore_dovute_ore_70_funzionali) . '</td>';
    echo '</tr>';
}
?>
        </tbody>
        </table>
        </div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>

</body>
</html>
