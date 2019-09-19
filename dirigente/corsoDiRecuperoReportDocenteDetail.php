<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$docente_id = $_GET["docente_id"];
$query = "	SELECT
					corso_di_recupero.id AS corso_di_recupero_id,
					docente.nome AS docente_nome,
					docente.cognome AS docente_cognome
				FROM
					corso_di_recupero corso_di_recupero
				INNER JOIN docente docente
				ON corso_di_recupero.docente_id = docente.id
				WHERE
					docente.id = $docente_id
				AND
					corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id';
			";

foreach(dbGetAll($query) as $row) {
	// lo fissa anche se sarebbe sempre lo stesso
	$docenteNome = $row['docente_nome'];
	$docenteCognome = $row['docente_cognome'];
	$_GET['idCorso'] = $row['corso_di_recupero_id'];
	include '../dirigente/corsoDiRecuperoReportDetail.php';
}

$data = '';

$query = "	SELECT SUM(lezione_corso_di_recupero.numero_ore) FROM lezione_corso_di_recupero
			INNER JOIN corso_di_recupero corso_di_recupero
			ON lezione_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
			INNER JOIN docente docente
			ON corso_di_recupero.docente_id = docente.id
			WHERE docente.id = $docente_id AND lezione_corso_di_recupero.firmato = true
	";
$oreFatte = dbGetValue($query);
$data .= '
<div class="panel panel-info">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-12 text-center">
			<h2>' . $docenteCognome . ' ' . $docenteNome . '</h2>
		</div>
	</div>
</div>
<div class="panel-body">
	<div class="row">
		<div class="col-md-12 text-center">
			<h2>Totale ore firmate = ' . $oreFatte . '</h2>
		</div>
	</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
			';

echo $data;
?>
