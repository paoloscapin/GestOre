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
require_once '../common/importi_load.php';
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
		<div class="col-md-2">
			<span class="glyphicon glyphicon-dashboard"></span>&emsp;<strong>Ore Previste</strong>
		</div>
		<div class="col-md-2 text-center" id="totale_previste">
		</div>
		<div class="col-md-2 text-center" id="totale_previste_clil">
		</div>
		<div class="col-md-2 text-center" id="totale_previste_orientamento">
		</div>
		<div class="col-md-3 text-center" id="totale_previste_corsi_di_recupero">
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
            <button onclick="refreshPagina()" class="btn btn-xs btn-orange4"><span class="glyphicon glyphicon-refresh"></span></button>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
    <div class="col-md-12">
    <div class="table-wrapper"><table id="previste_docenti_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-1">Docente</th>
            <th class="text-center col-md-1">Diaria</th>
            <th class="text-center col-md-1">Assegnato</th>
            <th class="text-center col-md-1">Ore</th>
            <?php if($__settings->config->gestioneClil) : ?>
                <th class="text-center col-md-1">Clil</th>
            <?php else: ?>
                <!-- <th class="text-center col-md-1"h></th> -->
            <?php endif; ?>
            <?php if($__settings->config->gestioneOrientamento) : ?>
                <th class="text-center col-md-1">Orientamento</th>
            <?php else: ?>
                <!-- <th class="text-center col-md-1"h></th> -->
            <?php endif; ?>
            <th class="text-center col-md-1">Corsi di Recupero</th>
		</tr>
    </thead>
    <tbody>
<?php
require_once '../docente/oreFatteAggiorna.php';

$fuis_totale_previsto = 0;
$fuis_totale_previsto_clil = 0;
$fuis_totale_previsto_orientamento = 0;
$fuis_totale_corsi_di_recupero = 0;
foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY cognome,nome;") as $docente) {
    $docenteId = $docente['id'];
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];

    // aggiunge una stellina se qualcosa e' cambiato dall'ultimo controllo
    $ultimo_controllo = dbGetValue("SELECT ultimo_controllo FROM ore_previste WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId;");
    $marker = '';

    // controlla se e' cambiato qualcosa nelle ore previste, nelle clil, nei viaggi
    $numChanges = dbGetValue("SELECT COUNT(ultima_modifica) from ore_previste_attivita WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId AND ultima_modifica > '$ultimo_controllo';");
    $marker = ($numChanges == 0) ? '': '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';

    $openTabMode = getSettingsValue('interfaccia','apriDocenteInNuovoTab', false) ? '_blank' : '_self';

    $fuisPrevisto = oreFatteAggiorna(true, $docenteId, 'dirigente', $ultimo_controllo, true);

    echo '<tr>';
    echo '<td><a href="../docente/previste.php?docente_id='.$docenteId.'" target="'.$openTabMode.'">&ensp;'.$docenteCognomeNome.' '.$marker.' </a></td>';

    echo '<td class="text-right">'.importoStampabile($fuisPrevisto['diariaImportoPreviste']).'</td>';
    echo '<td class="text-right">'.importoStampabile($fuisPrevisto['fuisAssegnato']).'</td>';
    echo '<td class="text-right">'.importoStampabile($fuisPrevisto['fuisOrePreviste']).'</td>';
    if (getSettingsValue("config", "gestioneClil", false)) {
        echo '<td class="text-right">'.importoStampabile($fuisPrevisto['fuisClilFunzionalePreviste'] + $fuisPrevisto['fuisClilConStudentiPreviste']).'</td>';
    } else {
        //echo '<td></td>';
    }
    if (getSettingsValue("config", "gestioneOrientamento", false)) {
        echo '<td class="text-right">'.importoStampabile($fuisPrevisto['fuisOrientamentoFunzionalePreviste'] + $fuisPrevisto['fuisOrientamentoConStudentiPreviste']).'</td>';
    } else {
        //echo '<td></td>';
    }
    echo '<td>'.importoStampabile($fuisPrevisto['fuisExtraCorsiDiRecupero']).'</td>';

    $fuis_totale_previsto = $fuis_totale_previsto + $fuisPrevisto['diariaImportoPreviste'] + $fuisPrevisto['fuisAssegnato'] + $fuisPrevisto['fuisOrePreviste'];
    $fuis_totale_previsto_clil = $fuis_totale_previsto_clil + $fuisPrevisto['fuisClilFunzionalePreviste'] + $fuisPrevisto['fuisClilConStudentiPreviste'];
    $fuis_totale_previsto_orientamento = $fuis_totale_previsto_orientamento + $fuisPrevisto['fuisOrientamentoFunzionalePreviste'] + $fuisPrevisto['fuisOrientamentoConStudentiPreviste'];
    $fuis_totale_corsi_di_recupero = $fuis_totale_corsi_di_recupero + $fuisPrevisto['fuisExtraCorsiDiRecupero'];

    // se la scuola paga i corsi di recupero extra, questi vanno aggiunti nel totale delle ore
    if (! getSettingsValue("corsiDiRecupero", "corsiDiRecuperoPagatiDaProvincia", true)) {
        $fuis_totale_previsto = $fuis_totale_previsto + $fuisPrevisto['fuisExtraCorsiDiRecupero'];
    }
}

function importoStampabile($importo) {
    if ($importo == 0) {
        return "";
    }
    return number_format($importo, 2,",",".");
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
<input type="hidden" id="hidden_fuis_totale_previsto_orientamento" value="<?php echo $fuis_totale_previsto_orientamento; ?>">
<input type="hidden" id="hidden_fuis_totale_corsi_di_recupero" value="<?php echo $fuis_totale_corsi_di_recupero; ?>">
<input type="hidden" id="hidden_fuis_budget" value="<?php echo $__importi['fuis']; ?>">
<input type="hidden" id="hidden_fuis_budget_clil" value="<?php echo $__importi['fuis_clil']; ?>">
<input type="hidden" id="hidden_fuis_budget_orientamento" value="<?php echo $__importi['fuis_orientamento']; ?>">
<input type="hidden" id="hidden_corsi_di_recupero_pagati_da_provincia" value="<?php echo (getSettingsValue("corsiDiRecupero", "corsiDiRecuperoPagatiDaProvincia", true)? "1": "0"); ?>">

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/scriptPrevisteDirigente.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>

<?php
$warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
$okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';
?>
