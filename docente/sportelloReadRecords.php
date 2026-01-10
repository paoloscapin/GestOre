<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$ancheCancellati     = (int)($_GET["ancheCancellati"] ?? 0);
$soloNuovi           = (int)($_GET["soloNuovi"] ?? 0);
$soloMiei            = (int)($_GET["soloMiei"] ?? 0);
$categoria_filtro_id = (int)($_GET["categoria_filtro_id"] ?? 0);
$materia_filtro_id   = (int)($_GET["materia_filtro_id"] ?? 0);
$classe_filtro_id    = (int)($_GET["classe_filtro_id"] ?? 0);

$direzioneOrdinamento = "ASC";

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Categoria</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-1">Data</th>
						<th class="text-center col-md-1">Ora</th>';

if ($__settings->sportelli->unSoloArgomento == true) {
	$data .= '			<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-2">Argomento</th>
						';
} else {
	$data .= '			<th class="text-center col-md-3">Materia</th>
						';
}

$data .= '				<th class="text-center col-md-1">Ore</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Luogo</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">Studenti Prenotati</th>
						<th class="text-center col-md-1">Max prenotazioni</th>
						<th class="text-center col-md-1"></th>
					</tr>
					</thead><tbody>';

$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.docente_id AS sportello_docente_id,
				sportello.data AS sportello_data,
				sportello.ora AS sportello_ora,
				sportello.numero_ore AS sportello_numero_ore,
				sportello.argomento as sportello_argomento,
				sportello.luogo AS sportello_luogo,
				sportello.classe AS sportello_classe,
				sportello.firmato AS sportello_firmato,
				sportello.cancellato AS sportello_cancellato,
				sportello.categoria AS sportello_categoria,
				sportello.online AS sportello_online,
				sportello.clil AS sportello_clil,
				sportello.orientamento AS sportello_orientamento,
				sportello.max_iscrizioni AS sportello_max_iscrizioni,
				materia.nome AS materia_nome,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome,
				(SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti
			FROM sportello sportello
			INNER JOIN docente docente ON sportello.docente_id = docente.id
			INNER JOIN materia materia ON sportello.materia_id = materia.id
			INNER JOIN classi classe ON sportello.classe_id = classe.id
			";

// filtri: costruiamo una WHERE unica
$where = [];
$where[] = "sportello.anno_scolastico_id = $__anno_scolastico_corrente_id";

if ($categoria_filtro_id > 0) {
	// todo: trasformare la categoria in id invece che nome
	$categoria_filtro_nome = dbGetValue("SELECT nome FROM sportello_categoria WHERE id='$categoria_filtro_id';");
	if (!empty($categoria_filtro_nome)) {
		$where[] = "sportello.categoria = '" . escapeString($categoria_filtro_nome) . "'";
	}
}

if ($classe_filtro_id > 0) {
    $classe_filtro_id = intval($classe_filtro_id);

    // include anche i sottogruppi definiti in classe_include
    $query .= "AND sportello.classe_id IN (
                    SELECT ci.includes_classe_id
                    FROM classe_include ci
                    WHERE ci.classe_id = $classe_filtro_id
               ) ";
}

if ($materia_filtro_id > 0) {
	$where[] = "sportello.materia_id = $materia_filtro_id";
}

if ($soloMiei) {
	$where[] = "sportello.docente_id = $__docente_id";
}

if (!$ancheCancellati) {
	$where[] = "NOT sportello.cancellato";
}

if ($soloNuovi) {
	$where[] = "sportello.data >= CURDATE()";
}

