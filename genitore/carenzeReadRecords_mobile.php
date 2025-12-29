<?php
/**
 *  Carenze GENITORE - MOBILE
 *  Allineato allo studente:
 *  - corso 1ª sessione (carenza_sessione=1) su anno corsi corrente
 *  - eventuale corso collegato tramite corso_carenze_seconda
 *  - mostra "Esame tenuto il ..." in chiaro (non solo tooltip)
 *  - rimosso campo "Data ricezione"
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$studente_filtro_id = isset($_GET["studente_filtro_id"]) ? intval($_GET["studente_filtro_id"]) : 0;
$__studente_id = $studente_filtro_id;

$anni_filtro_id = isset($_GET["anni_filtro_id"]) ? intval($_GET["anni_filtro_id"]) : 0;
$anno_corsi_id  = intval($__anno_scolastico_corrente_id); // anno dei corsi (corrente)

function dtLabel($dt, $aula = '')
{
    if (empty($dt)) return '';
    try {
        $s = "Esame tenuto il " . (new DateTime($dt))->format('d-m-Y H:i');
        if (!empty($aula)) $s .= " in aula " . htmlspecialchars($aula);
        return $s;
    } catch (Exception $e) {
        return '';
    }
}

function badge($cls, $txt, $title = '')
{
    // su mobile il tooltip non è affidabile: lo teniamo solo se serve, ma mostriamo anche testo sotto
    $t = $title ? ' title="' . htmlspecialchars($title) . '"' : '';
    return '<span class="label label-' . $cls . '"' . $t . '>' . $txt . '</span>';
}

function renderEsameMobile($label, $s, $extraPrefixHtml = '')
{
    $html = '<div style="margin-bottom:6px;">';
    if ($label !== '') $html .= "<strong>{$label}:</strong> ";
    $html .= $extraPrefixHtml;

    if (!$s) {
        $html .= badge('warning', 'Nessuna sessione di esame');
        return $html . '</div>';
    }

    $firmato = intval($s['firmato'] ?? 0) === 1;
    $infoEsame = dtLabel($s['data_inizio_esame'] ?? '', $s['aula'] ?? '');

    if (!$firmato) {
        $html .= badge('warning', 'In attesa esito');
        // se già programmato, mostro comunque info sotto (se c'è data)
        if (!empty($infoEsame)) {
            $html .= '<div style="font-size:12px; margin-top:4px; color:#555;">' . $infoEsame . '</div>';
        }
        return $html . '</div>';
    }

    $presente = ($s['presente'] !== null) ? intval($s['presente']) : null;
    $assG     = ($s['assenza_giustificata'] !== null) ? intval($s['assenza_giustificata']) : 0;
    $rec      = ($s['recuperato'] !== null) ? intval($s['recuperato']) : null;

    if ($presente === null) {
        $html .= badge('default', 'Esito non registrato');
        if (!empty($infoEsame)) {
            $html .= '<div style="font-size:12px; margin-top:4px; color:#555;">' . $infoEsame . '</div>';
        }
        return $html . '</div>';
    }

    if ($presente === 0) {
        $html .= ($assG === 1)
            ? badge('default', 'Assente (giust.)')
            : badge('default', 'Assente');

        if (!empty($infoEsame)) {
            $html .= '<div style="font-size:12px; margin-top:4px; color:#555;">' . $infoEsame . '</div>';
        }
        return $html . '</div>';
    }

    // presente
    $html .= badge('primary', 'Presente') . ' ';
    $html .= ($rec === 1)
        ? badge('success', 'Recuperato')
        : badge('danger', 'Non recuperato');

    if (!empty($infoEsame)) {
        $html .= '<div style="font-size:12px; margin-top:4px; color:#555;">' . $infoEsame . '</div>';
    }

    return $html . '</div>';
}

// ======================
// QUERY CARENZE
// ======================
if ($__studente_id <= 0) {
    echo '<div class="cards-container"><div class="card mb-3 p-3 shadow-sm" style="border-radius:12px; background:#fff;">Seleziona uno studente.</div></div>';
    exit;
}

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
    WHERE s.id = {$__studente_id}
      AND (c.stato = 2 OR c.stato = 3)
";

if ($anni_filtro_id > 0) {
    $query .= " AND c.id_anno_scolastico = " . intval($anni_filtro_id);
}

$query .= " ORDER BY m.nome ASC";
$carenze = dbGetAll($query) ?: [];

// ======================
// OUTPUT
// ======================
echo '<div class="cards-container">';

foreach ($carenze as $row) {
    $idcarenza     = intval($row['carenza_id']);
    $idStudente    = intval($row['id_studente']);
    $idMateria     = intval($row['id_materia']);
    $idAnnoCarenza = intval($row['id_anno_scolastico']); // serve al print

    $materia = htmlspecialchars($row['materia']);
    $docente = ($row['doc_id'] == 0) ? 'Studente esterno' : htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome']);
    $note    = htmlspecialchars($row['nota']);

    echo '<div class="card mb-3 p-3 shadow-sm" style="border-radius:12px; background:#fff;">';
    echo "<div><strong>Materia:</strong> {$materia}</div>";
    echo "<div><strong>Docente:</strong> {$docente}</div>";
    if (!empty($note)) echo "<div><strong>Note:</strong> {$note}</div>";

    // Recupero in itinere
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

    echo '<div style="margin-top:10px;"><strong>Esiti:</strong><br>';

    // 1) corso 1ª sessione nell'anno corsi corrente
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
    $idCorso1 = intval($idCorso1);

    if ($idCorso1 <= 0) {
        echo badge('warning', 'Nessuna sessione di esame');
    } else {
        // esame corso1
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

        echo renderEsameMobile($labelPrimo, $primo, $badgeInItinere);

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
                $extraBadge = '<span class="label label-info" style="margin-right:4px;">Iscritto</span>';
            }
            echo renderEsameMobile($label2, $secondo, $extraBadge);
        }
    }

    echo '</div>'; // esiti

    // azione PDF (genitore: usa id_anno_carenza)
    echo '<div class="mt-2 text-center" style="margin-top:10px;">';
    echo '<button onclick="carenzaPrint(\'' . $idcarenza . '\',\'' . $idAnnoCarenza . '\')" class="btn btn-primary btn-sm">';
    echo '<span class="glyphicon glyphicon-print"></span> PDF</button>';
    echo '</div>';

    echo '</div>'; // card
}

echo '</div>';
