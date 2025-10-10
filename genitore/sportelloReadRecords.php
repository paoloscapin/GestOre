<?php

/**
 *  Versione DESKTOP di GestOre - Sportelli (GENITORE)
 *  - Tabella
 *  - Genitore NON può iscriversi/cancellarsi
 *  - Cutoff iscrizioni: ore 13:00 del giorno precedente (ora da settings: sportelli.chiusuraOrario, default 13)
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

date_default_timezone_set('Europe/Rome');

// --- LOG INIZIALE ---
debug("=== SPORTELLI GENITORE: inizio rendering tabella ===");

// Lettura parametri GET (forzati a intero dove sensato)
$ancheCancellati     = isset($_GET["ancheCancellati"])     ? (int)$_GET["ancheCancellati"]     : 0;
$soloNuovi           = isset($_GET["soloNuovi"])           ? (int)$_GET["soloNuovi"]           : 0;
$soloIscritto        = isset($_GET["soloIscritto"])        ? (int)$_GET["soloIscritto"]        : 0;
$docente_filtro_id   = isset($_GET["docente_filtro_id"])   ? (int)$_GET["docente_filtro_id"]   : 0;
$materia_filtro_id   = isset($_GET["materia_filtro_id"])   ? (int)$_GET["materia_filtro_id"]   : 0;
$classe_filtro_id    = isset($_GET["classe_filtro_id"])    ? (int)$_GET["classe_filtro_id"]    : 0;
$categoria_filtro_id = isset($_GET["categoria_filtro_id"]) ? (int)$_GET["categoria_filtro_id"] : 0;
$studente_filtro_id  = isset($_GET["studente_filtro_id"])  ? (int)$_GET["studente_filtro_id"]  : 0;

// per le sottoquery
$__studente_id = $studente_filtro_id;

debug("Parametri GET: ancheCancellati=" . var_export($ancheCancellati, true) .
	", soloNuovi=" . var_export($soloNuovi, true) .
	", soloIscritto=" . var_export($soloIscritto, true) .
	", docente_filtro_id=" . var_export($docente_filtro_id, true) .
	", materia_filtro_id=" . var_export($materia_filtro_id, true) .
	", classe_filtro_id=" . var_export($classe_filtro_id, true) .
	", categoria_filtro_id=" . var_export($categoria_filtro_id, true) .
	", studente_filtro_id=" . var_export($studente_filtro_id, true));

debug("PHP default_timezone=" . date_default_timezone_get());

$direzioneOrdinamento = "ASC";
$nome_categoria = dbGetValue("SELECT nome FROM sportello_categoria WHERE id = " . $categoria_filtro_id);
debug("Filtro categoria id=" . $categoria_filtro_id . " => nome_categoria=" . $nome_categoria);

// Header tabella
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
    <thead>
    <tr>
        <th class="text-center col-md-1">Categoria</th>
        <th class="text-center col-md-1">Data</th>
        <th class="text-center col-md-1">Ora</th>
        <th class="text-center col-md-2">Materia</th>
        <th class="text-center col-md-1">Docente</th>
        <th class="text-center col-md-2">Argomento</th>
        <th class="text-center col-md-1">Luogo</th>
        <th class="text-center col-md-1">Classe</th>
        <th class="text-center col-md-1">Posti disponibili</th>
        <th class="text-center col-md-1">Iscrizione</th>
    </tr>
    </thead>';

// Query principale (stessa della studente, ma UI poi senza azioni)
$query = "SELECT
        sportello.id AS sportello_id,
        sportello.data AS sportello_data,
        sportello.ora AS sportello_ora,
        sportello.numero_ore AS sportello_numero_ore,
        sportello.argomento AS sportello_argomento,
        sportello.luogo AS sportello_luogo,
        sportello.classe AS sportello_classe,
        sportello.firmato AS sportello_firmato,
        sportello.cancellato AS sportello_cancellato,
        sportello.categoria AS sportello_categoria,
        sportello.online AS sportello_online,
        sportello.max_iscrizioni AS sportello_max_iscrizioni,
        materia.nome AS materia_nome,
        docente.cognome AS docente_cognome,
        docente.nome AS docente_nome,
        docente.email AS docente_email,
        (SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti,
        (SELECT sportello_studente.iscritto FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id LIMIT 1) AS iscritto,
        (SELECT sportello_studente.presente FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id LIMIT 1) AS presente,
        (SELECT sportello_studente.argomento FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id LIMIT 1) AS argomento,
        (SELECT studente.cognome FROM studente WHERE id = $__studente_id LIMIT 1) AS studente_cognome,
        (SELECT studente.nome FROM studente WHERE id = $__studente_id LIMIT 1) AS studente_nome,
        (SELECT studente.email FROM studente WHERE id = $__studente_id LIMIT 1) AS studente_email,
        (SELECT classi.classe FROM classi WHERE id = (SELECT studente_frequenta.id_classe FROM studente_frequenta WHERE id_studente = $__studente_id AND id_anno_scolastico = $__anno_scolastico_corrente_id LIMIT 1) LIMIT 1) AS studente_classe
    FROM sportello sportello
    INNER JOIN docente docente ON sportello.docente_id = docente.id
    INNER JOIN materia materia ON sportello.materia_id = materia.id
    INNER JOIN classe  classe  ON sportello.classe_id  = classe.id
    WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
";

if ($classe_filtro_id > 0)    $query .= "AND sportello.classe_id = $classe_filtro_id ";
if ($materia_filtro_id > 0)   $query .= "AND sportello.materia_id = $materia_filtro_id ";
if ($docente_filtro_id > 0)   $query .= "AND sportello.docente_id = $docente_filtro_id ";
if ($categoria_filtro_id > 0) $query .= "AND sportello.categoria = '" . addslashes($nome_categoria) . "' ";
if (!$ancheCancellati)        $query .= "AND NOT sportello.cancellato ";
if ($soloNuovi)               $query .= "AND sportello.data >= CURDATE() ";

$query .= "ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC, docente_nome ASC";

debug("Query principale pronta");

// Esecuzione query
$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];
debug("Numero sportelli trovati: " . count($resultArray));

foreach ($resultArray as $row) {

	if ((($soloIscritto == 1) && ($row["iscritto"] == 1)) || ($soloIscritto == 0)) {

		$todayDate     = new DateTime("today");
		$sportelloDate = new DateTime($row['sportello_data']);
		$passato       = ($sportelloDate < $todayDate);

		$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
		$dataSportello = utf8_encode(strftime("%d %B %Y", strtotime($row['sportello_data'])));
		setlocale(LC_TIME, $oldLocale);

		// elenco studenti prenotati per tooltip (come nella studente)
		$studenteTip = '';
		if ($row['numero_studenti'] > 0) {
			$query2 = "SELECT
                sportello_studente.id AS sportello_studente_id,
                sportello_studente.iscritto AS sportello_studente_iscritto,
                sportello_studente.presente AS sportello_studente_presente,
                sportello_studente.note AS sportello_studente_note,
                studente.cognome AS studente_cognome,
                studente.nome AS studente_nome,
                studente.id AS studente_id,
                (SELECT classi.classe FROM classi WHERE id = (SELECT studente_frequenta.id_classe FROM studente_frequenta WHERE id_studente = $__studente_id AND id_anno_scolastico = $__anno_scolastico_corrente_id)) AS studente_classe
            FROM sportello_studente
            INNER JOIN studente ON sportello_studente.studente_id = studente.id
            WHERE sportello_studente.sportello_id = '$row[sportello_id]';";
			$studenti = dbGetAll($query2);

			foreach ($studenti as $studente) {
				if (getSettingsValue('sportelli', 'nascondiNomeStudenti', false)) {
					$studenteTip .= '--- --- ' . $studente['studente_classe'] . "</br>";
				} else {
					$studenteTip .= $studente['studente_cognome'] . " " . $studente['studente_nome'] . " " . $studente['studente_classe'] . "</br>";
				}
			}
		}

		// marker online
		$luogo_or_onine_marker = $row['sportello_luogo'];
		if ($row['sportello_online']) {
			$luogo_or_onine_marker = '<span class="label label-danger">online</span>';
		}

		// capienza
		$max_iscrizioni    = $row['sportello_max_iscrizioni'];
		$posti_disponibili = $max_iscrizioni - $row['numero_studenti'];
		$sportello_cancellato = $row['sportello_cancellato'];

		// riga tabella (come studente)
		$data .= '<tr>
            <td align="center">' . $row['sportello_categoria'] . '</td>
            <td align="center">' . $dataSportello . '</td>
            <td align="center">' . $row['sportello_ora'] . ' &nbsp;&nbsp;&nbsp;(' . $row['sportello_numero_ore'] . ($row['sportello_numero_ore'] > 1 ? ' ore)' : ' ora)') . '</td>
            <td>' . $row['materia_nome'] . '</td>
            <td align="center">' . $row['docente_nome'] . ' ' . $row['docente_cognome'] . '</td>';

		if ($row['sportello_argomento'] == "") {
			if ($row['argomento'] == "") {
				$data .= '<td></td>';
			} else {
				$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento indicato dallo studente"><span>' . $row['argomento'] . '</span></td>';
			}
		} else {
			$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento scelto dal docente"><span>' . $row['sportello_argomento'] . '</span></td>';
		}

		$data .= '
            <td align="center">' . $luogo_or_onine_marker . '</td>
            <td align="center">' . $row['sportello_classe'] . '</td>
            <td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="' . $studenteTip . '">' . $posti_disponibili . '</td>
        ';

		// --- COLONNA STATO (GENITORE: SOLO LABEL, NESSUN BOTTONE) ---
		$data .= '<td class="text-center">';

		if ($passato) {
			if ($row['presente']) {
				$data .= '<div data-toggle="tooltip" data-placement="left" title="Presenza registrata"><span class="label label-success">Presente</span></div>';
			} elseif ($row['iscritto']) {
				$data .= '<div data-toggle="tooltip" data-placement="left" title="Assente a uno sportello prenotato"><span class="label label-danger">Assente</span></div>';
			} else {
				$data .= '<div data-toggle="tooltip" data-placement="left" title="Sportello passato a cui non eri iscritto"><span class="label label-default">Non iscritto</span></div>';
			}
			if ($sportello_cancellato) {
				$data .= '<div data-toggle="tooltip" data-placement="left" title="Sportello annullato"><span class="label label-default">Cancellato</span></div>';
			}
		} else {
			// Finestra temporale come studente: 13:00 del giorno precedente
			$tz  = new DateTimeZone('Europe/Rome');
			$now = new DateTime('now', $tz);
			$dataSportello = $row['sportello_data'];

			$dataSportelloObj = new DateTime($dataSportello, $tz);
			$orario = getSettingsValue('sportelli', 'chiusuraOrario', '13'); // es. 13
			$lastDay = clone $dataSportelloObj;
			$lastDay->modify('-1 day')->setTime((int)$orario, 0, 0);

			$previousMonday = new DateTime($dataSportello . ' Monday ago', $tz);
			$todayAfterpreviousMonday = ($now >= $previousMonday);

			$prenotaMaxSettimanaSuccessiva = getSettingsValue('sportelli', 'prenotaMaxSettimanaSuccessiva', true);
			if (!$prenotaMaxSettimanaSuccessiva) {
				$todayAfterpreviousMonday = true;
			}

			$todayBeforeLastDay = ($now < $lastDay);

			// Stato calcolato (ma il genitore non ha azioni: niente pulsanti)
			$prenotabile  = ($todayAfterpreviousMonday && $todayBeforeLastDay && (!$sportello_cancellato));
			$cancellabile = false; // forzato
			$prenotabile  = false; // forzato

			if ($max_iscrizioni == null && $row['sportello_categoria'] == 'sportello didattico') {
				$max_iscrizioni = getSettingsValue('sportelli', 'numero_max_prenotazioni', 10);
			}
			if ($max_iscrizioni != null && $max_iscrizioni > 0 && $max_iscrizioni <= $row['numero_studenti']) {
				$prenotabile = false;
			}

			// UI: solo etichette
			if ($sportello_cancellato) {
				$data .= '<div data-toggle="tooltip" data-placement="left" title="Sportello annullato"><span class="label label-default">Cancellato</span></div>';
			} elseif ($row['iscritto']) {
				$data .= '<div data-toggle="tooltip" data-placement="left" title="Studente iscritto"><span class="label label-success">Iscritto</span></div>';
			} else {
				if ($posti_disponibili == 0) {
					$data .= '<div data-toggle="tooltip" data-placement="left" title="Posti disponibili esauriti"><span class="label label-danger">Posti esauriti</span></div>';
				} else {
					if ($now >= $lastDay) {
						$data .= '<div data-toggle="tooltip" data-placement="left" title="Iscrizioni chiuse dalle ore ' . sprintf('%02d:00', (int)$orario) . ' del giorno precedente"><span class="label label-danger">Iscrizioni chiuse</span></div>';
					} elseif (!$todayAfterpreviousMonday) {
						$data .= '<div data-toggle="tooltip" data-placement="left" title="Prenotabile dal lunedì precedente"><span class="label label-info">Non ancora prenotabile</span></div>';
					} else {
						// sarebbe prenotabile ma il genitore non può agire → mostra solo stato
						$data .= '<div data-toggle="tooltip" data-placement="left" title="Disponibile (azioni non abilitate per il genitore)"><span class="label label-primary">Disponibile</span></div>';
					}
				}
			}
		}

		$data .= '</td></tr>'; // chiude colonna stato e riga
	}
}

$data .= '</table></div>';
echo $data;

debug("=== SPORTELLI GENITORE: fine rendering tabella ===");
