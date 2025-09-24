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
	<title>Ore Assegnate</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<?php


	require_once '../common/header-common.php';
	require_once '../common/style.php';
	require_once '../common/_include_bootstrap-select.php';
	require_once '../common/_include_select2.php';
	ruoloRichiesto('dirigente', 'segreteria-docenti');
	?>

	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

	<!-- Custom JS file -->
	<script type="text/javascript" src="js/oreAssegnate.js"></script>
</head>

<body>
	<?php require_once '../common/header-segreteria.php'; ?>

	<!-- Content Section -->
	<div class="container-fluid" style="margin-top:60px">
		<div class="panel panel-lima4">
			<div class="panel-heading container-fluid">
				<div class="row">
					<div class="col-md-3">
						<span class="glyphicon glyphicon-list-alt"></span>&emsp;Ore Assegnate
					</div>
					<div class="col-md-3 text-center">
						<label id="import_btn" class="btn btn-xs btn-lima4 btn-file"><span class="glyphicon glyphicon-upload"></span>&emsp;Importa<input type="file" id="file_select_id" style="display: none;"></label>
					</div>
					<div class="col-md-3 text-center">
						<button onclick="ricalcolaTutti()" class="btn btn-xs btn-lima4"><span class="glyphicon glyphicon-refresh"></span>&ensp;Ricalcola Tutti</button>
					</div>
					<div class="col-md-4 text-right">
					</div>
					<div class="col-md-4 text-right">
					</div>
				</div>
			</div>
		</div>
		<div class="row" style="margin-bottom:10px;">
			<div class="col-md-12 text-center" id='result_text'>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">


				<?php
				// prepara l'elenco delle categorie di attivita'
				$query = "	SELECT * FROM `ore_previste_tipo_attivita` WHERE valido = true AND inserito_da_docente = false AND previsto_da_docente = false;";
				$resultArrayTipoAttivita = dbGetAll($query);

				$data = '';
				foreach ($resultArrayTipoAttivita as $tipoAttivita) {
					$tipoAttivitaId = $tipoAttivita['id'];
					$categoria = $tipoAttivita['categoria'];
					$nome = $tipoAttivita['nome'];
					$ore = $tipoAttivita['ore'];
					$ore_max = $tipoAttivita['ore_max'];
					$da_rendicontare = $tipoAttivita['da_rendicontare'];
					$data .= '
		<div class="panel panel-lima4">
		<div class="panel-heading container-fluid">
			<div class="row">
				<div class="col-md-4">
				</div>
				<div class="col-md-4 text-center">
					<strong>' . $nome . '</strong>
				</div>
				<div class="col-md-4 text-right">
					<button onclick="addAttivita(' . $tipoAttivitaId . ',\'' . addslashes($nome) . '\',' . $ore . ',' . $ore_max . ')" class="btn btn-xs btn-info"><span class="glyphicon glyphicon-plus"></span></button>
				</div>
			</div>
		</div>
		<div class="panel-body">
		';
					$data .= '
		<div class="table-wrapper">
			<table class="table table-bordered table-striped" id="table_' . $tipoAttivitaId . '" >
				<thead>
					<th style="display:none;">id</th>
					<th class="col-md-2">Docente</th>
					<th class="col-md-8">Dettaglio</th>
					<th class="col-md-1">Ore</th>
					<th class="col-md-1">Modifica</th>
				</thead>
				<tbody>
			';

					$query = "	SELECT
					ore_previste_attivita.id AS ore_previste_attivita_id,
					ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
					ore_previste_attivita.ore AS ore_previste_attivita_ore,
					ore_previste_attivita.ore_previste_tipo_attivita_id AS ore_previste_attivita_ore_previste_tipo_attivita_id,
					docente.nome AS docente_nome,
					docente.cognome AS docente_cognome
				FROM
					ore_previste_attivita
				INNER JOIN docente docente
				ON ore_previste_attivita.docente_id = docente.id
				WHERE
					ore_previste_attivita.anno_scolastico_id = '$__anno_scolastico_corrente_id'
				AND
					ore_previste_attivita.ore_previste_tipo_attivita_id = '$tipoAttivitaId'
				ORDER BY
					docente.cognome ASC,
					docente.nome ASC
				;
		";
					$resultArrayOre = dbGetAll($query);
					$classname = "";
					foreach ($resultArrayOre as $row_ore) {
						$classname = ($classname === "even_row") ? "odd_row" : "even_row";
						$data .= '
    <tr class="'.$classname.'">
        <td style="display:none;">'.$row_ore['ore_previste_attivita_id'].'</td>
        <td>'.$row_ore['docente_cognome'].' '.$row_ore['docente_nome'].'</td>
        <td>'.$row_ore['ore_previste_attivita_dettaglio'].'</td>
        <td class="col-md-1 text-center">'.$row_ore['ore_previste_attivita_ore'].'</td>
        <td class="col-md-2 text-center">
            <button onclick="attivitaGetDetails('
                .$row_ore['ore_previste_attivita_id'].','.$row_ore['ore_previste_attivita_ore_previste_tipo_attivita_id'].
            ')" class="btn btn-warning btn-xs">
                <span class="glyphicon glyphicon-pencil"></span>
            </button>

            <button onclick="deleteOreAttivita('
                .$row_ore['ore_previste_attivita_id'].',' 
                .$row_ore['ore_previste_attivita_ore_previste_tipo_attivita_id'].',\' ' 
                .str_replace('\'',' ',$row_ore['docente_cognome']).'\',\'' 
                .str_replace('\'',' ',$row_ore['docente_nome']).'\')" 
                class="btn btn-danger btn-xs">
                <span class="glyphicon glyphicon-trash"></span>
            </button>
        </td>
    </tr>';



							
					}
					$data .= '
				</tbody>
				';
					$data .= '
			</table>
			<div style="page-break-after: always;">
		</div>
	</div>
';
					$data .= '
  </div> <!-- // Div - panel body -->
</div> <!-- // Div - inner panel -->
';
				}
				echo $data;
				?>

			</div>
		</div>
	</div> <!-- // Div - Content Section Container-fluid -->

	<?php

	// prepara l'elenco dei docenti
	$docenteOptionList = '				<option value="0"></option>';
	$query = "	SELECT * FROM docente
			WHERE docente.attivo = true
			ORDER BY docente.cognome, docente.nome ASC
			;";
	$resultArray = dbGetAll($query);
	foreach ($resultArray as $row) {
		$docenteOptionList .= '
		<option value="' . $row['id'] . '" >' . $row['cognome'] . ' ' . $row['nome'] . '</option>
	';
	}
	?>

	<!-- Bootstrap Modals -->
	<!-- Modal - Add New Record -->
	<div class="modal fade" id="add_new_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-body">
					<div class="panel panel-lima4">
						<div class="panel-heading">
							<h5 class="modal-title" id="myModalLabel">Nuovo Assegnamento</h5>
						</div>
						<div class="panel-body">
							<form class="form-horizontal">

								<div class="form-group docente_incaricato_selector">
									<label class="col-sm-2 control-label" for="docente_incaricato">Docente</label>
									<div class="col-sm-8">
										<select id="docente_incaricato" name="docente_incaricato" class="docente_incaricato selectpicker" data-style="btn-success" data-live-search="true"
											data-noneSelectedText="seleziona..." data-width="70%">
											<?php echo $docenteOptionList ?>
										</select>
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-2 control-label" for="dettaglio">Dettaglio</label>
									<div class="col-sm-8"><input type="text" id="dettaglio" placeholder="dettaglio" class="form-control" /></div>
								</div>

								<div class="form-group">
									<label class="col-sm-2 control-label" for="ore" id="oreLabel">Ore</label>
									<div class="col-sm-8"><input type="text" id="ore" placeholder="ore" class="form-control" /></div>
								</div>
							</form>

						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
							<button type="button" class="btn btn-primary" onclick="oreAssegnateAddRecord()">Salva</button>
							<input type="hidden" id="hidden_ore_previste_tipo_attivita_id">
							<input type="hidden" id="hidden_ore_previste_attivita_id">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div> <!-- // Div - add_new_record_modal -->
	<!-- // Modal - Add New Record -->

	<!-- Modal - Update attivita details -->
	<div class="modal fade" id="update_attivita_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalProfiloLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-body">
					<div class="panel panel-lightblue4">
						<div class="panel-heading">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h5 class="modal-title" id="myModalProfiloLabel">Attività</h5>
						</div>
						<div class="panel-body">

							<div class="form-group">
								<label for="update_docente_incaricato">Docente</label>
								<input type="text" id="update_docente_incaricato" placeholder="docente" class="form-control" disabled />
							</div>

							<div class="form-group">
								<label for="update_attivita">Attività</label>
								<input type="text" id="update_attivita" placeholder="attività" class="form-control" disabled />
							</div>

							<div class="form-group">
								<label for="update_dettaglio">Dettaglio</label>
								<input type="text" id="update_dettaglio" placeholder="dettaglio" class="form-control" />
							</div>

							<div class="form-group">
								<label for="update_ore">Ore</label>
								<input type="text" id="update_ore" placeholder="ore" class="form-control" />
							</div>

						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
							<button type="button" class="btn btn-primary" onclick="attivitaUpdateDetails()">Salva</button>
							<input type="hidden" id="hidden_ore_previste_attivita_id">
							<input type="hidden" id="hidden_ore_previste_tipo_attivita_id">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- // Modal - Update docente details -->

</body>

</html>