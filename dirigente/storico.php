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
	<title>Storico</title>

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
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<div class="panel panel-yellow4">
<div class="panel-heading">Storico</div>
<div class="panel-body">
	<div class="form-horizontal">
		<div class="form-group">
			<label class="col-sm-2 control-label" for="importo_fuis">anno scolastico</label>
			<div class="col-sm-2">
            <select title="anno" id="anno_select" >
<?php
foreach (dbGetAll("SELECT * FROM anno_scolastico WHERE id <= $__anno_scolastico_corrente_id;") as $annoResult) {
    $annoId = $annoResult['id'];
    $annoCodice = $annoResult['anno'];
    echo '<option value="' . $annoId . '"'.($__anno_scolastico_corrente_id == $annoId? 'selected ': '').' > ' . $annoCodice . '</option>';
}
?>
            </select>
			</div>
			<div class="col-md-4">
			<button onclick="storicoBonus()" class="btn btn-lima4"><span class="glyphicon glyphicon-list-alt"> Bonus</button>
	        </div>
			<div class="col-md-4">
			<button onclick="storicoFuis()" class="btn btn-deeporange4"><span class="glyphicon glyphicon-euro"> Fuis</button>
	        </div>
		</div>
    </div>
</div>
</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptStorico.js"></script>

</body>
</html>