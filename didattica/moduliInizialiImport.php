<?php
/**
 *  This file is part of GestOre
 *  @author     ...
 *  @license    GPL-3.0+
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=UTF-8');

$programma_id = $_POST['programma_id'] ?? null;
$classe_id    = $_POST['classe_id'] ?? null;
$materia_id   = $_POST['materia_id'] ?? null;

if (!$programma_id || !$classe_id || !$materia_id) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Parametri mancanti: programma_id, classe_id o materia_id.'
    ]);
    exit;
}

// 1) Classe
$query = "SELECT * FROM classi WHERE id=$classe_id";
$result = dbGetFirst($query);
if (!$result) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Classe non trovata.'
    ]);
    exit;
}
$anno_classe     = $result['anno'];
$primo_indirizzo = $result['id_primo_indirizzo'];
$secondo_indirizzo = $result['id_secondo_indirizzo'];

// 2) Programma materie: prova primo indirizzo, poi secondo
$base = "SELECT * FROM programma_materie WHERE anno=$anno_classe AND id_materia=$materia_id ";
$result = dbGetFirst($base . "AND id_indirizzo=$primo_indirizzo");
if (!$result && $secondo_indirizzo) {
    $result = dbGetFirst($base . "AND id_indirizzo=$secondo_indirizzo");
}
if (!$result) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Nessun programma di dipartimento trovato per la combinazione classe/materia selezionata.'
    ]);
    exit;
}
$programma_materia_id = $result['ID'];

// 3) Moduli del programma
$query = "SELECT * FROM programma_moduli WHERE id_programma=$programma_materia_id";
$resultArray = dbGetAll($query);
if (!$resultArray) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Nessun modulo definito nel programma di dipartimento selezionato.'
    ]);
    exit;
}

// 4) Svuota moduli iniziali esistenti del programma e inserisci i nuovi
dbExec("DELETE FROM programmi_iniziali_moduli WHERE id_programma=$programma_id");

$data    = '';
$nmoduli = 0;

// helper per escape semplice (se hai già una funzione, usa quella)
function esc($s) { return str_replace('"', "''", $s ?? ''); }

foreach ($resultArray as $row) {
    $nmoduli++;
    $id_programma = (int)$programma_id;
    $ordine       = (int)($row['ORDINE'] ?? $nmoduli);
    $titolo       = esc($row['NOME'] ?? '');
    date_default_timezone_set("Europe/Rome");
    $updated      = date("Y-m-d H-i-s");
    $id_autore    = $__utente_id ?? 0;

    $conoscenze   = esc($row['CONOSCENZE'] ?? ''); // 🟢 FIX: non usare $contenuto
    $abilita      = esc($row['ABILITA'] ?? '');
    $competenze   = esc($row['COMPETENZE'] ?? '');
    $periodo   = esc($row['PERIODO'] ?? '');

    $ins = "INSERT INTO programmi_iniziali_moduli
            (id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, updated)
            VALUES (\"$id_programma\", \"$ordine\", \"$titolo\", \"$conoscenze\", \"$abilita\", \"$competenze\", \"$periodo\", '$id_autore', '$updated')";
    dbExec($ins);
    $idmodulo = dblastId();
    info("aggiunto programma modulo iniziale id=$idmodulo id_programma=$id_programma");

    $authorRow = dbGetFirst("SELECT cognome, nome FROM utente WHERE id = $id_autore");
    $autore    = ($authorRow ? ($authorRow['cognome'].' '.$authorRow['nome']) : '');

    $data .= '<tr>
        <td align="center">'.$ordine.'</td>
        <td align="center">'.$titolo.'</td>
        <td align="center">'.$autore.'</td>
        <td align="center">'.$updated.'</td>
        <td class="text-center">';

    if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
        $data .= '
            <button onclick="moduloInizialiGetDetails('.$idmodulo.')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></span></button>
            <button onclick="moduloInizialiDelete('.$idmodulo.', \''.$id_programma.'\', \''.$titolo.'\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></span></button>
        ';
    } elseif (haRuolo('docente')) {
        if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
            if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
                $data .= '
                    <button onclick="moduloInizialiGetDetails('.$idmodulo.')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></span></button>';
            } else {
                $data .= '
                    <button onclick="moduloInizialiGetDetails('.$idmodulo.')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></span></button>';
            }
        }
    }

    $data .= '</td></tr>';
}

$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value="'.$nmoduli.'">';

echo json_encode([
    'status' => 'success',
    'html'   => $data,
    'count'  => $nmoduli
]);
