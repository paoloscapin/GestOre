<?php

/**
 *  Versione MOBILE di GestOre - Sportelli (GENITORE)
 *  - Vista a card
 *  - Genitore NON può iscriversi/cancellarsi
 *  - Logica cutoff: 13:00 del giorno precedente (ora da settings: sportelli.chiusuraOrario, default 13)
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

date_default_timezone_set('Europe/Rome');

echo '<style>
.badge-primary { background-color:#007bff; color:#fff; }
.badge-success { background-color:#28a745; color:#fff; }
.badge-danger  { background-color:#dc3545; color:#fff; }
.badge-info    { background-color:#17a2b8; color:#fff; }
.badge-default { background-color:#6c757d; color:#fff; }
.badge-secondary { background-color:#6c757d; color:#fff; }
</style>';

// --- Debug helper (facoltativo) ---
if (!function_exists('debug')) {
    function debug($msg)
    {
        error_log(date('d/m/Y - H:i:s') . "  [debug] sportelloMobileGenitore.php: " . $msg);
    }
}

// --- PARAMETRI GET ---
$ancheCancellati     = isset($_GET["ancheCancellati"])     ? (int)$_GET["ancheCancellati"]     : 0;
$soloNuovi           = isset($_GET["soloNuovi"])           ? (int)$_GET["soloNuovi"]           : 0;
$soloIscritto        = isset($_GET["soloIscritto"])        ? (int)$_GET["soloIscritto"]        : 0;
$docente_filtro_id   = isset($_GET["docente_filtro_id"])   ? (int)$_GET["docente_filtro_id"]   : 0;
$materia_filtro_id   = isset($_GET["materia_filtro_id"])   ? (int)$_GET["materia_filtro_id"]   : 0;
$classe_filtro_id    = isset($_GET["classe_filtro_id"])    ? (int)$_GET["classe_filtro_id"]    : 0;
$categoria_filtro_id = isset($_GET["categoria_filtro_id"]) ? (int)$_GET["categoria_filtro_id"] : 0;
$studente_filtro_id  = isset($_GET["studente_filtro_id"])  ? (int)$_GET["studente_filtro_id"]  : 0;

// necessarie per le sottoquery
$__studente_id = $studente_filtro_id;
$direzioneOrdinamento = "ASC";

$nome_categoria = $categoria_filtro_id > 0
    ? dbGetValue("SELECT nome FROM sportello_categoria WHERE id = " . $categoria_filtro_id)
    : '';

debug("=== SPORTELLI MOBILE GENITORE: start ===");

// --- QUERY PRINCIPALE ---
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
FROM sportello
INNER JOIN docente ON sportello.docente_id = docente.id
INNER JOIN materia ON sportello.materia_id = materia.id
INNER JOIN classe  ON sportello.classe_id  = classe.id
WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id";

if ($classe_filtro_id > 0)    $query .= " AND sportello.classe_id = $classe_filtro_id ";
if ($materia_filtro_id > 0)   $query .= " AND sportello.materia_id = $materia_filtro_id ";
if ($docente_filtro_id > 0)   $query .= " AND sportello.docente_id = $docente_filtro_id ";
if ($categoria_filtro_id > 0) $query .= " AND sportello.categoria = '" . addslashes($nome_categoria) . "' ";
if (!$ancheCancellati)        $query .= " AND NOT sportello.cancellato ";
if ($soloNuovi)               $query .= " AND sportello.data >= CURDATE() ";

$query .= " ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC, docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

debug("Trovati sportelli: " . count($resultArray));

$data = '<div class="cards-container">';

foreach ($resultArray as $row) {
    if ((($soloIscritto == 1) && ($row["iscritto"] == 1)) || ($soloIscritto == 0)) {

        $sportello_cancellato = $row['sportello_cancellato'];

        $todayDate     = new DateTime("today");
        $sportelloDate = new DateTime($row['sportello_data']);
        $passato       = ($sportelloDate < $todayDate);

        // Data in italiano
        $oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
        $dataSportelloDisp = utf8_encode(strftime("%d %B %Y", strtotime($row['sportello_data'])));
        setlocale(LC_TIME, $oldLocale);

        // Capienza
        $max_iscrizioni     = $row['sportello_max_iscrizioni'];
        $posti_disponibili  = $max_iscrizioni - $row['numero_studenti'];

        // Marker online
        $luogo_or_online = $row['sportello_online']
            ? '<span class="badge badge-danger">Online</span>'
            : htmlspecialchars($row['sportello_luogo']);

        // --- CARD ---
        $data .= '<div class="card mb-3 p-2" style="border:1px solid #ddd; border-radius:10px; background:#fff; padding:12px;">';

        $data .= '<div><strong>Categoria:</strong> ' . htmlspecialchars($row['sportello_categoria']) . '</div>';
        $data .= '<div><strong>Data:</strong> ' . $dataSportelloDisp . ' - ' . htmlspecialchars($row['sportello_ora']) . ' (' . intval($row['sportello_numero_ore']) . ($row['sportello_numero_ore'] > 1 ? ' ore)' : ' ora)') . '</div>';
        $data .= '<div><strong>Materia:</strong> ' . htmlspecialchars($row['materia_nome']) . '</div>';
        $data .= '<div><strong>Docente:</strong> ' . htmlspecialchars($row['docente_nome']) . ' ' . htmlspecialchars($row['docente_cognome']) . '</div>';
        if (!empty($row['sportello_argomento'])) {
            $data .= '<div><strong>Argomento:</strong> ' . htmlspecialchars($row['sportello_argomento']) . '</div>';
        } elseif (!empty($row['argomento'])) {
            $data .= '<div><strong>Argomento studente:</strong> ' . htmlspecialchars($row['argomento']) . '</div>';
        }
        $data .= '<div><strong>Luogo:</strong> ' . $luogo_or_online . '</div>';
        $data .= '<div><strong>Classe:</strong> ' . htmlspecialchars($row['sportello_classe']) . '</div>';
        $data .= '<div><strong>Posti disponibili:</strong> ' . htmlspecialchars($posti_disponibili) . '</div>';

        // --- STATO / MESSAGGI (NO AZIONI per genitore) ---
        $data .= '<div class="mt-2 text-center">';

        if ($passato) {
            if ($row['presente']) {
                $data .= '<span class="badge badge-success">Presente</span>';
            } elseif ($row['iscritto']) {
                $data .= '<span class="badge badge-danger">Assente</span>';
            } else {
                $data .= '<span class="badge badge-secondary">Non iscritto</span>';
            }
            if ($sportello_cancellato) {
                $data .= '<span class="badge badge-secondary">Cancellato</span>';
            }
        } else {
            // Finestra temporale: 13:00 del giorno precedente (ora da settings)
            $tz  = new DateTimeZone('Europe/Rome');
            $now = new DateTime('now', $tz);

            $dataSportelloRaw = $row['sportello_data'];
            $dataSportelloObj = new DateTime($dataSportelloRaw, $tz);

            $orarioCutoffH = (int) getSettingsValue('sportelli', 'chiusuraOrario', '13'); // es. 13
            $lastDay = clone $dataSportelloObj;
            $lastDay->modify('-1 day')->setTime($orarioCutoffH, 0, 0);

            // lunedì precedente
            $previousMonday = new DateTime($dataSportelloRaw . ' Monday ago', $tz);
            $todayAfterpreviousMonday = ($now >= $previousMonday);

            $prenotaMaxSettimanaSuccessiva = getSettingsValue('sportelli', 'prenotaMaxSettimanaSuccessiva', true);
            if (!$prenotaMaxSettimanaSuccessiva) {
                $todayAfterpreviousMonday = true;
            }

            $todayBeforeLastDay = ($now < $lastDay);

            // stato base (ma GENITORE non può agire: niente pulsanti, niente prenotazione/cancellazione)
            $prenotabile  = ($todayAfterpreviousMonday && $todayBeforeLastDay && !$sportello_cancellato);
            $cancellabile = false; // forzato per genitore
            $prenotabile  = false; // forzato per genitore

            // capienza default per categoria didattico
            if ($max_iscrizioni == null && $row['sportello_categoria'] == 'sportello didattico') {
                $max_iscrizioni    = getSettingsValue('sportelli', 'numero_max_prenotazioni', 10);
                $posti_disponibili = $max_iscrizioni - $row['numero_studenti'];
            }
            if ($max_iscrizioni != null && $max_iscrizioni > 0 && $max_iscrizioni <= $row['numero_studenti']) {
                // anche se pieno, il genitore vede solo il messaggio (nessuna azione)
                $prenotabile = false;
            }

            // --- UI (solo etichette, nessun bottone) ---
            if ($sportello_cancellato) {
                $data .= '<span class="badge badge-secondary">Cancellato</span>';
            } elseif ($row['iscritto']) {
                $data .= '<span class="badge badge-success">Iscritto</span>';
            } else {
                if ($posti_disponibili <= 0) {
                    $data .= '<span class="badge badge-danger">Posti esauriti</span>';
                } elseif ($now >= $lastDay) {
                    $data .= '<span class="badge badge-danger">Iscrizioni chiuse dalle ore ' . sprintf('%02d:00', $orarioCutoffH) . ' del giorno precedente</span>';
                } elseif (!$todayAfterpreviousMonday) {
                    $data .= '<span class="badge badge-info">Prenotabile dal lunedì precedente</span>';
                } else {
                    // Disponibile ma non prenotabile dal genitore
                    $data .= '<span class="badge badge-primary">Disponibile</span>';
                }
            }
        }

        $data .= '</div>'; // /text-center
        $data .= '</div>'; // /card
    }
}

$data .= '</div>';

echo $data;
debug("=== SPORTELLI MOBILE GENITORE: end ===");
