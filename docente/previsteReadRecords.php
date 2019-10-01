<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$modificabile = $__config->getOre_previsioni_aperto();

$docente_id = $__docente_id;
if (haRuolo('dirigente')) {
	// il dirigente può sempre fare modifiche
	$modificabile = true;
}

$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<tr>
							<th>Tipo</th>
							<th>Nome</th>
							<th>Dettaglio</th>
							<th>ore</th>
							<th></th>
						</tr>';

$query = "	SELECT
					ore_previste_attivita.id AS ore_previste_attivita_id,
					ore_previste_attivita.ore AS ore_previste_attivita_ore,
					ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
					ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
					ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
					ore_previste_tipo_attivita.inserito_da_docente AS ore_previste_tipo_attivita_inserito_da_docente,
					ore_previste_tipo_attivita.previsto_da_docente AS ore_previste_tipo_attivita_previsto_da_docente,
					ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome

				FROM ore_previste_attivita ore_previste_attivita
				INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
				ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
				WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND ore_previste_attivita.docente_id = $docente_id
				ORDER BY
				ore_previste_tipo_attivita.inserito_da_docente DESC,
				ore_previste_tipo_attivita.previsto_da_docente DESC,
				ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC;"
				;

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
	<td>'.$row['ore_previste_tipo_attivita_categoria'].'</td>
	<td>'.$row['ore_previste_tipo_attivita_nome'].'</td>
	<td>'.$row['ore_previste_attivita_dettaglio'].'</td>
	<td>'.$row['ore_previste_attivita_ore'].'</td>
	';

	$data .='<td>';
	// si possono modificare solo le righe previste da docente
	if ($row['ore_previste_tipo_attivita_previsto_da_docente']) {
		if ($modificabile) {
			$data .='
			<button onclick="previstaModifica('.$row['ore_previste_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="previstaDelete('.$row['ore_previste_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		';
		}
	}
	$data .='</td></tr>';
}

$data .= '</table></div>';

echo $data;
?>
