<?php

/**
 * Import sostituzioni da JSON inviato via POST dal client Python
 * Nessuna sessione richiesta: endpoint pensato per uso locale/automatizzato
 */

require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=utf-8');

/* =========================================================
   HELPERS
   ========================================================= */

function respond($payload, $httpCode = 200) {
	http_response_code($httpCode);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}

function norm($v) {
	return trim((string)$v);
}

function normalizeSpaces($s) {
	$s = norm($s);
	return preg_replace('/\s+/u', ' ', $s);
}

function normalizeTeacherKey($s) {
	$s = normalizeSpaces($s);
	$s = mb_strtoupper($s, 'UTF-8');
	$s = str_replace(["’", "`", "´"], "'", $s);
	return $s;
}

function isValidDateYmd($s) {
	return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s);
}

function normalizeTimeToHms($s) {
	$s = norm($s);
	if ($s === '') return '';

	if (preg_match('/^\d{2}:\d{2}$/', $s)) {
		return $s . ':00';
	}
	if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) {
		return $s;
	}
	return '';
}

function tableHasColumn($tableName, $columnName) {
	$tableName = dbEscape($tableName);
	$columnName = dbEscape($columnName);

	$q = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
	$rows = dbGetAll($q);
	return is_array($rows) && count($rows) > 0;
}

/* =========================================================
   DOCENTI
   ========================================================= */

function buildDocentiMap() {
	$map = [];

	$q = "
		SELECT id, cognome, nome, attivo
		FROM docente
	";
	$rows = dbGetAll($q);

	if (!is_array($rows)) return [];

	foreach ($rows as $row) {
		$id = (int)($row['id'] ?? 0);
		if ($id <= 0) continue;

		if (isset($row['attivo']) && (string)$row['attivo'] !== '' && (int)$row['attivo'] === 0) {
			continue;
		}

		$cognome = norm($row['cognome'] ?? '');
		$nome = norm($row['nome'] ?? '');
		if ($cognome === '' || $nome === '') continue;

		$key = normalizeTeacherKey($cognome . ' ' . $nome);

		if (!isset($map[$key])) $map[$key] = [];
		$map[$key][] = $id;
	}

	return $map;
}

function findDocenteId($fullNamePdf, $docentiMap) {
	$key = normalizeTeacherKey($fullNamePdf);

	if ($key === '') {
		return ['ok' => false, 'reason' => 'Nome docente vuoto', 'id' => null];
	}

	if (!isset($docentiMap[$key])) {
		return ['ok' => false, 'reason' => 'Docente non trovato', 'id' => null];
	}

	$ids = $docentiMap[$key];
	if (count($ids) > 1) {
		return ['ok' => false, 'reason' => 'Docente ambiguo', 'id' => null];
	}

	return ['ok' => true, 'reason' => '', 'id' => (int)$ids[0]];
}

/* =========================================================
   INPUT JSON
   ========================================================= */

$raw = file_get_contents('php://input');
if (!$raw) {
	respond([
		'ok' => false,
		'error' => 'Body JSON vuoto'
	], 400);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
	respond([
		'ok' => false,
		'error' => 'JSON non valido'
	], 400);
}

$items = $data['items'] ?? null;
if (!is_array($items)) {
	respond([
		'ok' => false,
		'error' => 'Campo items mancante o non valido'
	], 400);
}

/* =========================================================
   CHECK STRUTTURA TABELLA
   ========================================================= */

$hasDocSostPdf = tableHasColumn('sostituzioni', 'docenteSostitutoPdf');
$hasDocSostituitoPdf = tableHasColumn('sostituzioni', 'docenteSostituitoPdf');
$hasDataImport = tableHasColumn('sostituzioni', 'dataImport');

/* =========================================================
   PRECARICO DOCENTI
   ========================================================= */

$docentiMap = buildDocentiMap();

if (empty($docentiMap)) {
	respond([
		'ok' => false,
		'error' => 'Nessun docente disponibile o tabella docente non leggibile'
	], 500);
}

/* =========================================================
   IMPORT
   ========================================================= */

$totaleRicevuti = count($items);
$inseriti = 0;
$aggiornati = 0;
$scartati = [];
$preview = [];

dbExec("START TRANSACTION");

