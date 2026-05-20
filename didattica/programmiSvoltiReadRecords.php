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
require_once '../common/programmiSvoltiCopertineLib.php';
require_once '../common/__Settings.php';

function programmiSvoltiCanExportClasseWord(int $classeId, int $annoScolasticoId, int $docenteCorrenteId): bool
{
	if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
		return true;
	}

	if (!impersonaRuolo('docente') || $docenteCorrenteId <= 0) {
		return false;
	}

	$coord = dbGetFirst("SELECT id FROM coordinatori WHERE id_docente=" . intval($docenteCorrenteId) . " AND id_classe=" . intval($classeId) . " AND (id_anno_scolastico=" . intval($annoScolasticoId) . " OR id_anno_scolastico IS NULL OR id_anno_scolastico=0)");
	if ($coord == null) {
		$coord = dbGetFirst("SELECT id FROM coordinatori WHERE id_docente=" . intval($docenteCorrenteId) . " AND id_classe=" . intval($classeId) . " LIMIT 1");
	}
	return $coord != null;
}

$classe_filtro_raw = $_GET["classi_id"] ?? 0;
$classe_filtro_id = intval($classe_filtro_raw);
$classe_filtro_articolata_id = 0;

if (is_string($classe_filtro_raw) && strlen($classe_filtro_raw) > 1 && strtoupper(substr($classe_filtro_raw, 0, 1)) === 'A') {
	$classe_filtro_articolata_id = intval(substr($classe_filtro_raw, 1));
	$classe_filtro_id = 0;
}

$materia_filtro_id = intval($_GET["materia_id"] ?? 0);
$docenti_filtro_id = intval($_GET["docenti_id"] ?? 0);
$da_completare_filtro_id = intval($_GET["da_completare_id"] ?? 0);
$anni_filtro_id = intval($_GET["anni_id"] ?? 0);
$sollecito_lista = '';
$coordinatore_classi = [];
$coordinatore_classi_ids = [];
$coordinatore_classi_by_classe = [];
$coordinatore_vede_programmi_altri = getSettingsValue('programmiSvolti', 'coordinatore_vede_programmi_altri_docenti', true);

$utente_ruolo = $GLOBALS['__utente_ruolo'] ?? '';
$docente_corrente_id = intval($GLOBALS['__docente_id'] ?? 0);
$copertineTableExists = programmiSvoltiCopertineTableExists();

$is_contesto_docente = (
	$utente_ruolo === 'docente'
	|| impersonaRuolo('docente')
	|| $docente_corrente_id > 0
);

if ($docente_corrente_id <= 0 && $is_contesto_docente) {
	$query_docente = "SELECT id FROM docente WHERE attivo=1";
	if (!empty($__username) && !empty($__useremail)) {
		$query_docente .= " AND (username='" . dbEscape($__username) . "' OR email='" . dbEscape($__useremail) . "')";
	} else if (!empty($__username)) {
		$query_docente .= " AND username='" . dbEscape($__username) . "'";
	} else if (!empty($__useremail)) {
		$query_docente .= " AND email='" . dbEscape($__useremail) . "'";
	} else {
		$query_docente .= " AND 1=0";
	}
	$result_docente = dbGetFirst($query_docente);
	if ($result_docente != null) {
		$docente_corrente_id = intval($result_docente['id']);
	}
}

if ($docente_corrente_id > 0) {
	$query_coord = "SELECT id_classe, id_anno_scolastico FROM coordinatori WHERE id_docente=" . $docente_corrente_id;
	$result_coord = dbGetAll($query_coord);
	if ($result_coord == null) {
		$result_coord = [];
	}
	foreach ($result_coord as $coord_row) {
		$coord_classe_id = intval($coord_row['id_classe']);
		$coord_key = $coord_classe_id . '_' . intval($coord_row['id_anno_scolastico']);
		$coordinatore_classi[$coord_key] = true;
		$coordinatore_classi_ids[$coord_classe_id] = true;
		$coordinatore_classi_by_classe[$coord_classe_id] = true;
	}
}

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-3">Materia</th>
						<th class="text-center col-md-2">Azioni</th>
						<th class="text-center col-md-2">Ultimo aggiornamento</th>
						<th class="text-center col-md-2">Autore ultimo aggiornamento</th>
					</tr>
					</thead>';

