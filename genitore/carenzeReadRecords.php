<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani
 *  @copyright  (C) 2025
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$__studente_id = intval($studente_filtro_id);
$anni_filtro_id = $_GET["anni_filtro_id"] ?? 0;

// ======================
// HEADER
// ======================
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
    <thead>
    <tr>
        <th class="text-center col-md-2">Materia</th>						
        <th class="text-center col-md-2">Docente</th>
        <th class="text-center col-md-1">Data ricezione</th>
        <th class="text-center col-md-3">Note</th>
        <th class="text-center col-md-1">Programma Carenza</th>
        <th class="text-center col-md-3">Esito carenza</th>
    </tr>
    </thead><tbody>';

// ======================
// QUERY PRINCIPALE
// ======================
$query = "
    SELECT
        c.id AS carenza_id,
        c.id_studente,
        c.id_materia,
        c.id_docente,
        c.id_anno_scolastico,
        c.data_invio,
        c.nota_docente AS nota,
        d.cognome AS doc_cognome,
        d.nome AS doc_nome,
        d.id AS doc_id,
        m.nome AS materia
    FROM carenze c
    INNER JOIN docente d ON c.id_docente = d.id
    INNER JOIN studente s ON c.id_studente = s.id
    INNER JOIN materia m ON c.id_materia = m.id
    WHERE s.id = '$__studente_id'
      AND (c.stato = 2 OR c.stato = 3)";

if ($anni_filtro_id > 0) {
    $query .= " AND c.id_anno_scolastico = " . intval($anni_filtro_id);
}

$query .= " ORDER BY m.nome ASC";
$carenze = dbGetAll($query) ?: [];

// ======================
// CICLO CARICAMENTO
// ======================
foreach ($carenze as $row) {
    $idcarenza = intval($row['carenza_id']);
    $materia = htmlspecialchars($row['materia']);
    if ($row['doc_id'] == 0) {
        $docente = 'Studente esterno';
    } else {
        $docente = htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome']);
    }
    $note = htmlspecialchars($row['nota']);
    $anno_carenza = intval($row['id_anno_scolastico']);
    $data_ricezione = (new DateTime($row['data_invio']))->format('d-m-Y H:i');

    $data .= "<tr>
        <td align='center'>{$materia}</td>
        <td align='center'>{$docente}</td>
        <td align='center'>{$data_ricezione}</td>
        <td align='center'>{$note}</td>
        <td align='center'>
            <button onclick=\"carenzaPrint('{$idcarenza}','{$anno_carenza}')\" 
                    class='btn btn-primary btn-xs' 
                    data-toggle='tooltip' title='Scarica il PDF del programma della carenza'>
                <span class='glyphicon glyphicon-print'></span>
            </button>
        </td>
        <td align='center'>";

    // ======================
    // VERIFICA RECUPERO IN ITINERE
    // ======================
    $itinere = dbGetValue("
        SELECT COALESCE(MAX(co.in_itinere),0)
        FROM carenze car
        INNER JOIN corso co ON co.id_materia = car.id_materia
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = car.id_studente
        WHERE car.id = {$idcarenza}
    ");

    $badgeInItinere = ($itinere == 1)
        ? '<span class="label label-info" style="margin-right:4px;">Recupero in itinere</span>'
        : '';

    // ======================
    // RECUPERO SESSIONI D’ESAME (1° e 2° TENTATIVO)
    // ======================
    $esami = dbGetAll("
        SELECT 
            ced.id              AS id_esame_data,
            COALESCE(ced.tentativo,1) AS tentativo,
            ced.data_inizio_esame,
            ced.aula,
            ced.firmato,
            ce.presente,
            ce.recuperato
        FROM carenze car
        INNER JOIN corso_esiti ce ON ce.id_studente = car.id_studente
        INNER JOIN corso_esami_date ced ON ced.id = ce.id_esame_data
        INNER JOIN corso co ON co.id = ce.id_corso AND co.id_materia = car.id_materia
        WHERE car.id = {$idcarenza}
        ORDER BY ced.tentativo ASC
    ");

    if (!$esami || count($esami) == 0) {
        $data .= '<span class="label label-warning">Corso non ancora iniziato</span>';
    } else {
        $numTentativi = count($esami);

        foreach ($esami as $i => $es) {
            $tentativo = intval($es['tentativo']);
            $firmato = intval($es['firmato']);
            $tooltip = '';

            if (!empty($es['data_inizio_esame'])) {
                $tooltip = "Esame il " . (new DateTime($es['data_inizio_esame']))->format('d-m-Y H:i');
                if (!empty($es['aula'])) $tooltip .= " in aula " . htmlspecialchars($es['aula']);
            }

            // Etichetta tentativo → solo se più di uno
            $labelTent = ($numTentativi > 1)
                ? (($tentativo == 1) ? '<strong>Primo tentativo:</strong> ' : '<strong>Secondo tentativo:</strong> ')
                : '';

            $data .= "<div style='margin-bottom:4px;'>{$labelTent}{$badgeInItinere}";

            if ($firmato == 0) {
                if ($tentativo == 2) {
                    $data .= '<span class="label label-info" style="margin-right:4px;">Iscritto</span>';
                }
                $data .= '<span class="label label-warning">In attesa esito</span>';
            } else {
                // Firmato → mostra esito
                if ($es['presente']) {
                    $data .= '<span class="label label-primary" title="' . $tooltip . '">Presente</span> ';
                } else {
                    $data .= '<span class="label label-default" title="' . $tooltip . '">Assente</span> ';
                }

                if ($es['recuperato']) {
                    $data .= '<span class="label label-success" title="' . $tooltip . '">Recuperato</span>';
                } else {
                    $data .= '<span class="label label-danger" title="' . $tooltip . '">Non recuperato</span>';
                }
            }

            $data .= '</div>';
        }
    }

    $data .= "</td></tr>";
}

$data .= '</tbody></table></div>';
echo $data;
