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
require_once '../common/_include_bootstrap-notify.php';
require_once '../common/__Minuti.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');
?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-vcolor-index.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
	<title>Ore Previste</title>
</head>

<body >
<?php

// default opera sul docente connesso e agisce come docente
$docente_id = $__docente_id;
$operatore = 'docente';

if(isset($_GET['docente_id']) && $_GET['docente_id'] != "") {
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
}
$ultimo_controllo = dbGetValue("SELECT ultimo_controllo FROM ore_previste WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
debug('ultimo_controllo=' . $ultimo_controllo);

require_once '../common/header-docente.php';
?>

<div class="container-fluid" style="margin-top:60px">

<?php if($operatore == 'dirigente') : ?>
<!-- pannello fuis per dirigente -->
<div class="panel panel-deeporange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-2">
			<span class="glyphicon glyphicon-euro"></span>&emsp;FUIS - previsione
		</div>
		<div class="col-md-2 text-center">
			<button onclick="previsteChiudi()" class="btn btn-deeporange4 btn-xs"><span class="glyphicon glyphicon-off"> Chiudi</button>
		</div>
		<div class="col-md-2 text-center">
			<button onclick="previsteRivisto()" class="btn btn-lima4 btn-xs"><span class="glyphicon glyphicon-ok"> Rivisto</button>
		</div>
		<div class="col-md-2 text-center">
			<button onclick="previsteEmail()" class="btn btn-lightblue4 btn-xs"><span class="glyphicon glyphicon-envelope"> Notifica Docente</button>
		</div>
		<div class="col-md-2 text-center">
			<button onclick="previsteAzzeraSostituzioni()" class="btn btn-yellow4 btn-xs"><span class="glyphicon glyphicon-retweet"> Azzera Sostituzioni</button>
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
				<th class="col-md-2"></th>
				<th class="col-md-2 text-left">Aggiornamento</th>
				<th class="col-md-2 text-left"><?php echoLabel('Funzionali');?></th>
				<th class="col-md-2 text-left">con Studenti</th>
				<th class="col-md-2 text-left"><span class="clil hidden">CLIL (<?php echoLabel('Funzionali');?>)</span></th>
				<th class="col-md-2 text-left"><span class="clil hidden">CLIL (con Studenti)</span></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echoLabel('dovute');?></td>
				<td class="text-left" id="dovute_ore_40_aggiornamento"></td>
				<td class="text-left" id="dovute_ore_70_funzionali"></td>
				<td class="text-left" id="dovute_totale_con_studenti"></td>
				<td class="text-left" ></td>
				<td class="text-left" ></td>
			</tr>
			<tr class="orange5">
				<td>previste</td>
				<td class="text-left" id="previste_ore_40_aggiornamento"></td>
				<td class="text-left" id="previste_ore_70_funzionali"></td>
				<td class="text-left" id="previste_totale_con_studenti"></td>
				<td class="text-left clil" id="clil_previste_funzionali"></td><td class="NOclil"></td>
				<td class="text-left clil" id="clil_previste_con_studenti"></td><td class="NOclil"></td>
			</tr>
		</tbody>
	</table>
	</div>
	<div id="ore_message" class="row" style="margin-bottom:10px;"></div>
	<input type="hidden" id="accetta_con_studenti_per_funzionali" value="<?php if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {echo('1');} else {echo('0');} ?>">
	<input type="hidden" id="accetta_funzionali_per_con_studenti" value="<?php if (getSettingsValue('fuis','accetta_funzionali_per_con_studenti', false)) {echo('1');} else {echo('0');} ?>">
</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<div class="panel panel-orange4">
<div class="panel-heading">
<div class="row">
		<div class="col-md-2">
			<span class="glyphicon glyphicon-list-alt"></span>&ensp;Ore Previste
		</div>
		<div class="col-md-8 text-right">
		</div>
		<div class="col-md-2 text-right">
            <?php
			// il dirigente puo' comunque modificare le previsioni, anche quando e' chiuso
            if ($__config->getOre_previsioni_aperto() || $operatore == 'dirigente') {
            	echo '
					<button onclick="attivitaPrevistaAdd()" class="btn btn-xs btn-orange4"><span class="glyphicon glyphicon-plus"></span></button>
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
<div id="notificationBlock"></div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_previste_records_content"></div>
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

<div class="panel panel-deeporange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-2">
			<span class="glyphicon glyphicon-picture"></span>&emsp;Viaggi con Diaria - previsione
		</div>
		<div class="col-md-8 text-right">
		</div>
		<div class="col-md-2 text-right">
            <?php
			// il dirigente puo' comunque modificare le previsioni, anche quando e' chiuso
            if ($__config->getOre_previsioni_aperto() || $operatore == 'dirigente') {
            	echo '<button onclick="diariaPrevistaGetDetails(-1)" class="btn btn-xs btn-deeporange4"><span class="glyphicon glyphicon-plus"></span></button>';
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
	// se non va previsto dal docente lo disabilito
	$disable = '';
	if (! $row['previsto_da_docente']) {
		$disable = ' disabled ';
	}
	if ($row['previsto_da_docente']) {
		$tipoAttivitaOptionList .= '
			<option value="'.$row['id'].'"'.$subtext.$disable.' >'.$row['nome'].'</option>
			';
	}
}
$tipoAttivitaOptionList .= '</optgroup>';
?>

<!-- Modal - attivita details -->
<div class="modal fade" id="update_attivita_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
		<div class="modal-body">
			<div class="panel panel-orange4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Attività Prevista</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

                <div class="form-group tipo_attivita_selector">
                    <label class="col-sm-3 control-label" for="tipo_attivita">Tipo attività</label>
					<div class="col-sm-8"><select id="tipo_attivita" name="tipo_attivita" class="tipo_attivita selectpicker" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $tipoAttivitaOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="update_ore">Ore</label>
                    <div class="col-sm-3"><input type="text" id="update_ore" placeholder="ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="update_dettaglio">dettaglio</label>
                    <div class="col-sm-9"><input type="text" id="update_dettaglio" placeholder="specificare solo se necessario" class="form-control"/></div>
                </div>

                <div class="form-group" id="commento-part">
                    <hr>
                    <label class="col-sm-3 control-label" for="update_commento">commento</label>
                    <div class="col-sm-9"><input type="text" id="update_commento" placeholder="commento" class="form-control"/></div>
                </div>

                <div class="form-group" id="_error-previste-part"><strong>
                    <hr>
                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                    <div class="col-sm-9" id="_error-previste"></div>
				</strong></div>
            </div>
            </div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="previstaUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_previste_attivita_id">
				<input type="hidden" id="hidden_docente_id" value="<?php echo $docente_id; ?>">
				<input type="hidden" id="hidden_operatore" value="<?php echo $operatore; ?>">
				<input type="hidden" id="hidden_ultimo_controllo" value="<?php echo $ultimo_controllo; ?>">
				</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - attivita details -->

<!-- Modal - diaria details -->
<div class="modal fade" id="diaria_modal" tabindex="-1" role="dialog" aria-labelledby="diariaModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
		<div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Diaria Viaggio Prevista</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

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
				<input type="hidden" id="hidden_diaria_docente_id" value="<?php echo $docente_id; ?>">
				<input type="hidden" id="hidden_diaria_operatore" value="<?php echo $operatore; ?>">
				<input type="hidden" id="hidden_diaria_ultimo_controllo" value="<?php echo $ultimo_controllo; ?>">
				</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - diaria details -->

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

</div>

<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>

<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/_util.js?v=<?php echo $__software_version; ?>"></script>
<script type="text/javascript" src="js/scriptPreviste.js?v=<?php echo $__software_version; ?>"></script>
<script type="text/javascript" src="js/scriptAttribuite.js?v=<?php echo $__software_version; ?>"></script>
</body>
</html>
