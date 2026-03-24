<?php

/**
 * Import sostituzioni da JSON inviato via POST dal client Python
 * Nessuna sessione richiesta: endpoint pensato per uso locale/automatizzato
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/send-mail.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

infoimportsost("==== AVVIO importSostituzioni.php ====");

/* =========================================================
   CONFIG
   ========================================================= */

$TELEGRAM_BOT_TOKEN       = trim((string)($__settings->telegram->bot_token ?? ''));
$IS_TELEGRAM_ENABLED      = (bool)($__settings->telegram->enabled ?? false);
$IS_MAIL_DOCENTE_ENABLED  = (bool)($__settings->sostituzioni->inviaMailDocente ?? false);
$MAIL_TEST_OVERRIDE       = 'massimo.saiani@buonarroti.tn.it';
$MAIL_TEST_OVERRIDE_NAME  = 'Massimo Saiani';
$IS_TELEGRAM_TEST_MODE    = (bool)($__settings->sostituzioni->telegramTestMode ?? false);
$TELEGRAM_TEST_CHAT_ID    = trim((string)($__settings->sostituzioni->telegramTestChatId ?? ''));

infoimportsost(
	"CONFIG telegram_enabled=" . ($IS_TELEGRAM_ENABLED ? '1' : '0') .
		" telegram_test_mode=" . ($IS_TELEGRAM_TEST_MODE ? '1' : '0') .
		" telegram_test_chat_id=[" . $TELEGRAM_TEST_CHAT_ID . "]" .
		" mail_enabled=" . ($IS_MAIL_DOCENTE_ENABLED ? '1' : '0')
);

/* =========================================================
   HELPERS
   ========================================================= */

function formatDateItLong($ymd)
{
	$mesi = [
		'01' => 'gennaio',
		'02' => 'febbraio',
		'03' => 'marzo',
		'04' => 'aprile',
		'05' => 'maggio',
		'06' => 'giugno',
		'07' => 'luglio',
		'08' => 'agosto',
		'09' => 'settembre',
		'10' => 'ottobre',
		'11' => 'novembre',
		'12' => 'dicembre'
	];

	$ymd = trim((string)$ymd);
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
		return $ymd;
	}

	[$y, $m, $d] = explode('-', $ymd);
	return ltrim($d, '0') . ' ' . ($mesi[$m] ?? $m) . ' ' . $y;
}

function buildMailSubjectSostituzione($evento, $data, $oraInizio)
{
	$dataIt = formatDateItLong($data);
	$ora = substr((string)$oraInizio, 0, 5);

	if ($evento === 'ANNULLAMENTO') {
		return "GestOre - Notifica annullamento sostituzione delle ore $ora del giorno $dataIt";
	}

	return "GestOre - Notifica sostituzione assegnata alle ore $ora del giorno $dataIt";
}

function eh($s)
{
	return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stripEmojiForLog($text)
{
	$text = (string)$text;

	// rimuove la maggior parte degli emoji / simboli unicode estesi
	$text = preg_replace('/[\x{1F000}-\x{1FAFF}]/u', '', $text);
	$text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);

	// pulizia spazi multipli
	$text = preg_replace('/[ \t]+/u', ' ', $text);
	$text = preg_replace('/\n{3,}/u', "\n\n", $text);

	return trim($text);
}

function respond($payload, $httpCode = 200)
{
	infoimportsost("Risposta HTTP $httpCode - ok=" . ((!empty($payload['ok'])) ? '1' : '0'));
	http_response_code($httpCode);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}

function norm($v)
{
	return trim((string)$v);
}

function normalizeSpaces($s)
{
	$s = norm($s);
	return preg_replace('/\s+/u', ' ', $s);
}

function normalizeLatinChars($s)
{
	$s = (string)$s;

	$map = [
		'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
		'à' => 'A', 'á' => 'A', 'â' => 'A', 'ã' => 'A', 'ä' => 'A', 'å' => 'A',

		'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
		'è' => 'E', 'é' => 'E', 'ê' => 'E', 'ë' => 'E',

		'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
		'ì' => 'I', 'í' => 'I', 'î' => 'I', 'ï' => 'I',

		'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
		'ò' => 'O', 'ó' => 'O', 'ô' => 'O', 'õ' => 'O', 'ö' => 'O',

		'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
		'ù' => 'U', 'ú' => 'U', 'û' => 'U', 'ü' => 'U',

		'Ç' => 'C', 'ç' => 'C',
		'Ñ' => 'N', 'ñ' => 'N'
	];

	return strtr($s, $map);
}

