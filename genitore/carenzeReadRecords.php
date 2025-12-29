<?php
/**
 * Carenze (genitore) - esiti corretti con gestione:
 * - 1ª sessione (corso carenza_sessione=1) => esame tentativo=1
 * - eventuale corso collegato (corso_carenze_seconda) => esame tentativo=1
 *   label: "Recupero assenza" se titolo contiene "recupero assenza", altrimenti "Seconda sessione"
 * NOTE: tolta "Data ricezione" (non interessa a genitore)
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$studente_filtro_id = $_GET["studente_filtro_id"] ?? 0;
$__studente_id = intval($studente_filtro_id);

$anni_filtro_id = isset($_GET["anni_filtro_id"]) ? intval($_GET["anni_filtro_id"]) : 0;
$anno_corsi_id  = intval($__anno_scolastico_corrente_id); // anno dei corsi (corrente)

// ======================
// HEADER TABELLA (senza Data ricezione)
// ======================
$data = '
<div class="table-wrapper table-responsive">
<table class="table table-bordered table-striped table-green">
<thead>
<tr>
    <th class="text-center col-md-2">Materia</th>
    <th class="text-center col-md-2">Docente</th>
    <th class="text-center col-md-4">Note</th>
    <th class="text-center col-md-1">Programma Carenza</th>
    <th class="text-center col-md-3">Esiti</th>
</tr>
</thead>
<tbody>';

// ======================
// QUERY PRINCIPALE: carenze dello studente scelto
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
    WHERE s.id = '{$__studente_id}'
      AND (c.stato = 2 OR c.stato = 3)
";

if ($anni_filtro_id > 0) {
    $query .= " AND c.id_anno_scolastico = " . intval($anni_filtro_id);
}

$query .= " ORDER BY m.nome ASC";
$carenze = dbGetAll($query) ?: [];

// ----------------------
// helpers
// ----------------------
function dtLabel($dt, $aula = '') {
    if (empty($dt)) return '';
    try {
        $s = "Esame tenuto il " . (new DateTime($dt))->format('d-m-Y H:i');
        if (!empty($aula)) $s .= " in aula " . htmlspecialchars($aula);
        return $s;
    } catch (Exception $e) { return ''; }
}

function badge($cls, $txt, $title = '') {
    $t = $title ? ' data-toggle="tooltip" title="'.htmlspecialchars($title).'"' : '';
    return '<span class="label label-'.$cls.'"'.$t.'>'.$txt.'</span>';
}

function renderEsame($label, $s, $extraPrefixHtml = '') {
    $html = '<div style="margin-bottom:4px;">';
    if ($label !== '') $html .= "<strong>{$label}: </strong>";
    $html .= $extraPrefixHtml;

    if (!$s) {
        $html .= badge('warning', 'Nessuna sessione di esame');
        return $html . '</div>';
    }

    $firmato = intval($s['firmato'] ?? 0) === 1;
    $tooltip = dtLabel($s['data_inizio_esame'] ?? '', $s['aula'] ?? '');

    if (!$firmato) {
        $html .= badge('warning', 'In attesa esito', 'In attesa esito esame');
        return $html . '</div>';
    }

    $presente = ($s['presente'] !== null) ? intval($s['presente']) : null;
    $assG     = ($s['assenza_giustificata'] !== null) ? intval($s['assenza_giustificata']) : 0;
    $rec      = ($s['recuperato'] !== null) ? intval($s['recuperato']) : null;

    if ($presente === null) {
        $html .= badge('default', 'Esito non registrato', 'Esito non registrato per lo studente');
        return $html . '</div>';
    }

    if ($presente === 0) {
        $html .= ($assG === 1)
            ? badge('default', 'Assente (giust.)', $tooltip)
            : badge('default', 'Assente', $tooltip);
        return $html . '</div>';
    }

    $html .= badge('primary', 'Presente', $tooltip) . '&ensp;';
    $html .= ($rec === 1)
        ? badge('success', 'Recuperato', $tooltip)
        : badge('danger', 'Non recuperato', $tooltip);

    return $html . '</div>';
}

// ======================
// RIGHE
// ======================
foreach ($carenze as $row) {
    $idcarenza   = intval($row['carenza_id']);
    $idStudente  = intval($row['id_studente']);
    $idMateria   = intval($row['id_materia']);
    $idAnnoCarenza = intval($row['id_anno_scolastico']);

    $materia = htmlspecialchars($row['materia']);
    $docente = ($row['doc_id'] == 0)
        ? 'Studente esterno'
        : htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome']);

    $note    = htmlspecialchars($row['nota']);

    $data .= "<tr>
        <td align='center'>{$materia}</td>
        <td align='center'>{$docente}</td>
        <td align='center'>{$note}</td>
        <td align='center'>
            <button onclick=\"carenzaPrint('{$idcarenza}','{$idAnnoCarenza}')\" class='btn btn-primary btn-xs' data-toggle='tooltip' title='Scarica il PDF del programma'>
                <span class='glyphicon glyphicon-print'></span>
            </button>
        </td>
        <td align='center'>";

    // Recupero in itinere
    $itinere = dbGetValue("
        SELECT COALESCE(MAX(co.in_itinere),0)
        FROM carenze car
        INNER JOIN corso co ON co.id_materia = car.id_materia
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = car.id_studente
        WHERE car.id = {$idcarenza}
    ");

    $badgeInItinere = (intval($itinere) === 1)
        ? '<span class="label label-info" data-toggle="tooltip" title="Recupero della carenza durante il corso">Recupero in itinere</span>&ensp;'
        : '';

    // 1) corso prima sessione (anno corsi corrente!)
    $idCorso1 = dbGetValue("
        SELECT co.id
        FROM corso co
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id
        WHERE ci.id_studente = {$idStudente}
          AND co.id_materia  = {$idMateria}
          AND co.id_anno_scolastico = {$anno_corsi_id}
          AND co.carenza = 1
          AND COALESCE(co.carenza_sessione,1) = 1
        ORDER BY co.id DESC
        LIMIT 1
    ");

    if (!$idCorso1) {
        $data .= badge('warning', 'Nessuna sessione di esame', 'Nessun corso di recupero 1ª sessione trovato');
        $data .= "</td></tr>";
        continue;
    }
    $idCorso1 = intval($idCorso1);

    $primo = dbGetFirst("
        SELECT
            ced.id AS id_esame_data,
            ced.data_inizio_esame,
            ced.data_fine_esame,
            ced.aula,
            ced.firmato,
            ce.presente,
            ce.recuperato,
            ce.assenza_giustificata
        FROM corso_esami_date ced
        LEFT JOIN corso_esiti ce
               ON ce.id_esame_data = ced.id
              AND ce.id_studente  = {$idStudente}
        WHERE ced.id_corso = {$idCorso1}
          AND COALESCE(ced.tentativo,1) = 1
        ORDER BY ced.data_inizio_esame ASC
        LIMIT 1
    ");

    // mapping corso2
    $map = dbGetFirst("
        SELECT ccs.id_corso_secondo, co2.titolo
        FROM corso_carenze_seconda ccs
        INNER JOIN corso co2 ON co2.id = ccs.id_corso_secondo
        WHERE ccs.id_studente = {$idStudente}
          AND ccs.id_corso_primo = {$idCorso1}
        LIMIT 1
    ");

    $hasSecondo = ($map && intval($map['id_corso_secondo']) > 0);
    $labelPrimo = $hasSecondo ? 'Primo tentativo' : '';

    $data .= renderEsame($labelPrimo, $primo, $badgeInItinere);

    if ($hasSecondo) {
        $idCorso2 = intval($map['id_corso_secondo']);
        $titolo2  = strtolower(trim($map['titolo'] ?? ''));
        $label2   = (strpos($titolo2, 'recupero assenza') !== false) ? 'Recupero assenza' : 'Seconda sessione';

        $secondo = dbGetFirst("
            SELECT
                ced.id AS id_esame_data,
                ced.data_inizio_esame,
                ced.data_fine_esame,
                ced.aula,
                ced.firmato,
                ce.presente,
                ce.recuperato,
                ce.assenza_giustificata
            FROM corso_esami_date ced
            LEFT JOIN corso_esiti ce
                   ON ce.id_esame_data = ced.id
                  AND ce.id_studente  = {$idStudente}
            WHERE ced.id_corso = {$idCorso2}
              AND COALESCE(ced.tentativo,1) = 1
            ORDER BY ced.data_inizio_esame ASC
            LIMIT 1
        ");

        $extraBadge = '';
        if ($secondo && intval($secondo['firmato'] ?? 0) === 0) {
            $extraBadge = '<span class="label label-info" data-toggle="tooltip" title="Iscritto al corso collegato">Iscritto</span>&ensp;';
        }

        $data .= renderEsame($label2, $secondo, $extraBadge);
    }

    $data .= "</td></tr>";
}

$data .= '</tbody></table></div>';
echo $data;
