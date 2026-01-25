<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$ancheCancellati    = isset($_GET["ancheCancellati"]) ? $_GET["ancheCancellati"] : "false";
$soloNuovi          = isset($_GET["soloNuovi"]) ? (int)$_GET["soloNuovi"] : 0;
$soloPrenotati      = isset($_GET["soloPrenotati"]) ? (int)$_GET["soloPrenotati"] : 0;
$categoria_filtro_id= isset($_GET["categoria_filtro_id"]) ? (int)$_GET["categoria_filtro_id"] : 0;
$docente_filtro_id  = isset($_GET["docente_filtro_id"]) ? (int)$_GET["docente_filtro_id"] : 0;
$materia_filtro_id  = isset($_GET["materia_filtro_id"]) ? (int)$_GET["materia_filtro_id"] : 0;
$classe_filtro_id   = isset($_GET["classe_filtro_id"]) ? (int)$_GET["classe_filtro_id"] : 0;
$bozza_filtro_id    = isset($_GET["bozza_filtro_id"]) ? (int)$_GET["bozza_filtro_id"] : 0;

// normalizza boolean (arriva come "true"/"false" o "1"/"0")
$ancheCancellatiBool = false;
if (is_string($ancheCancellati)) {
    $ancheCancellatiBool = (strtolower($ancheCancellati) === "true" || $ancheCancellati === "1");
} else {
    $ancheCancellatiBool = (bool)$ancheCancellati;
}

$direzioneOrdinamento = "ASC";