function normalizeTeacherKey($s)
{
	$s = normalizeSpaces($s);
	$s = normalizeLatinChars($s);
	$s = mb_strtoupper($s, 'UTF-8');

	// uniforma tutti i tipi di apostrofo
	$s = str_replace(["’", "`", "´", "ʻ", "ʼ"], "'", $s);

	// rimuove del tutto apostrofi, punti e trattini
	$s = str_replace(["'", ".", "-"], " ", $s);

	// compatta di nuovo gli spazi
	$s = preg_replace('/\s+/u', ' ', $s);
	$s = trim($s);

	return $s;
}

function isValidDateYmd($s)
{
	return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s);
}

function normalizeTimeToHms($s)
{
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

function normOra($o)
{
	$o = trim((string)$o);
	if ($o === '') return '';
	return substr($o, 0, 5);
}

function tableHasColumn($tableName, $columnName)
{
	$tableName = dbEscape($tableName);
	$columnName = dbEscape($columnName);

	$q = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
	$rows = dbGetAll($q);
	return is_array($rows) && count($rows) > 0;
}

function buildMailHtmlSostituzione($tipoEvento, $docenteDestinatario, $data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia, $testNote = '')
{
	$titoloTop = ($tipoEvento === 'ANNULLAMENTO')
		? '❌ ANNULLAMENTO SOSTITUZIONE'
		: '✅ NUOVA SOSTITUZIONE';

	$badge = ($tipoEvento === 'ANNULLAMENTO')
		? '⚠️ SOSTITUZIONE ANNULLATA'
		: '📌 SOSTITUZIONE ASSEGNATA';

	$intro = ($tipoEvento === 'ANNULLAMENTO')
		? 'La sostituzione precedentemente assegnata è stata annullata o modificata.'
		: 'Ti è stata assegnata una nuova sostituzione.';

	$headerBg = ($tipoEvento === 'ANNULLAMENTO') ? '#b45309' : '#0f766e';
	$headerText = '#ffffff';

	$badgeBg = ($tipoEvento === 'ANNULLAMENTO') ? '#fef3c7' : '#dff7f4';
	$badgeText = ($tipoEvento === 'ANNULLAMENTO') ? '#9a3412' : '#0f766e';

	$testHtml = '';
	if ($testNote !== '') {
		$testHtml = '
			<tr>
				<td colspan="2" style="padding-top:18px;">
					<div style="
						background:#fff3cd;
						border:1px solid #ffe08a;
						color:#8a5a00;
						border-radius:10px;
						padding:12px 14px;
						font-size:13px;
						line-height:1.4;
					">
						<strong>🧪 TEST MODE</strong><br>' . eh($testNote) . '
					</div>
				</td>
			</tr>
		';
	}

	return '
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>' . eh($titoloTop) . '</title>
</head>
<body style="margin:0;padding:0;background:#f2f2f2;font-family:Arial,Helvetica,sans-serif;color:#2f3542;">
	<div style="max-width:920px;margin:0 auto;padding:20px 18px;">
		<div style="background:' . $headerBg . ';color:' . $headerText . ';border-radius:18px 18px 0 0;padding:22px 26px;font-size:22px;font-weight:700;letter-spacing:.3px;">
			' . eh($titoloTop) . '
		</div>

		<div style="background:#f7f7f7;border:1px solid #e3e3e3;border-top:none;border-radius:0 0 18px 18px;padding:22px 26px 18px 26px;">
			<div style="font-size:18px;font-weight:700;color:#2d3340;margin-bottom:6px;">
				' . eh($docenteDestinatario) . '
			</div>

			<div style="font-size:16px;color:#6b7280;margin-bottom:20px;">
				' . eh($intro) . '
			</div>

			<hr style="border:none;border-top:1px solid #d9d9d9;margin:0 0 18px 0;">

			<div style="
				display:inline-block;
				background:' . $badgeBg . ';
				color:' . $badgeText . ';
				font-weight:700;
				border-radius:20px;
				padding:10px 18px;
				font-size:14px;
				letter-spacing:.4px;
				margin-bottom:16px;
			">
				' . eh($badge) . '
			</div>

			<div style="
				background:#f1f2f4;
				border:1px solid #d9dde3;
				border-radius:18px;
				padding:18px 18px 10px 18px;
			">
				<table role="presentation" style="width:100%;border-collapse:collapse;font-size:16px;">
					<tr>
						<td style="width:34%;padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">📅 Data</td>
						<td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($data) . '</td>
					</tr>
					<tr>
						<td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">🕒 Ora</td>
						<td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($oraInizio . ' - ' . $oraFine) . '</td>
					</tr>
					<tr>
						<td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">👤 Docente sostituito</td>
						<td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($docenteSostituito) . '</td>
					</tr>
					<tr>
						<td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">🏫 Classe</td>
						<td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($classe) . '</td>
					</tr>
					<tr>
						<td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">🚪 Aula</td>
						<td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($aula) . '</td>
					</tr>
					<tr>
						<td style="padding:12px 12px;color:#6b7280;">📚 Materia</td>
						<td style="padding:12px 12px;font-weight:700;color:#2d3340;">' . eh($materia) . '</td>
					</tr>
					' . $testHtml . '
				</table>
			</div>

			<div style="margin-top:18px;font-size:15px;color:#4b5563;line-height:1.5;">
				🤖 Messaggio automatico <strong>GestOre</strong>.
			</div>
		</div>
	</div>
</body>
</html>';
}

/* =========================================================
   DOCENTI
   ========================================================= */

function buildDocentiMap()
{
	$map = [];

	$q = "
        SELECT id, cognome, nome, attivo, email
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

function findDocenteId($fullNamePdf, $docentiMap)
{
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

function getDocenteById($idDocente)
{
	$idDocente = (int)$idDocente;
	if ($idDocente <= 0) return null;

	$q = "
        SELECT id, cognome, nome, email
        FROM docente
        WHERE id = " . dbI($idDocente) . "
        LIMIT 1
    ";

	return dbGetFirst($q);
}

function docenteFullName($doc)
{
	if (!$doc) return '';
	return trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));
}

/* =========================================================
   TELEGRAM
   ========================================================= */

function getTelegramProfileByDocenteId($idDocente)
{
	$idDocente = (int)$idDocente;
	if ($idDocente <= 0) return null;

	$q = "
        SELECT *
        FROM docente_telegram
        WHERE idDocente = " . dbI($idDocente) . "
          AND attivo = 1
          AND consenso_notifiche = 1
        LIMIT 1
    ";

	return dbGetFirst($q);
}

function updateTelegramLastOk($idDocente)
{
	$q = "
        UPDATE docente_telegram
        SET ultimo_invio_ok = NOW(),
            ultimo_errore = NULL,
            ultimo_errore_data = NULL
        WHERE idDocente = " . dbI($idDocente) . "
    ";
	dbExec($q);
}

function updateTelegramLastError($idDocente, $msg)
{
	$q = "
        UPDATE docente_telegram
        SET ultimo_errore = " . dbQ(mb_substr((string)$msg, 0, 1000, 'UTF-8')) . ",
            ultimo_errore_data = NOW()
        WHERE idDocente = " . dbI($idDocente) . "
    ";
	dbExec($q);
}

function logTelegramEsito($idDocente, $idSostituzione, $tipoEvento, $messaggio, $esito, $rispostaApi)
{
	$messaggioLog = stripEmojiForLog($messaggio);
	$rispostaApiLog = stripEmojiForLog($rispostaApi);

	$q = "
        INSERT INTO docente_telegram_log (
            idDocente,
            idSostituzione,
            tipo_evento,
            messaggio,
            esito,
            risposta_api,
            data_invio
        ) VALUES (
            " . dbI($idDocente) . ",
            " . dbI($idSostituzione) . ",
            " . dbQ($tipoEvento) . ",
            " . dbQ($messaggioLog) . ",
            " . dbQ($esito) . ",
            " . dbQ($rispostaApiLog) . ",
            NOW()
        )
    ";
	dbExec($q);
}

function inviaTelegramDocente($botToken, $chatId, $text, $destDocenteNome = '')
{
	global $IS_TELEGRAM_TEST_MODE, $TELEGRAM_TEST_CHAT_ID;

	$chatId = trim((string)$chatId);
	$botToken = trim((string)$botToken);
	$destDocenteNome = trim((string)$destDocenteNome);

	if ($botToken === '') {
		return ['ok' => false, 'response' => 'bot token mancante'];
	}

	if ($IS_TELEGRAM_TEST_MODE) {
		$realChatId = $chatId;
		$chatId = $TELEGRAM_TEST_CHAT_ID;

		$prefix = "🧪 TEST MODE - messaggio destinato a: " . ($destDocenteNome !== '' ? $destDocenteNome : 'DOCENTE SCONOSCIUTO');
		if ($realChatId !== '') {
			$prefix .= "\n💬 Chat reale prevista: " . $realChatId;
		}
		$prefix .= "\n\n";

		$text = $prefix . $text;
	}

	if ($chatId === '') {
		return ['ok' => false, 'response' => 'chat_id mancante'];
	}

	$url = "https://api.telegram.org/bot{$botToken}/sendMessage";

	$payload = [
		'chat_id' => $chatId,
		'text' => $text
	];

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);

	$response = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	curl_close($ch);

	if ($errno) {
		return ['ok' => false, 'response' => "cURL error: $error"];
	}

	$data = json_decode($response, true);
	if (is_array($data) && !empty($data['ok'])) {
		return ['ok' => true, 'response' => $response];
	}

	return ['ok' => false, 'response' => $response ?: 'Risposta Telegram vuota'];
}

