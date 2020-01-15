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
require_once '../common/__Minuti.php';
ruoloRichiesto('dirigente');
?>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<!-- Custom JS file moved to the end -->

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
		<div class="col-md-3">
			<span class="glyphicon glyphicon-list-alt"></span>&emsp;<strong>Ore Previste</strong>
		</div>
		<div class="col-md-3 text-center" id="totale_previste">
		</div>
		<div class="col-md-3 text-center" id="totale_previste_clil">
		</div>
		<div class="col-md-3 text-center" id="toggle_previste_clil">
            <div class="text-center">
                <?php if($__settings->config->gestioneClil) : ?>
				<label class="checkbox-inline">
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="ancheClilCheckBox" ><strong>Clil</strong>
				</label>
                <?php endif; ?>
            </div>
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
            <?php if($__settings->config->gestioneClil) : ?>
            <th class="text-center col-md-1">CLIL</th>
            <?php endif; ?>
            <?php if($__settings->config->gestioneClil) : ?>
            <th class="text-center col-md-1">Da Pagare CLIL</th>
            <?php endif; ?>
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
    docente.attivo = true
AND
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
$fuis_totale_previsto_clil = 0;
foreach($resultArray as $docente) {
    $docenteId = $docente['id'];
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];

    $dovute_con_studenti_total = $docente['ore_dovute_ore_70_con_studenti'] + $docente['ore_dovute_ore_40_con_studenti'];
    $previste_con_studenti_total = $docente['ore_previste_ore_70_con_studenti'] + $docente['ore_previste_ore_40_con_studenti'];

    // non considerato in previsioni !!
    $ore_sostituzioni =  $docente['ore_fatte_ore_40_sostituzioni_di_ufficio'] - $docente['ore_dovute_ore_40_sostituzioni_di_ufficio'];

    // totale
    $ore_funzionali = round($docente['ore_previste_ore_70_funzionali'] - $docente['ore_dovute_ore_70_funzionali']);
    $ore_con_studenti = round($previste_con_studenti_total - $dovute_con_studenti_total);

    // se si possono compensare in ore quelle mancanti funzionali con quelle fatte in piu' con studenti lo aggiorna ora
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
        if ($ore_funzionali < 0) {
			$daSpostare = -$ore_funzionali;
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if ($ore_con_studenti < $daSpostare) {
				$daSpostare = $ore_con_studenti;
			}
			$ore_con_studenti = $ore_con_studenti - $daSpostare;
			$ore_funzionali = $ore_funzionali + $daSpostare;
		}
    }

    // calcola gli importi fuis previsti per questo docente
    $fuis_funzionale_previsto = $ore_funzionali * $__settings->importi->oreFunzionali;
    $fuis_con_studenti_previsto = $ore_con_studenti * $__settings->importi->oreConStudenti;

	// se non configurato per compensare, i valori negativi devono essere azzerati (se ce ne sono...)
	if (!getSettingsValue('fuis','compensa_in_valore', false)) {
		$fuis_funzionale_previsto = max($fuis_funzionale_previsto, 0);
		$fuis_con_studenti_previsto = max($fuis_con_studenti_previsto, 0);
	}

    $fuis_docente_previsto = $fuis_funzionale_previsto + $fuis_con_studenti_previsto;
    // non si chiedono soldi indietro !!
    if ($fuis_docente_previsto < 0) {
        $fuis_docente_previsto = 0;
    }
    $fuis_totale_previsto = $fuis_totale_previsto + $fuis_docente_previsto;

    // aggiunge una stellina se qualcosa e' cambiato dall'ultimo controllo
    $marker = '';
    $ultimo_controllo = $docente['ore_previste_ultimo_controllo'];
    $q2 = "SELECT COUNT(ultima_modifica) from ore_previste_attivita WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId AND ultima_modifica > '$ultimo_controllo';";
    $numChanges = dbGetValue($q2);
    $marker = ($numChanges == 0) ? '': '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';

    // controlla le ore di clil
    $clil_funzionali_previste = 0;
    $clil_con_studenti_previste = 0;
    if ($__settings->config->gestioneClil) {
        $query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId
        AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'funzionali' ;";
        $clil_funzionali_previste=round(dbGetValue($query));

        $query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId
        AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'con studenti' ;";
        $clil_con_studenti_previste=round(dbGetValue($query));
    }
    $fuis_docente_previsto_clil = $clil_funzionali_previste * $__settings->importi->oreFunzionali + $clil_con_studenti_previste * $__settings->importi->oreConStudenti;
    if ($fuis_docente_previsto_clil > 0) {
        $fuis_totale_previsto_clil += $fuis_docente_previsto_clil;
    }

    echo '<tr>';
    echo '<td><a href="../docente/previste.php?docente_id='.$docenteId.'" target="_blank">&ensp;'.$docenteCognomeNome.' '.$marker.' </a></td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisualLimited($docente['ore_previste_ore_40_aggiornamento'],$docente['ore_dovute_ore_40_aggiornamento']).'</td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisual($docente['ore_fatte_ore_40_sostituzioni_di_ufficio'],$docente['ore_dovute_ore_40_sostituzioni_di_ufficio']).'</td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisual($docente['ore_previste_ore_70_funzionali'],$docente['ore_dovute_ore_70_funzionali']).'</td>';
    echo '<td class="text-left">'.getHtmlNumAndPrevisteVisual($previste_con_studenti_total,$dovute_con_studenti_total).'</td>';
    echo '<td class="text-center">'.(($fuis_docente_previsto > 0) ? $fuis_docente_previsto : '') . '</td>';
    if ($__settings->config->gestioneClil) {
        echo '<td class="text-left">'.getHtmlClil($clil_funzionali_previste,$clil_con_studenti_previste).'</td>';
        echo '<td class="text-center">'.(($fuis_docente_previsto_clil > 0) ? $fuis_docente_previsto_clil : '') .'</td>';
    }
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
<input type="hidden" id="hidden_fuis_totale_previsto_clil" value="<?php echo $fuis_totale_previsto_clil; ?>">

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/scriptPrevisteDirigente.js"></script>

</body>
</html>

<?php
$warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
$okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';

function getHtmlNum($value) {
    // arrotonda
    $value = round($value);
	return '&emsp;' . (($value >= 10) ? $value : '&ensp;' . $value);
}

function getHtmlNumAndPrevisteVisualLimited($value, $total) {
	global $okSymbol;
	global $warning;

    // arrotonda
    $value = round($value);
    $total = round($total);

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
 
    // arrotonda
    $value = round($value);
    $total = round($total);
   
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

function getHtmlClil($funzionali, $con_studenti) {
    // arrotonda
    $funzionali = round($funzionali);
    $con_studenti = round($con_studenti);

    if ($funzionali == 0 && $con_studenti == 0){
        return '';
    }

    $result = '';
        $result .= '&ensp;<span class="text-left">F='. (($funzionali >= 10) ? $funzionali : '&ensp;' . $funzionali) .'</span>';
        $result .= '&ensp;<span class="text-right">S='. (($con_studenti >= 10) ? $con_studenti : '&ensp;' . $con_studenti) .'</span>';
    return $result;
}

?>
