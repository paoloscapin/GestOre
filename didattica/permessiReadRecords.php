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
require_once '../common/permessi_uscita_lib.php';
ruoloRichiesto('segreteria-didattica', 'dirigente', 'personale-ata');

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$data_filtro = $_GET["data_filtro"] ?? null;
$solo_richiesti = $_GET["solo_richiesti"] ?? 0;
$filtro_permessi = trim((string)($_GET["filtro_permessi"] ?? ($solo_richiesti == 1 ? 'richiesti' : 'da_inviare')));
$filtro_permessi_values = array_values(array_filter(array_map('trim', explode(',', $filtro_permessi))));
if (empty($filtro_permessi_values)) {
    $filtro_permessi_values = ['da_inviare', 'richiesti'];
}
$filtro_show_da_inviare = in_array('da_inviare', $filtro_permessi_values, true) || in_array('tutti', $filtro_permessi_values, true);
$filtro_show_richiesti = in_array('richiesti', $filtro_permessi_values, true) || in_array('tutti', $filtro_permessi_values, true);
$filtro_show_altri = in_array('altri', $filtro_permessi_values, true) || in_array('tutti', $filtro_permessi_values, true);
$live_presence = intval($_GET["live_presence"] ?? 0) === 1;
$hasSyncColumns = permessiUscitaColumnExists('mastercom_sync_stato');
$hasPresenceSnapshotColumns = permessiUscitaColumnExists('mastercom_presence_stato')
    && permessiUscitaColumnExists('mastercom_presence_label')
    && permessiUscitaColumnExists('mastercom_presence_detail');
$hasMastercomStudenti = mastercomAdminTableExists('mastercom_studenti')
    && mastercomAdminTableColumnExists('mastercom_studenti', 'id_studente_gestore')
    && mastercomAdminTableColumnExists('mastercom_studenti', 'mastercom_id_studente')
    && mastercomAdminTableColumnExists('mastercom_studenti', 'mastercom_id_classe_corrente');
$mastercomSelect = $hasMastercomStudenti ? "
                    ms.mastercom_id_studente,
                    ms.mastercom_id_classe_corrente,
                    ms.nome AS mastercom_nome,
                    ms.cognome AS mastercom_cognome," : "
                    NULL AS mastercom_id_studente,
                    NULL AS mastercom_id_classe_corrente,
                    NULL AS mastercom_nome,
                    NULL AS mastercom_cognome,";
$mastercomJoin = $hasMastercomStudenti ? "
                LEFT JOIN mastercom_studenti ms
                ON ms.id_studente_gestore = permessi_uscita.id_studente" : "";
$syncSelect = $hasSyncColumns ? "
                    permessi_uscita.mastercom_sync_stato,
                    permessi_uscita.mastercom_sync_at,
                    permessi_uscita.mastercom_sync_attempts,
                    permessi_uscita.mastercom_sync_last_error,
                    permessi_uscita.mastercom_sync_last_note," : "";
$presenceSnapshotSelect = $hasPresenceSnapshotColumns ? "
                    permessi_uscita.mastercom_presence_stato,
                    permessi_uscita.mastercom_presence_label,
                    permessi_uscita.mastercom_presence_detail,
                    permessi_uscita.mastercom_presence_at," : "";

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green permessi-table">
<colgroup>
    <col class="permessi-col-date">
    <col class="permessi-col-class">
    <col class="permessi-col-time">
    <col class="permessi-col-student">
    <col class="permessi-col-time">
    <col class="permessi-col-parent">
    <col class="permessi-col-reason">
    <col class="permessi-col-presence">
    <col class="permessi-col-state">
    <col class="permessi-col-mastercom">
    <col class="permessi-col-notes">
    <col class="permessi-col-actions">
</colgroup>
<thead>
<tr>
    <th class="text-center">Data</th>
    <th class="text-center sortable" data-sort="classe">Classe</th>
    <th class="text-center sortable" data-sort="ora_uscita">Ora uscita</th>
    <th class="text-center sortable" data-sort="studente">Studente</th>
    <th class="text-center">Ora rientro</th>
    <th class="text-center">Genitore</th>
    <th class="text-center">Motivo</th>
    <th class="text-center">Presenza ora</th>
    <th class="text-center">Segreteria</th>
    <th class="text-center">MasterCom</th>
    <th class="text-center">Note segreteria</th>
    <th class="text-center">Azioni</th>
</tr>
</thead>
<tbody>';