/* =========================================================
   NOTIFICHE
   ========================================================= */

function notificaGiaInviata($idSostituzione, $idDocenteDestinatario, $evento, $canale)
{
	$q = "
        SELECT idNotifica
        FROM sostituzioni_notifiche
        WHERE idSostituzione = " . dbI($idSostituzione) . "
          AND idDocenteDestinatario = " . dbI($idDocenteDestinatario) . "
          AND evento = " . dbQ($evento) . "
          AND canale = " . dbQ($canale) . "
        LIMIT 1
    ";
	$row = dbGetFirst($q);
	return is_array($row) && !empty($row['idNotifica']);
}

function registraNotificaInviata($idSostituzione, $idDocenteDestinatario, $evento, $canale)
{
	$q = "
        INSERT IGNORE INTO sostituzioni_notifiche (
            idSostituzione,
            idDocenteDestinatario,
            evento,
            canale,
            dataInvio
        ) VALUES (
            " . dbI($idSostituzione) . ",
            " . dbI($idDocenteDestinatario) . ",
            " . dbQ($evento) . ",
            " . dbQ($canale) . ",
            NOW()
        )
    ";
	dbExec($q);
}

function inviaMailDocente($to, $toName, $subject, $htmlBody)
{
	global $IS_MAIL_DOCENTE_ENABLED, $MAIL_TEST_OVERRIDE, $MAIL_TEST_OVERRIDE_NAME;

	if (!$IS_MAIL_DOCENTE_ENABLED) {
		warningimportsost("MAIL disabilitata da config");
		return false;
	}

	$to = trim((string)$to);
	$toName = trim((string)$toName);

	if ($to === '') {
		warningimportsost("MAIL non inviata: destinatario vuoto");
		return false;
	}

	$realTo = $to;
	$realToName = $toName;

	$to = $MAIL_TEST_OVERRIDE;
	$toName = $MAIL_TEST_OVERRIDE_NAME;

	$testFooter = '
        <hr>
        <p style="font-size:12px;color:#666;">
            TEST MODE - destinatario reale previsto: ' . eh($realToName) . ' &lt;' . eh($realTo) . '&gt;
        </p>
    ';

	infoimportsost("Tentativo invio MAIL TEST to=[$to] realTo=[$realTo] subj=[$subject]");

	return sendMail($to, $toName, $subject, $htmlBody . $testFooter);
}