$query = "	SELECT
			    programmi_svolti.id AS programma_id,
				programmi_svolti.id_classe AS classe_id,
				programmi_svolti.id_docente AS docente_id,
				programmi_svolti.id_materia AS materia_id,
				programmi_svolti.id_anno_scolastico AS anno_scolastico_id,
				programmi_svolti.id_utente AS utente_id,
				programmi_svolti.updated AS ultimo_agg,
                classi.id,
                classi.classe AS classe_nome,
				(
					SELECT GROUP_CONCAT(c2.classe ORDER BY c2.classe SEPARATOR ' / ')
					FROM programmi_svolti_classi psc2
					INNER JOIN classi c2 ON c2.id = psc2.id_classe
					WHERE psc2.id_programma_svolto = programmi_svolti.id
				) AS classi_collegate_nome,
                classi.anno AS classe_anno,
                materia.id,
                materia.nome AS materia_nome,
				docente.id,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome,
				utente.id,
				utente.nome AS utente_nome,
				utente.cognome AS utente_cognome
			FROM programmi_svolti
			INNER JOIN classi classi
			ON programmi_svolti.id_classe = classi.id
			INNER JOIN materia materia
			ON programmi_svolti.id_materia = materia.id
			INNER JOIN docente docente
			ON programmi_svolti.id_docente = docente.id
			INNER JOIN utente utente
			ON programmi_svolti.id_utente = utente.id
			WHERE 1=1";

if ($anni_filtro_id > 0) {
	$query .= " AND programmi_svolti.id_anno_scolastico=" . $anni_filtro_id;
}

if ($classe_filtro_articolata_id > 0) {
	$query .= "
		AND EXISTS (
			SELECT 1
			FROM programmi_svolti_classi psc_filter
			INNER JOIN classi_articolate_classi cac_filter
				ON cac_filter.id_classe = psc_filter.id_classe
			INNER JOIN classi_articolate ca_filter
				ON ca_filter.id = cac_filter.id_articolata
			WHERE psc_filter.id_programma_svolto = programmi_svolti.id
			  AND ca_filter.id = " . intval($classe_filtro_articolata_id) . "
			  AND ca_filter.attiva = 1
			  AND ca_filter.id_anno_scolastico = " . intval($anni_filtro_id) . "
		)
	";
} else if ($classe_filtro_id > 0) {
	$query .= "
		AND EXISTS (
			SELECT 1
			FROM programmi_svolti_classi psc_filter
			WHERE psc_filter.id_programma_svolto = programmi_svolti.id
			  AND psc_filter.id_classe = " . intval($classe_filtro_id) . "
		)
	";
}
if ($materia_filtro_id > 0) {
	$query .= " AND programmi_svolti.id_materia=$materia_filtro_id ";
}
if ($is_contesto_docente) {
	if ($docenti_filtro_id > 0) {
		if ($docente_corrente_id > 0 && $docenti_filtro_id === $docente_corrente_id) {
			$query .= " AND programmi_svolti.id_docente=" . intval($docente_corrente_id);
		} else {
			$query .= " AND 1=0";
		}
	} else {
	$coord_class_ids = array_keys($coordinatore_classi_ids);
	if ($coordinatore_vede_programmi_altri && $docente_corrente_id > 0 && count($coord_class_ids) > 0) {
		$query .= " AND (
	programmi_svolti.id_docente=" . intval($docente_corrente_id) . "
			OR EXISTS (
				SELECT 1
				FROM programmi_svolti_classi psc_coord
				WHERE psc_coord.id_programma_svolto = programmi_svolti.id
				AND psc_coord.id_classe IN (" . implode(',', array_map('intval', $coord_class_ids)) . ")
			)
		)";
	} else if ($docente_corrente_id > 0) {
		$query .= " AND programmi_svolti.id_docente=" . $docente_corrente_id;
	}
	}
} else if ($docenti_filtro_id > 0) {
	$query .= " AND programmi_svolti.id_docente=" . intval($docenti_filtro_id);
}

