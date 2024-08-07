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
	<title>Configurazione</title>

<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('dirigente');
?>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
require_once '../common/importi_load.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<div class="panel panel-orange4">
<div class="panel-heading">Importi</div>
<div class="panel-body">
	<div class="form-horizontal">

		<div class="form-group">
			<label class="col-sm-2 control-label" for="importo_fuis">Importo FUIS</label>
			<div class="col-sm-1"><input type="text" id="importo_fuis" placeholder="0" class="form-control" value="<?php echo $__importo_fuis; ?>" /></div>

			<label class="col-sm-2 control-label" for="importo_fuis_clil">Importo FUIS CLIL</label>
			<div class="col-sm-1"><input type="text" id="importo_fuis_clil" placeholder="0" class="form-control" value="<?php echo $__importo_fuis_clil; ?>" /></div>

			<label class="col-sm-2 control-label" for="importo_fuis_orientamento">Importo FUIS Orientamento</label>
			<div class="col-sm-1"><input type="text" id="importo_fuis_orientamento" placeholder="0" class="form-control" value="<?php echo $__importo_fuis_orientamento; ?>" /></div>

			<label class="col-sm-2 control-label" for="importo_bonus">Importo Bonus</label>
			<div class="col-sm-1"><input type="text" id="importo_bonus" placeholder="0" class="form-control" value="<?php echo $__importo_bonus; ?>" /></div>
		</div>
    </div>
	<input type="hidden" id="hidden_importo_id" value="<?php echo $__importo_id; ?>" >
	<hr>
	<div class="text-center">
			<button type="button" class="btn btn-primary" onclick="salvaImporti()">Salva Importi</button>
	</div>
</div>
</div>

<div class="panel panel-success">
<div class="panel-heading">Ore Dovute</div>
<div class="panel-body">
	<div class="form-horizontal">
			<label class="col-sm-3 control-label" for="ore_previsioni_checkbox">Inserimento Ore Previste
				<input type="checkbox" class="checkbox-inline col-sm-1" id="ore_previsioni_checkbox"  data-toggle="toggle" data-size="small" data-onstyle="success" data-on="Aperto" data-off="Chiuso" <?php if ($__config->getOre_previsioni_aperto()) echo 'checked'; ?> >
			</label>

			<label class="col-sm-3 control-label" for="ore_fatte_checkbox">Inserimento Ore Fatte
				<input type="checkbox" class="checkbox-inline col-sm-1" id="ore_fatte_checkbox"  data-toggle="toggle" data-size="small" data-onstyle="success" data-on="Aperto" data-off="Chiuso" <?php if ($__config->getOre_fatte_aperto()) echo 'checked'; ?> >
			</label>
		</div>
    </div>
</div>

<div class="panel panel-info">
<div class="panel-heading">Corsi di Recupero</div>
<div class="panel-body">
	<div class="form-horizontal">
		<div class="form-group">
			<label class="col-sm-3 control-label" for="voti_recupero_settembre_checkbox">Voti Recupero Settembre
				<input type="checkbox" class="checkbox-inline col-sm-1" id="voti_recupero_settembre_checkbox"  data-toggle="toggle" data-size="small" data-onstyle="info" data-on="Aperto" data-off="Chiuso" <?php if ($__config->getVoti_recupero_settembre_aperto()) echo 'checked'; ?> >
			</label>
			<label class="col-sm-3 control-label" for="voti_recupero_novembre_checkbox">Voti Recupero Novembre
				<input type="checkbox" class="checkbox-inline col-sm-1" id="voti_recupero_novembre_checkbox"  data-toggle="toggle" data-size="small" data-onstyle="info" data-on="Aperto" data-off="Chiuso" <?php if ($__config->getVoti_recupero_novembre_aperto()) echo 'checked'; ?> >
			</label>
			<label class="col-sm-3 control-label" for="email_carenze_checkbox">email Carenze
				<input type="checkbox" class="checkbox-inline col-sm-1" id="email_carenze_checkbox"  data-toggle="toggle" data-size="small" data-onstyle="info" data-on="Aperto" data-off="Chiuso" <?php if ($__config->getEmail_carenze_aperto()) echo 'checked'; ?> >
			</label>
		</div>
	</div>
</div>
</div>

<div class="panel panel-success">
<div class="panel-heading">Bonus</div>
<div class="panel-body">
	<div class="form-horizontal">
			<label class="col-sm-3 control-label" for="bonus_adesione_checkbox">Adesione Bonus
				<input type="checkbox" class="checkbox-inline col-sm-1" id="bonus_adesione_checkbox"  data-toggle="toggle" data-size="small" data-onstyle="success" data-on="Aperto" data-off="Chiuso" <?php if ($__config->getBonus_adesione_aperto()) echo 'checked'; ?> >
			</label>

			<label class="col-sm-3 control-label" for="bonus_rendiconto_checkbox">Rendiconto Bonus
				<input type="checkbox" class="checkbox-inline col-sm-1" id="bonus_rendiconto_checkbox"  data-toggle="toggle" data-size="small" data-onstyle="success" data-on="Aperto" data-off="Chiuso" <?php if ($__config->getBonus_rendiconto_aperto()) echo 'checked'; ?> >
			</label>
			<div class="col-sm-6 text-center">
				<label id="import_btn" class="btn btn-lima4 btn-file"><span class="glyphicon glyphicon-upload"></span>&emsp;Importa Bonus<input type="file" id="bonus_select_id" style="display: none;"></label>
			</div>
		</div>
    </div>
</div>

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptConfigurazione.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>