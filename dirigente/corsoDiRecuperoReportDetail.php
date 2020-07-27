<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
	require_once '../common/connect.php';

	$idCorso = $_GET["idCorso"];
	$query = "	SELECT
					corso_di_recupero.id AS corso_di_recupero_id,
					corso_di_recupero.codice AS corso_di_recupero_codice,
					docente.nome AS docente_nome,
					docente.cognome AS docente_cognome,
					materia.nome AS materia_nome
				FROM
					corso_di_recupero corso_di_recupero
				INNER JOIN docente docente
				ON corso_di_recupero.docente_id = docente.id
				INNER JOIN materia materia
				ON corso_di_recupero.materia_id = materia.id
				WHERE corso_di_recupero.id = $idCorso;
			";
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}
	$data = '';
	if(mysqli_num_rows($result) > 0) {
		$resultArray = $result->fetch_all(MYSQLI_ASSOC);
		foreach($resultArray as $row) {
			$data .= '
<div class="panel panel-success">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<h4>'.$row['corso_di_recupero_codice'].'</h4>
		</div>
		<div class="col-md-4 text-center">
			<h4>'.$row['materia_nome'].'</h4>
		</div>
		<div class="col-md-4 text-right">
			<h4>'.$row['docente_cognome'].' '.$row['docente_nome'].'</h4>
		</div>
	</div>
</div>
<div class="panel-body">
			';
//-------------------------------------------------------------
			$query = "	SELECT
							lezione_corso_di_recupero.id AS lezione_corso_di_recupero_id,
							lezione_corso_di_recupero.data AS lezione_corso_di_recupero_data,
							lezione_corso_di_recupero.orario AS lezione_corso_di_recupero_orario,
							lezione_corso_di_recupero.firmato AS lezione_corso_di_recupero_firmato,
							lezione_corso_di_recupero.argomento AS lezione_corso_di_recupero_argomento,
							lezione_corso_di_recupero.note AS lezione_corso_di_recupero_note
						FROM lezione_corso_di_recupero
						WHERE lezione_corso_di_recupero.corso_di_recupero_id = $idCorso
						"
						;
			$query .= "
						ORDER BY
							lezione_corso_di_recupero.data ASC
						"
						;
			if (!$result = mysqli_query($con, $query)) {
				exit(mysqli_error($con));
			}

			if(mysqli_num_rows($result) > 0) {
				$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green" id="corso_table">
									<tr>
										<th class="col-md-1">Data</th>
										<th class="col-md-5">Argomento</th>
										<th class="col-md-5">Note</th>
										<th class="col-md-1">Firma</th>
									</tr>';
				$resultArray = $result->fetch_all(MYSQLI_ASSOC);
				foreach($resultArray as $row) {
					$firmaMarker = '';
					if ($row['lezione_corso_di_recupero_firmato']) {
						$firmaMarker = '<span class=\'label label-success\'>firmato</span>';
					} else {
						$firmaMarker = '<span class=\'label label-warning\'>non firmato</span>';
					}
					$data .= '<tr>
					<td>'.date('j M', strtotime($row['lezione_corso_di_recupero_data'])).'</td>
					<td>'.$row['lezione_corso_di_recupero_argomento'].'</td>
					<td><p class="form-control-static" style="white-space: pre-wrap;" >'.$row['lezione_corso_di_recupero_note'].'</p></td>
					<td>'.$firmaMarker.'</td>
					</tr>';
				}
				$data .= '</table></div>';
			}

//-------------------------------------------------------------
	$data .= '
		<div class="table-wrapper"><table class="table table-bordered table-striped">
';

			$query = "	SELECT
							studente_partecipa_lezione_corso_di_recupero.id AS studente_partecipa_lezione_corso_di_recupero_id,
							studente_partecipa_lezione_corso_di_recupero.ha_partecipato AS studente_partecipa_lezione_corso_di_recupero_ha_partecipato,
							studente_per_corso_di_recupero.id AS studente_per_corso_di_recupero_id,
							studente_per_corso_di_recupero.cognome AS studente_per_corso_di_recupero_cognome,
							studente_per_corso_di_recupero.nome AS studente_per_corso_di_recupero_nome,
							studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe,
							studente_per_corso_di_recupero.voto_settembre AS studente_per_corso_di_recupero_voto_settembre,
							studente_per_corso_di_recupero.voto_novembre AS studente_per_corso_di_recupero_voto_novembre,
							studente_per_corso_di_recupero.passato AS studente_per_corso_di_recupero_passato,
							studente_per_corso_di_recupero.serve_voto AS studente_per_corso_di_recupero_serve_voto,
							lezione_corso_di_recupero.id AS lezione_per_corso_di_recupero_id,
							lezione_corso_di_recupero.data AS lezione_per_corso_di_recupero_data
						FROM studente_partecipa_lezione_corso_di_recupero
						INNER JOIN studente_per_corso_di_recupero studente_per_corso_di_recupero
						ON studente_partecipa_lezione_corso_di_recupero.studente_per_corso_di_recupero_id = studente_per_corso_di_recupero.id
						INNER JOIN lezione_corso_di_recupero lezione_corso_di_recupero
						ON studente_partecipa_lezione_corso_di_recupero.lezione_corso_di_recupero_id = lezione_corso_di_recupero.id
						WHERE
							lezione_corso_di_recupero.corso_di_recupero_id = $idCorso
						ORDER BY
							studente_per_corso_di_recupero.cognome ASC,
							studente_per_corso_di_recupero.nome ASC,
							lezione_corso_di_recupero.data ASC

						"
						;

			if (!$result = mysqli_query($con, $query)) {
				exit(mysqli_error($con));
			}
			$partecipaArray = $result->fetch_all(MYSQLI_ASSOC);
			$cognomeNomeClasse = '';
			$primaRiga = true;
			$primoStudente = true;
			$dateLezioni = array();
			foreach($partecipaArray as $partecipaRow) {
				$nuovoCognomeNomeClasse = $partecipaRow['studente_per_corso_di_recupero_cognome'].' '.$partecipaRow['studente_per_corso_di_recupero_nome'].' - '.$partecipaRow['studente_per_corso_di_recupero_classe'];
				if ($nuovoCognomeNomeClasse !== $cognomeNomeClasse) {
					$cognomeNomeClasse = $nuovoCognomeNomeClasse;
					if ($primaRiga) {
						$primaRiga = false;
					} else {
						$primoStudente = false;
						$data .= '</tr>';
					}
					$data .= '
						<tr>
							<td>'.$nuovoCognomeNomeClasse.'</td>
						';
					$esente = (!empty($partecipaRow['studente_per_corso_di_recupero_serve_voto'])) && $partecipaRow['studente_per_corso_di_recupero_serve_voto'] == 0;

					if ($esente) {
						$data .= '
    							<td></td>';
						$data .= '
    							<td></td>';
					} else {
						$votoSettembre = $partecipaRow['studente_per_corso_di_recupero_voto_settembre'];
						if ($votoSettembre >= 4) {
							$bgColor = ($votoSettembre <= 5) ? 'red' : 'green';
							$data .= '
							<td class="col-md-1 text-center"><span class=\'label label-info\' style=\'background-color: '.$bgColor.';\' >'.$votoSettembre.'</span></td>
			';
						} else {
							$data .= '
    							<td class="col-md-1 text-center"></td>';
						}

						$votoNovembre = $partecipaRow['studente_per_corso_di_recupero_voto_novembre'];
						if ($votoNovembre >= 4) {
							$bgColor = ($votoNovembre <= 5) ? 'red' : 'green';
							$data .= '
							<td class="col-md-1 text-center"><span class=\'label label-info\' style=\'background-color: '.$bgColor.';\' >'.$votoNovembre.'</span></td>
			';
						} else {
							$data .= '
    							<td class="col-md-1 text-center"></td>';
						}

					}

					$passatoMarker = '';
					if ($partecipaRow['studente_per_corso_di_recupero_passato']) {
						$passatoMarker = '<span class=\'label label-success\'>passato</span>';
					} else if (isset($partecipaRow['studente_per_corso_di_recupero_passato']) && $partecipaRow['studente_per_corso_di_recupero_passato'] == 0){
						$passatoMarker = '<span class=\'label label-danger\'>non passato</span>';
					}
					if ($esente) {
						$passatoMarker = '<span class=\'label label-info\'>esente</span>';
					}

					$data .= '<td class="col-md-1 text-center">'.$passatoMarker.'</td>';

				}
				$presenteMarker = '';
				if ($partecipaRow['studente_partecipa_lezione_corso_di_recupero_ha_partecipato']) {
					$presenteMarker = '<span class=\'label label-success\'>presente</span>';
				} else {
					$presenteMarker = '<span class=\'label label-danger\'>assente</span>';
				}

				$data .= '<td class="col-md-1 text-center">'.$presenteMarker.'</td>';
				if ($primoStudente) {
					array_push($dateLezioni, $partecipaRow['lezione_per_corso_di_recupero_data']);
				}
			}

			$data .= '</tr>
									<thead>
								<th>studente</th>
								<th>voto settembre</th>
								<th>voto novembre</th>
								<th>passato</th>
			';
			foreach($dateLezioni as $dataLezione) {
				$data .= '<th class="col-md-1 text-center">'.date('j M', strtotime($dataLezione)).'</th>
				';
			}

		$data .= '
						</thead>
		</table></div>
		';

//-------------------------------------------------------------
	$data .= '
</div>

<!-- <div class="panel-footer"></div> -->
</div>
			';
			echo $data;
		}
	}
?>
