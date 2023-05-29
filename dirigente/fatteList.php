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
	<title>Ore Fatte</title>
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
            <th class="text-center col-md-1">Diaria</th>
            <th class="text-center col-md-1">Assegnato</th>
            <th class="text-center col-md-1">Ore</th>
            <?php if($__settings->config->gestioneClil) : ?>
                <th class="text-center col-md-1">Clil Funzionale</th>
                <th class="text-center col-md-1">Clil con Studenti</th>
            <?php else: ?>
                <th></th>
                <th></th>
            <?php endif; ?>
            <th class="text-center col-md-1">Corsi di Recupero</th>
		</tr>
    </thead>
    <tbody>

<?php
require_once '../dirigente/fuisFatteCalcolaDocente.php';

$fuis_totale_fatto = 0;
$fuis_totale_fatto_clil = 0;
$fuis_totale_corsi_di_recupero = 0;
foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY cognome,nome;") as $docente) {
    $docenteId = $docente['id'];
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];

    // aggiunge una stellina se qualcosa e' cambiato dall'ultimo controllo
    $ultimo_controllo = dbGetValue("SELECT ultimo_controllo FROM ore_fatte WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId;");
    $marker = '';

    // controlla se e' cambiato qualcosa nelle ore fatte, nelle clil, nei viaggi
    $numChanges = dbGetValue("SELECT COUNT(ultima_modifica) from ore_fatte_attivita WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId AND ultima_modifica > '$ultimo_controllo';");
    if ($numChanges == 0) {
        $numChanges = dbGetValue("SELECT COUNT(ultima_modifica) from ore_fatte_attivita_clil WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId AND ultima_modifica > '$ultimo_controllo';");
    }
    if ($numChanges == 0) {
        $numChanges = dbGetValue("SELECT COUNT(ultima_modifica) from viaggio_diaria_fatta WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId AND ultima_modifica > '$ultimo_controllo';");
    }

    $marker = ($numChanges == 0) ? '': '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';

    $openTabMode = getSettingsValue('interfaccia','apriDocenteInNuovoTab', false) ? '_blank' : '_self';

    $fuisFatto = calcolaFuisDocente($docenteId);

    echo '<tr>';
    echo '<td><a href="../docente/attivita.php?docente_id='.$docenteId.'" target="'.$openTabMode.'">&ensp;'.$docenteCognomeNome.' '.$marker.' </a></td>';

    echo '<td class="text-left">'.importoStampabile($fuisFatto['diaria']).'</td>';
    echo '<td class="text-left">'.importoStampabile($fuisFatto['assegnato']).'</td>';
    echo '<td class="text-left">'.importoStampabile($fuisFatto['ore']).'</td>';
    if (getSettingsValue("config", "gestioneClil", false)) {
        echo '<td class="text-left">'.importoStampabile($fuisFatto['clilFunzionale']).'</td>';
        echo '<td class="text-left">'.importoStampabile($fuisFatto['clilConStudenti']).'</td>';
    } else {
        echo '<td></td><td></td>';
    }
    echo '<td>'.importoStampabile($fuisFatto['extraCorsiDiRecupero']).'</td>';

    $fuis_totale_fatto = $fuis_totale_fatto + $fuisFatto['diaria'] + $fuisFatto['assegnato'] + $fuisFatto['ore'];
    $fuis_totale_fatto_clil = $fuis_totale_fatto_clil + $fuisFatto['clilFunzionale'] + $fuisFatto['clilConStudenti'];
    $fuis_totale_corsi_di_recupero = $fuis_totale_corsi_di_recupero + $fuisFatto['extraCorsiDiRecupero'];

    // se la scuola paga i corsi di recupero extra, questi vanno aggiunti nel totale delle ore
    if (! getSettingsValue("corsiDiRecupero", "corsiDiRecuperoPagatiDaProvincia", true)) {
        $fuis_totale_fatto = $fuis_totale_fatto + $fuisFatto['extraCorsiDiRecupero'];
    }
}

function importoStampabile($importo) {
    if ($importo == 0) {
        return "";
    }
    return number_format($importo, 2);
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
<input type="hidden" id="hidden_fuis_totale_fatto" value="<?php echo $fuis_totale_fatto; ?>">
<input type="hidden" id="hidden_fuis_totale_fatto_clil" value="<?php echo $fuis_totale_fatto_clil; ?>">
<input type="hidden" id="hidden_fuis_totale_corsi_di_recupero" value="<?php echo $fuis_totale_corsi_di_recupero; ?>">
<input type="hidden" id="hidden_fuis_budget" value="<?php echo $__importi['fuis']; ?>">
<input type="hidden" id="hidden_fuis_budget_clil" value="<?php echo $__importi['fuis_clil']; ?>">
<input type="hidden" id="hidden_corsi_di_recupero_pagati_da_provincia" value="<?php echo (getSettingsValue("corsiDiRecupero", "corsiDiRecuperoPagatiDaProvincia", true)? "1": "0"); ?>">

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/scriptFatteDirigente.js"></script>

</body>
</html>

<?php
$warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
$okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';
?>
