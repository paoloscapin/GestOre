<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

function fieldValue(array $row, string $upper, string $lower, $default = '')
{
    if (array_key_exists($upper, $row)) {
        return $row[$upper];
    }
    if (array_key_exists($lower, $row)) {
        return $row[$lower];
    }
    return $default;
}

$programma_modulo_id = intval($_POST['programma_modulo_id'] ?? 0);

if ($programma_modulo_id <= 0) {
    http_response_code(400);
    echo "Programma non valido";
    exit;
}

$programma_svolto = dbGetFirst("
    SELECT
        programmi_svolti.id,
        programmi_svolti.id_docente,
        programmi_svolti.id_classe,
        programmi_svolti.id_materia,
        programmi_svolti.id_anno_scolastico,
        classi.anno AS classe_anno
    FROM programmi_svolti
    INNER JOIN classi
    ON classi.id = programmi_svolti.id_classe
    WHERE programmi_svolti.id = " . $programma_modulo_id
);

if ($programma_svolto == null) {
    http_response_code(404);
    echo "Programma svolto non trovato";
    exit;
}

$docente_id = intval($programma_svolto['id_docente']);
$classe_id = intval($programma_svolto['id_classe']);
$materia_id = intval($programma_svolto['id_materia']);
$anno_scolastico_id = intval($programma_svolto['id_anno_scolastico']);
$classe_anno = intval($programma_svolto['classe_anno']);
$is_quinta = ($classe_anno === 5);

$programma_iniziale = dbGetFirst("
    SELECT *
    FROM programmi_iniziali
    WHERE id_classe = $classe_id
      AND id_materia = $materia_id
      AND id_anno_scolastico = $anno_scolastico_id
      AND id_docente = $docente_id
    ORDER BY updated DESC, id DESC
");

if ($programma_iniziale == null) {
    $programma_iniziale = dbGetFirst("
        SELECT *
        FROM programmi_iniziali
        WHERE id_classe = $classe_id
          AND id_materia = $materia_id
          AND id_anno_scolastico = $anno_scolastico_id
        ORDER BY updated DESC, id DESC
    ");
}

if ($programma_iniziale == null) {
    http_response_code(404);
    echo "Nessun programma iniziale trovato per classe e materia";
    exit;
}

$programma_iniziale_id = intval($programma_iniziale['id']);
$resultArray = dbGetAll("
    SELECT *
    FROM programmi_iniziali_moduli
    WHERE id_programma = $programma_iniziale_id
    ORDER BY ordine ASC
");

if ($resultArray == null) {
    $resultArray = [];
}

dbExec("DELETE FROM programmi_svolti_moduli WHERE id_programma = $programma_modulo_id");

$data = '';
$nmoduli = 0;
date_default_timezone_set("Europe/Rome");

foreach ($resultArray as $row) {
    $nmoduli++;
    $ordine = intval(fieldValue($row, 'ORDINE', 'ordine', $nmoduli));
    $titolo = (string)fieldValue($row, 'NOME', 'nome', '');
    $updated = date("Y-m-d H-i-s");
    $id_autore = $__utente_id;

    if ($is_quinta) {
        $contenuto = json_encode([
            'schema' => 'programma_svolto_quinta_v1',
            'competenze_raggiunte' => (string)fieldValue($row, 'COMPETENZE', 'competenze', ''),
            'contenuti_trattati' => (string)fieldValue($row, 'CONOSCENZE', 'conoscenze', ''),
            'abilita' => (string)fieldValue($row, 'ABILITA', 'abilita', ''),
            'metodologie' => '',
            'criteri_valutazione' => '',
            'testi_materiali' => '',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $contenuto = (string)fieldValue($row, 'CONOSCENZE', 'conoscenze', '');
    }

    $titolo_sql = dbEscape($titolo);
    $contenuto_sql = dbEscape($contenuto);

    $query = "INSERT INTO programmi_svolti_moduli(id_programma,ordine,nome,contenuto,id_utente,updated)
        VALUES('$programma_modulo_id', '$ordine', '$titolo_sql', '$contenuto_sql','$id_autore','$updated')";
    dbExec($query);
    $idmodulo = dblastId();
    info("aggiunto programma modulo svolto id=$idmodulo id_programma=$programma_modulo_id id_utente=$id_autore updated=$updated");

    $autore_row = dbGetFirst("SELECT utente.cognome, utente.nome FROM utente WHERE utente.id = " . intval($id_autore));
    $autore = ($autore_row != null) ? ($autore_row['cognome'] . " " . $autore_row['nome']) : '';

    $data .= '<tr>
        <td align="center">' . $ordine . '</td>
        <td align="center">' . htmlspecialchars($titolo) . '</td>
        <td align="center">' . htmlspecialchars($autore) . '</td>
        <td align="center">' . $updated . '</td>
        <td class="text-center">';

    if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
        $data .= '
            <button onclick="moduloSvoltiGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></span></button>
            <button onclick="moduloSvoltiDelete(' . $idmodulo . ',\'' . $programma_modulo_id . '\',\'' . js_escape($titolo) . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></span></button>
        ';
    } elseif (haRuolo('docente')) {
        if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
            if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
                $data .= '
                    <button onclick="moduloSvoltiGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></span></button>';
            } else {
                $data .= '
                    <button onclick="moduloSvoltiGetDetails(' . $idmodulo . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></span></button>';
            }
        }
    }

    $data .= '</td></tr>';
}

$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value="' . $nmoduli . '">';

echo $data;
?>
