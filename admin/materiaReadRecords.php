<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

function materiaReadH($value): string
{
	return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function materiaReadTableExists(string $tableName): bool
{
	return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

function materiaReadColumnExists(string $tableName, string $columnName): bool
{
	$row = dbGetFirst("SHOW COLUMNS FROM `$tableName` LIKE " . dbQ($columnName));
	return is_array($row) && !empty($row);
}

$hasDipartimento = materiaReadTableExists('dipartimenti') && materiaReadColumnExists('materia', 'id_dipartimento');

// Design initial table header
$data = '<style>
	.materia-table.table-green thead th:last-child,
	.materia-table.table-green tbody td:last-child {
		border: 1px solid #ddd !important;
	}
	.materia-table.table-green tbody td:last-child {
		border-left: 1px solid #c7ecc7 !important;
	}
	.materia-table.table-green tbody tr:hover td:last-child {
		border-color: #ffff0f !important;
	}
</style>
<div class="table-wrapper"><table class="table table-bordered table-striped table-green materia-table">
					<thead>
					<tr>
						<th>codice</th>
						<th>Nome</th>
						' . ($hasDipartimento ? '<th>Dipartimento</th>' : '') . '
						<th class="text-center">Modifica</th>
					</tr>
					</thead>
					<tbody>';

$query = "	SELECT
				materia.id AS local_id,
				materia.*" . ($hasDipartimento ? ",
				dipartimenti.nome AS dipartimento_nome" : "") . "
			FROM materia
			" . ($hasDipartimento ? "LEFT JOIN dipartimenti ON dipartimenti.id = materia.id_dipartimento" : "") . "
			";

$query .= "order by nome";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
		<td>'.materiaReadH($row['codice']).'</td>
		<td>'.materiaReadH($row['nome']).'</td>
		' . ($hasDipartimento ? '<td>'.materiaReadH($row['dipartimento_nome'] ?? '').'</td>' : '') . '
		';
	$data .='
		<td class="text-center" style="white-space:nowrap;">
		<button onclick="materiaGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="materiaDelete('.$row['local_id'].', \''.addslashes((string)$row['nome']).'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</tbody></table></div>';
echo $data;
?>

