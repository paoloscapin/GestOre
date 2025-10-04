<?php
/**
 *  Versione MOBILE di GestOre - Carenze lato genitore con esiti (1° e 2° tentativo)
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$__studente_id = intval($studente_filtro_id);

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
      AND (c.stato = 2 OR c.stato = 3)
      AND (c.id_anno_scolastico = '$__anno_scolastico_corrente_id' OR c.id_anno_scolastico = '$__anno_scolastico_scorso_id')
    ORDER BY m.nome ASC";

$carenze = dbGetAll($query) ?? [];

echo '<div class="cards-container">';

foreach ($carenze as $row) {
    $idcarenza = intval($row['carenza_id']);
    $materia = htmlspecialchars($row['materia']);
    $docente = htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome']);
    $note = htmlspecialchars($row['nota']);
    $data_ricezione = $row['data_invio'] ? (new DateTime($row['data_invio']))->format('d-m-Y H:i') : '';

    echo '<div class="card mb-3 p-2" style="border:1px solid #ddd; border-radius:10px; padding:12px; background:#fff;">';
    echo "<div><strong>Materia:</strong> {$materia}</div>";
    echo "<div><strong>Docente:</strong> {$docente}</div>";
    echo "<div><strong>Data ricezione:</strong> {$data_ricezione}</div>";
    if (!empty($note)) {
        echo "<div><strong>Note:</strong> {$note}</div>";
    }

    echo '<div style="margin-top:8px; text-align:center;"><div style="margin-bottom:4px;"><strong>Esito:</strong></div>';

    // Recupero in itinere
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

    // Esami schedulati (1° o 2°)
    $esamiSched = dbGetAll("
        SELECT 
            ced.id AS id_esame_data,
            COALESCE(ced.tentativo,1) AS tentativo,
            ced.data_inizio_esame,
            ced.aula,
            ced.firmato,
            ce.presente,
            ce.recuperato
        FROM carenze car
        INNER JOIN corso_esiti ce ON ce.id_studente = car.id_studente
        INNER JOIN corso co ON co.id = ce.id_corso AND co.id_materia = car.id_materia
        INNER JOIN corso_esami_date ced ON ced.id = ce.id_esame_data
        WHERE car.id = {$idcarenza}
        ORDER BY ced.tentativo ASC
    ") ?: [];

    // Secondo tentativo iscritto ma non schedulato
    $secondoIscritto = dbGetValue("
        SELECT COUNT(*)
        FROM carenze car
        INNER JOIN corso_esiti ce ON ce.id_studente = car.id_studente
        INNER JOIN corso co ON co.id = ce.id_corso AND co.id_materia = car.id_materia
        WHERE car.id = {$idcarenza}
          AND ce.id_esame_data IS NULL
    ");

    $byTent = [];
    foreach ($esamiSched as $es) {
        $byTent[intval($es['tentativo'])] = $es;
    }
    $numTent = count($byTent) + ($secondoIscritto ? 1 : 0);

    // ---- Primo tentativo ----
    if (isset($byTent[1])) {
        $es = $byTent[1];
        $labelTent = ($numTent > 1) ? '<strong>Primo tentativo:</strong> ' : '';
        $tooltip = '';
        if (!empty($es['data_inizio_esame'])) {
            $tooltip = "Esame il " . (new DateTime($es['data_inizio_esame']))->format('d-m-Y H:i');
            if (!empty($es['aula'])) $tooltip .= " in aula " . htmlspecialchars($es['aula']);
        }

        echo "<div style='margin-bottom:4px;'>{$labelTent}{$badgeInItinere}";
        if (intval($es['firmato']) === 0) {
            echo '<span class="label label-warning">In attesa esito</span>';
        } else {
            if ($es['presente'])
                echo '<span class="label label-primary" title="' . $tooltip . '">Presente</span> ';
            else
                echo '<span class="label label-default" title="' . $tooltip . '">Assente</span> ';

            if ($es['recuperato'])
                echo '<span class="label label-success" title="' . $tooltip . '">Recuperato</span>';
            else
                echo '<span class="label label-danger" title="' . $tooltip . '">Non recuperato</span>';
        }
        echo '</div>';
    }

    // ---- Secondo tentativo ----
    if (isset($byTent[2]) || $secondoIscritto) {
        $labelTent2 = ($numTent > 1) ? '<strong>Secondo tentativo:</strong> ' : '';
        echo "<div style='margin-bottom:4px;'>{$labelTent2}";
        if (isset($byTent[2])) {
            $es2 = $byTent[2];
            $tooltip2 = '';
            if (!empty($es2['data_inizio_esame'])) {
                $tooltip2 = "Esame il " . (new DateTime($es2['data_inizio_esame']))->format('d-m-Y H:i');
                if (!empty($es2['aula'])) $tooltip2 .= " in aula " . htmlspecialchars($es2['aula']);
            }

            if (intval($es2['firmato']) === 0) {
                echo '<span class="label label-info" style="margin-right:4px;">Iscritto</span>';
                echo '<span class="label label-warning">In attesa esito</span>';
            } else {
                if ($es2['presente'])
                    echo '<span class="label label-primary" title="' . $tooltip2 . '">Presente</span> ';
                else
                    echo '<span class="label label-default" title="' . $tooltip2 . '">Assente</span> ';

                if ($es2['recuperato'])
                    echo '<span class="label label-success" title="' . $tooltip2 . '">Recuperato</span>';
                else
                    echo '<span class="label label-danger" title="' . $tooltip2 . '">Non recuperato</span>';
            }
        } else {
            echo '<span class="label label-info" style="margin-right:4px;">Iscritto</span>';
            echo '<span class="label label-warning">In attesa esito</span>';
        }
        echo '</div>';
    }

    // Nessun esame
    if (empty($byTent) && !$secondoIscritto) {
        echo '<span class="label label-warning">Corso non ancora iniziato</span>';
    }

    echo '</div>';

    // Pulsante PDF
    echo '<div class="mt-2 text-center" style="margin-top:10px;">';
    echo '<button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-primary btn-sm">';
    echo '<span class="glyphicon glyphicon-print"></span> PDF</button>';
    echo '</div>';

    echo '</div>'; // fine card
}

echo '</div>';
?>
