<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$__studente_id = $studente_filtro_id;
$anni_filtro_id = $_GET["anni_filtro_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
    <thead>
    <tr>
        <th class="text-center col-md-2">Materia</th>						
        <th class="text-center col-md-2">Docente</th>
        <th class="text-center col-md-1">Data ricezione</th>
        <th class="text-center col-md-3">Note</th>
        <th class="text-center col-md-1">Programma Carenza</th>
        <th class="text-center col-md-2">Esito carenza</th>
    </tr>
    </thead>';

$query = "	SELECT
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
            INNER JOIN docente docente
                ON carenze.id_docente = docente.id
            INNER JOIN studente studente
                ON carenze.id_studente = studente.id
            INNER JOIN materia materia
                ON carenze.id_materia = materia.id
            INNER JOIN classi classi
                ON carenze.id_classe = classi.id";

if ($anni_filtro_id > 0) {
    $query .= " WHERE carenze.id_anno_scolastico=" . $anni_filtro_id . " 
                 AND studente.id='$__studente_id' 
                 AND (carenze.stato=2 OR carenze.stato=3)";
} else {
    $query .= " WHERE studente.id='$__studente_id' 
                 AND (carenze.stato=2 OR carenze.stato=3)";
}

$resultArray = dbGetAll($query);
if ($resultArray == null) {
    $resultArray = [];
}

foreach ($resultArray as $row) {
    $materia = $row['materia'];
    $anno_carenza = $row['carenza_id_anno_scolastico'];
    $idcarenza = $row['carenza_id'];

    // Data ricezione
    $datf = new DateTime($row['carenza_invio']);
    $data_ricezione  = $datf->format('d-m-Y H:i:s');
    $note = $row['nota'];

    $data .= '<tr>
        <td align="center">' . $materia . '</td>
        <td align="center">' . $row['doc_cognome'] . ' ' . $row['doc_nome'] . '</td>
        <td align="center">' . $data_ricezione . '</td>
        <td align="center">' . $note . '</td>
        <td align="center">
            <button onclick="carenzaPrint(\'' . $idcarenza . '\',\'' . $anno_carenza . '\')" 
                    class="btn btn-primary btn-xs" 
                    data-toggle="tooltip" title="Scarica il PDF del programma della carenza">
                <span class="glyphicon glyphicon-print"></span>
            </button>
        </td>';

    // --- Colonna Esito carenza ---
    $data .= '<td align="center">';

    // Verifico se era un recupero in itinere
    $query = "SELECT co.in_itinere AS in_itinere
                FROM carenze car
                INNER JOIN corso co ON co.id_materia = car.id_materia 
                INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = car.id_studente
                WHERE car.id = $idcarenza";
    $itinere = dbGetValue($query);

    // Recupero eventuale esito esame
    $query = "SELECT 
            car.id,
            car.id_studente AS studente_id,
            ce.presente AS presente,
            ce.recuperato AS recuperato,
            ced.data_inizio_esame AS data_inizio_esame,
            ced.data_fine_esame AS data_fine_esame,
            ced.firmato AS firmato,
            ced.aula AS aula_esame,
            m.nome AS materia
        FROM carenze car
        INNER JOIN corso_esiti ce 
            ON ce.id_studente = car.id_studente
        INNER JOIN corso c 
            ON c.id = ce.id_corso
        INNER JOIN materia m 
            ON m.id = car.id_materia 
        AND m.id = c.id_materia      -- 🔑 questo lega la materia del corso alla materia della carenza
        INNER JOIN corso_esami_date ced 
            ON ced.id_corso = ce.id_corso
        WHERE car.id = $idcarenza";
    $esito = dbGetFirst($query);

    $firmato = $esito && $esito['firmato'] == 1;

    if ($itinere) {
        $data .= '<span class="label label-info" title="Recupero in itinere della carenza entro il 31-10">in itinere</span>&ensp;';
    }

    if (!$firmato) {
        $data .= '<span class="label label-warning" title="Esame non ancora svolto o non registrato">In attesa esito esame</span>&ensp;';
    } else {
        $tooltip = "Esame il " . (new DateTime($esito['data_inizio_esame']))->format('d-m-Y H:i') . 
                   " in aula " . $esito['aula_esame'];

        if ($esito['presente']) {
            $data .= '<span class="label label-primary" title="' . $tooltip . '">presente</span>&ensp;';
        } else {
            $data .= '<span class="label label-default" title="' . $tooltip . '">assente</span>&ensp;';
        }

        if ($esito['recuperato']) {
            $data .= '<span class="label label-success" title="' . $tooltip . '">recuperato</span>';
        } else {
            $data .= '<span class="label label-danger" title="' . $tooltip . '">non recuperato</span>';
        }
    }

    $data .= '</td>';
    $data .= '</tr>';
}

$data .= '</table></div>';

echo $data;
