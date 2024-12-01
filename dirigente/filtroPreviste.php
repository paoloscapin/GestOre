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
	<title>Filtro Previste</title>
<?php

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<script type="text/javascript" src="js/scriptFiltroPreviste.js"></script>

</head>

<body >
<div class="container-fluid" style="margin-top:60px">

<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';

// prepara l'elenco dei tipi di attivita
	$categoria = '';
	$tipoAttivitaOptionList = '<option value="0"></option>';
	$query = "SELECT * FROM ore_previste_tipo_attivita WHERE ore_previste_tipo_attivita.valido = true ORDER BY ore_previste_tipo_attivita.categoria DESC, ore_previste_tipo_attivita.nome ASC ;";
	foreach(dbGetAll($query) as $attivita) {
		if ($categoria !== $attivita['categoria']) {
			if ($categoria !== '') {
				$tipoAttivitaOptionList .= '</optgroup>';
			}
			$categoria = $attivita['categoria'];
			$tipoAttivitaOptionList .= '<optgroup label="'.$categoria.'">';
		}
		// se non va inserito dal docente lo segnala
		$subtext = '';
		if (! $attivita['inserito_da_docente']) {
			$subtext = ' data-subtext="assegnato"';
		}
		$tipoAttivitaOptionList .= '<option value="'.$attivita['id'].'"'.$subtext.' >'.$attivita['nome'].'</option>';
	}
	$tipoAttivitaOptionList .= '</optgroup>';

	$ordinamentoOptionList = '<option value="0">Ore (decrescente)</option>';
	$ordinamentoOptionList .= '<option value="1">Ore (crescente)</option>';
	$ordinamentoOptionList .= '<option value="2">Alfabetico</option>';
?>

<div class="row">
	<div class="col-md-6">
	   <div class="form-group tipo_attivita_selector text-center">
			<label for="tipo_attivita">Filtro Attività Previste</label>
			<select id="tipo_attivita" name="tipo_attivita" class="tipo_attivita selectpicker" data-style="btn-info" data-live-search="true"
			data-noneSelectedText="seleziona..." data-width="50%" >
	<?php echo $tipoAttivitaOptionList ?>
			</select>
		</div>
	</div>
	<div class="col-md-6">
	   <div class="form-group ordinamento_selector text-center">
			<label for="ordinamento">Ordinamento</label>
			<select id="ordinamento" name="ordinamento" class="ordinamento selectpicker" data-style="btn-success" data-live-search="true"
			data-noneSelectedText="seleziona..." data-width="50%" >
	<?php echo $ordinamentoOptionList ?>
			</select>
		</div>
	</div>
</div>

<div class="panel panel-orange4">
	<div class="records_content"></div>
</div>
</div>

</body>
</html>
