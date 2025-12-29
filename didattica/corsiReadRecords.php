<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$docente_id = 0;
if (impersonaRuolo("docente")) {
    $docente_id = $__docente_id;
} else {
    $docente_id = intval($_GET["docente_id"] ?? 0);
}

$carenza_sessione = isset($_GET['carenza_sessione']) ? intval($_GET['carenza_sessione']) : 0;

$materia_id        = intval($_GET["materia_id"] ?? 0);
$anni_filtro_id    = intval($_GET["anni_id"] ?? 0);
$futuri            = intval($_GET["futuri"] ?? 0);
$carenze_toggle    = isset($_GET['carenze']) ? intval($_GET['carenze']) : 0;
$in_itinere_toggle = isset($_GET['itinere']) ? intval($_GET['itinere']) : 0;

function badge($text, $type = 'default') {
    $text = htmlspecialchars($text);
    return '<span class="label label-' . $type . '" style="margin-right:4px; display:inline-block;">' . $text . '</span>';
}

function fmtDT($dt) {
    if (!$dt) return '—';
    return date("d-m-Y \\a\\l\\l\\e \\o\\r\\e H:i", strtotime($dt));
}

// header tabella
$data = '<style>
  .col-azioni   { width: 9%; }
  .col-inizio   { width: 10%; }
  .col-fine     { width: 10%; }
  .col-materia  { width: 19%; }
  .col-docente  { width: 10%; }
  .col-titolo   { width: 24%; }
  .col-studenti { width: 7%; }
  .col-stato    { width: 11%; }
</style>
<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
<thead>
<tr>
  <th class="text-center col-materia">Materia</th>
  <th class="text-center col-docente">Docente</th>
  <th class="text-center col-titolo">Titolo</th>
  <th class="text-center col-inizio">Data inizio</th>
  <th class="text-center col-fine">Data fine</th>
  <th class="text-center col-studenti">Studenti iscritti</th>
  <th class="text-center col-stato">Stato</th>
  <th class="text-center col-azioni">Azioni</th>
</tr>
</thead>';

$query = "
SELECT
    c.id AS corso_id,
    c.id_materia AS materia_id,
    c.id_docente AS doc_id,
    c.titolo AS titolo,
    c.id_anno_scolastico AS anno_id,
    c.carenza AS carenza,
    c.carenza_sessione AS carenza_sessione,
    c.in_itinere AS in_itinere,

    m.nome AS materia_nome,
    d.cognome AS docente_cognome,
    d.nome AS docente_nome,

    MIN(cd.data_inizio) AS data_inizio,
    MAX(cd.data_inizio) AS data_fine,
    SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) AS lezioni_firmate,
    COUNT(cd.id) AS lezioni_totali,

    CASE
        WHEN COUNT(cd.id) = 0 THEN 3
        WHEN COUNT(cd.id) > 0 AND SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) = 0 THEN 0
        WHEN SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) < COUNT(cd.id) THEN 1
        WHEN SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) = COUNT(cd.id) THEN 2
    END AS stato,

    (SELECT COUNT(*) FROM corso_iscritti ci WHERE ci.id_corso = c.id) AS studenti_iscritti,

    -- Esame tentativo 1 (per carenze, sia S1 che S2)
    (SELECT id FROM corso_esami_date ed1 WHERE ed1.id_corso=c.id AND ed1.tentativo=1 LIMIT 1) AS esame1_id,
    (SELECT ed1.firmato FROM corso_esami_date ed1 WHERE ed1.id_corso=c.id AND ed1.tentativo=1 LIMIT 1) AS esame1_firmato,
    (SELECT ed1.data_inizio_esame FROM corso_esami_date ed1 WHERE ed1.id_corso=c.id AND ed1.tentativo=1 LIMIT 1) AS esame1_data

FROM corso c
INNER JOIN docente d ON d.id = c.id_docente
INNER JOIN materia m ON m.id = c.id_materia
LEFT JOIN corso_date cd ON cd.id_corso = c.id

WHERE c.id_anno_scolastico = $anni_filtro_id
  AND c.carenza = $carenze_toggle
  AND c.in_itinere = $in_itinere_toggle
";

