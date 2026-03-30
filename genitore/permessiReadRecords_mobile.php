<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/**
 *  Versione MOBILE di GestOre - Permessi di uscita
 */


require_once '../common/checkSession.php';
require_once '../common/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("/error/unauthorized.php");
}

ruoloRichiesto('genitore', 'segreteria-didattica', 'dirigente');

function permessiFailUnauthorized()
{
    redirect("/error/unauthorized.php");
    exit;
}

function canGenitoreAccessStudente($idStudente, $idGenitore)
{
    $idStudente = (int)$idStudente;
    $idGenitore = (int)$idGenitore;

    if ($idStudente <= 0 || $idGenitore <= 0) {
        return false;
    }

    $q = "
        SELECT id_studente
        FROM genitori_studenti
        WHERE id_genitore = " . dbI($idGenitore) . "
          AND id_studente = " . dbI($idStudente) . "
        LIMIT 1
    ";

    $row = dbGetFirst($q);
    return is_array($row) && !empty($row['id_studente']);
}

function eh($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$studente_filtro_id = isset($_POST['studente_filtro_id']) ? (int)$_POST['studente_filtro_id'] : 0;
if ($studente_filtro_id <= 0) {
    permessiFailUnauthorized();
}

$__studente_id = 0;

if (impersonaRuolo('genitore')) {
    if (!canGenitoreAccessStudente($studente_filtro_id, (int)$__genitore_id)) {
        permessiFailUnauthorized();
    }
    $__studente_id = $studente_filtro_id;
} else {
    $__studente_id = $studente_filtro_id;
}

// Design iniziale container
$data = '<div class="cards-container">';

$query = "
SELECT 
    permessi_uscita.id,
    permessi_uscita.id_studente,
    permessi_uscita.id_genitore,
    permessi_uscita.data,
    permessi_uscita.ora_uscita,
    permessi_uscita.ora_rientro,
    permessi_uscita.rientro,
    permessi_uscita.motivo,
    permessi_uscita.stato,
    genitori.nome AS genitore_nome,
    genitori.cognome AS genitore_cognome,
    studente.nome AS studente_nome,
    studente.cognome AS studente_cognome,
    classi.classe AS classe,
    studente_frequenta.id_classe AS id_classe
FROM permessi_uscita
INNER JOIN genitori
    ON permessi_uscita.id_genitore = genitori.id
INNER JOIN studente_frequenta
    ON studente_frequenta.id_studente = permessi_uscita.id_studente
   AND studente_frequenta.id_anno_scolastico = " . dbI($__anno_scolastico_corrente_id) . "
INNER JOIN classi
    ON classi.id = studente_frequenta.id_classe
INNER JOIN studente
    ON permessi_uscita.id_studente = studente.id
WHERE studente.id = " . dbI($__studente_id) . "
ORDER BY permessi_uscita.data DESC, permessi_uscita.ora_uscita DESC
";

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

foreach ($resultArray as $row) {
    $id_permesso = $row['id'];

    // Formattazione data e ora
    $data_it = date('d/m/Y', strtotime($row['data']));
    $ora_uscita = date('H:i', strtotime($row['ora_uscita']));
    $ora_rientro = date('H:i', strtotime($row['ora_rientro']));
    $genitore_nome = eh($row['genitore_nome'] . ' ' . $row['genitore_cognome']);
    $studente_nome = eh($row['studente_nome'] . ' ' . $row['studente_cognome']);
    $motivo = eh($row['motivo']);

    // Badge per stato
    switch ($row['stato']) {
        case 1:
            $badge = '<span class="badge" style="background-color: yellow; color: black;">Richiesto</span>';
            break;
        case 2:
            $badge = '<span class="badge" style="background-color: green; color: white;">Confermato</span>';
            break;
        case 3:
            $badge = '<span class="badge" style="background-color: red; color: white;">Rifiutato</span>';
            break;
        case 4:
            $badge = '<span class="badge" style="background-color: red; color: white;">Assente</span>';
            break;
        default:
            $badge = '<span class="badge bg-secondary">Sconosciuto</span>';
    }

    $stato = $row['stato'];

    $data .= '<div class="card mb-2 p-2" style="border:1px solid #ddd; border-radius:10px; margin-top:8px; margin-bottom:8px; padding:12px;">
        <div><strong>Data:</strong> ' . $data_it . '</div>
        <div><strong>Ora uscita:</strong> ' . $ora_uscita . '</div>';
        if ($row['rientro']) {
            $data .= '
        <div><strong>Ora rientro:</strong> ' . $ora_rientro . '</div>';
        }
    $data .= '
        <div><strong>Studente:</strong> ' . $studente_nome . '</div>
        <div><strong>Genitore:</strong> ' . $genitore_nome . '</div>
        <div><strong>Motivo:</strong> ' . $motivo . '</div>
        <div><strong>Segreteria:</strong> ' . $badge . '</div>';

    if ($stato == 1) { // Solo se richiesto
        $data .= '<div class="mt-2 text-center" style="margin-top:10px;">
        <button onclick="permessiGetDetails(\'' . $id_permesso . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" title="Modifica la richiesta">
            <span class="glyphicon glyphicon-pencil"></span> Modifica
        </button>
        <button onclick="permessiDelete(\'' . $id_permesso . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" title="Cancella la richiesta">
            <span class="glyphicon glyphicon-trash"></span> Cancella
        </button>
    </div>';
    }

    $data .= '</div>'; // /card
}

$data .= '</div>'; // /cards-container

echo $data;
