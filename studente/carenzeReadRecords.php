<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani
 *  @copyright  (C) 2025
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$anni_filtro_id = isset($_GET["anni_filtro_id"]) ? intval($_GET["anni_filtro_id"]) : 0;

// ======================
// HEADER TABELLA
// ======================
$data = '
<div class="table-wrapper table-responsive">
<table class="table table-bordered table-striped table-green">
<thead>
<tr>
    <th class="text-center col-md-2">Materia</th>						
    <th class="text-center col-md-2">Docente</th>
    <th class="text-center col-md-1">Data ricezione</th>
    <th class="text-center col-md-3">Note</th>
    <th class="text-center col-md-1">Programma Carenza</th>
    <th class="text-center col-md-3">Esiti</th>
</tr>
</thead>
<tbody>';

// ======================
// QUERY PRINCIPALE: carenze dello studente
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
        m.nome AS materia
    FROM carenze c
    INNER JOIN docente d ON c.id_docente = d.id
    INNER JOIN studente s ON c.id_studente = s.id
    INNER JOIN materia m ON c.id_materia = m.id
    WHERE s.id = '$__studente_id'
      AND (c.stato = 2 OR c.stato = 3)";

if ($anni_filtro_id > 0) {
    $query .= " AND c.id_anno_scolastico = " . $anni_filtro_id;
}

$query .= " ORDER BY m.nome ASC";
$carenze = dbGetAll($query) ?: [];

// ======================
// COSTRUZIONE RIGHE
// ======================
foreach ($carenze as $row) {
    $idcarenza   = intval($row['carenza_id']);
    $idStudente  = intval($row['id_studente']);
    $idMateria   = intval($row['id_materia']);

    $materia = htmlspecialchars($row['materia']);
    $docente = htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome']);
    $note    = htmlspecialchars($row['nota']);
    $data_ricezione = (new DateTime($row['data_invio']))->format('d-m-Y H:i');

    $data .= "<tr>
        <td align='center'>{$materia}</td>
        <td align='center'>{$docente}</td>
        <td align='center'>{$data_ricezione}</td>
        <td align='center'>{$note}</td>
        <td align='center'>
            <button onclick=\"carenzaPrint('{$idcarenza}')\" class='btn btn-primary btn-xs' data-toggle='tooltip' title='Scarica il PDF del programma'>
                <span class='glyphicon glyphicon-print'></span>
            </button>
            <button onclick=\"carenzaSend('{$idcarenza}')\" class='btn btn-info btn-xs' data-toggle='tooltip' title='Invia via mail'>
                <span class='glyphicon glyphicon-envelope'></span>
            </button>
        </td>
        <td align='center'>";

    // ===========================
    // Badge "Recupero in itinere"
    // ===========================
    $itinere = dbGetValue("
        SELECT COALESCE(MAX(co.in_itinere),0)
        FROM carenze car
        INNER JOIN corso co ON co.id_materia = car.id_materia
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = car.id_studente
        WHERE car.id = {$idcarenza}
    ");

    $badgeInItinere = '';
    if (intval($itinere) === 1) {
        $badgeInItinere = '<span class="label label-info" data-toggle="tooltip" title="Recupero della carenza durante il corso">Recupero in itinere</span>&ensp;';
    }

    // ===========================
    // Recupero i corsi della materia a cui lo studente è iscritto
    // ===========================
    $corsi = dbGetAll("
        SELECT co.id
        FROM corso co
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id
        WHERE ci.id_studente = {$idStudente}
          AND co.id_materia = {$idMateria}
    ") ?: [];

    if (count($corsi) === 0) {
        $data .= '<span class="label label-warning" data-toggle="tooltip" title="Nessun corso di recupero trovato">Nessuna sessione di esame</span>';
        $data .= "</td></tr>";
        continue;
    }

    $corsoIds = implode(',', array_map(fn($r) => intval($r['id']), $corsi));

    // ===========================
    // Sessioni d’esame per i corsi trovati
    // ===========================
    $sessioni = dbGetAll("
        SELECT 
            ced.id              AS id_esame_data,
            COALESCE(ced.tentativo, 1) AS tentativo,
            ced.data_inizio_esame,
            ced.data_fine_esame,
            ced.aula,
            ced.firmato,
            ce.presente,
            ce.recuperato
        FROM corso_esami_date ced
        LEFT JOIN corso_esiti ce 
               ON ce.id_esame_data = ced.id
              AND ce.id_studente = {$idStudente}
        WHERE ced.id_corso IN ($corsoIds)
        ORDER BY ced.tentativo ASC, ced.data_inizio_esame ASC
    ") ?: [];

    $numTentativi = count($sessioni);
    if ($numTentativi === 0) {
        $data .= '<span class="label label-warning">Corso non ancora iniziato</span>';
        $data .= "</td></tr>";
        continue;
    }

    // ================
    // Render tentativo (badge sulla stessa riga)
    // ================
    $renderTentativo = function($label, $s, $extraBadge = '') {
        $firmato = intval($s['firmato']) === 1;
        $tooltip = '';
        if (!empty($s['data_inizio_esame'])) {
            $tooltip = "Esame tenuto il " . (new DateTime($s['data_inizio_esame']))->format('d-m-Y H:i');
            if (!empty($s['aula'])) $tooltip .= " in aula " . htmlspecialchars($s['aula']);
        }

        $html = '<div style="margin-bottom:4px;">';
        if ($label !== '') $html .= "<strong>{$label}: </strong>";

        $html .= $extraBadge;

        if (!$firmato) {
            $html .= '<span class="label label-warning" data-toggle="tooltip" title="In attesa esito esame">In attesa esito</span>';
        } else {
            if ($s['presente'] !== null) {
                $html .= ($s['presente']
                    ? '<span class="label label-primary" data-toggle="tooltip" title="'.$tooltip.'">Presente</span>&ensp;'
                    : '<span class="label label-default" data-toggle="tooltip" title="'.$tooltip.'">Assente</span>&ensp;');
                $html .= ($s['recuperato']
                    ? '<span class="label label-success" data-toggle="tooltip" title="'.$tooltip.'">Recuperato</span>'
                    : '<span class="label label-danger" data-toggle="tooltip" title="'.$tooltip.'">Non recuperato</span>');
            } else {
                $html .= '<span class="label label-default" data-toggle="tooltip" title="Esito non registrato per lo studente">Esito non registrato</span>';
            }
        }

        $html .= '</div>';
        return $html;
    };

    // ================
    // PRIMO TENTATIVO
    // ================
    $primo = $sessioni[0];
    $labelPrimo = ($numTentativi > 1) ? 'Primo tentativo' : '';
    $data .= $renderTentativo($labelPrimo, $primo, $badgeInItinere);

    // ================
    // SECONDO TENTATIVO
    // ================
    if ($numTentativi > 1) {
        $secondo = $sessioni[1];
        $extraBadge = '';

        if (intval($secondo['firmato']) === 0) {
            $extraBadge = '
                <span class="label label-info" data-toggle="tooltip" title="Iscritto al secondo tentativo">Iscritto</span>&ensp;';
        }

        $data .= $renderTentativo('Secondo tentativo', $secondo, $extraBadge);
    }

    $data .= "</td></tr>";
}

$data .= '</tbody></table></div>';

echo $data;