if ($materia_id > 0) $query .= " AND c.id_materia = $materia_id";
if ($docente_id > 0) $query .= " AND c.id_docente = $docente_id";

// filtro sessione carenze (solo se carenze=1 e filtro attivo)
if ($carenze_toggle == 1 && ($carenza_sessione == 1 || $carenza_sessione == 2)) {
    $query .= " AND c.carenza_sessione = $carenza_sessione";
}

$query .= "
GROUP BY c.id, c.id_materia, c.id_docente, c.id_anno_scolastico, c.carenza, c.carenza_sessione, c.in_itinere, m.nome, d.cognome, d.nome
HAVING ($futuri = 0 OR COALESCE(MAX(cd.data_inizio), '1970-01-01') >= CURDATE())
ORDER BY m.nome ASC, d.cognome ASC, d.nome ASC, c.titolo ASC
";

$rows = dbGetAll($query);
if (!$rows) $rows = [];

foreach ($rows as $row) {
    $idcorso = intval($row['corso_id']);

    $materia = $row['materia_nome'];
    $nome_docente = $row['docente_cognome'] . ' ' . $row['docente_nome'];
    $studenti_iscritti = intval($row['studenti_iscritti']);

    $isCarenza = (intval($row['carenza']) === 1);
    $sess = intval($row['carenza_sessione']);
    $isSessione2 = ($isCarenza && $sess === 2);
    $isItinere = (intval($row['in_itinere']) === 1);

    // ----- BADGE NEL TITOLO (solo “umani”) -----
    $titleBadges = '';

    if ($isCarenza) {
        if ($sess === 1) $titleBadges .= badge('S1', 'info');
        else if ($sess === 2) $titleBadges .= badge('S2', 'primary');
        else $titleBadges .= badge('CARENE', 'default');
    }

    // ITINERE nel titolo SOLO se ti serve, ma non metto dettagli esame
    if ($isItinere) {
        $titleBadges .= badge('ITINERE', 'primary');
    }

    $titolo = $titleBadges . htmlspecialchars($row['titolo']);

    // ----- DATE CORSO -----
    if ($isItinere || $isSessione2) {
        $data_inizio = '—';
        $data_fine = '—';
    } else {
        $data_inizio = $row['data_inizio'] ? fmtDT($row['data_inizio']) : '—';
        $data_fine   = $row['data_fine'] ? fmtDT($row['data_fine']) : '—';
    }

    // ----- STATO (badge principale: UNO SOLO) -----
// NB: per itinere forzo lo stato “Recupero itinere”
$stato = $isItinere ? 4 : intval($row['stato']);

$statoMarker = '';
if ($stato == 0) $statoMarker = badge('Non iniziato', 'default');
else if ($stato == 1) $statoMarker = badge('In svolgimento', 'warning');
else if ($stato == 2) $statoMarker = badge('Terminato', 'success');
else if ($stato == 3) $statoMarker = badge('Nessuna data', 'danger');
else if ($stato == 4) $statoMarker = badge('Recupero itinere', 'primary');

// ----- BADGE INFO EXTRA (tutti qui, senza doppioni) -----

// 1) Tipo corso: carenze + sessione (S1/S2)
if ($isCarenza) {
    if ($sess === 1) $statoMarker .= ' ' . badge('S1', 'info');
    else if ($sess === 2) $statoMarker .= ' ' . badge('S2', 'primary');
    else $statoMarker .= ' ' . badge('CARENE', 'default');
}

// 2) ITINERE come badge extra SOLO se non è già lo stato "Recupero itinere"
if ($isItinere && $stato != 4) {
    $statoMarker .= ' ' . badge('ITINERE', 'primary');
}

// 3) Lezioni firmate/totali (solo se ha date e non è itinere e non è S2)
$lezTot = intval($row['lezioni_totali']);
$lezFir = intval($row['lezioni_firmate']);
if (!$isItinere && !$isSessione2 && $lezTot > 0) {
    $statoMarker .= ' ' . badge(
        'L: ' . $lezFir . '/' . $lezTot,
        ($lezFir === $lezTot ? 'success' : ($lezFir === 0 ? 'default' : 'warning'))
    );
}

// 4) Esame (solo carenze): NP / PRG / BOZ / FIR
if ($isCarenza) {
    $e1_id = intval($row['esame1_id'] ?? 0);
    $e1_f  = intval($row['esame1_firmato'] ?? 0);
    $e1_dt = $row['esame1_data'] ?? null;

    if ($e1_id <= 0) {
        $statoMarker .= ' ' . badge('Esame NP', 'danger');
    } else if ($e1_f === 1) {
        $statoMarker .= ' ' . badge('Esame FIR', 'success');
    } else if ($e1_dt) {
        $statoMarker .= ' ' . badge('Esame PRG', 'warning');
    } else {
        $statoMarker .= ' ' . badge('Esame BOZ', 'default');
    }
}


    // ----- ROW -----
    $data .= '<tr>';
    $data .= '<td align="center">' . htmlspecialchars($materia) . '</td>';
    $data .= '<td align="center">' . htmlspecialchars($nome_docente) . '</td>';
    $data .= '<td align="center">' . $titolo . '</td>';
    $data .= '<td align="center">' . $data_inizio . '</td>';
    $data .= '<td align="center">' . $data_fine . '</td>';
    $data .= '<td align="center">' . $studenti_iscritti . '</td>';
    $data .= '<td align="center">' . $statoMarker . '</td>';
    $data .= '<td class="text-center">';

    // ----- AZIONI -----
    if (impersonaRuolo('docente')) {
        if (getSettingsValue('config', 'corsi', false) && getSettingsValue('corsi', 'visibile_docenti', false)) {

            if (getSettingsValue('corsi', 'docente_puo_modificare', false)) {
                $data .= '
                <button onclick="corsiGetDetails(\'' . $idcorso . '\')" 
                        class="btn btn-warning btn-xs" 
                        data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                        title="Modifica il corso">
                    <span class="glyphicon glyphicon-pencil"></span>
                </button>';
            }

            // Registro lezioni: solo se NON itinere e NON sessione2
            if (!$isItinere && !$isSessione2) {
                $data .= '
                <button onclick="apriRegistroLezione(\'' . $idcorso . '\')" 
                        class="btn btn-primary btn-xs" 
                        data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                        title="Gestisci le presenze e gli argomenti">
                    <span class="glyphicon glyphicon-user"></span>
                </button>';
            }

            // Esame: su corsi carenze (S1 e S2)
            if ($isCarenza) {
                $data .= '
                <button onclick="apriEsameModal(\'' . $idcorso . '\')" 
                        class="btn btn-success btn-xs" 
                        data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                        title="Esame carenze">
                    <span class="glyphicon glyphicon-check"></span>
                </button>';
            }
        }

    } else if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {

        $data .= '
        <button onclick="corsiGetDetails(\'' . $idcorso . '\')" 
                class="btn btn-warning btn-xs" 
                data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                title="Modifica il corso">
            <span class="glyphicon glyphicon-pencil"></span>
        </button>
        <button onclick="corsiDuplicaOpen(\'' . $idcorso . '\')" 
                class="btn btn-success btn-xs" 
                data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                title="Duplica il corso">
            <span class="glyphicon glyphicon-duplicate"></span>
        </button>
        <button onclick="corsiDelete(\'' . $idcorso . '\',\'' . addslashes($materia) . '\',\'' . addslashes($nome_docente) . '\',\'' . $studenti_iscritti . '\',\'' . $stato . '\')" 
                class="btn btn-danger btn-xs" 
                data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                title="Cancella il corso">
            <span class="glyphicon glyphicon-trash"></span>
        </button>';

        // Registro lezioni: solo se NON itinere e NON sessione2 e non "nessuna data"
        if (!$isItinere && !$isSessione2 && $stato != 3) {
            $data .= '
            <button onclick="apriRegistroLezione(\'' . $idcorso . '\')" 
                    class="btn btn-primary btn-xs" 
                    data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                    title="Gestisci le presenze e gli argomenti">
                <span class="glyphicon glyphicon-user"></span>
            </button>';
        }

        if ($isCarenza) {
            $data .= '
            <button onclick="apriEsameModal(\'' . $idcorso . '\')" 
                    class="btn btn-success btn-xs" 
                    data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                    title="Esame carenze">
                <span class="glyphicon glyphicon-check"></span>
            </button>';
        }
    }

    $data .= '</td></tr>';
}

echo $data;
