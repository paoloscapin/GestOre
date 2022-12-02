<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<html>
<head>
<?php
require_once '../common/checkSession.php';
ruoloRichiesto('docente','dirigente','segreteria-docenti');
require_once '../common/connect.php';
require_once '../common/header-common.php';

if(! isset($_GET)) {
	return;
} else {
	$piano_di_lavoro_id = $_GET['piano_di_lavoro_id'];
}

// recupera dal db i dati di questo piano di lavoro
$query = "	SELECT
				piano_di_lavoro.id AS piano_di_lavoro_id, piano_di_lavoro.*, materia.nome AS materia_nome,
				docente.id AS docente_id, docente.cognome AS docente_cognome, docente.nome AS docente_nome,
				indirizzo.nome_breve AS indirizzo_nome_breve, indirizzo.nome AS indirizzo_nome,
				anno_scolastico.anno AS anno_scolastico_anno, anno_scolastico.id AS anno_scolastico_id FROM piano_di_lavoro piano_di_lavoro
			INNER JOIN docente docente
			ON piano_di_lavoro.docente_id = docente.id
			INNER JOIN materia materia
			ON piano_di_lavoro.materia_id = materia.id
			INNER JOIN indirizzo indirizzo
			ON piano_di_lavoro.indirizzo_id = indirizzo.id
			INNER JOIN anno_scolastico anno_scolastico
			ON piano_di_lavoro.anno_scolastico_id = anno_scolastico.id
			WHERE piano_di_lavoro.id = $piano_di_lavoro_id
			";

$pianoDiLavoro = dbGetFirst($query);

$nomeClasse = $pianoDiLavoro['classe'] . $pianoDiLavoro['indirizzo_nome_breve'] . $pianoDiLavoro['sezione'];
$nomeCognomeDocente = $pianoDiLavoro['docente_nome'] . ' ' . $pianoDiLavoro['docente_cognome'];
$annoScolasticoNome = $pianoDiLavoro['anno_scolastico_anno'];
$materiaNome = $pianoDiLavoro['materia_nome'];
$competenze = $pianoDiLavoro['competenze'];

// controllo lo stato
$statoMarker = '';
if ($pianoDiLavoro['stato'] == 'draft') {
	$statoMarker .= '<span class="label label-warning">draft</span>';
} elseif ($pianoDiLavoro['stato'] == 'annullato') {
	$statoMarker .= '<span class="label label-danger">annullato</span>';
} elseif ($pianoDiLavoro['stato'] == 'finale') {
	$statoMarker .= '<span class="label label-success">finale</span>';
} elseif ($pianoDiLavoro['stato'] == 'pubblicato') {
	$statoMarker .= '<span class="label label-info">pubblicato</span>';
}

// controlla se e' un template
$templateMarker = ($pianoDiLavoro['template'] == true)? '<span class="label label-success">Template</span>' : '';


echo '<title>Piano di Lavoro  ' . $nomeClasse.' - '. $annoScolasticoNome . '</title>';

?>

<meta content="text/html; charset=UTF-8" http-equiv="content-type">

<style>
h1,h2,h3,h4,h5 {
  color: #0e2c50;
  font-family: Helvetica, Sans-Serif;
}

.unita_titolo {display:inline-block; vertical-align: middle;}

body {
    max-width: 800px;
}

</style>

</head>

<body>
	<div style="; text-align: center;">

			<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 642.82px; height: 136.00px;">
				<img alt="" src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'")); ?>" style="width: 642.82px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
			</span>

	</div>

<h1 style="text-align: center;">Piano di lavoro</h1>
<table style="width: 100%; border-collapse: collapse; border-style: none; border=0">
<tbody>
<tr>
<td style="width: 18%;">
<h3 style="text-align: center;"><strong><?php echo $nomeClasse;?></strong></h3>
</td>
<td style="width: 64%;">
<h2 style="text-align: center;"><strong><?php echo $materiaNome;?></strong></h2>
</td>
<td style="width: 18%;">
<h3 style="text-align: center;"><strong><?php echo $annoScolasticoNome;?></strong></h3>
</td>
</tr>
</tbody>
</table>
<p style="text-align: center;">Docente: <?php echo $nomeCognomeDocente;?></p>
<p>&nbsp;</p>

<hr>
<h2 style="text-align: center;">COMPETENZE</h2>
<?php echo $competenze; ?>
<p>&nbsp;</p>

