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

$programma_modulo_id = $_POST['programma_modulo_id'];
$classe_id = $_POST['classe_id'];
$materia_id = $_POST['materia_id'];

// recupero i dati della classe
$query = "SELECT * from classi WHERE id=$classe_id";
$result = dbGetFirst($query);
$anno_classe = $result['anno'];
$primo_indirizzo = $result['id_primo_indirizzo'];
$secondo_indirizzo = $result['id_secondo_indirizzo'];

// recupero il programma della materia
$query = "SELECT * from programma_materie WHERE anno=$anno_classe AND id_materia=$materia_id ";
$query2 = $query . "AND id_indirizzo=$secondo_indirizzo";

$query .= "AND id_indirizzo=$primo_indirizzo";

// cerco nel primo indirizzo
$result = dbGetFirst($query);
// altrimenti cerco nel secondo indirizzo
if ($result==null)
{
	$result = dbGetFirst($query2);
}
$programma_materia_id = $result['ID'];


// recupero i moduli della materia selezionata
$query = "SELECT * from programma_moduli WHERE id_programma=$programma_materia_id";
$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

// cancello se esistono i moduli (da cancellare)
$query = "DELETE from programmi_svolti_moduli WHERE ID_PROGRAMMA=$programma_modulo_id";
dbExec($query);

$nmoduli = 0;
foreach ($resultArray as $row) { {
		$nmoduli++;
		$id_programma = $programma_modulo_id;
		$ordine = $row['ORDINE'];
		$titolo = $row['NOME'];
		date_default_timezone_set("Europe/Rome");
    	$updated = date("Y-m-d H-i-s");
		$id_autore = $__utente_id;
		$contenuto = $row['CONOSCENZE'];
		$contenuto = str_replace('"',"''",$contenuto);
		
		// aggiungi il programma del modulo nella tabella dei moduli svolti 
		$query = "INSERT INTO programmi_svolti_moduli(id_programma,ordine,nome,contenuto,id_utente,updated) VALUES(\"$id_programma\", \"$ordine\", \"$titolo\", \"$contenuto\",'$id_autore','$updated')";
		dbExec($query);
		$idmodulo = dblastId();
		info("aggiunto programma modulo svolto id=$idmodulo  id_programma=$id_programma id_utente=$id_autore updated=$updated");

		$data='';
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
			<button onclick="moduloSvoltiGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="moduloSvoltiDelete(' . $idmodulo . ',\'' . $id_programma . '\',\'' . $titolo . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></button>
			';
		} else
			if (haRuolo('docente')) {
				if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
					if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
						$data .= '
  						<button onclick="moduloSvoltiGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></button>';
					} else {
						$data .= '
						<button onclick="moduloSvoltiGetDetails(' . $idmodulo . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></button>';
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

echo $data;
?>