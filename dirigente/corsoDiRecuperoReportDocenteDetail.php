<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

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
if (!$result = mysqli_query($con, $query)) {
    exit(mysqli_error($con));
}
if (mysqli_num_rows($result) > 0) {
    $resultArray = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($resultArray as $row) {
        $_GET['idCorso'] = $row['corso_di_recupero_id'];
        include '../dirigente/corsoDiRecuperoReportDetail.php';
    }
}

$query = "	SELECT
					ore_con_studenti.corsi_recupero_settembre_fatte ore_con_studenti_corsi_recupero_settembre_fatte,
					docente.nome AS docente_nome,
					docente.cognome AS docente_cognome
				FROM
					ore_con_studenti ore_con_studenti
				INNER JOIN docente docente
				ON ore_con_studenti.docente_id = docente.id
				WHERE
					docente.id = $docente_id
				AND
					ore_con_studenti.anno_scolastico_id = '$__anno_scolastico_corrente_id';
			";
if (!$result = mysqli_query($con, $query)) {
    exit(mysqli_error($con));
}
if (mysqli_num_rows($result) > 0) {
    $resultArray = $result->fetch_all(MYSQLI_ASSOC);
    $data = '';
    foreach ($resultArray as $row) {
        $data .= '
<div class="panel panel-info">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-12 text-center">
			<h2>' . $row['docente_cognome'] . ' ' . $row['docente_nome'] . '</h2>
		</div>
	</div>
</div>
<div class="panel-body">
	<div class="row">
		<div class="col-md-12 text-center">
			<h2>Totale ore firmate = ' . $row['ore_con_studenti_corsi_recupero_settembre_fatte'] . '</h2>
		</div>
	</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
			';
        echo $data;
    }
}
