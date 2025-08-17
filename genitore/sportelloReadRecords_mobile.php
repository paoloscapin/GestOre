<?php
/**
 *  Versione MOBILE di GestOre - Sportelli
 *  Le informazioni sono mostrate in formato card invece che tabella
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$ancheCancellati   = $_GET["ancheCancellati"];
$soloNuovi         = $_GET["soloNuovi"];
$soloIscritto      = $_GET["soloIscritto"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$materia_filtro_id = $_GET["materia_filtro_id"];
$classe_filtro_id  = $_GET["classe_filtro_id"];
$categoria_filtro_id = $_GET["categoria_filtro_id"];
$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$__studente_id = $studente_filtro_id;

$direzioneOrdinamento = "ASC";
$nome_categoria = $categoria_filtro_id > 0 ? dbGetValue("SELECT nome FROM sportello_categoria WHERE id = " . $categoria_filtro_id) : '';

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
    (SELECT sportello_studente.iscritto FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS iscritto,
    (SELECT sportello_studente.presente FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS presente,
    (SELECT sportello_studente.argomento FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS argomento,
    (SELECT studente.cognome FROM studente WHERE id = $__studente_id) AS studente_cognome,
    (SELECT studente.nome FROM studente WHERE id = $__studente_id) AS studente_nome,
    (SELECT studente.email FROM studente WHERE id = $__studente_id) AS studente_email,
    (SELECT classi.classe FROM classi WHERE id = (SELECT studente_frequenta.id_classe FROM studente_frequenta WHERE id_studente = $__studente_id AND id_anno_scolastico = $__anno_scolastico_corrente_id)) AS studente_classe
FROM sportello
INNER JOIN docente ON sportello.docente_id = docente.id
INNER JOIN materia ON sportello.materia_id = materia.id
INNER JOIN classe ON sportello.classe_id = classe.id
WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id";

if ($classe_filtro_id > 0)    $query .= " AND sportello.classe_id = $classe_filtro_id ";
if ($materia_filtro_id > 0)   $query .= " AND sportello.materia_id = $materia_filtro_id ";
if ($docente_filtro_id > 0)   $query .= " AND sportello.docente_id = $docente_filtro_id ";
if ($categoria_filtro_id > 0) $query .= " AND sportello.categoria = '" . $nome_categoria . "' ";
if (!$ancheCancellati)        $query .= " AND NOT sportello.cancellato ";
if ($soloNuovi)               $query .= " AND sportello.data >= CURDATE() ";

$query .= " ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC, docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

$data = '<div class="cards-container">';

foreach ($resultArray as $row) {
    if ((($soloIscritto == 1) && ($row["iscritto"] == 1)) || ($soloIscritto == 0)) {

        $sportello_id        = $row['sportello_id'];
        $sportello_categoria = $row['sportello_categoria'];
        $sportello_cancellato = $row['sportello_cancellato'];

        $todayDate     = new DateTime("today");
        $sportelloDate = new DateTime($row['sportello_data']);
        $passato       = ($sportelloDate < $todayDate);

        // Data in italiano per display
        $oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
        $dataSportelloDisp = utf8_encode(strftime("%d %B %Y", strtotime($row['sportello_data'])));
        setlocale(LC_TIME, $oldLocale);

        // Limiti e posti disponibili (iniziale)
        $max_iscrizioni = $row['sportello_max_iscrizioni'];
        $posti_disponibili = $max_iscrizioni - $row['numero_studenti'];

        // marker online
        $luogo_or_online = $row['sportello_online'] ? '<span class="badge bg-danger">Online</span>' : $row['sportello_luogo'];

        // Card container
        $data .= '<div class="card mb-3 p-2" style="border:1px solid #ddd; border-radius:10px; background:#fff; padding:12px;">';

        if ($sportello_cancellato) {
            $data .= '<div class="badge bg-secondary">Sportello annullato</div>';
        }

        // Contenuto card
        $data .= '<div><strong>Categoria:</strong> ' . htmlspecialchars($sportello_categoria) . '</div>';
        $data .= '<div><strong>Data:</strong> ' . $dataSportelloDisp . ' - ' . htmlspecialchars($row['sportello_ora']) . ' (' . intval($row['sportello_numero_ore']) . ($row['sportello_numero_ore'] > 1 ? ' ore)' : ' ora)') . '</div>';
        $data .= '<div><strong>Materia:</strong> ' . htmlspecialchars($row['materia_nome']) . '</div>';
        $data .= '<div><strong>Docente:</strong> ' . htmlspecialchars($row['docente_nome']) . ' ' . htmlspecialchars($row['docente_cognome']) . '</div>';

        if ($row['sportello_argomento'] != "") {
            $data .= '<div><strong>Argomento:</strong> ' . htmlspecialchars($row['sportello_argomento']) . '</div>';
        } elseif ($row['argomento'] != "") {
            $data .= '<div><strong>Argomento studente:</strong> ' . htmlspecialchars($row['argomento']) . '</div>';
        }

        $data .= '<div><strong>Luogo:</strong> ' . $luogo_or_online . '</div>';
        $data .= '<div><strong>Classe:</strong> ' . htmlspecialchars($row['sportello_classe']) . '</div>';
        $data .= '<div><strong>Posti disponibili:</strong> ' . htmlspecialchars($posti_disponibili) . '</div>';

        // --- LOGICA DI STATO / AZIONI (presa dalla versione desktop) ---
        $data .= '<div class="mt-2 text-center">';

        if ($passato) {
            // sportelli passati
            if ($row['presente']) {
                $data .= '<span class="badge" style="background-color:#28a745; color:#fff;">Presente</span>';
            } else {
                if ($row['iscritto']) {
                    $data .= '<span class="badge bg-danger">Assente</span>';
                } else {
                    $data .= '<span class="badge bg-secondary">Non iscritto</span>';
                }
            }
        } else {
            // sportelli non passati: calcolo prenotabile/cancellabile

            // prende la data di oggi e quella dello sportello
            $today = new DateTime('today');
            $dataSportelloRaw = $row['sportello_data'];

            // controlla quanti giorni prima chiudono le iscrizioni (0 = la mezzanotte del giorno precedente)
            $daysInAdvance = getSettingsValue('sportelli', 'chiusuraIscrizioniGiorni', '1');

            // 1 days ago = la mezzanotte del giorno prima, quindi +1 per il controllo
            $daysAgo = $daysInAdvance + 1;

            // ultimo giorno valido per la prenotazione (compreso)
            $lastDay = new DateTime($dataSportelloRaw . ' ' . $daysAgo . ' days ago');

            // lunedì della settimana precedente allo sportello
            $previousMonday = new DateTime($dataSportelloRaw . ' Monday ago');

            $todayAfterpreviousMonday = ($today >= $previousMonday);

            // se non configurato il limite “settimana successiva”, ignora la regola del lunedì
            if (!getSettingsValue('sportelli', 'prenotaMaxSettimanaSuccessiva', true)) {
                $todayAfterpreviousMonday = true;
            }

            // oggi entro l’ultimo giorno valido?
            $todayBeforeLastDay = ($today <= $lastDay);

            // prenotabile/cancellabile base
            $prenotabile = ($todayAfterpreviousMonday && $todayBeforeLastDay && (!$sportello_cancellato));
            $cancellabile = $todayBeforeLastDay;

            // se max_iscrizioni non impostato e categoria didattico, usa default da settings
            if ($max_iscrizioni == null && $row['sportello_categoria'] == 'sportello didattico') {
                $max_iscrizioni = getSettingsValue('sportelli', 'numero_max_prenotazioni', 10);
                // opzionale: ricalcolo posti disponibili per display coerente
                $posti_disponibili = $max_iscrizioni - $row['numero_studenti'];
            }

            // se raggiunto il massimo, non prenotabile
            if ($max_iscrizioni != null && $max_iscrizioni > 0 && $max_iscrizioni <= $row['numero_studenti']) {
                $prenotabile = false;
            }

            // override per ruolo segreteria-didattica
            if (haRuolo('segreteria-didattica')) {
                $prenotabile = true;
                $cancellabile = true;
            }

            // stati e azioni
            if ($sportello_cancellato) {
                $data .= '<span class="badge bg-secondary">Cancellato</span>';
            } elseif ($row['iscritto']) {
                if ($cancellabile) {
                    $data .= '<span class="badge" style="background-color:#28a745; color:#fff;">Iscritto</span> ';
                    $data .= '<button onclick="sportelloCancellaIscrizione('
                        . $row['sportello_id'] . ', \''
                        . addslashes($row['materia_nome']) . '\', \''
                        . addslashes($row['sportello_categoria']) . '\', \''
                        . addslashes($row['sportello_argomento']) . '\', \''
                        . addslashes($row['sportello_data']) . '\', \''
                        . addslashes($row['sportello_ora']) . '\', \''
                        . addslashes($row['sportello_numero_ore']) . '\', \''
                        . addslashes($row['sportello_luogo']) . '\', \''
                        . addslashes($row['studente_cognome']) . '\', \''
                        . addslashes($row['studente_nome']) . '\', \''
                        . addslashes($row['studente_email']) . '\', \''
                        . addslashes($row['studente_classe']) . '\', \''
                        . addslashes($row['docente_cognome']) . '\', \''
                        . addslashes($row['docente_nome']) . '\', \''
                        . addslashes($row['docente_email'])
                        . '\')" class="btn btn-danger btn-sm"><span class="glyphicon glyphicon-trash"></span> Cancellati</button>';
                } else {
                    $data .= '<span class="badge" style="background-color:#28a745; color:#fff;">Iscritto</span>';
                }
            } else {
                if ($prenotabile) {
                    $data .= '<span class="badge bg-primary">Disponibile</span> ';
                    $data .= '<button onclick="sportelloIscriviti('
                        . $row['sportello_id'] . ', \''
                        . addslashes($row['materia_nome']) . '\', \''
                        . addslashes($row['sportello_categoria']) . '\', \''
                        . addslashes($row['sportello_argomento']) . '\', \''
                        . addslashes($row['sportello_data']) . '\', \''
                        . addslashes($row['sportello_ora']) . '\', \''
                        . addslashes($row['sportello_numero_ore']) . '\', \''
                        . addslashes($row['sportello_luogo']) . '\', \''
                        . addslashes($row['studente_cognome']) . '\', \''
                        . addslashes($row['studente_nome']) . '\', \''
                        . addslashes($row['studente_email']) . '\', \''
                        . addslashes($row['studente_classe']) . '\', \''
                        . addslashes($row['docente_cognome']) . '\', \''
                        . addslashes($row['docente_nome']) . '\', \''
                        . addslashes($row['docente_email'])
                        . '\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></span> Iscriviti</button>';
                } else {
                    if ($max_iscrizioni != null && $max_iscrizioni > 0 && $posti_disponibili <= 0) {
                        $data .= '<span class="badge bg-danger">Posti esauriti</span>';
                    } else {
                        $tSportello = new DateTime($dataSportelloRaw);
                        if ($tSportello <= $today) {
                            $data .= '<span class="badge bg-danger">Iscrizioni chiuse</span>';
                        } else {
                            $data .= '<span class="badge bg-info">Non ancora prenotabile</span>';
                        }
                    }
                }
            }
        }

        $data .= '</div>'; // /mt-2 text-center
        $data .= '</div>'; // /card
    }
}

$data .= '</div>';
echo $data;
?>
