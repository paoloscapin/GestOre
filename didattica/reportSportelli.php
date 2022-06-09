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
require_once '../common/__Minuti.php';
ruoloRichiesto('segreteria-didattica,dirigente');

$anno_id = $__anno_scolastico_corrente_id;

$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");
echo '<title>Storico Sportelli ' . $nome_anno_scolastico.' - '.getSettingsValue('local','nomeIstituto', '') . '</title>';
?>
</head>

<body >

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<?php

function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatDate($value) {
	$dateFormatter = '%e %b %Y';
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
		$dateFormatter = '%#d %b %Y';
	}
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$result = utf8_encode(strftime($dateFormatter, strtotime($value)));
	setlocale(LC_TIME, $oldLocale);

    return $result;
}

// totali
$totaleOreSportelliIstituto = 0;

// tag
$accettato = '<td class="col-md-1 text-center"><span style="color:green !important;font-weight:bold">&#10004;</span></td>';
$contestataMarker = '<span style="color:red !important;font-weight:bold">&#10008;</span>';
$accettataMarker = '<span style="color:green !important;font-weight:bold">&#10004;</span>';

// Intestazione pagina
$dataContenuto = '';
$dataCopertina = '';
$dataConsuntivo = '';

// prima pagina
$dataCopertina .= '<h2 style="text-align: center; padding-bottom: 1cm;"><img style="text-align: center;" alt="" src="data:image/png;base64,'. base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'")).'" title=""></h2>';
$dataCopertina .= '<h3 style="text-align: center; padding-bottom: 3cm;">'.getSettingsValue('local','nomeIstituto', '').'</h3>';
$dataCopertina .= '<h2 style="text-align: center;">Report Sportelli anno scolastico '.$nome_anno_scolastico.'</h2>';

// cicla gli studenti
$query = "SELECT * FROM studente WHERE studente.classe <> '' ORDER BY studente.classe ASC, studente.cognome ASC, studente.nome ASC";

foreach(dbGetAll("SELECT * FROM studente WHERE studente.classe <> '' ORDER BY studente.classe ASC, studente.cognome ASC, studente.nome ASC;") as $studente) {

    // anche se non lo salto, controllo se effettivamente ci sta qualcosa di significativo
	$significativo = false;
    $studenteId = $studente['id'];
	$data = '';

	$data .= '<h2 style="page-break-before: always;text-align: center;">'.$studente['cognome'] . ' ' . $studente['nome'].' - '. $studente['classe'].'</h2>';
	$data .= '';

    $significativo = false;

    $query = "SELECT sportello_studente.argomento AS argomento_studente, materia.nome AS nome_materia, docente.cognome AS cognome_docente, docente.nome AS nome_docente, sportello.* FROM sportello_studente
        INNER JOIN sportello ON sportello_studente.sportello_id = sportello.id
        INNER JOIN docente ON sportello.docente_id = docente.id
        INNER JOIN materia ON sportello.materia_id = materia.id
        WHERE sportello_studente.studente_id = $studenteId
        AND sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
        AND sportello_studente.presente = 1
        ORDER BY materia.nome ASC, sportello.data ASC
    ";

    $sportelloList = dbGetAll($query);
    $oreSportelli = 0;
    if (!empty($sportelloList)) {
        $data .= '<h4 style="background-color: #9be3bf !important;">Sportelli</h4>';
		$data .= '<table class="table table-bordered table-striped table-green"><thead><tr><th class="col-md-3 text-left">Materia</th><th class="col-md-1 text-center">Data</th><th class="col-md-3 text-left">Docente</th><th class="col-md-4 text-center">Argomento</th><th class="col-md-1 text-center">Ore</th></tr></thead><tbody>';
		foreach($sportelloList as $sportello) {
			$data .= '<tr><td>'.$sportello['nome_materia'].'</td><td class="text-center">'.formatDate($sportello['data']).'</td><td>'.$sportello['nome_docente'].' '.$sportello['cognome_docente'].'</td><td>'.$sportello['argomento_studente'].'</td><td class="text-center">'.$sportello['numero_ore'].'</td></tr>';
			$oreSportelli = $oreSportelli + $sportello['numero_ore'];
		}
		$data .= '</tbody><tfooter>';
		$data .='<tr><td colspan="4" class="text-right"><strong>Totale ore sportelli:</strong></td><td class="text-center funzionale"><strong>' . $oreSportelli . '</strong></td></tr>';
		$data .='</tfooter></table>';
		$data .= '<hr>';
		$significativo = true;
        $totaleOreSportelliIstituto += $oreSportelli;
    }

	// se ha trovato qualcosa di significativo, include il docente nello storico
	if ($significativo) {
		$dataContenuto = $dataContenuto . $data;
	}
}

// stampa i totali di istituto
$dataConsuntivo .= '<hr style="page-break-before: always;">';
$dataConsuntivo .= '<h2 style="text-align: center; padding-top: 3cm; padding-bottom: 2cm;">Report Sportelli anno scolastico '.$nome_anno_scolastico.'</h2>';
$dataConsuntivo .= '<table class="table table-bordered table-striped table-green">';

$dataConsuntivo .= '<thead><tr><th class="col-md-11 text-left">Materia</th><th class="col-md-1 text-center">ore</th></tr></thead><tbody>';
$dataConsuntivo .= '<tr><td class="col-md-11 text-left">Totale Ore Sportelli</td><td class="col-md-1 text-right">' . $totaleOreSportelliIstituto . '</td></tr>';
$dataConsuntivo .= '</tbody></table>';
$dataConsuntivo .= '<hr>';

// copertina, consuntivo, poi tutto il resto

echo $dataCopertina;
echo $dataConsuntivo;
echo $dataContenuto;
?>

</body>
</html>
