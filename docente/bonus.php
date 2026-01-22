<?php

/**
 *  This file is part of GestOre
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$anno_scolastico_id = isset($_GET['anno_scolastico_id'])
	? intval($_GET['anno_scolastico_id'])
	: $__anno_scolastico_corrente_id;

ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');
?>
<!DOCTYPE html>
<html>

<head>
	<?php
	require_once '../common/header-common.php';
	require_once '../common/style.php';
	require_once '../common/_include_bootstrap-select.php';
	?>
	<title>Bonus Docente</title>
</head>

<body>
	<?php
	require_once '../common/header-docente.php';
	?>

	<div class="container-fluid">
		<div class="panel panel-lima4">
			<div class="panel-heading">
				<div class="row">
					<div class="col-md-4">
						<span class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus
					</div>

					<div class="col-md-4 text-center">
					</div>

					<div class="col-md-4 text-right">
						<select id="anno_scolastico_select" class="form-control" style="display:inline-block; width:auto;">
							<?php
							$anni = dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC");
							foreach ($anni as $a) {
								$selected = ($a['id'] == $anno_scolastico_id) ? 'selected' : '';
								echo '<option value="' . $a['id'] . '" ' . $selected . '>' . $a['anno'] . '</option>';
							}
							?>
						</select>

						<?php
						if ($__config->getBonus_adesione_aperto() && $anno_scolastico_id == $__anno_scolastico_corrente_id) {
							// Adesioni: passa l'anno selezionato
							echo '&ensp;<button id="btn_adesioni" class="btn btn-xs btn-lima4"><span class="glyphicon glyphicon-cog"></span>&ensp;Adesioni</button>';
						}
						?>
					</div>
				</div>
			</div>

			<div class="panel-body">
				<div class="row">
					<div class="col-md-12">
						<div class="table-wrapper">
							<table class="table table-bordered table-striped table-green" id="bonus_docente_table">
								<thead>
									<tr>
										<th class="text-center">Codice</th>
										<th class="text-center">Descrittore</th>
										<th class="text-center">Evidenze</th>
										<th class="text-center">Valore</th>
										<th class="text-center"></th>
										<th class="text-center">Approvato</th>
									</tr>
								</thead>
								<tbody>
									<?php
									$query = "
SELECT
	bonus_docente.id AS bonus_docente_id,
	bonus_docente.approvato AS bonus_docente_approvato,

	bonus_area.codice AS bonus_area_codice,
	bonus_area.descrizione AS bonus_area_descrizione,

	bonus_indicatore.codice AS bonus_indicatore_codice,
	bonus_indicatore.descrizione AS bonus_indicatore_descrizione,

	bonus.codice AS bonus_codice,
	bonus.descrittori AS bonus_descrittori,
	bonus.evidenze AS bonus_evidenze,
	bonus.valore_previsto AS bonus_valore_previsto

FROM bonus_docente
INNER JOIN bonus
	ON bonus_docente.bonus_id = bonus.id
INNER JOIN bonus_indicatore
	ON bonus.bonus_indicatore_id = bonus_indicatore.id
INNER JOIN bonus_area
	ON bonus_indicatore.bonus_area_id = bonus_area.id

WHERE
	bonus_docente.docente_id = $__docente_id
AND bonus_docente.anno_scolastico_id = $anno_scolastico_id

ORDER BY bonus.codice;
";
									$resultArray = dbGetAll($query);

									foreach ($resultArray as $bonus) {
										$bonus_valore = getSettingsValue('bonus', 'punteggio_variabile', false) ? '0 - ' : '';
										$bonus_valore .= $bonus['bonus_valore_previsto'];

										$bonus_descrittori = js_escape($bonus['bonus_descrittori']);
										$bonus_evidenze = js_escape($bonus['bonus_evidenze']);

										$data = '
								<tr>
									<td class="text-left">' . htmlspecialchars($bonus['bonus_codice']) . '</td>
									<td class="text-left"><span style="white-space: pre-line">' . htmlspecialchars($bonus['bonus_descrittori']) . '</span></td>
									<td class="text-left"><span style="white-space: pre-line">' . htmlspecialchars($bonus['bonus_evidenze']) . '</span></td>
									<td class="text-center">' . htmlspecialchars($bonus_valore) . '</td>
								';

										$data .= '
									<td class="text-center">
										<button onclick="bonusRendiconto(' . intval($bonus['bonus_docente_id']) . ', \'' . $bonus['bonus_codice'] . '\', \'' . $bonus_descrittori . '\', \'' . $bonus_evidenze . '\')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></span></button>
									</td>
								';

										if (getSettingsValue('bonus', 'punteggio_variabile', false)) {
											$data .= '<td class="text-left">' . htmlspecialchars($bonus['bonus_docente_approvato']) . '</td></tr>';
										} else {
											if ($bonus['bonus_docente_approvato'] !== NULL) {
												if (intval($bonus['bonus_docente_approvato']) == 1) {
													$data .= '<td class="text-left">' . htmlspecialchars($bonus_valore) . '</td></tr>';
												} else {
													$data .= '<td class="text-left">0</td></tr>';
												}
											} else {
												$data .= '<td class="text-left"></td></tr>';
											}
										}

										echo $data;
									}
									?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal - rendiconto details -->
		<div class="modal fade" id="bonus_docente_rendiconto_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-body">
						<div class="panel panel-success">
							<div class="panel-heading">
								<h5 class="modal-title text-center" id="myModalLabel">Rendiconto Evidenze</h5>
							</div>
							<div class="panel-body">
								<div class="form-group">
									<div id="evidenze_text"></div>
								</div>

								<div class="form-group">
									<label for="rendiconto_rendiconto">Rendiconto</label>
									<textarea class="form-control" rows="5" id="rendiconto_rendiconto"
										placeholder="rendiconto"
										<?php echo ($anno_scolastico_id == $__anno_scolastico_corrente_id && $__config->getBonus_rendiconto_aperto()) ? '' : 'readonly="readonly"'; ?>></textarea>

								</div>
								<hr style="margin:10px 0;">
								<div class="form-group">
									<label>Allegati (PDF)</label>

									<div id="allegati_list"></div>

									<div style="margin-top:8px;">
										<input type="file" id="allegati_files" multiple accept="application/pdf" class="form-control">
									</div>

									<div style="margin-top:8px;">
										<button type="button" class="btn btn-primary" id="btn_upload_allegati">
											<span class="glyphicon glyphicon-upload"></span>&ensp;Carica PDF
										</button>
									</div>

									<div class="text-muted" style="margin-top:6px;">
										Puoi caricare uno o più PDF come evidenze. (Solo anno corrente, se il rendiconto è aperto)
									</div>
								</div>

							</div>
							<div class="modal-footer">
								<div class="col-sm-12 text-center">
									<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
									<?php
									if ($__config->getBonus_rendiconto_aperto() && $anno_scolastico_id == $__anno_scolastico_corrente_id) {
										echo '<button type="button" class="btn btn-primary" onclick="bonusDocenteRendicontoUpdateDetails()">Salva</button>';
									}

									?>
									<input type="hidden" id="hidden_bonus_docente_id">
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

	</div>

	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<script type="text/javascript" src="js/scriptBonus.js"></script>

</body>

</html>