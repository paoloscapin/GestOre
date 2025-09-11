<?php

/**
 *  Versione MOBILE di GestOre - Permessi di uscita
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$__studente_id = $studente_filtro_id;

// Design iniziale container
$data = '<div class="cards-container">';

$query = "SELECT 
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
INNER JOIN genitori ON permessi_uscita.id_genitore = genitori.id
INNER JOIN studente_frequenta ON studente_frequenta.id_studente = permessi_uscita.id_studente AND studente_frequenta.id_anno_scolastico = '$__anno_scolastico_corrente_id'
INNER JOIN classi ON classi.id = studente_frequenta.id_classe
INNER JOIN studente ON permessi_uscita.id_studente = studente.id
WHERE studente.id='$__studente_id'";

$resultArray = dbGetAll($query);
if ($resultArray == null) $resultArray = [];

foreach ($resultArray as $row) {
    $id_permesso = $row['id'];
    $id_genitore = $row['id_genitore'];
    $genitore_nome = $row['genitore_nome'] . ' ' . $row['genitore_cognome'];
    $studente_nome = $row['studente_nome'] . ' ' . $row['studente_cognome'];

    // Formattazione data e ora
    $data_it = date('d/m/Y', strtotime($row['data']));
    $ora_uscita = date('H:i', strtotime($row['ora_uscita']));
    $ora_rientro = date('H:i', strtotime($row['ora_rientro']));

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

    $motivo = $row['motivo'];
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
        <div><strong>Motivo:</strong> ' . htmlspecialchars($motivo) . '</div>
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
