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
//require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
require_once '../common/__Minuti.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');
?>
	<title>Ore Fatte</title>
</head>

<body >
<?php
// default opera sul docente connesso e agisce come docente
$docente_id = $__docente_id;
$operatore = 'docente';

if(isset($_GET['docente_id']) && isset($_GET['docente_id']) != "") {
	// se specificato il docente id nel get, devi essere dirigente
	ruoloRichiesto('dirigente');

	// agisci quindi come dirigente
	$operatore = 'dirigente';

	// simula l'utente in modo che il menu poi il menu docenti si comporti correttamente
	$docente_id = $_GET['docente_id'];
	$query = "SELECT * FROM docente WHERE docente.id = '$docente_id'";
	$result = dbGetFirst($query);
    if ($result != null) {
        $session->set('docente_id', $result['id']);
        $session->set('docente_nome', $result['nome']);
		$session->set('docente_cognome', $result['cognome']);
		$__docente_id = $result['id'];
		$__docente_nome = $result['nome'];
		$__docente_cognome = $result['cognome'];
	}
	$ultimo_controllo = dbGetValue("SELECT ultimo_controllo FROM ore_fatte WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
    debug('ultimo_controllo=' . $ultimo_controllo);
}

require_once '../common/header-docente.php';
?>

<div class="container-fluid" style="margin-top:60px">

<?php if($operatore == 'dirigente') : ?>
<!-- pannello fuis per dirigente -->
<div class="panel panel-deeporange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-2">
			<span class="glyphicon glyphicon-euro"></span>&emsp;FUIS
		</div>
		<div class="col-md-2 text-center">
			<button onclick="fatteChiudi()" class="btn btn-deeporange4 btn-xs"><span class="glyphicon glyphicon-off"> Chiudi</button>
		</div>
		<div class="col-md-2 text-center">
			<button onclick="fatteRivisto()" class="btn btn-lima4 btn-xs"><span class="glyphicon glyphicon-ok"> Rivisto</button>
		</div>
		<div class="col-md-2 text-center">
			<button onclick="fatteEmail()" class="btn btn-lightblue4 btn-xs"><span class="glyphicon glyphicon-envelope"> Notifica Docente</button>
		</div>
		<div class="col-md-2 text-center">
		</div>
		<div class="col-md-2 text-right">
		</div>
	</div>
</div>
<div class="panel-body">

<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-2"></th>
				<th class="col-md-1 text-right">Fuis Docente</th>
				<th class="col-md-1"></th>
				<th class="col-md-2"></th>
				<th class="col-md-1 text-right">Fuis CLIL</th>
				<th class="col-md-1"></th>
				<th class="col-md-2"></th>
				<th class="col-md-1 text-right">Corsi di Recupero</th>
				<th class="col-md-1"></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="col-md-2 text-right" ><?php echoLabel('Assegnato');?></td>
				<td class="col-md-1 text-right" id="fuis_assegnato">0.00</td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ><?php echoLabel('Funzionali');?></td>
				<td class="col-md-1 text-right" id="fuis_clil_funzionali">0.00</td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ><?php echoLabel('Ore Extra');?></td>
				<td class="col-md-1 text-right" id="fuis_corsi_di_recupero">0.00</td>
				<td class="col-md-1 text-right"></td>
			</tr>
			<tr>
				<td class="col-md-2 text-right" ><?php echoLabel('Ore');?></td>
				<td class="col-md-1 text-right" id="fuis_ore">0.00</td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ><?php echoLabel('Con Studenti');?></td>
				<td class="col-md-1 text-right" id="fuis_clil_con_studenti">0.00</td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ></td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-1 text-right"></td>
			</tr>
			<tr>
				<td class="col-md-2 text-right" ><?php echoLabel('Diaria Viaggi');?></td>
				<td class="col-md-1 text-right" id="fuis_diaria">0.00</td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ></td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ></td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-1 text-right"></td>
			</tr>
		</tbody>
		<tfooter>
			<tr class="deeporange5">
				<td class="col-md-2 text-right" ><strong><?php echoLabel('Totale');?></strong></td>
				<td class="col-md-1 text-right" id="fuis_docente_totale"><strong>0.00</strong></td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ><strong><?php echoLabel('Totale');?></strong></td>
				<td class="col-md-1 text-right" id="fuis_clil_totale"><strong>0.00</strong></td>
				<td class="col-md-1 text-right"></td>
				<td class="col-md-2 text-right" ><strong><?php echoLabel('Totale');?></strong></td>
				<td class="col-md-1 text-right" id="fuis_corsi_di_recupero_totale"><strong>0.00</strong></td>
				<td class="col-md-1 text-right"></td>
			</tr>
		<tfooter>
	</table>
	</div>
	<div id="fuis_message" class="row" style="margin-bottom:10px;"></div>
	<div id="fuis_messageEccesso" class="row" style="margin-bottom:10px;"></div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<?php endif; ?>

<div class="panel panel-lima4">
<div class="panel-heading">
	<span class="glyphicon glyphicon-time"></span>
	<a data-toggle="collapse" href="#collapse_40">&ensp;40+70 ore </a>
</div>
<div id="collapse_40" class="panel-collapse collapse  collapse in">
<div class="panel-body">

	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-1"></th>
				<?php if(getSettingsValue('interfaccia','visualizzaSostituzioniDocenteInFatte', false)) : ?>
					<th class="col-md-1">Sostituzioni</th>
				<?php else : ?>
					<th class="col-md-1"></th>
				<?php endif; ?>
				<?php if(getSettingsValue('interfaccia','visualizzaAggiornamento', true)) : ?>
					<th class="col-md-1 text-left">Aggiornamento</th>
				<?php else : ?>
					<th class="col-md-1"></th>
				<?php endif; ?>
				<th class="col-md-1"></th>
				<th class="col-md-2 text-left"><?php echoLabel('Funzionali');?></th>
				<th class="col-md-2 text-left">con Studenti</th>
				<th class="col-md-2 text-left"><span class="clil hidden">CLIL (<?php echoLabel('Funzionali');?>)</span></th>
				<th class="col-md-2 text-left"><span class="clil hidden">CLIL (con Studenti)</span></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echoLabel('dovute');?></td>
				<?php if(getSettingsValue('interfaccia','visualizzaSostituzioniDocenteInFatte', false)) : ?>
					<td class="text-left" id="dovute_ore_40_sostituzioni_di_ufficio"></td>
				<?php else : ?>
					<td></td>
				<?php endif; ?>
				<?php if(getSettingsValue('interfaccia','visualizzaAggiornamento', true)) : ?>
					<td class="text-left" id="dovute_ore_40_aggiornamento"></td>
				<?php else : ?>
					<td></td>
				<?php endif; ?>
				<td></td>
				<td class="text-left" id="dovute_ore_70_funzionali"></td>
				<td class="text-left" id="dovute_totale_con_studenti"></td>
				<td class="text-left" ></td>
				<td class="text-left" ></td>
			</tr>
			<tr class="orange5">
				<td>previste</td>
				<td></td>
				<?php if(getSettingsValue('interfaccia','visualizzaAggiornamento', true)) : ?>
					<td class="text-left" id="previste_ore_40_aggiornamento"></td>
				<?php else : ?>
					<td></td>
				<?php endif; ?>
				<td></td>
				<td class="text-left" id="previste_ore_70_funzionali"></td>
				<td class="text-left" id="previste_totale_con_studenti"></td>
				<td class="text-left clil" id="clil_previste_funzionali"></td><td class="NOclil"></td>
				<td class="text-left clil" id="clil_previste_con_studenti"></td><td class="NOclil"></td>
			</tr>
			<tr class="teal5">
				<td>fatte</td>
				<?php if(getSettingsValue('interfaccia','visualizzaSostituzioniDocenteInFatte', false)) : ?>
					<td class="text-left" id="fatte_ore_40_sostituzioni_di_ufficio"></td>
				<?php else : ?>
					<td></td>
				<?php endif; ?>
				<?php if(getSettingsValue('interfaccia','visualizzaAggiornamento', true)) : ?>
					<td class="text-left" id="fatte_ore_40_aggiornamento"></td>
				<?php else : ?>
					<td></td>
				<?php endif; ?>
				<td></td>
				<td class="text-left" id="fatte_ore_70_funzionali"></td>
				<td class="text-left" id="fatte_totale_con_studenti"></td>
				<td class="text-left clil" id="clil_fatte_funzionali"></td><td class="NOclil"></td>
				<td class="text-left clil" id="clil_fatte_con_studenti"></td><td class="NOclil"></td>
			</tr>
		</tbody>
	</table>
	</div>
	<div id="ore_message" class="row" style="margin-bottom:10px;"></div>
	<input type="hidden" id="accetta_con_studenti_per_funzionali" value="<?php if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {echo('1');} else {echo('0');} ?>">
	<input type="hidden" id="accetta_funzionali_per_con_studenti" value="<?php if (getSettingsValue('fuis','accetta_funzionali_per_con_studenti', false)) {echo('1');} else {echo('0');} ?>">
	<div id="ore_eccesso_message" class="row" style="margin-bottom:10px;"></div>
	<input type="hidden" id="segnala_fatte_eccedenti_previsione" value="<?php if (getSettingsValue('fuis','segnala_fatte_eccedenti_previsione', false)) {echo('1');} else {echo('0');} ?>">
</div>
<input type="hidden" id="hidden_docente_id" value="<?php echo $docente_id; ?>">
<input type="hidden" id="hidden_operatore" value="<?php echo $operatore; ?>">
<input type="hidden" id="hidden_ultimo_controllo" value="<?php echo $ultimo_controllo; ?>">
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<div class="panel panel-teal4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-folder-close"></span>&ensp;Attività Fatte
		</div>
		<div class="col-md-4 text-center">
			<button onclick="oreFatteSommario()" class="btn btn-xs btn-teal4"><span class="glyphicon glyphicon-option-horizontal"></span> Sommario</button>
		</div>
		<div class="col-md-4 text-right">
            <?php
            if ($__config->getOre_fatte_aperto() || $operatore == 'dirigente') {
            	echo '
					<button onclick="oreFatteGetAttivita(0)" class="btn btn-xs btn-teal4"><span class="glyphicon glyphicon-plus"></span></button>
				';
            }
   			?>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_fatte_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<?php if($__settings->config->gestioneClil) : ?>
<div class="panel panel-info">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-education"></span>&ensp;Attività CLIL
		</div>
		<div class="col-md-4 text-center">
			<button onclick="oreFatteClilSommario()" class="btn btn-xs btn-info"><span class="glyphicon glyphicon-option-horizontal"></span> Sommario</button>
		</div>
		<div class="col-md-4 text-right">
            <?php
            if ($__config->getOre_fatte_aperto() || $operatore == 'dirigente') {
            	echo '
					<button onclick="oreFatteClilGetAttivita(0)" class="btn btn-xs btn-info"><span class="glyphicon glyphicon-plus"></span></button>
				';
            }
   			?>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_fatte_clil_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
<?php endif; ?>

<?php if(getSettingsValue("config", "sportelli", false)) : ?>
<div class="panel panel-orange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_fatte_sportelli_records_content"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- <div class="panel-footer"></div> -->
</div>

<div class="panel panel-lightblue4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-gift"></span>&ensp;Gruppi
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_fatte_gruppi_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<div class="panel panel-lima4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-2">
		<span class="glyphicon glyphicon-list-alt"></span>&ensp;Attribuite
		</div>
		<div class="col-md-8 text-center">
		</div>
		<div class="col-md-2 text-right">
		<?php
			// il dirigente puo' comunque modificare le attribuite
            if ($operatore == 'dirigente') {
            	echo '
					<button onclick="attribuiteGetDetails(-1)" class="btn btn-xs btn-lima4"><span class="glyphicon glyphicon-plus"></span></button>
				';
            }
   			?>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attribuite_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<div class="panel panel-yellow4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-retweet"></span>&ensp;Sostituzioni
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="sostituzioni_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<!-- corsi di recupero solo se ne ha fatti -->
<?php
	$dataCdr = '';
	$oreCdr = dbGetValue("SELECT COALESCE(SUM(lezione_corso_di_recupero.numero_ore),0) FROM `lezione_corso_di_recupero` INNER JOIN corso_di_recupero ON lezione_corso_di_recupero.corso_di_recupero_id=corso_di_recupero.id  WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id AND firmato=true;");
	if ($oreCdr > 0) {
		$dataCdr .= '<div class="panel panel-lightblue4">
						<div class="panel-heading container-fluid"><div class="row">
								<div class="col-md-2"><span class="glyphicon glyphicon-repeat"></span>&emsp;Corsi di Recupero</div>
								<div class="col-md-8 text-right"></div><div class="col-md-2 text-right"></div>
						</div></div>
						<div class="panel-body"><div class="row"><div class="col-md-12">
						<div class="corso_di_recupero_records_content"></div>
						</div></div></div><input type="hidden" id="hidden_corso_di_recupero_id"></div>';
	}
	echo $dataCdr;
?>

<!-- gestione viaggi completa -->
<?php if(! getSettingsValue("config", "gestioneViaggiSemplificata", false)) : ?>
<div class="panel panel-deeporange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-picture"></span>&ensp;Viaggi
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="viaggi_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<!-- gestione viaggi semplificata -->
<?php else : ?>
<div class="panel panel-deeporange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-2">
			<span class="glyphicon glyphicon-picture"></span>&emsp;Viaggi
		</div>
		<div class="col-md-8 text-right">
		</div>
		<div class="col-md-2 text-right">
            <?php
			// il dirigente puo' comunque modificare, anche quando e' chiuso
            if ($__config->getOre_fatte_aperto() || $operatore == 'dirigente') {
            	echo '<button onclick="diariaFattaGetDetails(-1)" class="btn btn-xs btn-deeporange4"><span class="glyphicon glyphicon-plus"></span></button>';
            }
   			?>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
            <div class="diaria_records_content"></div>
        </div>
    </div>
</div>
<input type="hidden" id="hidden_diaria_id">

<!-- <div class="panel-footer"></div> -->
</div>
<?php endif; ?>

<?php
	// prepara l'elenco dei tipi di attivita
	$categoria = '';
	$tipoAttivitaOptionList = '				<option value="0"></option>';
	$query = "	SELECT * FROM ore_previste_tipo_attivita
				WHERE ore_previste_tipo_attivita.valido = true
				ORDER BY ore_previste_tipo_attivita.categoria DESC, ore_previste_tipo_attivita.nome ASC
				;";
	$resultArray = dbGetAll($query);
	foreach($resultArray as $row) {
		if ($categoria !== $row['categoria']) {
			if ($categoria !== '') {
				$tipoAttivitaOptionList .= '</optgroup>';
			}
			$categoria = $row['categoria'];
			$tipoAttivitaOptionList .= '<optgroup label="'.$categoria.'">';
		}
		// se ha un numero fisso di ore o un max, lo segnala
		$subtext = '';
		if ($row['ore'] != 0) {
			$subtext = ' data-subtext="'.$row['ore'].' ore"';
		} else if ($row['ore_max'] != 0) {
			$subtext = ' data-subtext="max '.$row['ore_max'].' ore"';
		}
		// se non va inserito dal docente lo disabilito
		$disable = '';
		if (! $row['inserito_da_docente']) {
			$disable = ' disabled ';
		}
		if ($row['inserito_da_docente']) {
			if ($row['da_rendicontare']) {
				$tipoAttivitaOptionList .= '
				<option value="'.$row['id'].'"'.$subtext.$disable.' >'.$row['nome'].'</option>
				';
			}
		}
	}
	$tipoAttivitaOptionList .= '</optgroup>';
?>

<!-- Modal - attivita details -->
<div class="modal fade" id="docente_attivita_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Attività</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

                <div class="form-group tipo_attivita_selector">
                    <label class="col-sm-2 control-label" for="tipo_attivita">Tipo attività</label>
					<div class="col-sm-6">
						<select id="attivita_tipo_attivita" name="attivita_tipo_attivita" class="attivita_tipo_attivita selectpicker" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $tipoAttivitaOptionList ?>
						</select>
					</div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id=attivita_data placeholder="data" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="attivita_ora_inizio">Alle</label>
                    <div class="col-sm-4"><input type="text" id="attivita_ora_inizio" placeholder="ora inizio" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_ore">Ore</label>
                    <div class="col-sm-3"><input type="text" id="attivita_ore" placeholder="ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_dettaglio">Dettaglio</label>
                    <div class="col-sm-9"><input type="text" id="attivita_dettaglio" placeholder="specificare se necessario" class="form-control"/></div>
                </div>

                <div class="form-group" id="commento-part">
                    <hr>
                    <label class="col-sm-2 control-label" for="attivita_commento">commento</label>
                    <div class="col-sm-9"><input type="text" id="attivita_commento" placeholder="commento" class="form-control"/></div>
                </div>

                <div class="form-group" id="_error-attivita-part"><strong>
                    <hr>
                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                    <div class="col-sm-9" id="_error-attivita"></div>
				</strong></div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaSave()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_attivita_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - attivita details -->

<!-- Modal - registro details -->
<div class="modal fade" id="docente_registro_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Registro Attività</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_tipo_attivita">Tipo attività</label>
                    <div class="col-sm-4" id="registro_tipo_attivita"></div>

                    <label class="col-sm-2 control-label" for="registro_attivita_dettaglio">Dettaglio</label>
                    <div class="col-sm-4" id="registro_attivita_dettaglio"></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_attivita_data">Data</label>
                    <div class="col-sm-2" id="registro_attivita_data"></div>

                    <label class="col-sm-2 control-label" for="registro_attivita_ora_inizio">Alle</label>
                    <div class="col-sm-2" id="registro_attivita_ora_inizio"></div>

	                <div class="form-group">
	                    <label class="col-sm-2 control-label" for="registro_attivita_ore">Ore</label>
	                    <div class="col-sm-2" id="registro_attivita_ore"></div>
	                </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_descrizione">Descrizione</label>
                    <div class="col-sm-9"><textarea class="form-control" rows="3" id="registro_descrizione" placeholder="descrizione" ></textarea></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_descrizione">Studenti</label>
                    <div class="col-sm-9"><textarea class="form-control" rows="3" id="registro_studenti" placeholder="studenti" ></textarea></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaRegistroUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_registro_id">
				<input type="hidden" id="hidden_registro_clil">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - registro details -->

<!-- Modal - rendiconto details -->
<div class="modal fade" id="docente_rendiconto_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
				<h5 class="modal-title text-center" id="myModalLabel">Rendiconto Attività</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="rendiconto_tipo_attivita">Tipo attività</label>
                    <div class="col-sm-4" id="rendiconto_tipo_attivita"></div>

                    <label class="col-sm-2 control-label" for="rendiconto_attivita_dettaglio">Dettaglio</label>
                    <div class="col-sm-4" id="rendiconto_attivita_dettaglio"></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="rendiconto_rendiconto">Rendiconto</label>
                    <div class="col-sm-9"><textarea class="form-control" rows="3" id="rendiconto_rendiconto" placeholder="rendiconto" ></textarea></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaRendicontoUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_rendiconto_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - rendiconto details -->

<!-- Modal - sommario details -->
<div class="modal fade" id="docente_sommario_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
				<h5 class="modal-title text-center" id="myModalLabel">Sommario Attività</h5>
			</div>
            <div class="panel-body">
                <div class="row"  style="margin-bottom:10px;">
                    <div class="col-md-6">
                    </div>
                    <div class="col-md-6">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="sommario_attivita_records_content"></div>
                    </div>
                </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - rendiconto details -->

<!-- Modal - attivita clil details -->
<div class="modal fade" id="docente_attivita_clil_modal" tabindex="-1" role="dialog" aria-labelledby="myModal_clilLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModal_clilLabel">Attività CLIL</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">
				<div class="form-group attivita_clil_tipo_attivita_selector">
                    <label class="col-sm-2 control-label" for="attivita_clil_tipo_attivita">Tipo attività</label>
					<div class="col-sm-6">
						<select id="attivita_clil_tipo_attivita" name="attivita_clil_tipo_attivita" class="attivita_clil_tipo_attivita selectpicker" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
							<option value="0"></option>
							<option value="1">funzionali</option>
							<option value="2">con studenti</option>
						</select>
					</div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_clil_data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id=attivita_clil_data placeholder="data" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="attivita_clil_ora_inizio">Alle</label>
                    <div class="col-sm-4"><input type="text" id="attivita_clil_ora_inizio" placeholder="ora inizio" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_clil_ore">Ore</label>
                    <div class="col-sm-3"><input type="text" id="attivita_clil_ore" placeholder="ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_clil_dettaglio">Dettaglio</label>
                    <div class="col-sm-9"><input type="text" id="attivita_clil_dettaglio" placeholder="specificare se necessario" class="form-control"/></div>
                </div>
                <div class="form-group" id="clil_commento-part">
                    <hr>
                    <label class="col-sm-2 control-label" for="attivita_clil_commento">commento</label>
                    <div class="col-sm-9"><input type="text" id="attivita_clil_commento" placeholder="commento" class="form-control"/></div>
                </div>
				
                <div class="form-group" id="_error-attivita_clil-part"><strong>
                    <hr>
                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                    <div class="col-sm-9" id="_error-attivita_clil"></div>
				</strong></div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaClilSave()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_clil_attivita_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - attivita clil details -->

<!-- Modal - corsi di recupero details -->
<div class="modal fade" id="corso_di_recupero_modal" tabindex="-1" role="dialog" aria-labelledby="corso_di_recuperoModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
		<div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Corso di Recupero pagamento</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

			<div class="form-group">
                    <label class="col-sm-3 control-label" for="corso_di_recupero_codice">codice</label>
                    <div class="col-sm-9"><input type="text" id="corso_di_recupero_codice" placeholder="" class="form-control" readonly="readonly"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="corso_di_recupero_ore_totali">ore totali</label>
                    <div class="col-sm-2"><input type="text" id="corso_di_recupero_ore_totali" placeholder="" class="form-control" readonly="readonly"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="corso_di_recupero_ore_recuperate">ore recuperate</label>
                    <div class="col-sm-2"><input type="text" id="corso_di_recupero_ore_recuperate" placeholder="" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="corso_di_recupero_ore_extra">ore extra</label>
                    <div class="col-sm-2"><input type="text" id="corso_di_recupero_ore_extra" placeholder="" class="form-control"/></div>
                </div>

            </div>
            </div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="corsoDiRecuperoPrevisteSave()" >Salva</button>
				<input type="hidden" id="hidden_corso_di_recupero_id">
				</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - corsi di recupero details -->

<!-- include la gestione delle ore attribuite (Modal Dialog) -->
<?php
require_once '../docente/attribuiteModal.php';
?>

<!-- Modal - diaria details -->
<div class="modal fade" id="diaria_modal" tabindex="-1" role="dialog" aria-labelledby="diariaModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
		<div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Diaria Viaggio</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

				<div class="form-group">
                    <label class="col-sm-2 control-label" for="diaria_data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id=diaria_data placeholder="data" class="form-control" /></div>
                </div>

				<div class="form-group">
                    <label class="col-sm-3 control-label" for="diaria_descrizione">descrizione viaggio</label>
                    <div class="col-sm-9"><input type="text" id="diaria_descrizione" placeholder="inserire i dettagli noti del viaggio" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="diaria_giorni_senza_pernottamento">giorni senza pernottamento</label>
                    <div class="col-sm-2"><input type="text" id="diaria_giorni_senza_pernottamento" placeholder="" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="diaria_giorni_con_pernottamento">giorni con pernottamento</label>
                    <div class="col-sm-2"><input type="text" id="diaria_giorni_con_pernottamento" placeholder="" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="diaria_ore">ore recuperate</label>
                    <div class="col-sm-2"><input type="text" id="diaria_ore" placeholder="" class="form-control"/></div>
                </div>

                <div class="form-group" id="diaria_commento-part">
                    <hr>
                    <label class="col-sm-3 control-label" for="diaria_commento">commento</label>
                    <div class="col-sm-9"><input type="text" id="diaria_commento" placeholder="commento" class="form-control"/></div>
                </div>

            </div>
            </div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="diariaSave()" >Salva</button>
				<input type="hidden" id="hidden_diaria_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - diaria details -->

<!-- include la gestione delle ore attribuite (Modal Dialog) -->
<?php
require_once '../docente/attribuiteModal.php';
?>

</div>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

<!-- Custom JS file -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/_util.js"></script>
<script type="text/javascript" src="js/scriptAttivita.js"></script>
<script type="text/javascript" src="js/scriptAttribuite.js"></script>

</body>
</html>