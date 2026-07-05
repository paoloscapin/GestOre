<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/carenze_course_detail_lib.php';

ruoloRichiesto('studente');

if (!getSettingsValue('config', 'carenzeObiettiviMinimi', false) ||
    !getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false)) {
    redirect("/error/unauthorized.php");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("/error/unauthorized.php");
}

$anni_filtro_id = isset($_POST["anni_filtro_id"]) ? (int)$_POST["anni_filtro_id"] : 0;
$anno_corsi_id = (int)$__anno_scolastico_corrente_id;
$visibileEsiti = (bool)getSettingsValue(
    'carenzeObiettiviMinimi',
    'visibile_esiti_studenti',
    getSettingsValue('carenzeObiettiviMinimi', 'studente_vede_esito', true)
);
$visibileCorsi = (bool)getSettingsValue('carenzeObiettiviMinimi', 'visibile_corsi_studenti', true);

// ======================
// HEADER TABELLA
// ======================
$data = '
<div class="carenze-table-card table-responsive">
<table class="table carenze-table">
<thead>
<tr>
    <th class="text-center col-md-2">Materia</th>
    <th class="text-center col-md-2">Docente</th>
    <th class="text-center col-md-3">Note</th>
    <th class="text-center col-md-1">Programma Carenza</th>
    ' . ($visibileEsiti ? '<th class="text-center col-md-2">Esiti</th>' : '') . '
    ' . ($visibileCorsi ? '<th class="text-center col-md-2">Corso</th>' : '') . '
</tr>
</thead>
<tbody>';

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
    WHERE s.id = " . (int)$__studente_id . "
      AND (c.stato = 2 OR c.stato = 3)
";

if ($anni_filtro_id > 0) {
    $query .= " AND c.id_anno_scolastico = " . (int)$anni_filtro_id;
}

$query .= " ORDER BY m.nome ASC";
$carenze = dbGetAll($query) ?: [];

