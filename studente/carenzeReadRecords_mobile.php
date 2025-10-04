<?php
/**
 * Versione MOBILE di GestOre - Carenze con esiti
 * Aggiornata per mostrare PRIMO e SECONDO tentativo con layout compatto
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$anni_filtro_id = $_GET["anni_filtro_id"] ?? 0;

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

echo '<div id="carenze_mobile_container" class="cards-container">';

foreach ($carenze as $row) {
    $idcarenza   = intval($row['carenza_id']);
    $idStudente  = intval($row['id_studente']);
    $idMateria   = intval($row['id_materia']);
    $materia     = htmlspecialchars($row['materia']);
    $docente     = htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome']);
    $note        = htmlspecialchars($row['nota']);
    $data_ricezione = (new DateTime($row['data_invio']))->format('d-m-Y H:i');

    echo '<div class="card mb-3 p-3 shadow-sm" style="border-radius:12px; background:#fff;">';
    echo "<div><strong>Materia:</strong> {$materia}</div>";
    echo "<div><strong>Docente:</strong> {$docente}</div>";
    echo "<div><strong>Data ricezione:</strong> {$data_ricezione}</div>";
    if (!empty($note)) echo "<div><strong>Note:</strong> {$note}</div>";

    // ===========================
    // RECUPERO IN ITINERE
    // ===========================
    $itinere = dbGetValue("
        SELECT COALESCE(MAX(co.in_itinere),0)
        FROM carenze car
        INNER JOIN corso co ON co.id_materia = car.id_materia
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = car.id_studente
        WHERE car.id = {$idcarenza}
    ");
    $badgeInItinere = (intval($itinere) === 1)
        ? '<span class="label label-info" style="margin-right:4px;">Recupero in itinere</span>'
        : '';

    // ===========================
    // CORSI ASSOCIATI ALLA CARENZA
    // ===========================
    $corsi = dbGetAll("
        SELECT co.id
        FROM corso co
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id
        WHERE ci.id_studente = {$idStudente}
          AND co.id_materia = {$idMateria}
    ") ?: [];

    echo '<div style="margin-top:10px;"><strong>Esiti:</strong><br>';

    if (count($corsi) === 0) {
        echo '<span class="label label-warning">Nessuna sessione di esame</span>';
        echo '</div>';
        goto AZIONI;
    }

    $corsoIds = implode(',', array_map(fn($r) => intval($r['id']), $corsi));

    // ===========================
    // SESSIONI D’ESAME (tentativi)
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

    if (count($sessioni) === 0) {
        echo '<span class="label label-warning">Corso non ancora iniziato</span>';
        echo '</div>';
        goto AZIONI;
    }

    // Funzione per render badge tentativo (compatti)
    $renderTentativo = function($label, $s, $extraBadge = '') {
        $firmato = intval($s['firmato']) === 1;
        $tooltip = '';
        if (!empty($s['data_inizio_esame'])) {
            $tooltip = "Esame il " . (new DateTime($s['data_inizio_esame']))->format('d-m-Y H:i');
            if (!empty($s['aula'])) $tooltip .= " in aula " . htmlspecialchars($s['aula']);
        }

        $html = '<div style="margin-bottom:4px;">';
        if ($label !== '') $html .= "<strong>{$label}:</strong> ";

        $html .= $extraBadge;

        if (!$firmato) {
            $html .= '<span class="label label-warning">In attesa esito</span>';
        } else {
            if ($s['presente'] !== null) {
                $html .= ($s['presente']
                    ? '<span class="label label-primary" title="'.$tooltip.'">Presente</span> '
                    : '<span class="label label-default" title="'.$tooltip.'">Assente</span> ');
                $html .= ($s['recuperato']
                    ? '<span class="label label-success" title="'.$tooltip.'">Recuperato</span>'
                    : '<span class="label label-danger" title="'.$tooltip.'">Non recuperato</span>');
            } else {
                $html .= '<span class="label label-default">Esito non registrato</span>';
            }
        }
        $html .= '</div>';
        return $html;
    };

    $numTentativi = count($sessioni);

    // Primo tentativo
    $primo = $sessioni[0];
    $labelPrimo = ($numTentativi > 1) ? 'Primo tentativo' : '';
    echo $renderTentativo($labelPrimo, $primo, $badgeInItinere);

    // Secondo tentativo (se presente)
    if ($numTentativi > 1) {
        $secondo = $sessioni[1];
        $extraBadge = '';

        if (intval($secondo['firmato']) === 0) {
            $extraBadge = '<span class="label label-info" style="margin-right:4px;">Iscritto</span>';
        }

        echo $renderTentativo('Secondo tentativo', $secondo, $extraBadge);
    }

    echo '</div>'; // fine sezione esiti

    // ===========================
    // PULSANTI AZIONI
    // ===========================
    AZIONI:
    echo '<div class="mt-2 text-center" style="margin-top:10px;">';
    echo '<button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-primary btn-sm me-1">';
    echo '<span class="glyphicon glyphicon-print"></span> PDF</button> ';
    echo '<button onclick="carenzaSend(\'' . $idcarenza . '\')" class="btn btn-info btn-sm">';
    echo '<span class="glyphicon glyphicon-envelope"></span> Invia</button>';
    echo '</div>';

    echo '</div>'; // fine card
}

echo '</div>';
