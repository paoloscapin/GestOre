<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

// --- LOG INIZIALE ---
debug("=== SPORTELLI: inizio rendering tabella ===");

// Lettura parametri GET
$ancheCancellati   = $_GET["ancheCancellati"];
$soloNuovi         = $_GET["soloNuovi"];
$soloIscritto      = $_GET["soloIscritto"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$materia_filtro_id = $_GET["materia_filtro_id"];
$classe_filtro_id  = $_GET["classe_filtro_id"];
$categoria_filtro_id = $_GET["categoria_filtro_id"];

// Log parametri
debug("Parametri GET: ancheCancellati=" . var_export($ancheCancellati, true) . ", soloNuovi=" . var_export($soloNuovi, true) . ", soloIscritto=" . var_export($soloIscritto, true) . ", docente_filtro_id=" . var_export($docente_filtro_id, true) . ", materia_filtro_id=" . var_export($materia_filtro_id, true) . ", classe_filtro_id=" . var_export($classe_filtro_id, true) . ", categoria_filtro_id=" . var_export($categoria_filtro_id, true));

// Info timezone PHP
debug("PHP default_timezone=" . date_default_timezone_get());

$direzioneOrdinamento = "ASC";

$nome_categoria = dbGetValue("SELECT nome FROM sportello_categoria WHERE id = " . $categoria_filtro_id);
debug("Filtro categoria id=" . $categoria_filtro_id . " => nome_categoria=" . $nome_categoria);

// Design initial table header
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

$query = "	SELECT
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
				(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti,
				(	SELECT sportello_studente.iscritto FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS iscritto,
				(	SELECT sportello_studente.presente FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS presente,
				(	SELECT sportello_studente.argomento FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS argomento,
				(	SELECT studente.cognome FROM studente WHERE id = $__studente_id) AS studente_cognome,
				(	SELECT studente.nome FROM studente WHERE id = $__studente_id) AS studente_nome,
				(	SELECT studente.email FROM studente WHERE id = $__studente_id) AS studente_email,
				(	SELECT classi.classe FROM classi WHERE id = (SELECT studente_frequenta.id_classe FROM studente_frequenta WHERE id_studente = $__studente_id AND id_anno_scolastico = $__anno_scolastico_corrente_id)) AS studente_classe
			FROM sportello sportello
			INNER JOIN docente docente ON sportello.docente_id = docente.id
			INNER JOIN materia materia ON sportello.materia_id = materia.id
			INNER JOIN classe classe ON sportello.classe_id = classe.id
			WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
			";

// rimossa riga da query visto che compare già qui sotto AND NOT sportello.cancellato

if ($classe_filtro_id > 0) {
	$query .= "AND sportello.classe_id = $classe_filtro_id ";
}
if ($materia_filtro_id > 0) {
	$query .= "AND sportello.materia_id = $materia_filtro_id ";
}
if ($docente_filtro_id > 0) {
	$query .= "AND sportello.docente_id = $docente_filtro_id ";
}
if ($categoria_filtro_id > 0) {
	$query .= "AND sportello.categoria = '" . $nome_categoria . "' ";
}
if (!$ancheCancellati) {
	$query .= "AND NOT sportello.cancellato ";
}
if ($soloNuovi) {
	$query .= "AND sportello.data >= CURDATE() ";
}

$query .= "ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC,docente_nome ASC";

debug("Query principale pronta");

// Esecuzione query
$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
debug("Numero sportelli trovati: " . count($resultArray));

foreach ($resultArray as $row) {
	// Log di base per ogni riga
	debug("---- SPORTELLO id=" . $row['sportello_id'] . " categoria=" . $row['sportello_categoria'] . " data=" . $row['sportello_data'] . " ora=" . $row['sportello_ora'] . " cancellato=" . ($row['sportello_cancellato'] ? '1' : '0') . " ----");

	if ((($soloIscritto == 1) && ($row["iscritto"] == 1)) || ($soloIscritto == 0)) {
		$sportello_id = $row['sportello_id'];
		$sportello_categoria = $row['sportello_categoria'];

		$todayDate = new DateTime("today");
		$sportelloDate = new DateTime($row['sportello_data']);
		$passato = ($sportelloDate < $todayDate);
		debug("passato=" . ($passato ? 'true' : 'false') . " (sportelloDate=" . $sportelloDate->format('Y-m-d H:i:s') . ", todayDate=" . $todayDate->format('Y-m-d H:i:s') . ")");

		$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
		$dataSportello = utf8_encode(strftime("%d %B %Y", strtotime($row['sportello_data'])));
		setlocale(LC_TIME, $oldLocale);

		// se ci sono prenotazioni, cerca la lista di studenti che sono prenotati
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
				(	SELECT classi.classe FROM classi WHERE id = (SELECT studente_frequenta.id_classe FROM studente_frequenta WHERE id_studente = $__studente_id AND id_anno_scolastico = $__anno_scolastico_corrente_id)) AS studente_classe
			FROM
				sportello_studente
			INNER JOIN studente
			ON sportello_studente.studente_id = studente.id
			WHERE sportello_studente.sportello_id = '$sportello_id';";

			$studenti = dbGetAll($query2);
			debug("studenti prenotati: " . count($studenti));

			foreach ($studenti as $studente) {
				if (getSettingsValue('sportelli', 'nascondiNomeStudenti', false)) {
					$studenteTip = $studenteTip . '---' . " " . '---' . " " . $studente['studente_classe'] . "</br>";
				} else {
					$studenteTip = $studenteTip . $studente['studente_cognome'] . " " . $studente['studente_nome'] . " " . $studente['studente_classe'] . "</br>";
				}
			}
		}

		// marker per eventuali sportelli online
		$luogo_or_onine_marker = $row['sportello_luogo'];
		if ($row['sportello_online']) {
			$luogo_or_onine_marker = '<span class="label label-danger">online</span>';
		}

		// calcola il numero di posti disponibili per lo sportello
		$max_iscrizioni = $row['sportello_max_iscrizioni'];
		$posti_disponibili = $max_iscrizioni - $row['numero_studenti'];
		$sportello_cancellato = $row['sportello_cancellato'];

		debug("capienza: max_iscrizioni=" . var_export($max_iscrizioni, true) . ", numero_studenti=" . $row['numero_studenti'] . ", posti_disponibili=" . $posti_disponibili . ", sportello_cancellato=" . ($sportello_cancellato ? '1' : '0') . ", iscritto_corrente=" . ($row['iscritto'] ? '1' : '0'));

		if (!$sportello_cancellato) {
			$data .= '<tr>
		<td align="center">' . $sportello_categoria . '</td>
		<td align="center">' . $dataSportello . '</td>
		<td align="center">' . $row['sportello_ora'] . ' &nbsp;&nbsp;&nbsp;(' . $row['sportello_numero_ore'] . ($row['sportello_numero_ore'] > 1 ? ' ore)' : ' ora)') . '</td>
		<td>' . $row['materia_nome'] . '</td>
		<td align="center">' . $row['docente_nome'] . ' ' . $row['docente_cognome'] . '</td>
		';
			if ($row['sportello_argomento'] == "") {
				if ($row['argomento'] == "") {
					$data .= '<td></td>';
				} else {
					$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento da te indicato.<br><br><b>RICORDA</b><br>Ogni studente iscritto allo sportello<br>indica il proprio argomento.">' . $row['argomento'] . '</td>';
				}
			} else {
				$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento scelto dal docente<br><br><b>RICORDA</b><br>Per questo tipo di sportello l\'argomento<br>è deciso dal docente">' . $row['sportello_argomento'] . '</td>';
			}
			$data .= '
		<td align="center">' . $luogo_or_onine_marker . '</td>
		<td align="center">' . $row['sportello_classe'] . '</td>
		<td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="' . $studenteTip . '">' . $posti_disponibili . '</td>
		';
		} else {
			$data .= '<tr>
		<td align="center"><s>' . $sportello_categoria . '</td>
		<td align="center"><s>' . $dataSportello . '</td>
		<td align="center"><s>' . $row['sportello_ora'] . ' &nbsp;&nbsp;&nbsp;(' . $row['sportello_numero_ore'] . ($row['sportello_numero_ore'] > 1 ? ' ore)' : ' ora)') . '</td>
		<td><s>' . $row['materia_nome'] . '</td>
		<td align="center"><s>' . $row['docente_nome'] . ' ' . $row['docente_cognome'] . '</td>
		';
			if ($row['sportello_argomento'] == "") {
				if ($row['argomento'] == "") {
					$data .= '<td></td>';
				} else {
					$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento da te indicato.<br><br><b>RICORDA</b><br>Ogni studente iscritto allo sportello<br>indica il proprio argomento.">' . $row['argomento'] . '</td>';
				}
			} else {
				$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento scelto dal docente<br><br><b>RICORDA</b><br>Per questo tipo di sportello l\'argomento<br>è deciso dal docente">' . $row['sportello_argomento'] . '</td>';
			}
			$data .= '
		<td align="center"><s>' . $luogo_or_onine_marker . '</td>
		<td align="center"><s>' . $row['sportello_classe'] . '</td>
		<td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="<s>' . $studenteTip . '"><s>0</td>
		';
		}
		// apri l'ultima colonna
		$data .= '<td class="text-center">';

		// per prima cosa considera quelli passati
		if ($passato) {
			debug("Ramo: sportello passato");
			if ($row['presente']) {
				$data .= '<div data-toggle="tooltip" data-placement="left"  title="La tua presenza è stata registrata"><span class="label label-success">Presente</span></div>';
			} else {
				if ($row['iscritto']) {
					debug('iscritto');
					$data .= '<div data-toggle="tooltip" data-placement="left"  title="Sei risultato assente ad uno sportello a cui ti eri prenotato"><span class="label label-danger">Assente</span></div>';
				} else {
					$data .= '<div data-toggle="tooltip" data-placement="left"  title="Sportello passato a cui non eri iscritto"><span class="label label-default">Non iscritto</span></div>';
				}
			}
		} else {

			// prende la data di oggi e quella dello sportello
			// Imposta il fuso orario italiano per gestire correttamente ora legale/solare
			$tz = new DateTimeZone('Europe/Rome');
			$now = new DateTime('now', $tz);
			$dataSportello = $row['sportello_data'];

			debug("TZ Europe/Rome attivo; now=" . $now->format('Y-m-d H:i:sP') . "; raw sportello_data=" . $dataSportello . " sportello_ora=" . $row['sportello_ora']);

			// controlla quanti giorni prima chiudono le iscrizioni ( 0 = la mezzanotte del giorno precedente allo sportello)
			$daysInAdvance = getSettingsValue('sportelli', 'chiusuraIscrizioniGiorni', '1');
			debug("Impostazione chiusuraIscrizioniGiorni=" . $daysInAdvance . " (non usata per l'orario fisso 13:00, ma loggata)");

			// Crea la data dello sportello nel fuso orario corretto
			$dataSportelloObj = new DateTime($dataSportello, $tz);
			debug("dataSportelloObj=" . $dataSportelloObj->format('Y-m-d H:i:sP'));
            $orario = getSettingsValue('sportelli', 'chiusuraOrario', '13');
			// Calcola il limite: ore 13:00 del giorno prima
			$lastDay = clone $dataSportelloObj;
			$lastDay->modify('-1 day')->setTime($orario, 0, 0);
			debug("lastDay (deadline iscrizioni)=" . $lastDay->format('Y-m-d H:i:sP'));

			// calcola la data del lunedi della settimana precedente a quella dello sportello e scopre se siamo dopo quel giorno
			$previousMonday = new DateTime($dataSportello . ' Monday ago', $tz);
			debug("previousMonday=" . $previousMonday->format('Y-m-d H:i:sP'));

			$todayAfterpreviousMonday = ($now >= $previousMonday);
			debug("todayAfterpreviousMonday=" . ($todayAfterpreviousMonday ? 'true' : 'false'));

			// se non configurato per prenotare al massimo la settimana successiva, considera come se oggi fosse comunque una data dopo il lunedi della settimana precedente
			$prenotaMaxSettimanaSuccessiva = getSettingsValue('sportelli', 'prenotaMaxSettimanaSuccessiva', true);
			debug("prenotaMaxSettimanaSuccessiva=" . ($prenotaMaxSettimanaSuccessiva ? 'true' : 'false'));
			if (!$prenotaMaxSettimanaSuccessiva) {
				$todayAfterpreviousMonday = true;
				debug("prenotaMaxSettimanaSuccessiva=FALSE => todayAfterpreviousMonday forzato a true");
			}

			// ora puo' controllare se oggi viene prima dell'ultimo giorno valido per la prenotazione (o lo stesso giorno)
			$todayBeforeLastDay = ($now < $lastDay);
			debug("todayBeforeLastDay=" . ($todayBeforeLastDay ? 'true' : 'false') . " (now=" . $now->format('Y-m-d H:i:sP') . " vs lastDay=" . $lastDay->format('Y-m-d H:i:sP') . ")");

			// lo sportello si puo' prenotare se oggi e' >= al primo lunedi' da cui si puo' prenotare e <= all'ultimo giorno di prenotazione
			$prenotabile = ($todayAfterpreviousMonday && $todayBeforeLastDay && (!$sportello_cancellato));
			// e' cancellabile se oggi e' <= all'ultimo giorno di prenotazione
			$cancellabile = $todayBeforeLastDay;

			debug(
				"prenotabile(prima di capienza)=" . ($prenotabile ? 'true' : 'false') .
					", cancellabile=" . ($cancellabile ? 'true' : 'false') .
					", sportello_cancellato=" . ($sportello_cancellato ? 'true' : 'false')
			);

			// controlla che non sia stato raggiunto il massimo numero di prenotazioni
			if ($max_iscrizioni == null && $row['sportello_categoria'] == 'sportello didattico') {
				$max_iscrizioni = getSettingsValue('sportelli', 'numero_max_prenotazioni', 10);
				debug("max_iscrizioni era null per 'sportello didattico' => impostato da settings a " . $max_iscrizioni);
			}

			// zero o null significa nessun limite, altrimenti controlla quanti ce ne sono
			if ($max_iscrizioni != null && $max_iscrizioni > 0 && $max_iscrizioni <= $row['numero_studenti']) {
				$prenotabile = false;
				debug("Capienza raggiunta => prenotabile=FALSE (max_iscrizioni=" . $max_iscrizioni . " numero_studenti=" . $row['numero_studenti'] . ")");
			}

			// la didattica puo' inserire la prenotazione sempre e puo' sempre cancellare
			if (!(impersonaRuolo('studente'))&&(haRuolo('segreteria-didattica'))) {
				$prenotabile = true;
				$cancellabile = true;
			}

			debug("FINAL: prenotabile=" . ($prenotabile ? 'true' : 'false') . ", cancellabile=" . ($cancellabile ? 'true' : 'false'));

			// per quelli non passati, se sono iscritto lo dice e mi lascia cancellare, altrimenti mi lascia iscrivere se non sono scaduti i termini
			if ($sportello_cancellato) {
				debug("UI branch: sportello cancellato");
				$data .= '<div data-toggle="tooltip" data-placement="left"  title="Sportello annullato dal docente"><span class="label label-default">cancellato</span></div>';
			} else
				if ($row['iscritto']) {
				if ($cancellabile) {
					debug("UI branch: iscritto & cancellabile");
					$data .= '
						<div data-toggle="tooltip" data-placement="left"  title="Clicca qui per cancellare la prenotazione"><span class="label label-success">Iscritto</span><button onclick="sportelloCancellaIscrizione(' . $row['sportello_id'] . ', \'' . addslashes($row['materia_nome']) . '\', \'' . addslashes($row['sportello_categoria']) . '\', \'' . addslashes($row['sportello_argomento']) . '\',\'' . addslashes($row['sportello_data']) . '\',\'' . addslashes($row['sportello_ora']) . '\',\'' . addslashes($row['sportello_numero_ore']) . '\',\'' . addslashes($row['sportello_luogo']) . '\',\'' . addslashes($row['studente_cognome']) . '\',\'' . addslashes($row['studente_nome']) . '\',\'' . addslashes($row['studente_email']) . '\',\'' . addslashes($row['studente_classe']) . '\',\'' . addslashes($row['docente_cognome']) . '\',\'' . addslashes($row['docente_nome']) . '\',\'' . addslashes($row['docente_email']) . '\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button></div>
						';
				} else {
					debug("UI branch: iscritto & NON cancellabile");
					$data .= '
						<div data-toggle="tooltip" data-placement="left"  title="Iscrizione non più cancellabile"><span class="label label-success">Iscritto</span></div>
						';
				}
			} else {
				if ($prenotabile) {
					debug("UI branch: NON iscritto & prenotabile => mostra pulsante iscrizione");
					$data .= '
					<div data-toggle="tooltip" data-placement="left"  title="Clicca qui per iscriverti allo sportello"><span class="label label-primary">Disponibile</span>
					<button onclick="sportelloIscriviti(' . $row['sportello_id'] . ', \'' . addslashes($row['materia_nome']) . '\', \'' . addslashes($row['sportello_categoria']) . '\', \'' . addslashes($row['sportello_argomento']) . '\',\'' . addslashes($row['sportello_data']) . '\',\'' . addslashes($row['sportello_ora']) . '\',\'' . addslashes($row['sportello_numero_ore']) . '\',\'' . addslashes($row['sportello_luogo']) . '\',\'' . addslashes($row['studente_cognome']) . '\',\'' . addslashes($row['studente_nome']) . '\',\'' . addslashes($row['studente_email']) . '\',\'' . addslashes($row['studente_classe']) . '\',\'' . addslashes($row['docente_cognome']) . '\',\'' . addslashes($row['docente_nome']) . '\',\'' . addslashes($row['docente_email']) . '\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button></div>
					';
				} else {
					if ($posti_disponibili == 0) {
						debug("UI branch: NON iscritto & NON prenotabile & posti_esauriti");
						$data .= '
					<div data-toggle="tooltip" data-placement="left"  title="Posti disponibili esauriti"><span class="label label-danger">Posti esauriti</span></div>
					';
					} else {
						$tSportello = new DateTime($dataSportello);
						if ($tSportello <= $todayDate) {
							debug("UI branch: NON iscritto & NON prenotabile & iscrizioni chiuse (per data)");
							$data .= '
						<div data-toggle="tooltip" data-placement="left"  title="Iscrizioni chiuse"><span span class="label label-danger">Iscrizioni chiuse</span></div>
						';
						} else {
							// qui differenziamo il messaggio per chiarezza quando il blocco è la scadenza 13:00
							if ($now >= $lastDay) {
								debug("UI branch: NON iscritto & NON prenotabile & oltre il limite 13:00 del giorno precedente");
								$data .= '
						<div data-toggle="tooltip" data-placement="left"  title="Iscrizioni chiuse dalle ore 13:00 del giorno precedente"><span span class="label label-danger">Iscrizioni chiuse</span></div>
						';
							} else {
								debug("UI branch: NON iscritto & NON prenotabile & non ancora prenotabile (prima del lunedì precedente)");
								$data .= '
						<div data-toggle="tooltip" data-placement="left"  title="Prenotabile dal lunedì precedente"><span span class="label label-info">Non ancora prenotabile</span></div>
						';
							}
						}
					}
				}
			}
		}

		// chiudi l'ultima colonna e la riga
		$data .= '</td></tr>';
	}
}
$data .= '</table></div>';

echo $data;

debug("=== SPORTELLI: fine rendering tabella ===");