$query = "	SELECT 
					permessi_uscita.id,
					permessi_uscita.id_studente,
					permessi_uscita.id_genitore,
					permessi_uscita.data,
					permessi_uscita.ora_uscita,
					permessi_uscita.ora_rientro,
					permessi_uscita.rientro,
					permessi_uscita.motivo,
					permessi_uscita.stato,
					permessi_uscita.note_segreteria as note_segreteria,
                    $syncSelect
                    $presenceSnapshotSelect
					genitori.nome AS genitore_nome,
					genitori.cognome AS genitore_cognome,
					studente.nome AS studente_nome,
					studente.cognome AS studente_cognome,
					classi.classe AS classe,
					studente_frequenta.id_classe AS id_classe,
                    $mastercomSelect
                    classi.classe AS mastercom_classe
				FROM permessi_uscita
				INNER JOIN genitori genitori
				ON permessi_uscita.id_genitore = genitori.id
				INNER JOIN studente_frequenta
				ON studente_frequenta.id_studente = permessi_uscita.id_studente AND studente_frequenta.id_anno_scolastico = '$__anno_scolastico_corrente_id'
				INNER JOIN classi classi
				ON classi.id = studente_frequenta.id_classe
				INNER JOIN studente studente
				ON permessi_uscita.id_studente = studente.id
                $mastercomJoin
				WHERE 1=1";

if ($studente_filtro_id != 0 && $studente_filtro_id != null) {
	$query .= " AND permessi_uscita.id_studente = '$studente_filtro_id'";
}
if ($data_filtro != null && $data_filtro != "") {
    $query .= " AND permessi_uscita.data = '$data_filtro'";
}
if (!$filtro_show_altri) {
    $whereFilters = [];
    if ($filtro_show_richiesti) {
        $whereFilters[] = "permessi_uscita.stato = 1";
    }
    if ($filtro_show_da_inviare) {
        $whereFilters[] = "permessi_uscita.stato = 2";
    }
    if (!empty($whereFilters)) {
        $query .= " AND (" . implode(" OR ", $whereFilters) . ")";
    }
}
$query .= "	ORDER BY permessi_uscita.data ASC, permessi_uscita.ora_uscita ASC, classi.classe ASC, studente.cognome ASC, studente.nome ASC";