function dtLabelStud($dt, $aula = '')
{
    if (empty($dt)) return '';
    try {
        $s = "Esame tenuto il " . (new DateTime($dt))->format('d-m-Y H:i');
        if (!empty($aula)) $s .= " in aula " . htmlspecialchars($aula, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $s;
    } catch (Exception $e) {
        return '';
    }
}

function badgeStud($cls, $txt, $title = '')
{
    $t = $title ? ' data-toggle="tooltip" title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
    return '<span class="label label-' . $cls . ' carenze-status"' . $t . '>' . htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
}

function renderEsameStud($label, $s, $extraPrefixHtml = '', $emptyTitle = '')
{
    $html = '<div style="margin-bottom:4px;">';
    if ($label !== '') $html .= "<strong>" . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ":</strong> ";
    $html .= $extraPrefixHtml;

    if (!$s) {
        $html .= badgeStud('warning', 'Data esame non ancora fissata', $emptyTitle);
        return $html . '</div>';
    }

    $firmato = (int)($s['firmato'] ?? 0) === 1;
    $tooltip = dtLabelStud($s['data_inizio_esame'] ?? '', $s['aula'] ?? '');

    if (!$firmato) {
        $html .= badgeStud('warning', 'In attesa esito', 'In attesa esito esame');
        return $html . '</div>';
    }

    $presente = ($s['presente'] !== null) ? (int)$s['presente'] : null;
    $assG     = ($s['assenza_giustificata'] !== null) ? (int)$s['assenza_giustificata'] : 0;
    $rec      = ($s['recuperato'] !== null) ? (int)$s['recuperato'] : null;

    if ($presente === null) {
        $html .= badgeStud('default', 'Esito non registrato', 'Esito non registrato per lo studente');
        return $html . '</div>';
    }

    if ($presente === 0) {
        $html .= ($assG === 1)
            ? badgeStud('default', 'Assente (giust.)', $tooltip)
            : badgeStud('default', 'Assente', $tooltip);
        return $html . '</div>';
    }

    $html .= badgeStud('primary', 'Presente', $tooltip) . '&ensp;';
    $html .= ($rec === 1)
        ? badgeStud('success', 'Recuperato', $tooltip)
        : badgeStud('danger', 'Non recuperato', $tooltip);

    return $html . '</div>';
}

foreach ($carenze as $row) {
    $idcarenza  = (int)$row['carenza_id'];
    $idStudente = (int)$row['id_studente'];
    $idMateria  = (int)$row['id_materia'];

    $materia = htmlspecialchars($row['materia'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $note    = htmlspecialchars((string)$row['nota'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $itinere = dbGetValue("
        SELECT COALESCE(MAX(co.in_itinere),0)
        FROM carenze car
        INNER JOIN corso co ON co.id_materia = car.id_materia
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = car.id_studente
        WHERE car.id = {$idcarenza}
    ");

    $badgeInItinere = ((int)$itinere === 1)
        ? '<span class="label label-info" data-toggle="tooltip" title="Recupero della carenza durante il corso">Recupero in itinere</span>&ensp;'
        : '';

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

    $courseInfo = $idCorso1 ? carenzeCourseGetInfo((int)$idCorso1) : null;
    $docenteCorso = $courseInfo ? carenzeCourseDocentiLabel($courseInfo) : '';
    $docente = htmlspecialchars(
        $docenteCorso !== '' ? $docenteCorso : ($row['doc_cognome'] . ' ' . $row['doc_nome']),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $data .= "<tr>
        <td align='center'><div class='carenze-materia'>{$materia}</div></td>
        <td align='center'><div class='carenze-docente'>{$docente}</div></td>
        <td align='center'><div class='carenze-subtle'>{$note}</div></td>
        <td align='center'>
            <button onclick=\"carenzaPrint('{$idcarenza}')\" class='btn btn-primary btn-xs carenze-icon-btn' data-toggle='tooltip' title='Scarica il PDF del programma'>
                <span class='glyphicon glyphicon-print'></span>
            </button>
            <button onclick=\"carenzaSend('{$idcarenza}')\" class='btn btn-info btn-xs carenze-icon-btn' data-toggle='tooltip' title='Invia via mail'>
                <span class='glyphicon glyphicon-envelope'></span>
            </button>
        </td>";

    if (!$idCorso1) {
        if ($visibileEsiti) {
            $data .= "<td align='center'>" . badgeStud('warning', 'Nessun corso abbinato', 'Nessun corso di recupero 1ª sessione trovato') . "</td>";
        }
        if ($visibileCorsi) {
            $data .= "<td align='center'><span class='text-muted'>Nessun corso abbinato</span></td>";
        }
        $data .= "</tr>";
        continue;
    }

    $idCorso1 = (int)$idCorso1;
    $corsoCell = '';
    if ($visibileCorsi) {
        $primaDataCorso = carenzeCourseFormatFirstDate($idCorso1);
        $corsoCell = '<button onclick="carenzaCorsoRead(' . $idCorso1 . ')" class="btn btn-success btn-xs carenze-course-btn" data-toggle="tooltip" title="Vedi calendario, presenze e argomenti del corso">'
            . '<span class="glyphicon glyphicon-calendar"></span> Corso</button>';
        if ($primaDataCorso !== '') {
            $corsoCell .= '<div class="carenze-course-date">' . htmlspecialchars($primaDataCorso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }
    }

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

    $map = dbGetFirst("
        SELECT ccs.id_corso_secondo, co2.titolo
        FROM corso_carenze_seconda ccs
        INNER JOIN corso co2 ON co2.id = ccs.id_corso_secondo
        WHERE ccs.id_studente = {$idStudente}
          AND ccs.id_corso_primo = {$idCorso1}
        LIMIT 1
    ");

    $hasSecondo = ($map && (int)$map['id_corso_secondo'] > 0);
    $labelPrimo = $hasSecondo ? 'Prima sessione' : '';

    $esitiCell = renderEsameStud($labelPrimo, $primo, $badgeInItinere, 'Corso di recupero trovato, ma la data dell esame non e ancora stata inserita');

    if ($hasSecondo) {
        $idCorso2 = (int)$map['id_corso_secondo'];
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
        if ($secondo && (int)($secondo['firmato'] ?? 0) === 0) {
            $extraBadge = '<span class="label label-info" data-toggle="tooltip" title="Iscritto al corso collegato">Iscritto</span>&ensp;';
        }

        $esitiCell .= renderEsameStud($label2, $secondo, $extraBadge, 'Corso collegato trovato, ma la data dell esame non e ancora stata inserita');
    }

    if ($visibileEsiti) {
        $data .= "<td align='center'>{$esitiCell}</td>";
    }
    if ($visibileCorsi) {
        $data .= "<td align='center'>{$corsoCell}</td>";
    }
    $data .= "</tr>";
}

$data .= '</tbody></table></div>';
echo $data;