// helper escape (usa mysqli se disponibile, altrimenti fallback)
function escHtmlAttr($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function escSqlLike($s) {
    // per usare in stringhe SQL "semplici" con addslashes (meglio: prepared, ma qui patch minimale)
    return addslashes((string)$s);
}

// header tabella
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-1">Categoria</th>
            <th class="text-center col-md-2">Data</th>
            <th class="text-center col-md-1">Ora</th>
            <th class="text-center col-md-2">Materia</th>
            <th class="text-center col-md-2">Docente</th>
            <th class="text-center col-md-1">Ore</th>
            <th class="text-center col-md-1">Classe</th>
            <th class="text-center col-md-1">Luogo</th>
            <th class="text-center col-md-1">Stato</th>
            <th class="text-center col-md-1">Studenti Prenotati</th>
            <th class="text-center col-md-3">Azioni</th>
        </tr>
    </thead>';

// ✅ IMPORTANTISSIMO: niente INNER JOIN classe
$query = "
    SELECT
        sportello.id AS sportello_id,
        sportello.data AS sportello_data,
        sportello.ora AS sportello_ora,
        sportello.numero_ore AS sportello_numero_ore,
        sportello.luogo AS sportello_luogo,
        sportello.categoria AS sportello_categoria,
        sportello.classe AS sportello_classe,
        sportello.firmato AS sportello_firmato,
        sportello.online AS sportello_online,
        sportello.clil AS sportello_clil,
        sportello.orientamento AS sportello_orientamento,
        sportello.cancellato AS sportello_cancellato,
        sportello.max_iscrizioni AS sportello_max_iscrizioni,
        sportello.attivo AS sportello_attivo,
        materia.nome AS materia_nome,
        docente.cognome AS docente_cognome,
        docente.nome AS docente_nome,
        (
            SELECT COUNT(*)
            FROM sportello_studente
            WHERE sportello_studente.sportello_id = sportello.id
        ) AS numero_studenti
    FROM sportello sportello
    LEFT JOIN docente docente ON sportello.docente_id = docente.id
    INNER JOIN materia materia ON sportello.materia_id = materia.id
    WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
";

// filtro categoria (sportello.categoria è TESTO)
if ($categoria_filtro_id > 0) {
    $categoria_filtro_nome = dbGetValue("SELECT nome FROM sportello_categoria WHERE id=" . (int)$categoria_filtro_id . " LIMIT 1");
    $categoria_filtro_nome = escSqlLike($categoria_filtro_nome);
    $query .= " AND sportello.categoria = '$categoria_filtro_nome' ";
}

// filtro classe (usa classe_id + include)
if ($classe_filtro_id > 0) {
    $classe_filtro_id = (int)$classe_filtro_id;
    $query .= "
        AND sportello.classe_id IN (
            SELECT ci.includes_classe_id
            FROM classe_include ci
            WHERE ci.classe_id = $classe_filtro_id
        )
    ";
}

if ($materia_filtro_id > 0) $query .= " AND sportello.materia_id = " . (int)$materia_filtro_id . " ";
if ($docente_filtro_id > 0) $query .= " AND sportello.docente_id = " . (int)$docente_filtro_id . " ";

if (!$ancheCancellatiBool) $query .= " AND NOT sportello.cancellato ";

if ($soloNuovi) $query .= " AND sportello.data >= CURDATE() ";

// bozza: 0 = attivi; 1 = bozze
if ($bozza_filtro_id == 0) $query .= " AND sportello.attivo = 1 ";
else $query .= " AND sportello.attivo = 0 ";

$query .= " ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC, docente_nome ASC ";

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

foreach ($resultArray as $row) {
    if ((($soloPrenotati == 1) && ($row['numero_studenti'] > 0)) || ($soloPrenotati == 0)) {

        $sportello_id = (int)$row['sportello_id'];
        $sportello_categoria = $row['sportello_categoria'] ?? '';

        // stato marker
        $statoMarker = '';
        if (!empty($row['sportello_cancellato'])) {
            $statoMarker = '<span class="label label-default">cancellato</span>';
        } else {
            if (!empty($row['sportello_firmato'])) {
                $statoMarker = '<span class="label label-primary">firmato</span>';
            } else {
                if ((int)$row['numero_studenti'] === (int)$row['sportello_max_iscrizioni']) {
                    $statoMarker = '<span class="label label-danger">posti esauriti</span>';
                } else {
                    $statoMarker = '<span class="label label-success">posti disponibili</span>';
                }
            }
        }

        if ((int)$row['sportello_attivo'] === 0) {
            $statoMarker = '<span class="label label-default">BOZZA</span>';
        }

        $oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
        $dataSportello = utf8_encode(strftime("%d %B %Y", strtotime($row['sportello_data'])));
        setlocale(LC_TIME, $oldLocale);

        // tooltip studenti
        $studenteTip = '';
        if ((int)$row['numero_studenti'] > 0) {
            // ✅ FIX: usa sportello_studente.studente_id (non sportello_studente.id)
            $query2 = "
                SELECT
                    studente.cognome AS studente_cognome,
                    studente.nome AS studente_nome,
                    (
                        SELECT classi.classe
                        FROM classi
                        WHERE id = (
                            SELECT sf.id_classe
                            FROM studente_frequenta sf
                            WHERE sf.id_studente = sportello_studente.studente_id
                              AND sf.id_anno_scolastico = $__anno_scolastico_corrente_id
                            LIMIT 1
                        )
                    ) AS studente_classe
                FROM sportello_studente
                INNER JOIN studente ON sportello_studente.studente_id = studente.id
                WHERE sportello_studente.sportello_id = $sportello_id
            ";
            $studenti = dbGetAll($query2);
            if ($studenti) {
                foreach ($studenti as $studente) {
                    $studenteTip .= escHtmlAttr($studente['studente_cognome'] . " " . $studente['studente_nome'] . " " . ($studente['studente_classe'] ?? '')) . "</br>";
                }
            }
        }

        // luogo / online marker
        $luogo_or_online_marker = $row['sportello_luogo'] ?? '';
        if (!empty($row['sportello_online'])) {
            $luogo_or_online_marker = '<span class="label label-danger">online</span>';
        }

        $barrato = !empty($row['sportello_cancellato']) ? '<s>' : '';
        $doc = trim(($row['docente_nome'] ?? '') . ' ' . ($row['docente_cognome'] ?? ''));
        if ($doc === '' || $doc === 'Tutti') $doc = '---';

        $materia_nome = (string)($row['materia_nome'] ?? '');
        $materia_clear_js = escHtmlAttr($materia_nome);

        $data .= '<tr>
            <td align="center">' . $barrato . escHtmlAttr($sportello_categoria) . '</td>
            <td align="center">' . $barrato . escHtmlAttr($dataSportello) . '</td>
            <td align="center">' . $barrato . escHtmlAttr($row['sportello_ora']) . '</td>
            <td align="center">' . $barrato . escHtmlAttr($materia_nome) . '</td>
            <td align="center">' . $barrato . escHtmlAttr($doc) . '</td>
            <td align="center">' . $barrato . escHtmlAttr($row['sportello_numero_ore']) . '</td>
            <td align="center">' . $barrato . escHtmlAttr($row['sportello_classe']) . '</td>
            <td class="text-center">' . $barrato . $luogo_or_online_marker . '</td>
            <td class="text-center">' . $statoMarker . '</td>
            <td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="' . $studenteTip . '">' . $barrato . (int)$row['numero_studenti'] . '</td>
            <td class="text-center">
                <button onclick="sportelloGetDetails(' . $sportello_id . ')" class="btn btn-warning btn-xs" title="Modifica">
                    <span class="glyphicon glyphicon-pencil"></span>
                </button>

                <button onclick="sportelloDuplica(' . $sportello_id . ')" class="btn btn-success btn-xs" title="Duplica">
                    <span class="glyphicon glyphicon-copy"></span>
                </button>

                <button onclick="sportelloRimettiBozza(' . $sportello_id . ',\'' . $materia_clear_js . '\')" class="btn btn-primary btn-xs" title="Rimetti in bozza">
                    <span class="glyphicon glyphicon-refresh"></span>
                </button>

                <button onclick="sportelloDelete(' . $sportello_id . ', \'' . $materia_clear_js . '\')" class="btn btn-danger btn-xs" title="Cancella">
                    <span class="glyphicon glyphicon-trash"></span>
                </button>

                <button id="selectbutton' . $sportello_id . '" onclick="sportelloSelect(' . $sportello_id . ')" class="btn btn-info btn-xs" title="Seleziona">
                    <span id="selecticon' . $sportello_id . '" class="glyphicon glyphicon-remove"></span>
                </button>
            </td>
        </tr>';
    }
}

$data .= '</table></div>';
echo $data;