try {
	foreach ($items as $idx => $item) {
		$riga = $idx + 1;

		$dataVal = norm($item['data'] ?? '');
		$oraInizio = normalizeTimeToHms($item['oraInizio'] ?? '');
		$oraFine = normalizeTimeToHms($item['oraFine'] ?? '');
		$docenteSostitutoPdf = normalizeSpaces($item['docenteSostituto'] ?? '');
		$docenteSostituitoPdf = normalizeSpaces($item['docenteSostituito'] ?? '');
		$materia = normalizeSpaces($item['materia'] ?? '');
		$classe = normalizeSpaces($item['classe'] ?? '');
		$aula = normalizeSpaces($item['aula'] ?? '');

		if (
			$dataVal === '' ||
			$oraInizio === '' ||
			$oraFine === '' ||
			$docenteSostitutoPdf === '' ||
			$docenteSostituitoPdf === ''
		) {
			$scartati[] = [
				'riga' => $riga,
				'motivo' => 'Campi obbligatori mancanti o orari non validi',
				'item' => $item
			];
			continue;
		}

		if (!isValidDateYmd($dataVal)) {
			$scartati[] = [
				'riga' => $riga,
				'motivo' => 'Data non valida, atteso formato YYYY-MM-DD',
				'item' => $item
			];
			continue;
		}

		$matchSostituto = findDocenteId($docenteSostitutoPdf, $docentiMap);
		if (!$matchSostituto['ok']) {
			$scartati[] = [
				'riga' => $riga,
				'motivo' => 'Docente sostituto: ' . $matchSostituto['reason'],
				'docente' => $docenteSostitutoPdf,
				'item' => $item
			];
			continue;
		}

		$matchSostituito = findDocenteId($docenteSostituitoPdf, $docentiMap);
		if (!$matchSostituito['ok']) {
			$scartati[] = [
				'riga' => $riga,
				'motivo' => 'Docente sostituito: ' . $matchSostituito['reason'],
				'docente' => $docenteSostituitoPdf,
				'item' => $item
			];
			continue;
		}

		$idDocenteSostituto = (int)$matchSostituto['id'];
		$idDocenteSostituito = (int)$matchSostituito['id'];

		$whereDup = "
			data = " . dbQ($dataVal) . "
			AND oraInizio = " . dbQ($oraInizio) . "
			AND oraFine = " . dbQ($oraFine) . "
			AND idDocenteSostituto = " . dbI($idDocenteSostituto) . "
			AND idDocenteSostituito = " . dbI($idDocenteSostituito) . "
			AND classe <=> " . dbQ($classe) . "
			AND aula <=> " . dbQ($aula);

		$exists = dbGetFirst("
			SELECT idSostituzione
			FROM sostituzioni
			WHERE $whereDup
			LIMIT 1
		");

		$fields = [
			"data = " . dbQ($dataVal),
			"oraInizio = " . dbQ($oraInizio),
			"oraFine = " . dbQ($oraFine),
			"idDocenteSostituto = " . dbI($idDocenteSostituto),
			"idDocenteSostituito = " . dbI($idDocenteSostituito),
			"materia = " . dbQ($materia),
			"classe = " . dbQ($classe),
			"aula = " . dbQ($aula)
		];

		if ($hasDocSostPdf) {
			$fields[] = "docenteSostitutoPdf = " . dbQ($docenteSostitutoPdf);
		}
		if ($hasDocSostituitoPdf) {
			$fields[] = "docenteSostituitoPdf = " . dbQ($docenteSostituitoPdf);
		}
		if ($hasDataImport) {
			$fields[] = "dataImport = NOW()";
		}

		if ($exists) {
			$idSostituzione = (int)$exists['idSostituzione'];

			$q = "
				UPDATE sostituzioni
				SET " . implode(",\n", $fields) . "
				WHERE idSostituzione = " . dbI($idSostituzione);

			dbExec($q);
			$aggiornati++;
		} else {
			$insertCols = [
				'data',
				'oraInizio',
				'oraFine',
				'idDocenteSostituto',
				'idDocenteSostituito',
				'materia',
				'classe',
				'aula'
			];

			$insertVals = [
				dbQ($dataVal),
				dbQ($oraInizio),
				dbQ($oraFine),
				dbI($idDocenteSostituto),
				dbI($idDocenteSostituito),
				dbQ($materia),
				dbQ($classe),
				dbQ($aula)
			];

			if ($hasDocSostPdf) {
				$insertCols[] = 'docenteSostitutoPdf';
				$insertVals[] = dbQ($docenteSostitutoPdf);
			}
			if ($hasDocSostituitoPdf) {
				$insertCols[] = 'docenteSostituitoPdf';
				$insertVals[] = dbQ($docenteSostituitoPdf);
			}
			if ($hasDataImport) {
				$insertCols[] = 'dataImport';
				$insertVals[] = 'NOW()';
			}

			$q = "
				INSERT INTO sostituzioni (" . implode(', ', $insertCols) . ")
				VALUES (" . implode(', ', $insertVals) . ")
			";

			dbExec($q);
			$inseriti++;
		}

		if (count($preview) < 15) {
			$preview[] = [
				'data' => $dataVal,
				'oraInizio' => $oraInizio,
				'oraFine' => $oraFine,
				'idDocenteSostituto' => $idDocenteSostituto,
				'idDocenteSostituito' => $idDocenteSostituito,
				'docenteSostituto' => $docenteSostitutoPdf,
				'docenteSostituito' => $docenteSostituitoPdf,
				'materia' => $materia,
				'classe' => $classe,
				'aula' => $aula
			];
		}
	}

	dbExec("COMMIT");

	respond([
		'ok' => true,
		'totaleRicevuti' => $totaleRicevuti,
		'inseriti' => $inseriti,
		'aggiornati' => $aggiornati,
		'scartati' => count($scartati),
		'dettaglioScartati' => $scartati,
		'preview' => $preview
	]);
} catch (Throwable $e) {
	dbExec("ROLLBACK");

	respond([
		'ok' => false,
		'error' => 'Eccezione durante import: ' . $e->getMessage()
	], 500);
}