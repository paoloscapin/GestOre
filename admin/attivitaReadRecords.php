<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th class="col-md-1 text-left">Categoria</th>
						<th class="col-md-2 text-left">Nome</th>
						<th class="col-md-1 text-left">Funzionali</th>
						<th class="col-md-1 text-left">Con Studenti</th>
						<th class="col-md-1 text-left">Clil</th>
						<th class="col-md-1 text-left">Orientamento</th>
						<th class="col-md-1 text-left">Valido</th>
						<th class="col-md-1 text-left">Previsto da docente</th>
						<th class="col-md-1 text-left">Inserito da docente</th>
						<th class="col-md-1 text-left">Da Rendicontare</th>
						<th class="col-md-1 text-left"></th>
					</tr>';

$query = "	SELECT
				ore_previste_tipo_attivita.id AS local_ore_previste_tipo_attivita_id,
				ore_previste_tipo_attivita.*
			FROM ore_previste_tipo_attivita
			";

$query .= "order by categoria, nome";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
    <td>'.$row['categoria'].'</td>
    <td>'.$row['nome'].'</td>
    ';
	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="funzionali" ' . ($row['funzionali']? 'checked ' : '').'></td>';
	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="con_studenti" ' . ($row['con_studenti']? 'checked ' : '').'></td>';
	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="clil" ' . ($row['clil']? 'checked ' : '').'></td>';
	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="orientamento" ' . ($row['orientamento']? 'checked ' : '').'></td>';

	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="valido" ' . ($row['valido']? 'checked ' : '').'></td>';
	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="previsto_da_docente" ' . ($row['previsto_da_docente']? 'checked ' : '').'></td>';
	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="inserito_da_docente" ' . ($row['inserito_da_docente']? 'checked ' : '').'></td>';
	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="da_rendicontare" ' . ($row['da_rendicontare']? 'checked ' : '').'></td>';
	$data .='
		<td class="text-center">
		<button onclick="attivitaGetDetails('.$row['local_ore_previste_tipo_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="attivitaDelete('.$row['local_ore_previste_tipo_attivita_id'].', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>