function buildMessaggioAssegnazione($data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia)
{
	$dataIt = formatDateItLong($data);

	return
		"📌 Ti è stata assegnata una sostituzione.\n\n" .
		"📅 Data: $dataIt\n" .
		"🕒 Orario: $oraInizio - $oraFine\n" .
		"👤 Docente sostituito: $docenteSostituito\n" .
		"🏫 Classe: $classe\n" .
		"🚪 Aula: $aula\n" .
		"📚 Materia: $materia\n\n" .
		"🤖 Messaggio automatico GestOre.";
}

function buildMessaggioAnnullamento($data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia)
{
	$dataIt = formatDateItLong($data);

	return
		"⚠️ La sostituzione precedentemente assegnata è stata annullata o modificata.\n\n" .
		"📅 Data: $dataIt\n" .
		"🕒 Orario: $oraInizio - $oraFine\n" .
		"👤 Docente sostituito: $docenteSostituito\n" .
		"🏫 Classe: $classe\n" .
		"🚪 Aula: $aula\n" .
		"📚 Materia: $materia\n\n" .
		"🤖 Messaggio automatico GestOre.";
}

/* =========================================================
   INPUT JSON
   ========================================================= */

$raw = file_get_contents('php://input');
if (!$raw) {
	errorimportsost("Body JSON vuoto");
	respond([
		'ok' => false,
		'error' => 'Body JSON vuoto'
	], 400);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
	errorimportsost("JSON non valido");
	respond([
		'ok' => false,
		'error' => 'JSON non valido'
	], 400);
}

