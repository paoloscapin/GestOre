<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once __DIR__ . '/programmiAutoreUtils.php';

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

function importJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function programmaInizialeImportLabel(array $row): array
{
    $docente = trim(($row['docente_cognome'] ?? '') . ' ' . ($row['docente_nome'] ?? ''));
    $moduli = intval($row['numero_moduli'] ?? 0);

    return [
        'id' => intval($row['id']),
        'id_docente' => intval($row['id_docente']),
        'docente' => ($docente !== '') ? $docente : ('Docente id ' . intval($row['id_docente'])),
        'updated' => (string)($row['updated'] ?? ''),
        'numero_moduli' => $moduli,
    ];
}

function programmaSvoltoImportTextToRichHtml(string $text): string
{
    $lines = preg_split('/\R/u', str_replace("\t", '  ', $text));
    if ($lines === false) {
        return '';
    }

    $html = '';
    $listOpen = false;

    $closeList = function () use (&$html, &$listOpen): void {
        if ($listOpen) {
            $html .= '</ul>';
            $listOpen = false;
        }
    };

    foreach ($lines as $line) {
        $raw = (string)$line;
        $trimmed = trim($raw);
        if ($trimmed === '') {
            $closeList();
            continue;
        }

        if (preg_match('/^>>\s*(.+)$/u', $trimmed, $m)) {
            $closeList();
            $html .= '<h4>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</h4>';
            continue;
        }

        if (mb_strlen($trimmed, 'UTF-8') <= 90 && preg_match('/\p{L}/u', $trimmed) && !preg_match('/\p{Ll}/u', $trimmed)) {
            $closeList();
            $html .= '<h4>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</h4>';
            continue;
        }

        if (preg_match('/^(?:[•●▪◦\x{F0B7}\x{F0A7}]\s+|--\s+|>\s+|-\s+|\*\s+)(.+)$/u', ltrim($raw), $m)) {
            if (!$listOpen) {
                $html .= '<ul>';
                $listOpen = true;
            }
            $html .= '<li>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</li>';
            continue;
        }

        $closeList();
        $html .= '<p>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $closeList();
    return trim($html);
}

$programma_modulo_id = intval($_POST['programma_modulo_id'] ?? 0);
$selected_programma_iniziale_id = intval($_POST['programma_iniziale_id'] ?? 0);
$wants_json = (($_POST['response_format'] ?? '') === 'json');

if ($programma_modulo_id <= 0) {
    if ($wants_json) {
        importJsonResponse(['ok' => false, 'message' => 'Programma non valido'], 400);
    }
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
    if ($wants_json) {
        importJsonResponse(['ok' => false, 'message' => 'Programma svolto non trovato'], 404);
    }
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

$programma_iniziale_select = "
    SELECT
        programmi_iniziali.*,
        docente.cognome AS docente_cognome,
        docente.nome AS docente_nome,
        (
            SELECT COUNT(*)
            FROM programmi_iniziali_moduli
            WHERE programmi_iniziali_moduli.id_programma = programmi_iniziali.id
        ) AS numero_moduli
    FROM programmi_iniziali
    INNER JOIN docente
    ON docente.id = programmi_iniziali.id_docente
";

$programma_iniziale = null;

if ($selected_programma_iniziale_id > 0) {
    $programma_iniziale = dbGetFirst("
        $programma_iniziale_select
        WHERE programmi_iniziali.id = $selected_programma_iniziale_id
          AND programmi_iniziali.id_classe = $classe_id
          AND programmi_iniziali.id_materia = $materia_id
          AND programmi_iniziali.id_anno_scolastico = $anno_scolastico_id
    ");

    if ($programma_iniziale == null) {
        if ($wants_json) {
            importJsonResponse(['ok' => false, 'message' => 'Programma iniziale selezionato non valido'], 400);
        }
        http_response_code(400);
        echo "Programma iniziale selezionato non valido";
        exit;
    }
} else {
    $programma_iniziale = dbGetFirst("
        $programma_iniziale_select
        WHERE programmi_iniziali.id_classe = $classe_id
          AND programmi_iniziali.id_materia = $materia_id
          AND programmi_iniziali.id_anno_scolastico = $anno_scolastico_id
          AND programmi_iniziali.id_docente = $docente_id
        ORDER BY programmi_iniziali.updated DESC, programmi_iniziali.id DESC
    ");

    if ($programma_iniziale == null) {
        $programmi_iniziali_altri_docenti = dbGetAll("
            $programma_iniziale_select
            WHERE programmi_iniziali.id_classe = $classe_id
              AND programmi_iniziali.id_materia = $materia_id
              AND programmi_iniziali.id_anno_scolastico = $anno_scolastico_id
            ORDER BY programmi_iniziali.updated DESC, programmi_iniziali.id DESC
        ");

        if ($programmi_iniziali_altri_docenti != null && count($programmi_iniziali_altri_docenti) > 0) {
            $programmi = array_map('programmaInizialeImportLabel', $programmi_iniziali_altri_docenti);

            if ($wants_json) {
                importJsonResponse([
                    'ok' => false,
                    'needs_choice' => true,
                    'message' => 'Esistono programmi iniziali di altri docenti per questa classe e materia. Scegli quale importare.',
                    'programmi' => $programmi,
                ]);
            }

            http_response_code(409);
            echo "Esistono programmi iniziali di altri docenti per questa classe e materia. Scegli quale importare.";
            exit;
        }
    }
}

if ($programma_iniziale == null) {
    if ($wants_json) {
        importJsonResponse(['ok' => false, 'message' => 'Nessun programma iniziale trovato per classe e materia'], 404);
    }
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
    $id_autore = programmiUtenteAutoreDaDocente($docente_id, intval($__utente_id));

    if ($is_quinta) {
        $competenze = (string)fieldValue($row, 'COMPETENZE', 'competenze', '');
        $conoscenze = (string)fieldValue($row, 'CONOSCENZE', 'conoscenze', '');
        $abilita = (string)fieldValue($row, 'ABILITA', 'abilita', '');
        $contenuto = json_encode([
            'schema' => 'programma_svolto_quinta_v2',
            'competenze_raggiunte' => $competenze,
            'contenuti_trattati' => $conoscenze,
            'abilita' => $abilita,
            'competenze_raggiunte_html' => programmaSvoltoImportTextToRichHtml($competenze),
            'contenuti_trattati_html' => programmaSvoltoImportTextToRichHtml($conoscenze),
            'abilita_html' => programmaSvoltoImportTextToRichHtml($abilita),
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

if ($wants_json) {
    importJsonResponse([
        'ok' => true,
        'message' => 'Importazione completata',
        'programma_iniziale_id' => $programma_iniziale_id,
        'docente_origine' => programmaInizialeImportLabel($programma_iniziale)['docente'],
        'moduli_importati' => $nmoduli,
    ]);
}

echo $data;
?>
