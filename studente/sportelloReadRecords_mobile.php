<?php
/**
 *  Versione MOBILE di GestOre - Sportelli
 *  Le informazioni sono mostrate in formato card invece che tabella
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$ancheCancellati = $_GET["ancheCancellati"];
$soloNuovi = $_GET["soloNuovi"];
$soloIscritto = $_GET["soloIscritto"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$materia_filtro_id = $_GET["materia_filtro_id"];
$classe_filtro_id = $_GET["classe_filtro_id"];
$categoria_filtro_id = $_GET["categoria_filtro_id"];

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

if ($classe_filtro_id > 0) $query .= " AND sportello.classe_id = $classe_filtro_id ";
if ($materia_filtro_id > 0) $query .= " AND sportello.materia_id = $materia_filtro_id ";
if ($docente_filtro_id > 0) $query .= " AND sportello.docente_id = $docente_filtro_id ";
if ($categoria_filtro_id > 0) $query .= " AND sportello.categoria = '" . $nome_categoria . "' ";
if (!$ancheCancellati) $query .= " AND NOT sportello.cancellato ";
if ($soloNuovi) $query .= " AND sportello.data >= CURDATE() ";

$query .= " ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC, docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

$data = '<div class="cards-container">';

foreach ($resultArray as $row) {
    if ((($soloIscritto == 1) && ($row["iscritto"] == 1)) || ($soloIscritto == 0)) {

        $sportello_id = $row['sportello_id'];
        $sportello_categoria = $row['sportello_categoria'];
        $todayDate = new DateTime("today");
        $sportelloDate = new DateTime($row['sportello_data']);
        $passato = ($sportelloDate < $todayDate);

        $oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
        $dataSportello = utf8_encode(strftime("%d %B %Y", strtotime($row['sportello_data'])));
        setlocale(LC_TIME, $oldLocale);

        $max_iscrizioni = $row['sportello_max_iscrizioni'];
        $posti_disponibili = $max_iscrizioni - $row['numero_studenti'];

        $luogo_or_online = $row['sportello_online'] ? '<span class="badge bg-danger">Online</span>' : $row['sportello_luogo'];

        $data .= '<div class="card mb-3 p-2" style="border:1px solid #ddd; border-radius:10px; background:#fff; padding:12px;">';
        
        if ($row['sportello_cancellato']) {
            $data .= '<div class="badge bg-secondary">Sportello annullato</div>';
        } 

        $data .= '<div><strong>Categoria:</strong> ' . $sportello_categoria . '</div>';
        $data .= '<div><strong>Data:</strong> ' . $dataSportello . ' - ' . $row['sportello_ora'] . ' (' . $row['sportello_numero_ore'] . ($row['sportello_numero_ore'] > 1 ? ' ore)' : ' ora)') . '</div>';
        $data .= '<div><strong>Materia:</strong> ' . $row['materia_nome'] . '</div>';
        $data .= '<div><strong>Docente:</strong> ' . $row['docente_nome'] . ' ' . $row['docente_cognome'] . '</div>';

        if ($row['sportello_argomento'] != "") $data .= '<div><strong>Argomento:</strong> ' . $row['sportello_argomento'] . '</div>';
        elseif ($row['argomento'] != "") $data .= '<div><strong>Argomento studente:</strong> ' . $row['argomento'] . '</div>';

        $data .= '<div><strong>Luogo:</strong> ' . $luogo_or_online . '</div>';
        $data .= '<div><strong>Classe:</strong> ' . $row['sportello_classe'] . '</div>';
        $data .= '<div><strong>Posti disponibili:</strong> ' . $posti_disponibili . '</div>';

        $data .= '<div class="mt-2 text-center">';
        if ($passato) {
            if ($row['presente']) $data .= '<span class="badge" style="background-color:#28a745; color:#fff;">Presente</span>';
            elseif ($row['iscritto']) $data .= '<span class="badge bg-danger">Assente</span>';
            else $data .= '<span class="badge bg-secondary">Non iscritto</span>';
        } else {
            if ($row['sportello_cancellato']) $data .= '<span class="badge bg-secondary">Cancellato</span>';
            elseif ($row['iscritto']) {
                $data .= '<span class="badge" style="background-color:#28a745; color:#fff;">Iscritto</span> 	';
                $data .= '<button onclick="sportelloCancellaIscrizione(' . $row['sportello_id'] . ', \'' . addslashes($row['materia_nome']) . '\', \'' . addslashes($row['sportello_categoria']) . '\', \'' . addslashes($row['sportello_argomento']) . '\',\'' . addslashes($row['sportello_data']) . '\',\'' . addslashes($row['sportello_ora']) . '\',\'' . addslashes($row['sportello_numero_ore']) . '\',\'' . addslashes($row['sportello_luogo']) . '\',\'' . addslashes($row['studente_cognome']) . '\',\'' . addslashes($row['studente_nome']) . '\',\'' . addslashes($row['studente_email']) . '\',\'' . addslashes($row['studente_classe']) . '\',\'' . addslashes($row['docente_cognome']) . '\',\'' . addslashes($row['docente_nome']) . '\',\'' . addslashes($row['docente_email']) . '\')" class="btn btn-danger btn-sm"><span class="glyphicon glyphicon-trash"></span> Cancellati</button>';
            } elseif ($posti_disponibili > 0) {
                $data .= '<button onclick="sportelloIscriviti(' . $row['sportello_id'] . ', \'' . addslashes($row['materia_nome']) . '\', \'' . addslashes($row['sportello_categoria']) . '\', \'' . addslashes($row['sportello_argomento']) . '\',\'' . addslashes($row['sportello_data']) . '\',\'' . addslashes($row['sportello_ora']) . '\',\'' . addslashes($row['sportello_numero_ore']) . '\',\'' . addslashes($row['sportello_luogo']) . '\',\'' . addslashes($row['studente_cognome']) . '\',\'' . addslashes($row['studente_nome']) . '\',\'' . addslashes($row['studente_email']) . '\',\'' . addslashes($row['studente_classe']) . '\',\'' . addslashes($row['docente_cognome']) . '\',\'' . addslashes($row['docente_nome']) . '\',\'' . addslashes($row['docente_email']) . '\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></span> Iscriviti</button>';
            } else {
                $data .= '<span class="badge bg-danger">Posti esauriti</span>';
            }
        }
        $data .= '</div>';

        $data .= '</div>';
    }
}

$data .= '</div>';
echo $data;
?>