$items = $data['items'] ?? null;
if (!is_array($items)) {
	errorimportsost("Campo items mancante o non valido");
	respond([
		'ok' => false,
		'error' => 'Campo items mancante o non valido'
	], 400);
}

infoimportsost("Ricevuti " . count($items) . " item da importare");

/* =========================================================
   CHECK STRUTTURA TABELLA
   ========================================================= */

$hasDocSostPdf = tableHasColumn('sostituzioni', 'docenteSostitutoPdf');
$hasDocSostituitoPdf = tableHasColumn('sostituzioni', 'docenteSostituitoPdf');
$hasDataImport = tableHasColumn('sostituzioni', 'dataImport');

infoimportsost(
	"Check tabella sostituzioni: hasDocSostPdf=" . ($hasDocSostPdf ? '1' : '0') .
		" hasDocSostituitoPdf=" . ($hasDocSostituitoPdf ? '1' : '0') .
		" hasDataImport=" . ($hasDataImport ? '1' : '0')
);

/* =========================================================
   PRECARICO DOCENTI
   ========================================================= */

$docentiMap = buildDocentiMap();

if (empty($docentiMap)) {
	errorimportsost("Nessun docente disponibile o tabella docente non leggibile");
	respond([
		'ok' => false,
		'error' => 'Nessun docente disponibile o tabella docente non leggibile'
	], 500);
}

infoimportsost("Docenti caricati in mappa: " . count($docentiMap));

/* =========================================================
   IMPORT
   ========================================================= */

$totaleRicevuti = count($items);
$inseriti = 0;
$aggiornati = 0;
$scartati = [];
$preview = [];
$notificheDaInviare = [];
$notificheInviate = [];
$debugNotifiche = [];

