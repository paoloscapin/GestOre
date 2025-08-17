<?php
/**
 *  Versione MOBILE di GestOre - Carenze
 *  Le informazioni sono mostrate in formato card invece che tabella
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$__studente_id = $studente_filtro_id;

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
        INNER JOIN classi ON carenze.id_classe = classi.id
        WHERE carenze.id_anno_scolastico = '$__anno_scolastico_corrente_id' 
          AND studente.id = '$__studente_id' 
          AND (carenze.stato = 2 OR carenze.stato = 3)";

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

$data = '<div class="cards-container">';

foreach ($resultArray as $row) {
    $materia = $row['materia'];
    $idcarenza = $row['carenza_id'];

    // Data ricezione
    $datf = new DateTime($row['carenza_validazione']);
    $data_ricezione = $datf->format('d-m-Y H:i:s');

    $note = $row['nota'];

    $data .= '<div class="card mb-3 p-2" style="border:1px solid #ddd; border-radius:10px; padding:12px; background:#fff;">';
    
    $data .= '<div><strong>Materia:</strong> ' . $materia . '</div>';
    $data .= '<div><strong>Docente:</strong> ' . $row['doc_cognome'] . ' ' . $row['doc_nome'] . '</div>';
    $data .= '<div><strong>Data ricezione:</strong> ' . $data_ricezione . '</div>';
    if (!empty($note)) {
        $data .= '<div><strong>Note:</strong> ' . $note . '</div>';
    }

    $data .= '<div class="mt-2 text-center" style="margin-top:10px;">';
    $data .= '<button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-primary btn-sm me-1">
                <span class="glyphicon glyphicon-print"></span> PDF
              </button>';
    $data .= '<button onclick="carenzaSend(\'' . $idcarenza . '\')" class="btn btn-info btn-sm">
                <span class="glyphicon glyphicon-envelope"></span> Invia
              </button>';
    $data .= '</div>';

    $data .= '</div>'; // fine card
}

$data .= '</div>';

echo $data;
?>
