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
	<title>Bonus Docenti</title>
	<?php

	require_once '../common/header-common.php';
	require_once '../common/style.php';
	require_once '../common/_include_bootstrap-toggle.php';
	//require_once '../common/_include_bootstrap-select.php';
	ruoloRichiesto('dirigente');
	?>

	<!-- timejs -->
	<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
	<script type="text/javascript" src="js/scriptBonusDocenti.js"></script>

</head>

<body>
	<?php
	require_once '../common/header-dirigente.php';
	require_once '../common/connect.php';
	?>

	<div class="container-fluid" style="margin-top:60px">
		<div class="panel panel-success">
			<div class="panel-heading container-fluid">
				<div class="row">
					<div class="col-md-3">
						<span class="glyphicon glyphicon-education"></span>&emsp;<strong>Bonus Docenti</strong>
					</div>

					<div class="col-md-5 text-center" id="totale_bonus_docenti">
					</div>

					<div class="col-md-4 text-right">
						<select id="anno_scolastico_select" class="form-control" style="display:inline-block; width:auto;">
							<?php
							$anni = dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC");
							foreach ($anni as $a) {
								$selected = ($a['id'] == $__anno_scolastico_corrente_id) ? 'selected' : '';
								echo '<option value="' . $a['id'] . '" ' . $selected . '>' . $a['anno'] . '</option>';
							}
							?>
						</select>
					</div>
				</div>

			</div>
			<div class="panel-body">
				<div class="row" style="margin-bottom:10px;">
				</div>
				<div class="row">
					<div class="col-md-12">
						<div class="bonus_docenti_records_content"></div>
					</div>
				</div>
			</div>

			<!-- <div class="panel-footer"></div> -->
		</div>
	</div>
</body>

</html>