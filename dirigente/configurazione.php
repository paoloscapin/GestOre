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
ruoloRichiesto('dirigente');
?>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

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
		</div>
    </div>
</div>

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptConfigurazione.js"></script>

</body>
</html>