$query .= " ORDER BY classe_nome ASC, materia_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) {

	$programma_id = $row['programma_id'];

	$query = "SELECT COUNT(*) from programmi_svolti_moduli WHERE id_programma=" . $programma_id;
	$nmodulisvolti = dbGetValue($query);

	if (($da_completare_filtro_id == 0) || (($da_completare_filtro_id == 1) && ($nmodulisvolti == 0))) {
		if ($da_completare_filtro_id == 1) {
			if ($sollecito_lista == '') {
				$sollecito_lista .= $programma_id;
			} else {
				$sollecito_lista .= ',' . $programma_id;
			}
		}
		$classe = !empty($row['classi_collegate_nome'])
			? $row['classi_collegate_nome']
			: $row['classe_nome'];
		$docente = $row['docente_cognome'] . ' ' . $row['docente_nome'];
		$materia = $row['materia_nome'];
		$classe_anno = intval($row['classe_anno']);
		$row_docente_id = intval($row['docente_id']);
		$anno_scolastico_id = intval($row['anno_scolastico_id']);
		$classe_id = intval($row['classe_id']);
		$is_coordinatore_classe = isset($coordinatore_classi[$classe_id . '_' . $anno_scolastico_id]) || isset($coordinatore_classi_by_classe[$classe_id]);
		$is_programma_proprio = $docente_corrente_id > 0 && $row_docente_id === $docente_corrente_id;
		$can_export_classe_word = programmiSvoltiCanExportClasseWord($classe_id, $anno_scolastico_id, $docente_corrente_id);
		$can_export_programma_word = ($classe_anno === 5) && ($is_programma_proprio || $can_export_classe_word);
		$copertina_button = '';
		global $__settings;
		if ($__settings->programmiSvolti->stampa_copertine_verifiche) {
			$copertina = $copertineTableExists ? programmiSvoltiCopertinaByProgramma($programma_id) : null;
			$copertina_stato = strtoupper(trim((string)($copertina['stato'] ?? '')));
			if ($is_programma_proprio) {
				if (!$copertineTableExists) {
					$copertina_button = '<button class="btn btn-default btn-xs" disabled data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Tabella copertine non configurata"><span class="glyphicon glyphicon-folder-close"></span></button>';
				} elseif ($copertina_stato === 'RICHIESTA') {
					$copertina_button = '<button class="btn btn-default btn-xs" disabled data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Copertina richiesta"><span class="glyphicon glyphicon-folder-open"></span> Copertina richiesta</button>';
				} elseif ($copertina_stato === 'GENERATA') {
					$copertina_button = '<button class="btn btn-success btn-xs" disabled data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Copertina generata: ' . htmlspecialchars((string)($copertina['fascicolo_codice'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><span class="glyphicon glyphicon-ok"></span> Copertina generata</button>';
				} elseif ($copertina_stato === 'STAMPATA') {
					$copertina_button = '<button class="btn btn-primary btn-xs" disabled data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Copertina stampata: ' . htmlspecialchars((string)($copertina['fascicolo_codice'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><span class="glyphicon glyphicon-print"></span> Copertina stampata</button>';
				} else {
					$copertina_button = '<button onclick="programmiSvoltiRichiediCopertina(' . $programma_id . ')" class="btn btn-default btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Richiedi copertina fascicolo verifiche"><span class="glyphicon glyphicon-folder-close"></span> Richiedi copertina</button>';
				}
			}
		}
		$update = $row['ultimo_agg'];
		$autore = $row['utente_cognome'] . " " . $row['utente_nome'];

		$phpdate = strtotime($update);
		$update = date('d-m-Y', $phpdate) . " alle ore " . date('H:i:s', $phpdate);

		$data .= '<tr>
		<td align="center">' . $classe . '</td>
		<td align="center">' . $docente . '</td>
		<td align="center">' . $materia . '</td>
		';
		$data .= '
		<td class="text-center">';

		if ($is_contesto_docente) {
			if (getSettingsValue('programmiSvolti', 'visibile_docenti', false)) {
				$data .= '
			<button onclick="programmiSvoltiPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma svolto"><span class="glyphicon glyphicon-print"></button>
			' . ($classe_anno === 5 ? '<button onclick="programmiSvoltiPrintSolo(' . $programma_id . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF solo di questo docente"><span class="glyphicon glyphicon-user"></span></button>' : '') . '
			' . ($can_export_programma_word ? '<button onclick="programmiSvoltiWord(' . $programma_id . ')" class="btn btn-default btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Esporta Word del programma svolto di quinta"><span class="glyphicon glyphicon-file"></span></button>' : '') . '
			' . (($classe_anno === 5 && $can_export_classe_word) ? '<button onclick="programmiSvoltiWordClasse(' . $classe_id . ',' . $anno_scolastico_id . ')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Esporta Word unico dei programmi svolti della classe quinta"><span class="glyphicon glyphicon-book"></span></button>' : '') . '
			' . $copertina_button . '
					';
				if (!$is_programma_proprio) {
					$data .= '
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'false\',\'false\',\'true\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il programma"><span class="glyphicon glyphicon-search"></span></button>';
				}
				if ($is_programma_proprio) {
					if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
						$data .= '
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'false\',\'false\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il programma"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="programmiSvoltiDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il programma"><span class="glyphicon glyphicon-trash"></button>
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'true\',\'false\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Duplica il programma per un altra classe"><span class="glyphicon glyphicon-duplicate"></button>
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'false\',\'true\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Condividi il programma con un altro docente"><span class="glyphicon glyphicon-share"></button>
						';
					} else {
						$data .= '
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'false\',\'false\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il programma"><span class="glyphicon glyphicon-search"></button>';
					}
				}
			}
		} else if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
			$data .= '
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'false\',\'false\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il programma"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="programmiSvoltiDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il programma"><span class="glyphicon glyphicon-trash"></button>
			<button onclick="programmiSvoltiPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma svolto"><span class="glyphicon glyphicon-print"></button>
			' . ($classe_anno === 5 ? '<button onclick="programmiSvoltiPrintSolo(' . $programma_id . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF solo di questo docente"><span class="glyphicon glyphicon-user"></span></button>' : '') . '
			' . ($classe_anno === 5 ? '<button onclick="programmiSvoltiWord(' . $programma_id . ')" class="btn btn-default btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Esporta Word del programma svolto di quinta"><span class="glyphicon glyphicon-file"></span></button>' : '') . '
			' . ($classe_anno === 5 ? '<button onclick="programmiSvoltiWordClasse(' . $classe_id . ',' . $anno_scolastico_id . ')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Esporta Word unico della classe quinta"><span class="glyphicon glyphicon-book"></span></button>' : '') . '
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'true\',\'false\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Duplica il programma per un altra classe"><span class="glyphicon glyphicon-duplicate"></button>
			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ',\'false\',\'true\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Condividi il programma con un altro docente"><span class="glyphicon glyphicon-share"></button>';
			if ($da_completare_filtro_id == 1) {
				$data .= '<button onclick="inviaSollecito(' . $programma_id . ')" class="btn btn-dark btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Invia un sollecito al docente"><span class="glyphicon glyphicon-warning-sign"></button>';
			}
		}
		$data .= '
		</td>
		<td align="center">' . $update . '</td>
		<td align="center">' . $autore . '</td>
		</tr>';
	}
}

$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_sollecito" value="' . htmlspecialchars($sollecito_lista) . '">';

echo $data;