infoimportsost("START TRANSACTION");
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

		infoimportsost("Riga $riga letta: data=[$dataVal] ora=[$oraInizio-$oraFine] sostituto=[$docenteSostitutoPdf] sostituito=[$docenteSostituitoPdf] classe=[$classe] aula=[$aula] materia=[$materia]");

		if (
			$dataVal === '' ||
			$oraInizio === '' ||
			$oraFine === '' ||
			$docenteSostitutoPdf === '' ||
			$docenteSostituitoPdf === ''
		) {
			warningimportsost("Riga $riga scartata: campi obbligatori mancanti o orari non validi");
			$scartati[] = [
				'riga' => $riga,
				'motivo' => 'Campi obbligatori mancanti o orari non validi',
				'item' => $item
			];
			continue;
		}

		if (!isValidDateYmd($dataVal)) {
			warningimportsost("Riga $riga scartata: data non valida [$dataVal]");
			$scartati[] = [
				'riga' => $riga,
				'motivo' => 'Data non valida, atteso formato YYYY-MM-DD',
				'item' => $item
			];
			continue;
		}

		$matchSostituto = findDocenteId($docenteSostitutoPdf, $docentiMap);
		if (!$matchSostituto['ok']) {
			warningimportsost("Riga $riga scartata: docente sostituto non trovato/ambiguo [$docenteSostitutoPdf] motivo=[" . $matchSostituto['reason'] . "]");
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
			warningimportsost("Riga $riga scartata: docente sostituito non trovato/ambiguo [$docenteSostituitoPdf] motivo=[" . $matchSostituito['reason'] . "]");
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

		infoimportsost("Riga $riga match docenti: idSostituto=$idDocenteSostituto idSostituito=$idDocenteSostituito");

		$whereKey = "
            data = " . dbQ($dataVal) . "
            AND oraInizio = " . dbQ($oraInizio) . "
            AND oraFine = " . dbQ($oraFine) . "
            AND idDocenteSostituito = " . dbI($idDocenteSostituito) . "
            AND classe <=> " . dbQ($classe) . "
            AND aula <=> " . dbQ($aula);

		$exists = dbGetFirst("
            SELECT *
            FROM sostituzioni
            WHERE $whereKey
            LIMIT 1
        ");

		if ($exists) {
			$idSostituzione = (int)$exists['idSostituzione'];
			$oldIdDocenteSostituto = (int)($exists['idDocenteSostituto'] ?? 0);

			$stessaAssegnazione =
				$oldIdDocenteSostituto === $idDocenteSostituto
				&& norm($exists['materia'] ?? '') === $materia
				&& norm($exists['classe'] ?? '') === $classe
				&& norm($exists['aula'] ?? '') === $aula
				&& normOra($exists['oraInizio'] ?? '') === substr($oraInizio, 0, 5)
				&& normOra($exists['oraFine'] ?? '') === substr($oraFine, 0, 5);

			infoimportsost("Riga $riga trovata sostituzione esistente id=$idSostituzione oldIdDocenteSostituto=$oldIdDocenteSostituto stessaAssegnazione=" . ($stessaAssegnazione ? '1' : '0'));

			if ($stessaAssegnazione) {
				$notificheDaInviare[] = [
					'idSostituzione' => $idSostituzione,
					'idDocente' => $idDocenteSostituto,
					'evento' => 'ASSEGNAZIONE',
					'data' => $dataVal,
					'oraInizio' => $oraInizio,
					'oraFine' => $oraFine,
					'docenteSostituito' => $docenteSostituitoPdf,
					'classe' => $classe,
					'aula' => $aula,
					'materia' => $materia
				];
				infoimportsost("Riga $riga sostituzione già presente: accodata notifica ASSEGNAZIONE docente=$idDocenteSostituto idSostituzione=$idSostituzione");
				continue;
			}

			$fields = [
				"idDocenteSostituto = " . dbI($idDocenteSostituto),
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

			$q = "
                UPDATE sostituzioni
                SET " . implode(",\n", $fields) . "
                WHERE idSostituzione = " . dbI($idSostituzione);

			dbExec($q);
			$aggiornati++;
			infoimportsost("Riga $riga aggiornata sostituzione id=$idSostituzione nuovoIdDocenteSostituto=$idDocenteSostituto");

			if ($oldIdDocenteSostituto > 0 && $oldIdDocenteSostituto !== $idDocenteSostituto) {
				$notificheDaInviare[] = [
					'idSostituzione' => $idSostituzione,
					'idDocente' => $oldIdDocenteSostituto,
					'evento' => 'ANNULLAMENTO',
					'data' => $dataVal,
					'oraInizio' => $oraInizio,
					'oraFine' => $oraFine,
					'docenteSostituito' => $docenteSostituitoPdf,
					'classe' => $classe,
					'aula' => $aula,
					'materia' => $materia
				];

				$notificheDaInviare[] = [
					'idSostituzione' => $idSostituzione,
					'idDocente' => $idDocenteSostituto,
					'evento' => 'ASSEGNAZIONE',
					'data' => $dataVal,
					'oraInizio' => $oraInizio,
					'oraFine' => $oraFine,
					'docenteSostituito' => $docenteSostituitoPdf,
					'classe' => $classe,
					'aula' => $aula,
					'materia' => $materia
				];

				infoimportsost("Riga $riga cambio sostituto: accodate notifiche ANNULLAMENTO docente=$oldIdDocenteSostituto e ASSEGNAZIONE docente=$idDocenteSostituto");
			}
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
			$idSostituzione = dblastId();
			$inseriti++;

			infoimportsost("Riga $riga inserita nuova sostituzione id=$idSostituzione docenteSostituto=$idDocenteSostituto docenteSostituito=$idDocenteSostituito");

			$notificheDaInviare[] = [
				'idSostituzione' => $idSostituzione,
				'idDocente' => $idDocenteSostituto,
				'evento' => 'ASSEGNAZIONE',
				'data' => $dataVal,
				'oraInizio' => $oraInizio,
				'oraFine' => $oraFine,
				'docenteSostituito' => $docenteSostituitoPdf,
				'classe' => $classe,
				'aula' => $aula,
				'materia' => $materia
			];

			infoimportsost("Riga $riga accodata notifica ASSEGNAZIONE docente=$idDocenteSostituto idSostituzione=$idSostituzione");
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
	infoimportsost("COMMIT eseguito: inseriti=$inseriti aggiornati=$aggiornati scartati=" . count($scartati) . " notificheDaInviare=" . count($notificheDaInviare));

	foreach ($notificheDaInviare as $n) {
		$idSostituzione = (int)$n['idSostituzione'];
		$idDocenteDest = (int)$n['idDocente'];
		$evento = $n['evento'];

		infoimportsost("Gestione notifica idSostituzione=$idSostituzione idDocenteDest=$idDocenteDest evento=$evento");

		$doc = getDocenteById($idDocenteDest);
		if (!$doc) {
			warningimportsost("Docente non trovato per notifica: idDocenteDest=$idDocenteDest idSostituzione=$idSostituzione");
			continue;
		}

		$nomeDest = docenteFullName($doc);
		$email = trim((string)($doc['email'] ?? ''));

		$mailGiaInviata = notificaGiaInviata($idSostituzione, $idDocenteDest, $evento, 'MAIL');
		$tgGiaInviata   = notificaGiaInviata($idSostituzione, $idDocenteDest, $evento, 'TELEGRAM');

		$debugNotifiche[] = [
			'idSostituzione' => $idSostituzione,
			'idDocenteDest' => $idDocenteDest,
			'nomeDest' => $nomeDest,
			'email' => $email,
			'evento' => $evento,
			'mail_enabled' => $IS_MAIL_DOCENTE_ENABLED,
			'telegram_enabled' => $IS_TELEGRAM_ENABLED,
			'telegram_test_mode' => $IS_TELEGRAM_TEST_MODE,
			'telegram_test_chat_id' => $TELEGRAM_TEST_CHAT_ID,
			'mail_gia_inviata' => $mailGiaInviata,
			'tg_gia_inviata' => $tgGiaInviata
		];

		infoimportsost(
			"Notifica docente=[$nomeDest] email=[$email] evento=[$evento] " .
				"mail_enabled=" . ($IS_MAIL_DOCENTE_ENABLED ? '1' : '0') .
				" mail_gia_inviata=" . ($mailGiaInviata ? '1' : '0') .
				" telegram_enabled=" . ($IS_TELEGRAM_ENABLED ? '1' : '0') .
				" tg_gia_inviata=" . ($tgGiaInviata ? '1' : '0')
		);

		$subject = buildMailSubjectSostituzione($evento, $n['data'], $n['oraInizio']);

		if ($evento === 'ASSEGNAZIONE') {
			$body = buildMessaggioAssegnazione(
				$n['data'],
				substr($n['oraInizio'], 0, 5),
				substr($n['oraFine'], 0, 5),
				$n['docenteSostituito'],
				$n['classe'],
				$n['aula'],
				$n['materia']
			);
		} else {
			$body = buildMessaggioAnnullamento(
				$n['data'],
				substr($n['oraInizio'], 0, 5),
				substr($n['oraFine'], 0, 5),
				$n['docenteSostituito'],
				$n['classe'],
				$n['aula'],
				$n['materia']
			);
		}
		$mailTestNote = 'Destinatario reale previsto: ' . $nomeDest . ' <' . trim((string)($doc['email'] ?? '')) . '>';

		$mailHtml = buildMailHtmlSostituzione(
			$evento,
			$nomeDest,
			$n['data'],
			substr($n['oraInizio'], 0, 5),
			substr($n['oraFine'], 0, 5),
			$n['docenteSostituito'],
			$n['classe'],
			$n['aula'],
			$n['materia'],
			$mailTestNote
		);

		/* =======================
           MAIL
           ======================= */
		if ($email !== '' && !$mailGiaInviata) {
			infoimportsost("Tentativo MAIL docente=[$nomeDest] email=[$email] evento=[$evento] idSostituzione=$idSostituzione");

			$mailOk = inviaMailDocente($email, $nomeDest, $subject, $mailHtml);

			if ($mailOk) {
				registraNotificaInviata($idSostituzione, $idDocenteDest, $evento, 'MAIL');
				infoimportsost("MAIL inviata OK docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione");
				$notificheInviate[] = [
					'docente' => $nomeDest,
					'canale' => 'MAIL',
					'evento' => $evento
				];
			} else {
				errorimportsost("MAIL fallita docente=[$nomeDest] email=[$email] evento=[$evento] idSostituzione=$idSostituzione");
				$debugNotifiche[] = [
					'idSostituzione' => $idSostituzione,
					'idDocenteDest' => $idDocenteDest,
					'nomeDest' => $nomeDest,
					'erroreMail' => 'sendMail ha restituito false'
				];
			}
		} elseif ($email === '') {
			warningimportsost("MAIL saltata: docente=[$nomeDest] senza email idSostituzione=$idSostituzione");
		} else {
			infoimportsost("MAIL già inviata: docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione");
		}

		/* =======================
           TELEGRAM
           ======================= */
		if ($IS_TELEGRAM_ENABLED && !$tgGiaInviata) {
			infoimportsost("Verifica TELEGRAM docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione");

			$tg = getTelegramProfileByDocenteId($idDocenteDest);

			$chatIdToUse = '';
			$canSendTelegram = false;

			if ($IS_TELEGRAM_TEST_MODE && $TELEGRAM_TEST_CHAT_ID !== '') {
				$chatIdToUse = $TELEGRAM_TEST_CHAT_ID;
				$canSendTelegram = true;
				infoimportsost("TELEGRAM test mode attivo: uso chat test [$chatIdToUse] docente=[$nomeDest]");
			} elseif ($tg && !empty($tg['telegram_chat_id'])) {
				$chatIdToUse = trim((string)$tg['telegram_chat_id']);
				$canSendTelegram = true;
				infoimportsost("TELEGRAM profilo reale trovato: chatId=[$chatIdToUse] docente=[$nomeDest]");
			} else {
				infoimportsost("TELEGRAM profilo reale assente per docente=[$nomeDest]");
			}

			if ($canSendTelegram) {
				infoimportsost("Tentativo TELEGRAM docente=[$nomeDest] chatId=[$chatIdToUse] evento=[$evento] idSostituzione=$idSostituzione");

				$resTg = inviaTelegramDocente(
					$TELEGRAM_BOT_TOKEN,
					$chatIdToUse,
					$body,
					$nomeDest
				);

				infoimportsost("Risposta TELEGRAM docente=[$nomeDest] ok=" . ($resTg['ok'] ? '1' : '0') . " response=[" . mb_substr((string)$resTg['response'], 0, 500, 'UTF-8') . "]");

				logTelegramEsito(
					$idDocenteDest,
					$idSostituzione,
					$evento,
					$body,
					$resTg['ok'] ? 'OK' : 'ERRORE',
					$resTg['response']
				);

				if ($resTg['ok']) {
					registraNotificaInviata($idSostituzione, $idDocenteDest, $evento, 'TELEGRAM');

					if ($tg) {
						updateTelegramLastOk($idDocenteDest);
					}

					infoimportsost("TELEGRAM inviato OK docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione");

					$notificheInviate[] = [
						'docente' => $nomeDest,
						'canale' => 'TELEGRAM',
						'evento' => $evento
					];
				} else {
					errorimportsost("TELEGRAM fallito docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione risposta=[" . mb_substr((string)$resTg['response'], 0, 500, 'UTF-8') . "]");

					if ($tg) {
						updateTelegramLastError($idDocenteDest, $resTg['response']);
					}
				}
			} else {
				warningimportsost("TELEGRAM non inviabile docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione canSendTelegram=0");
				$debugNotifiche[] = [
					'idSostituzione' => $idSostituzione,
					'idDocenteDest' => $idDocenteDest,
					'nomeDest' => $nomeDest,
					'erroreTelegram' => 'canSendTelegram=false',
					'tg_profile_found' => $tg ? true : false,
					'chatIdToUse' => $chatIdToUse
				];
			}
		} elseif (!$IS_TELEGRAM_ENABLED) {
			warningimportsost("TELEGRAM disabilitato da config docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione");
		} else {
			infoimportsost("TELEGRAM già inviato docente=[$nomeDest] evento=[$evento] idSostituzione=$idSostituzione");
		}
	}

	infoimportsost("Import completato: totaleRicevuti=$totaleRicevuti inseriti=$inseriti aggiornati=$aggiornati scartati=" . count($scartati) . " notificheInviate=" . count($notificheInviate));

	respond([
		'ok' => true,
		'totaleRicevuti' => $totaleRicevuti,
		'inseriti' => $inseriti,
		'aggiornati' => $aggiornati,
		'scartati' => count($scartati),
		'dettaglioScartati' => $scartati,
		'preview' => $preview,
		'notificheInviate' => $notificheInviate,
		'debugNotifiche' => $debugNotifiche
	]);
} catch (Throwable $e) {
	dbExec("ROLLBACK");
	errorimportsost("Eccezione durante import: " . $e->getMessage());
	errorimportsost("ROLLBACK eseguito");

	respond([
		'ok' => false,
		'error' => 'Eccezione durante import: ' . $e->getMessage()
	], 500);
}