<hr>
<h2 style="text-align: center;">UNIT&Agrave; DIDATTICHE</h2>
<p>&nbsp;</p>

<?php

$query = "	SELECT
				piano_di_lavoro_contenuto.id AS piano_di_lavoro_contenuto_id,
				piano_di_lavoro_contenuto.titolo AS piano_di_lavoro_contenuto_titolo,
				piano_di_lavoro_contenuto.testo AS piano_di_lavoro_contenuto_testo,
				piano_di_lavoro_contenuto.posizione AS piano_di_lavoro_contenuto_posizione
			FROM piano_di_lavoro_contenuto
			WHERE piano_di_lavoro_id = $piano_di_lavoro_id
			ORDER BY piano_di_lavoro_contenuto.posizione ASC;";

$data = '';

foreach(dbGetAll($query) as $row) {
	$piano_di_lavoro_contenuto_posizione = $row['piano_di_lavoro_contenuto_posizione'];
	$piano_di_lavoro_contenuto_titolo = $row['piano_di_lavoro_contenuto_titolo'];
	$piano_di_lavoro_contenuto_testo = $row['piano_di_lavoro_contenuto_testo'];

    $data .= '
    <table style="border-collapse: collapse; width: 100%; border=1">
    <tbody>
    <tr>
    <td style="width: 6%;text-align: center;">
    <h2 class="unita_titolo">&nbsp;'.$piano_di_lavoro_contenuto_posizione.'</h2>
    </td>
    <td style="width: 94%;">
    <h2 class="unita_titolo">'.$piano_di_lavoro_contenuto_titolo.'</h2>
    </td>
    </tr>
    </tbody>
    </table>
    </br>
    <table style="border-collapse: collapse; width: 100%; border=0">
    <tbody>
    <tr>
    <td style="width: 6%;">&nbsp;</td>
    <td style="width: 94%;">
    '.$piano_di_lavoro_contenuto_testo.'
    </td>
    </tr>
    </tbody>
    </table>
    <p>&nbsp;</p>
        ';
}

echo $data;

// le metodologie se presenti
$data = '';
$metodologieList = dbGetAll("SELECT * FROM piano_di_lavoro_metodologia INNER JOIN piano_di_lavoro_usa_metodologia ON piano_di_lavoro_metodologia.id = piano_di_lavoro_usa_metodologia.piano_di_lavoro_metodologia_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
if (! empty ($metodologieList)) {
    $data .= '
	<hr>
	<h2 style="text-align: center;">METODOLOGIE</h2>

    <table style="border-collapse: collapse; width: 100%;">
    <tbody>';

	foreach($metodologieList as $metodologia) {
		$data .= '
		<tr padding-top: 50px;>
		<td style="width: 25%; padding-top: 30px; padding-bottom: 30px; text-align: right; padding-right: 30px; vertical-align: top;"><h5 style="text-transform:uppercase;">'.$metodologia['nome'].'</h5></td>
		<td style="width: 75%; padding-top: 30px; padding-bottom: 30px; vertical-align: top;">'.$metodologia['descrizione'].'</td>
		</tr>';
	}


	$data .= '
		</tbody>
		</table>
		</br>
		<p>&nbsp;</p>
        ';

	echo $data;

}

// TIC se presenti
$data = '';
$ticList = dbGetAll("SELECT * FROM piano_di_lavoro_tic INNER JOIN piano_di_lavoro_usa_tic ON piano_di_lavoro_tic.id = piano_di_lavoro_usa_tic.piano_di_lavoro_tic_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
if (! empty ($ticList)) {
    $data .= '
	<hr>
	<h2 style="text-align: center;">TIC</h2>

    <table style="border-collapse: collapse; width: 100%;">
    <tbody>';

	foreach($ticList as $tic) {
		$data .= '
		<tr padding-top: 50px;>
		<td style="width: 25%; padding-top: 30px; padding-bottom: 30px; text-align: right; padding-right: 30px; vertical-align: top;"><h5 style="text-transform:uppercase;">'.$tic['nome'].'</h5></td>
		<td style="width: 75%; padding-top: 30px; padding-bottom: 30px; vertical-align: top;">'.$tic['descrizione'].'</td>
		</tr>';
	}


	$data .= '
		</tbody>
		</table>
		</br>
		<p>&nbsp;</p>
        ';

	echo $data;

}

?>

</body>
</html>


