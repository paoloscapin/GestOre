<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$programma_id = $_POST['programma_id'];
$classe_id = $_POST['classe_id'];
$materia_id = $_POST['materia_id'];

// recupero il programma della materia
$query = "SELECT * from programmi_svolti WHERE id_anno_scolastico=$__anno_scolastico_scorso_id AND id_materia=$materia_id AND id_classe=$classe_id ";

// cerco nel primo indirizzo
$result = dbGetFirst($query);
if ($result==null)
{
    echo json_encode(['status' => 'error', 'message' => 'Nessun programma svolto trovato per la materia selezionata nell\'anno scolastico precedente']);
    exit;
}
$programma_svolto_id = $result['id'];

// recupero i moduli della materia selezionata
$query = "SELECT * from programmi_svolti_moduli WHERE ID_PROGRAMMA=$programma_svolto_id";
$resultArray = dbGetAll($query);
if ($resultArray == null) {
    echo json_encode(['status' => 'error', 'message' => 'Nessun modulo trovato per il programma svolto selezionato']);
    exit;
}

// cancello se esistono i moduli (da cancellare)
$query = "DELETE from programmi_iniziali_moduli WHERE id_programma=$programma_id";
dbExec($query);
$data='';
$nmoduli = 0;
foreach ($resultArray as $row) { {
		$nmoduli++;
		$id_programma = $programma_id;
		$ordine = $row['ORDINE'];
		$titolo = $row['NOME'];
		date_default_timezone_set("Europe/Rome");
    	$updated = date("Y-m-d H-i-s");
		$id_autore = $__utente_id;
		$contenuto = $row['CONTENUTO'];
		$contenuto = str_replace('"',"''",$contenuto);
		
		// aggiungi il programma del modulo nella tabella dei moduli iniziali
		$query = "INSERT INTO programmi_iniziali_moduli(id_programma,ordine,nome,contenuto,id_utente,updated) VALUES(\"$id_programma\", \"$ordine\", \"$titolo\", \"$contenuto\",'$id_autore','$updated')";
		dbExec($query);
		$idmodulo = dblastId();
		info("aggiunto programma modulo iniziale id=$idmodulo  id_programma=$id_programma id_utente=$id_autore updated=$updated");

		$query = "SELECT utente.cognome,utente.nome from utente WHERE utente.id = " . $id_autore;
		$result = dbGetFirst($query);
		$autore = $result['cognome'] . " " . $result['nome'];
		$data .= '<tr>
		<td align="center">' . $ordine . '</td>
		<td align="center">' . $titolo . '</td>
		<td align="center">' . $autore . '</td>
		<td align="center">' . $updated . '</td>
		';
		$data .= '
		<td class="text-center">';

		if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
			$data .= '
			<button onclick="moduloInizialiGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="moduloInizialiDelete(' . $idmodulo . ',\'' . $id_programma . '\',\'' . $titolo . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></button>
			';
		} else
			if (haRuolo('docente')) {
				if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
					if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
						$data .= '
  						<button onclick="moduloInizialiGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></button>';
					} else {
						$data .= '
						<button onclick="moduloInizialiGetDetails(' . $idmodulo . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></button>';
					}
				}
			}

		$data .= '
		</td>
		</tr>';
	}
}

$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value=' . $nmoduli . '>';


echo json_encode([
    'status' => 'success',
    'html' => $data,
    'count' => $nmoduli
]);
?>