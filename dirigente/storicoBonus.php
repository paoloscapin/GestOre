<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html>
<head>

<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('dirigente');

if(! isset($_GET)) {
	return;
} else {
	$anno_id = $_GET['anno_id'];
}
$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");
echo '<title>Storico Bonus ' . $nome_anno_scolastico . '</title>';

?>

</head>

<body >

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<?php

function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

// calcola il totale degli assegnati
$totale_bonus_assegnato = dbGetValue("SELECT SUM(importo) FROM `bonus_assegnato` WHERE anno_scolastico_id = $anno_id;");
debug('totale_bonus_assegnato=' . $totale_bonus_assegnato);

// calcola il totale in punti finora approvati
$totale_valore_approvato = dbGetValue("SELECT SUM(valore_previsto) FROM bonus LEFT JOIN bonus_docente ON bonus.id = bonus_docente.bonus_id WHERE anno_scolastico_id = $anno_id AND approvato is true;");
debug('totale_valore_approvato=' . $totale_valore_approvato);

// importo totale disponibile per il bonus
$importo_totale_bonus = dbGetValue("SELECT bonus FROM importo WHERE anno_scolastico_id = $anno_id;");

// quello che non e' stato ancora assegnato resta da dividere tra quelli approvati
$importo_totale_bonus_approvato = $importo_totale_bonus - $totale_bonus_assegnato;
debug('importo_totale_bonus_approvato=' . $importo_totale_bonus_approvato);
if ($totale_valore_approvato != 0) {
    $importo_per_punto = $importo_totale_bonus_approvato / $totale_valore_approvato;
} else {
    $importo_per_punto = 0;
}
debug('importo_per_punto=' . $importo_per_punto);

// Intestazione pagina
$data = '';
$data = $data . '<h2 style="">Bonus Docenti anno scolastico '.$nome_anno_scolastico.'</h2>';

// cicla i docenti
foreach(dbGetAll("SELECT * FROM docente INNER JOIN ore_dovute ON ore_dovute.docente_id=docente.id WHERE ore_dovute.anno_scolastico_id=$anno_id AND ore_dovute.ore_40_totale>0 ORDER BY docente.cognome ASC, docente.nome ASC;") as $docente) {
	$docente_id = $docente['id'];
	$totaleDocente = 0;
	
	$data .= '<h3 style="page-break-before: always;">'.$docente['cognome'] . ' ' . $docente['nome'].'</h3>';
	$data .= '';

	// bonus assegnato
	$assegnatoList = dbGetAll("SELECT * FROM bonus_assegnato WHERE bonus_assegnato.anno_scolastico_id = $anno_id AND bonus_assegnato.docente_id = $docente_id");
	if (!empty($assegnatoList)) {
		$data .= '<h4>Bonus Assegnato</h4>';
		$data .= '<table class="table table-bordered table-striped table-green"><thead><tr><th class="col-sm-11">Commento</th><th class="text-center col-sm-1">Importo</th></tr></thead><tbody>';
		foreach($assegnatoList as $assegnato) {
			$data .= '<tr><td>'.$assegnato['commento'].'</td><td class="text-right funzionale">'.$assegnato['importo'].'</td></tr>';
			$totaleDocente = $totaleDocente + $assegnato['importo'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
	}

	// bonus
	$data .= '<h4>Bonus</h4>';

	$data .='<table class="table table-bordered table-striped table-green">';
	$data .='<thead><tr><th class="text-center col-sm-1">Codice</th><th class="text-center col-sm-8">Descrittore</th><th class="text-center col-sm-1">Valore</th><th class="text-center col-sm-1">Approvato</th><th class="text-center col-sm-1">Importo</th></tr>';
	$data .='</thead><tbody>';

	$query = "
	SELECT
		bonus_docente.id AS bonus_docente_id,
		bonus_docente.approvato AS bonus_docente_approvato,
		bonus_docente.rendiconto_evidenze AS bonus_docente_rendiconto_evidenze,
		bonus_docente.ultimo_controllo AS bonus_docente_ultimo_controllo,
		bonus_docente.ultima_modifica AS bonus_docente_ultima_modifica,
		
		bonus_area.codice AS bonus_area_codice,
		bonus_area.descrizione AS bonus_area_descrizione,
		bonus_area.valore_massimo AS bonus_area_valore_massimo,
		bonus_area.peso_percentuale AS bonus_area_peso_percentuale,
		
		bonus_indicatore.codice AS bonus_indicatore_codice,
		bonus_indicatore.descrizione AS bonus_indicatore_descrizione,
		bonus_indicatore.valore_massimo AS bonus_indicatore_valore_massimo,
		
		bonus.codice AS bonus_codice,
		bonus.descrittori AS bonus_descrittori,
		bonus.evidenze AS bonus_evidenze,
		bonus.valore_previsto AS bonus_valore_previsto
		
	FROM bonus_docente
	
	INNER JOIN bonus
	ON bonus_docente.bonus_id = bonus.id
	
	INNER JOIN bonus_indicatore
	ON bonus.bonus_indicatore_id = bonus_indicatore.id
	
	INNER JOIN bonus_area
	ON bonus_indicatore.bonus_area_id = bonus_area.id
	
	WHERE
		bonus_docente.docente_id = ".$docente['id']."
	AND
		bonus_docente.anno_scolastico_id = $__anno_scolastico_corrente_id
		
	ORDER BY
		bonus.codice;
	";
	$totale = 0;
	$resultArray2 = dbGetAll($query);
	foreach($resultArray2 as $bonus) {
		// calcola l'importo
		$importo = $importo_per_punto * $bonus['bonus_valore_previsto'];
		$data .= '<tr>
				<td class="text-left">'.$bonus['bonus_codice'].'</td>
				<td class="text-left">'.$bonus['bonus_descrittori'].'<hr><strong>Rendiconto:</strong></br>'.$bonus['bonus_docente_rendiconto_evidenze'].'</td>
				<td class="text-center">'.$bonus['bonus_valore_previsto'].'</td>
			';
			$data .= '<td class="text-center"><input type="checkbox" ';
			if ($bonus['bonus_docente_approvato']) {
				$data .= 'checked ';
			}
			$data .= '></td>';
			if ($bonus['bonus_docente_approvato']) {
				$data .= '<td class="text-right funzionale">'.formatNoZero($importo).'</td>';
				$totale = $totale + $importo;
			} else {
				$data .= '<td></td>';
			}

			$data .= '</tr>';
	}
	$data .='</tbody>';
	if ($totale > 0) {
		$data .='<tfooter>';
		$data .='<tr><td colspan="4" class="text-right"><strong>Totale approvato:</td><td class="text-right funzionale">' . formatNoZero($totale) . '</strong></td></tr>';
		$data .='</tfooter>';
	}
	$data .='</table>';

	$totaleDocente = $totaleDocente + $totale;

	$data .= '<p><strong>'.$docente['cognome'] . ' ' . $docente['nome'].': Totale da pagare = ' . number_format($totaleDocente,2) . ' €</strong></p>';
	$data .= '<hr>';
}

echo $data;
?>

</body>
</html>