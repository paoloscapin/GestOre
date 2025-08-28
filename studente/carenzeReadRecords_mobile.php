<?php
/**
 * Versione MOBILE di GestOre - Carenze
 * Le informazioni sono mostrate in formato card invece che tabella
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$anni_filtro_id = $_GET["anni_filtro_id"] ?? 0;

// Query per recuperare le carenze dello studente
$query = "SELECT
            carenze.id AS carenza_id,
            carenze.id_studente AS carenza_id_studente,
            carenze.id_materia AS carenza_id_materia,
            carenze.id_classe AS carenza_id_classe,
            carenze.id_docente AS carenza_id_docente,
            carenze.id_anno_scolastico AS carenza_id_anno_scolastico,
            carenze.stato AS carenza_stato,
            carenze.data_inserimento AS carenza_inserimento,
            carenze.data_validazione AS carenza_validazione,
            carenze.data_invio AS carenza_invio,
            carenze.nota_docente AS nota,
            docente.cognome AS doc_cognome,
            docente.nome AS doc_nome,
            materia.nome AS materia
        FROM carenze
        INNER JOIN docente ON carenze.id_docente = docente.id
        INNER JOIN studente ON carenze.id_studente = studente.id
        INNER JOIN materia ON carenze.id_materia = materia.id
        INNER JOIN classi ON carenze.id_classe = classi.id";

if ($anni_filtro_id > 0) {
    $query .= " WHERE carenze.id_anno_scolastico=" . intval($anni_filtro_id) . 
              " AND studente.id='" . intval($__studente_id) . "' AND (carenze.stato=2 OR carenze.stato=3)";
} else {
    $query .= " WHERE studente.id='" . intval($__studente_id) . "' AND (carenze.stato=2 OR carenze.stato=3)";
}

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

echo '<div id="carenze_mobile_container" class="cards-container">';

foreach ($resultArray as $row) {
    $materia = htmlspecialchars($row['materia']);
    $docente = htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome']);
    $note = htmlspecialchars($row['nota']);
    $idcarenza = $row['carenza_id'];

    // Data ricezione, fallback se null
    $data_ricezione = '';
    if (!empty($row['carenza_validazione'])) {
        $datf = new DateTime($row['carenza_validazione']);
        $data_ricezione = $datf->format('d-m-Y H:i:s');
    }

    echo '<div class="card mb-3 p-2" style="border:1px solid #ddd; border-radius:10px; padding:12px; background:#fff;">';
    echo '<div><strong>Materia:</strong> ' . $materia . '</div>';
    echo '<div><strong>Docente:</strong> ' . $docente . '</div>';
    echo '<div><strong>Data ricezione:</strong> ' . $data_ricezione . '</div>';
    if (!empty($note)) {
        echo '<div><strong>Note:</strong> ' . $note . '</div>';
    }
    echo '<div class="mt-2 text-center" style="margin-top:10px;">';
    echo '<button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-primary btn-sm me-1">';
    echo '<span class="glyphicon glyphicon-print"></span> PDF</button> ';
    echo '<button onclick="carenzaSend(\'' . $idcarenza . '\')" class="btn btn-info btn-sm">';
    echo '<span class="glyphicon glyphicon-envelope"></span> Invia</button>';
    echo '</div>';
    echo '</div>'; // fine card
}

echo '</div>';
?>