$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
$today = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
function permessiPresenceColor(string $state): string {
    $state = strtoupper(trim($state));
    if ($state === 'USCITO_PERMESSO') {
        return '#337ab7';
    }
    if (in_array($state, ['PRESENTE', 'ENTRATA_RITARDO'], true)) {
        return 'green';
    }
    if ($state === 'EVENTO') {
        return '#5bc0de';
    }
    if (in_array($state, ['ASSENTE_MASTERCOM', 'USCITA', 'PERMESSO', 'NON_COLLEGATO', 'NON_DISPONIBILE'], true)) {
        return 'red';
    }
    return '#777';
}
function permessiStaticPresenceBadge(array $row): string {
    if (function_exists('permessiUscitaPresenceOverride')) {
        $override = permessiUscitaPresenceOverride($row);
        if (is_array($override)) {
            $title = htmlspecialchars((string)($override['detail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars((string)($override['label'] ?? 'Uscito con permesso'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $color = htmlspecialchars((string)($override['color'] ?? '#337ab7'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<span class="badge permessi-presence-static" style="background-color:' . $color . ';color:white;" data-toggle="tooltip" title="' . $title . '">' . $label . '</span>';
        }
    }

    $state = strtoupper(trim((string)($row['mastercom_presence_stato'] ?? '')));
    $label = trim((string)($row['mastercom_presence_label'] ?? ''));
    $detail = trim((string)($row['mastercom_presence_detail'] ?? ''));
    if ($state === '' && $label === '') {
        return '<span class="badge permessi-presence-static" style="background-color:#777;color:white;" data-toggle="tooltip" title="Snapshot presenza non disponibile">Snapshot mancante</span>';
    }
    if ($label === '') {
        $label = 'Da verificare';
    }
    $title = htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<span class="badge permessi-presence-static" style="background-color:' . permessiPresenceColor($state) . ';color:white;" data-toggle="tooltip" title="' . $title . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
}
function permessiPresenceBadge(array $row, string $dataFiltro, string $today, bool $hasPresenceSnapshotColumns, bool $livePresence): string {
    if (!$livePresence) {
        return $hasPresenceSnapshotColumns
            ? permessiStaticPresenceBadge($row)
            : '<span class="badge permessi-presence-static" style="background-color:#777;color:white;">Snapshot non configurato</span>';
    }
    if ($dataFiltro !== $today) {
        return '<span class="badge permessi-presence-static" style="background-color:#ddd;color:#333;">Solo oggi</span>';
    }
    $mcStudentId = intval($row['mastercom_id_studente'] ?? 0);
    $mcClassId = intval($row['mastercom_id_classe_corrente'] ?? 0);
    if ($mcStudentId <= 0) {
        return '<span class="badge permessi-presence-static" style="background-color:red;color:white;">Non collegato</span>';
    }
    if ($mcClassId <= 0) {
        return '<span class="badge permessi-presence-static" style="background-color:#777;color:white;">Classe MC mancante</span>';
    }

    return '<span class="badge permessi-presence-cell" style="background-color:#777;color:white;"'
        . ' data-permit-id="' . intval($row['id'] ?? 0) . '"'
        . ' data-student-id="' . $mcStudentId . '"'
        . ' data-class-id="' . $mcClassId . '"'
        . ' data-nome="' . htmlspecialchars((string)($row['mastercom_nome'] ?? $row['studente_nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-cognome="' . htmlspecialchars((string)($row['mastercom_cognome'] ?? $row['studente_cognome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-classe="' . htmlspecialchars((string)($row['mastercom_classe'] ?? $row['classe'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-toggle="tooltip" title="Caricamento presenza MasterCom">Caricamento...</span>';
}
function formatName($string) {
    $string = strtolower($string); // tutto minuscolo
    return mb_convert_case($string, MB_CASE_TITLE, "UTF-8"); // ogni parola con iniziale maiuscola
}

function permessiFormatSegreteriaNotes($note): string
{
    $note = (string)$note;
    $note = preg_replace_callback('/\[(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2}:\d{2})\]\s*/', function ($matches) {
        return '[' . $matches[3] . '/' . $matches[2] . '/' . $matches[1] . ' ' . $matches[4] . "]\n";
    }, $note);
    $note = preg_replace('/\[(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2})\]\s*/', "[$1]\n", $note);

    return nl2br(htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

	foreach ($resultArray as $row) {
	$id_permesso = $row['id'];
	$id_genitore = $row['id_genitore'];
	$genitore_nome = formatName($row['genitore_nome']) . ' ' . formatName($row['genitore_cognome']);
	$studente_nome = formatName($row['studente_nome']) . ' ' . formatName($row['studente_cognome']);
	$id_studente = $row['id_studente'];
	// Formattazione data e ora
	$data_it = date('d/m/Y', strtotime($row['data']));
	$ora_uscita = date('H:i', strtotime($row['ora_uscita']));
	$ora_rientro = date('H:i', strtotime($row['ora_rientro']));
	$note = $row['note_segreteria'] ?? '';
	$classe = $row['classe'] ?? '';
	// Badge per lo stato
	switch ($row['stato']) {
		case 1:
			$badge = '<span class="badge bg-warning" style="background-color: yellow; color: black;">Richiesto</span>';
			break;
		case 2:
			$badge = '<span class="badge bg-success" style="background-color: green; color: white;">Confermato</span>';
			break;
		case 3:
			$badge = '<span class="badge bg-danger" style="background-color: red; color: white;">Assente</span>';
			break;
		case 4:
			$badge = '<span class="badge bg-danger" style="background-color: red; color: white;">Rifiutato</span>';
			break;
		default:
			$badge = '<span class="badge bg-secondary">Sconosciuto</span>';
	}
	$motivo = $row['motivo'];
	$stato = $row['stato'];
    $presenceBadge = permessiPresenceBadge($row, (string)$data_filtro, $today, $hasPresenceSnapshotColumns, $live_presence);
    $mastercomBadge = '<span class="badge" style="background-color:#777;color:white;">Non configurato</span>';
    if ($hasSyncColumns) {
        $syncState = strtoupper(trim((string)($row['mastercom_sync_stato'] ?? '')));
        $presenceState = strtoupper(trim((string)($row['mastercom_presence_stato'] ?? '')));
        $syncNote = trim((string)($row['mastercom_sync_last_note'] ?? ''));
        $isManualMastercom = stripos($syncNote, 'inserito manualmente') !== false
            || stripos((string)($row['mastercom_presence_detail'] ?? ''), 'inserito manualmente') !== false;
        $hasMastercomSentNote = $isManualMastercom
            || stripos($syncNote, 'permesso inviato a mastercom') !== false;
        if ($hasMastercomSentNote && in_array($syncState, ['', 'DA_INVIARE', 'ASSENTE_ATTESA', 'ERRORE'], true)) {
            $syncState = 'INVIATO';
        }
        if ($syncState === 'ASSENTE_ATTESA' && in_array($presenceState, ['PRESENTE', 'ENTRATA_RITARDO', 'EVENTO'], true)) {
            $syncState = 'DA_INVIARE';
        }
        $isRequestedFilterRow = intval($stato) === 1;
        $isDaInviareFilterRow = intval($stato) === 2 && !in_array($syncState, ['INVIATO', 'ANNULLATO_ASSENTE'], true);
        $isOtherFilterRow = !$isRequestedFilterRow && !$isDaInviareFilterRow;
        if (
            !($filtro_show_richiesti && $isRequestedFilterRow)
            && !($filtro_show_da_inviare && $isDaInviareFilterRow)
            && !($filtro_show_altri && $isOtherFilterRow)
        ) {
            continue;
        }
        switch ($syncState) {
            case 'INVIATO':
                $mastercomBadge = $isManualMastercom
                    ? '<span class="badge" style="background-color:green;color:white;">Inserito manualmente</span>'
                    : '<span class="badge" style="background-color:green;color:white;">Inviato</span>';
                break;
            case 'DA_INVIARE':
                $mastercomBadge = '<span class="badge" style="background-color:#f0ad4e;color:white;">Da inviare</span>';
                break;
            case 'ASSENTE_ATTESA':
                $mastercomBadge = '<span class="badge" style="background-color:#f0ad4e;color:white;">Assente, riprova</span>';
                break;
            case 'ANNULLATO_ASSENTE':
                $mastercomBadge = '<span class="badge" style="background-color:red;color:white;">Annullato</span>';
                break;
            case 'ERRORE':
                $err = htmlspecialchars((string)($row['mastercom_sync_last_error'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $mastercomBadge = '<span class="badge" style="background-color:red;color:white;" data-toggle="tooltip" title="' . $err . '">Errore</span>';
                break;
            default:
                $mastercomBadge = $stato == 2
                    ? '<span class="badge" style="background-color:#777;color:white;">Non inviato</span>'
                    : '<span class="badge" style="background-color:#ddd;color:#333;">-</span>';
        }
    } else {
        $isRequestedFilterRow = intval($stato) === 1;
        $isDaInviareFilterRow = intval($stato) === 2;
        $isOtherFilterRow = !$isRequestedFilterRow && !$isDaInviareFilterRow;
        if (
            !($filtro_show_richiesti && $isRequestedFilterRow)
            && !($filtro_show_da_inviare && $isDaInviareFilterRow)
            && !($filtro_show_altri && $isOtherFilterRow)
        ) {
            continue;
        }
    }

	$data .= '<tr>
		<td align="center">' . $data_it . '</td>
		<td align="center">' . $classe . '</td>
		<td align="center">' . $ora_uscita . '</td>
		<td align="center">' . $studente_nome . '</td>
		<td align="center">' . $ora_rientro . '</td>
		<td align="center">' . $genitore_nome . '</td>
		<td align="center">' . $motivo . '</td>
		<td align="center">' . $presenceBadge . '</td>
		<td align="center">' . $badge . '</td>
		<td align="center">' . $mastercomBadge . '</td>
		<td class="permessi-notes-cell">' . permessiFormatSegreteriaNotes($note) . '</td>
		<td class="permessi-actions-cell">
		<button onclick="permessiGetDetails(\'' . $id_permesso . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-placement="top" title="Modifica la richiesta"><span class="glyphicon glyphicon-pencil"></span></button>
		<button onclick="permessiDelete(\'' . $id_permesso . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-placement="top" title="Cancella la richiesta"><span class="glyphicon glyphicon-trash"></span></button>';
	if ($stato == 1) {
		$data .= '
		<button onclick="permessoConfirm(\'' . $id_permesso . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-placement="top" title="Approva la richiesta"><span class="glyphicon glyphicon-ok"></span></button>';
	}
    if ($stato == 2) {
        $data .= '
        <button onclick="permessiMastercomSync(\'' . $id_permesso . '\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-placement="top" title="Invia/riprova su MasterCom"><span class="glyphicon glyphicon-refresh"></span></button>';
    }
	$data .= '</td>';

	$data .= '</tr>';
}

$data .= '</tbody></table></div>';

echo $data;
