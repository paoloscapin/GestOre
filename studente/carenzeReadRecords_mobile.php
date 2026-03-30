<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('studente');

if (!getSettingsValue('config', 'carenzeObiettiviMinimi', false) ||
    !getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false)) {
    redirect("/error/unauthorized.php");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("/error/unauthorized.php");
}

$anni_filtro_id = isset($_POST["anni_filtro_id"]) ? (int)$_POST["anni_filtro_id"] : 0;
$anno_corsi_id  = (int)$__anno_scolastico_corrente_id;

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

function dtLabelStudMob($dt, $aula = '')
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

function badgeStudMob($cls, $txt, $title = '')
{
    $t = $title ? ' title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
    return '<span class="label label-' . $cls . '"' . $t . '>' . htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
}

function renderEsameStudMob($label, $s, $extraPrefixHtml = '')
{
    $html = '<div style="margin-bottom:8px;">';
    if ($label !== '') $html .= "<strong>" . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ":</strong> ";
    $html .= $extraPrefixHtml;

    if (!$s) {
        $html .= badgeStudMob('warning', 'Nessuna sessione di esame');
        return $html . '</div>';
    }

    $firmato = (int)($s['firmato'] ?? 0) === 1;
    $info = dtLabelStudMob($s['data_inizio_esame'] ?? '', $s['aula'] ?? '');

    if (!$firmato) {
        $html .= badgeStudMob('warning', 'In attesa esito');
        if ($info) $html .= '<div style="margin-top:4px; font-size:12px; opacity:.85;">' . htmlspecialchars($info, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        return $html . '</div>';
    }

    $presente = ($s['presente'] !== null) ? (int)$s['presente'] : null;
    $assG     = ($s['assenza_giustificata'] !== null) ? (int)$s['assenza_giustificata'] : 0;
    $rec      = ($s['recuperato'] !== null) ? (int)$s['recuperato'] : null;

    if ($presente === null) {
        $html .= badgeStudMob('default', 'Esito non registrato');
        if ($info) $html .= '<div style="margin-top:4px; font-size:12px; opacity:.85;">' . htmlspecialchars($info, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        return $html . '</div>';
    }

    if ($presente === 0) {
        $html .= ($assG === 1)
            ? badgeStudMob('default', 'Assente (giust.)')
            : badgeStudMob('default', 'Assente');
        if ($info) $html .= '<div style="margin-top:4px; font-size:12px; opacity:.85;">' . htmlspecialchars($info, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        return $html . '</div>';
    }

    $html .= badgeStudMob('primary', 'Presente') . ' ';
    $html .= ($rec === 1)
        ? badgeStudMob('success', 'Recuperato')
        : badgeStudMob('danger', 'Non recuperato');

    if ($info) $html .= '<div style="margin-top:4px; font-size:12px; opacity:.85;">' . htmlspecialchars($info, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';

    return $html . '</div>';
}

foreach ($carenze as $row) {
    $idcarenza  = (int)$row['carenza_id'];
    $idStudente = (int)$row['id_studente'];
    $idMateria  = (int)$row['id_materia'];

    $materia = htmlspecialchars($row['materia'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $docente = htmlspecialchars($row['doc_cognome'] . ' ' . $row['doc_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $note    = htmlspecialchars((string)$row['nota'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<div class="card mb-3 p-3 shadow-sm" style="border-radius:12px; background:#fff;">';
    echo "<div><strong>Materia:</strong> {$materia}</div>";
    echo "<div><strong>Docente:</strong> {$docente}</div>";
    if (!empty($note)) echo "<div><strong>Note:</strong> {$note}</div>";

    $itinere = dbGetValue("
        SELECT COALESCE(MAX(co.in_itinere),0)
        FROM carenze car
        INNER JOIN corso co ON co.id_materia = car.id_materia
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = car.id_studente
        WHERE car.id = {$idcarenza}
    ");

    $badgeInItinere = ((int)$itinere === 1)
        ? '<span class="label label-info" style="margin-right:4px;">Recupero in itinere</span>'
        : '';

    echo '<div style="margin-top:10px;"><strong>Esiti:</strong><br>';

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
    $idCorso1 = (int)$idCorso1;

    if ($idCorso1 <= 0) {
        echo badgeStudMob('warning', 'Nessuna sessione di esame');
    } else {
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

        echo renderEsameStudMob($labelPrimo, $primo, $badgeInItinere);

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
                $extraBadge = '<span class="label label-info" style="margin-right:4px;">Iscritto</span>';
            }

            echo renderEsameStudMob($label2, $secondo, $extraBadge);
        }
    }

    echo '</div>';

    echo '<div class="mt-2 text-center" style="margin-top:10px;">';
    echo '<button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-primary btn-sm me-1">';
    echo '<span class="glyphicon glyphicon-print"></span> PDF</button> ';
    echo '<button onclick="carenzaSend(\'' . $idcarenza . '\')" class="btn btn-info btn-sm">';
    echo '<span class="glyphicon glyphicon-envelope"></span> Invia</button>';
    echo '</div>';

    echo '</div>';
}