$query .= " WHERE " . implode(" AND ", $where);
$query .= " ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC, docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) {
	$sportello_id          = (int)$row['sportello_id'];
	$sportello_categoria   = $row['sportello_categoria'];
	$sportello_firmato     = (int)$row['sportello_firmato'];
	$sportello_cancellato  = (int)$row['sportello_cancellato'];
	$sportello_nstudenti   = (int)$row['numero_studenti'];
	$sportello_docente_id  = (int)$row['sportello_docente_id'];

	// può modificare SOLO se è il suo sportello
	$isMioSportello = ($sportello_docente_id === (int)$__docente_id);

	$statoMarker = '';
	if ($sportello_cancellato) {
		$statoMarker .= '<span class="label label-default">cancellato</span>';
	} else {
		if ($sportello_firmato) {
			$statoMarker .= '<span class="label label-primary">firmato</span>';
		} else {
			if ((int)$row['sportello_max_iscrizioni'] === (int)$row['numero_studenti']) {
				$statoMarker .= '<span class="label label-danger">posti esauriti</span>';
			} else {
				$statoMarker .= '<span class="label label-success">posti disponibili</span>';
			}
		}
	}

	$dt_sportello = $row['sportello_data'];
	$dt_oggi = date("Y-m-d");
	$vecchio = 0;
	if (strtotime($dt_sportello) < strtotime($dt_oggi)) {
		$vecchio = 1;
	}

	$dataSportello = '';
	if ($row['sportello_data']) {
		$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
		$dataSportello = utf8_encode(strftime("%d %B %Y", strtotime($row['sportello_data'])));
		setlocale(LC_TIME, $oldLocale);
	}

	// tooltip studenti prenotati
	$studenteTip = '';
	if ($sportello_nstudenti > 0) {
		$query2 = "SELECT
				sportello_studente.id AS sportello_studente_id,
				sportello_studente.iscritto AS sportello_studente_iscritto,
				sportello_studente.presente AS sportello_studente_presente,
				sportello_studente.note AS sportello_studente_note,
				studente.cognome AS studente_cognome,
				studente.nome AS studente_nome,
				c.classe AS studente_classe,
				studente.id AS studente_id
			FROM sportello_studente
			INNER JOIN studente ON sportello_studente.studente_id = studente.id
			INNER JOIN studente_frequenta sf ON sf.id_studente = studente.id AND sf.id_anno_scolastico = $__anno_scolastico_corrente_id
			INNER JOIN classi c ON sf.id_classe = c.id
			WHERE sportello_studente.sportello_id = $sportello_id";

		$studenti = dbGetAll($query2);
		if ($studenti == null) $studenti = [];
		foreach ($studenti as $studente) {
			$studenteTip .= $studente['studente_cognome'] . " " . $studente['studente_nome'] . " " . $studente['studente_classe'] . "<br>";
		}
	}

	// marker online
	$luogo_or_online_marker = $row['sportello_luogo'];
	if ((int)$row['sportello_online'] === 1) {
		$luogo_or_online_marker = '<span class="label label-danger">online</span>';
	}

	$barrato = '';
	$sbarrato = '';
	if ($sportello_cancellato) {
		$barrato = '<s>';
		$sbarrato = '</s>';
	}

	$docenteLabel = $row['docente_cognome'] . ' ' . $row['docente_nome'];

	$data .= '<tr>
		<td align="center">' . $barrato . $sportello_categoria . $sbarrato . '</td>
		<td>' . $barrato . htmlspecialchars($docenteLabel, ENT_QUOTES, 'UTF-8') . $sbarrato . '</td>
		<td align="center">' . $barrato . $dataSportello . $sbarrato . '</td>
		<td align="center">' . $barrato . $row['sportello_ora'] . $sbarrato . '</td>
		<td>' . $barrato . $row['materia_nome'] . $sbarrato . '</td>';

	if ($__settings->sportelli->unSoloArgomento == true) {
		$data .= '<td>' . $barrato . $row['sportello_argomento'] . $sbarrato . '</td>';
	}

	$data .= '
		<td align="center">' . $barrato . $row['sportello_numero_ore'] . $sbarrato . '</td>
		<td align="center">' . $barrato . $row['sportello_classe'] . $sbarrato . '</td>
		<td align="center">' . $barrato . $luogo_or_online_marker . $sbarrato . '</td>
		<td class="text-center">' . $statoMarker . '</td>
		<td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="' . htmlspecialchars($studenteTip, ENT_QUOTES, 'UTF-8') . '">' . $barrato . $row['numero_studenti'] . $sbarrato . '</td>
		<td class="text-center">' . $barrato . $row['sportello_max_iscrizioni'] . $sbarrato . '</td>
		';

	// modificabile SOLO se:
	// - non cancellato
	// - non firmato
	// - non vecchio
	// - ed è del docente loggato
	if ((!$sportello_cancellato) && (!$sportello_firmato) && (!$vecchio) && $isMioSportello) {
		$data .= '
		<td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="Clicca qui per gestire lo sportello">
			<button onclick="sportelloGetDetails(' . $sportello_id . ',true,' . $sportello_nstudenti . ',\'' . $sportello_categoria . '\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></span></button>';

		// ✅ trash SOLO qui (mai quando non modificabile)
		if ($__settings->sportelli->docente_puo_eliminare) {
			$data .= '
			<button onclick="sportelloDelete(' . $sportello_id . ', \'' . $row['materia_nome'] . '\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></span></button>';
		}

		$data .= '</td></tr>';
	} else {
		$data .= '
		<td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="Sportello non modificabile">
			<button style="padding: 0;border: none;background: none;" onclick="sportelloGetDetails(' . $sportello_id . ',false,' . $sportello_nstudenti . ',\'' . $sportello_categoria . '\')">
				<span class="btn btn-danger btn-xs glyphicon glyphicon-lock"></span>
			</button>
		</td></tr>';
	}
}

$data .= '</tbody></table></div>';
echo $data;
