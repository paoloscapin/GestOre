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
	<title>Ore Previste</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
//require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<script type="text/javascript" src="js/scriptPrevisteDirigente.js"></script>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-list-alt"></span>&emsp;<strong>Ore Previste</strong>
		</div>
		<div class="col-md-4 text-center" id="totale_previste">
		</div>
		<div class="col-md-4 text-center" id="totale_previste_clil">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
    <div class="col-md-12">
    <div class="table-wrapper"><table id="previste_docenti_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-2">Docente</th>
            <th class="text-center col-md-1">Aggiornamento</th>
            <th class="text-center col-md-1">Sostituzioni</th>
            <th class="text-center col-md-1">Funzionali</th>
            <th class="text-center col-md-1">Con Studenti</th>
            <th class="text-center col-md-1">Da Pagare</th>
		</tr>
    </thead>
    <tbody>

<?php
// $query = "SELECT * FROM docente WHERE docente.attivo = true ORDER BY cognome,nome; ";
$query = "
SELECT
	docente.*,
	
	ore_dovute.ore_40_sostituzioni_di_ufficio AS ore_dovute_ore_40_sostituzioni_di_ufficio,
	ore_dovute.ore_40_con_studenti AS ore_dovute_ore_40_con_studenti,
	ore_dovute.ore_40_aggiornamento AS ore_dovute_ore_40_aggiornamento,
	ore_dovute.ore_70_funzionali AS ore_dovute_ore_70_funzionali,
	ore_dovute.ore_70_con_studenti AS ore_dovute_ore_70_con_studenti,
	
	ore_previste.ore_40_sostituzioni_di_ufficio AS ore_previste_ore_40_sostituzioni_di_ufficio,
	ore_previste.ore_40_con_studenti AS ore_previste_ore_40_con_studenti,
	ore_previste.ore_40_aggiornamento AS ore_previste_ore_40_aggiornamento,
	ore_previste.ore_70_funzionali AS ore_previste_ore_70_funzionali,
	ore_previste.ore_70_con_studenti AS ore_previste_ore_70_con_studenti,
	ore_previste.ultimo_controllo AS ore_previste_ultimo_controllo,
	
	ore_fatte.ore_40_sostituzioni_di_ufficio AS ore_fatte_ore_40_sostituzioni_di_ufficio,
	ore_fatte.ore_40_con_studenti AS ore_fatte_ore_40_con_studenti,
	ore_fatte.ore_40_aggiornamento AS ore_fatte_ore_40_aggiornamento,
	ore_fatte.ore_70_funzionali AS ore_fatte_ore_70_funzionali,
	ore_fatte.ore_70_con_studenti AS ore_fatte_ore_70_con_studenti
	
	
FROM docente

INNER JOIN ore_dovute
ON ore_dovute.docente_id = docente.id

INNER JOIN ore_previste
ON ore_previste.docente_id = docente.id

INNER JOIN ore_fatte
ON ore_fatte.docente_id = docente.id

WHERE
	ore_dovute.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
	ore_previste.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
    ore_fatte.anno_scolastico_id = $__anno_scolastico_corrente_id
ORDER BY
    cognome,nome
";

$resultArray = dbGetAll($query);
$fuis_totale_previsto = 0;
foreach($resultArray as $docente) {
    $docenteId = $docente['id'];
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];

    $dovute_con_studenti_total = $docente['ore_dovute_ore_70_con_studenti'] + $docente['ore_dovute_ore_40_con_studenti'];
    $previste_con_studenti_total = $docente['ore_previste_ore_70_con_studenti'] + $docente['ore_previste_ore_40_con_studenti'];

    // non considerato in previsioni !!
    $ore_sostituzioni =  $docente['ore_fatte_ore_40_sostituzioni_di_ufficio'] - $docente['ore_dovute_ore_40_sostituzioni_di_ufficio'];

    // totale
    $ore_funzionali = $docente['ore_previste_ore_70_funzionali'] - $docente['ore_dovute_ore_70_funzionali'];
    $ore_con_studenti = $previste_con_studenti_total - $dovute_con_studenti_total;
//    $ore_con_studenti_vecchio = $docente['ore_previste_ore_70_con_studenti'] - $docente['ore_dovute_ore_70_con_studenti'];
//    info("docenteCognomeNome=$docenteCognomeNome ore_con_studenti__vecchio=$ore_con_studenti__vecchio ore_con_studenti=$ore_con_studenti");

    $fuis_funzionale_previsto = $ore_funzionali * $__settings->importi->oreFunzionali;
    $fuis_con_studenti_previsto = $ore_con_studenti * $__settings->importi->oreConStudenti;
    $fuis_docente_previsto = $fuis_funzionale_previsto + $fuis_con_studenti_previsto;
    // non si chiedono soldi indietro !!
    if ($fuis_docente_previsto < 0) {
        $fuis_docente_previsto = 0;
    }
    $fuis_totale_previsto = $fuis_totale_previsto + $fuis_docente_previsto;
    $marker = '';
    $ultimo_controllo = $docente['ore_previste_ultimo_controllo'];
    $q2 = "SELECT COUNT(ultima_modifica) from ore_previste_attivita WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId AND ultima_modifica > '$ultimo_controllo';";
    $numChanges = dbGetValue($q2);
    $marker = ($numChanges == 0) ? '': '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';

    echo '<tr>';
    echo '<td><a href="../docente/previste.php?docente_id='.$docenteId.'" target="_blank">&ensp;'.$docenteCognomeNome.' '.$marker.' </a></td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisualLimited($docente['ore_previste_ore_40_aggiornamento'],$docente['ore_dovute_ore_40_aggiornamento']).'</td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisual($docente['ore_fatte_ore_40_sostituzioni_di_ufficio'],$docente['ore_dovute_ore_40_sostituzioni_di_ufficio']).'</td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisual($docente['ore_previste_ore_70_funzionali'],$docente['ore_dovute_ore_70_funzionali']).'</td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisual($previste_con_studenti_total,$dovute_con_studenti_total).'</td>';
    echo '<td class="text-center">'.$fuis_docente_previsto.'</td>';
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
<input type="hidden" id="hidden_fuis_totale_previsto" value="<?php echo $fuis_totale_previsto; ?>">
</body>
</html>

<?php
$warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
$okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';

function getHtmlNum($value) {
	return '&emsp;' . (($value >= 10) ? $value : '&ensp;' . $value);
}

function getHtmlNumAndPrevisteVisualLimited($value, $total) {
	global $okSymbol;
	global $warning;

	$numString = ($value >= 10) ? $value : '&ensp;' . $value;
	$diff = $total - $value;
	if ($diff > 0) {
		$numString .= '&ensp;<span class="label label-warning">- '. $diff .'</span>';
	} else if ($diff < 0) {
		$numString .= '&ensp;<span class="label label-danger">+ '. (-$diff) .'</span>';
	} else {
		$numString .= $okSymbol;
	}
	return '&emsp;' . $numString;
}

function getHtmlNumAndPrevisteVisual($value, $total) {
    global $okSymbol;
    global $warning;
    
    $numString = ($value >= 10) ? $value : '&ensp;' . $value;
    $diff = $total - $value;
    if ($diff > 0) {
        $numString .= '&ensp;<span class="label label-warning">- '. $diff .'</span>';
    } else if ($diff < 0) {
        $numString .= '&ensp;<span class="label label-danger">+ '. (-$diff) .'</span>';
    } else {
        $numString .= $okSymbol;
    }
    return '&emsp;' . $numString;
}
?>
