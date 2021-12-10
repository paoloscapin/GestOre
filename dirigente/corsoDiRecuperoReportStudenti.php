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
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
ruoloRichiesto('segreteria-didattica','dirigente','docente');
?>
	<title>Report Corsi di Recupero</title>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/print_static.css">
</head>

<body >


<div id="body">
<div id="content">
<div class="page" style="font-size: 7pt">

<?php
require_once '../common/connect.php';

function printableVoto($voto) {
	if ($voto != 0) {
		if ($voto == 1) {
			return 'Assente';
		}
		return $voto;
	}
	return null;
}

function printableDate($data) {
	if ($data != null) {
		return strftime("%d/%m/%Y", strtotime($data));
	}
	return null;
}

$data = '';

// prepara l'elenco delle classi
$query = "	SELECT DISTINCT studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe
			FROM
				studente_per_corso_di_recupero studente_per_corso_di_recupero
			INNER JOIN corso_di_recupero corso_di_recupero
			ON studente_per_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
			WHERE
				corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id' AND NOT in_itinere
			ORDER BY
				studente_per_corso_di_recupero.classe ASC;
			";

foreach(dbGetAll($query) as $row_classe) {
	$classe = $row_classe['studente_per_corso_di_recupero_classe'];
	$data .= "
	<table style=\"width: 100%;\" class=\"header\">
	<tr>
	<td><h1 style=\"text-align: center\">Classe $classe</h1></td>
	</tr>
	</table>
";
	$query = "	SELECT
					studente_per_corso_di_recupero.cognome AS studente_per_corso_di_recupero_cognome,
					studente_per_corso_di_recupero.nome AS studente_per_corso_di_recupero_nome,
					studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe,
					studente_per_corso_di_recupero.voto_settembre AS studente_per_corso_di_recupero_voto_settembre,
					studente_per_corso_di_recupero.data_voto_settembre AS studente_per_corso_di_recupero_data_voto_settembre,
					studente_per_corso_di_recupero.voto_novembre AS studente_per_corso_di_recupero_voto_novembre,
					studente_per_corso_di_recupero.data_voto_novembre AS studente_per_corso_di_recupero_data_voto_novembre,
					studente_per_corso_di_recupero.passato AS studente_per_corso_di_recupero_passato,
					studente_per_corso_di_recupero.serve_voto AS studente_per_corso_di_recupero_serve_voto,
					corso_di_recupero.codice AS corso_di_recupero_codice,
					materia.nome AS materia_nome,
					docente_set.nome AS docente_set_nome,
					docente_set.cognome AS docente_set_cognome,
					docente_nov.nome AS docente_nov_nome,
					docente_nov.cognome AS docente_nov_cognome
				FROM
					studente_per_corso_di_recupero
				INNER JOIN corso_di_recupero corso_di_recupero
				ON studente_per_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
				INNER JOIN materia materia
				ON corso_di_recupero.materia_id = materia.id
				LEFT JOIN docente docente_set
				ON studente_per_corso_di_recupero.docente_voto_settembre_id = docente_set.id
				LEFT JOIN docente docente_nov
				ON studente_per_corso_di_recupero.docente_voto_novembre_id = docente_nov.id
				WHERE
					corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id'
				AND
					studente_per_corso_di_recupero.classe='$classe'
				ORDER BY
					corso_di_recupero.codice ASC,
					studente_per_corso_di_recupero.cognome ASC,
					studente_per_corso_di_recupero.nome ASC
				;
		";
	debug($query);
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}
	if(mysqli_num_rows($result) > 0) {
		$data .= '
					<table class="corso_report_items">
						<tr><td colspan="6"><h2>Corsi di Recupero: Classe '.$classe.'</h2></td></tr>
						<tbody>
							<tr>
								<th style="text-align: center;">Studente</th>
								<th style="text-align: center;">Materia</th>
								<th style="text-align: center;">Voto Sett</th>
								<th style="text-align: center;">Data Sett</th>
								<th style="text-align: center;">Docente Sett</th>
								<th style="text-align: center;">Voto Nov</th>
								<th style="text-align: center;">Data Nov</th>
								<th style="text-align: center;">Docente Nov</th>
							</tr>

';
		$resultArrayStudente = $result->fetch_all(MYSQLI_ASSOC);
		$classname = "";
		foreach($resultArrayStudente as $row_studente) {
			$classname = ($classname==="even_row") ? "odd_row" : "even_row";
			$data .= '
							<tr class="'.$classname.'">
								<td style="text-align: left;width: 15%;">'.$row_studente['studente_per_corso_di_recupero_cognome'].' '.$row_studente['studente_per_corso_di_recupero_nome'].'</td>
								<td style="text-align: left;width: 15%;">'.$row_studente['materia_nome'].'</td>
								<td style="text-align: center;width: 10%;">'.printableVoto($row_studente['studente_per_corso_di_recupero_voto_settembre']).'</td>
								<td style="text-align: center;width: 10%;">'.printableDate($row_studente['studente_per_corso_di_recupero_data_voto_settembre']).'</td>
								<td style="text-align: left;width: 15%;"><small>'.$row_studente['docente_set_cognome'].' '.$row_studente['docente_set_nome'].'</small></td>
								<td style="text-align: center;width: 10%;">'.printableVoto($row_studente['studente_per_corso_di_recupero_voto_novembre']).'</td>
								<td style="text-align: center;width: 10%;">'.printableDate($row_studente['studente_per_corso_di_recupero_data_voto_novembre']).'</td>
								<td style="text-align: left;width: 15%;"><small>'.$row_studente['docente_nov_cognome'].' '.$row_studente['docente_nov_nome'].'</small></td>
							</tr>
';
		}
		$data .= '
						</tbody>
					</table>
<div style="page-break-after: always;"></div>
';
	}
}
echo $data;
?>

</div>
</div>
</div>
</body>
</html>