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
	ruoloRichiesto('dirigente','segreteria-docenti');
	require_once '../common/connect.php';

	$viaggio_id = $_GET["viaggio_id"];
	$query = "
				SELECT
					viaggio.id AS viaggio_id,
					viaggio.protocollo AS viaggio_protocollo,
					viaggio.tipo_viaggio AS viaggio_tipo_viaggio,
					viaggio.data_nomina AS viaggio_data_nomina,
					viaggio.data_partenza AS viaggio_data_partenza,
					viaggio.data_rientro AS viaggio_data_rientro,
					viaggio.ora_partenza AS viaggio_ora_partenza,
					viaggio.ora_rientro AS viaggio_ora_rientro,
					viaggio.destinazione AS viaggio_destinazione,
					viaggio.classe AS viaggio_classe,
					viaggio.note AS viaggio_note,
					viaggio.stato AS viaggio_stato,
					docente.cognome AS docente_cognome,
					docente.nome AS docente_nome
				FROM viaggio viaggio
				INNER JOIN docente docente
				ON viaggio.docente_id = docente.id
				WHERE viaggio.id = '$viaggio_id'";

	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}

	$response = array();
	$row = array();
	if(mysqli_num_rows($result) > 0) {
		$row = mysqli_fetch_assoc($result);
	}
	else {
		$response['status'] = 200;
		$response['message'] = "Data not found!";
	}

	$tipoViaggio = "";
	$tipoViaggioNoArticolo = "";
	if ($row['viaggio_tipo_viaggio'] === 'Uscita Formativa') {
	    $tipoViaggio = "l'uscita formativa";
	    $tipoViaggioNoArticolo = "uscita formativa";
	} else if ($row['viaggio_tipo_viaggio'] === 'Visita Guidata') {
	    $tipoViaggio = "la visita guidata";
	    $tipoViaggioNoArticolo = "visita guidata";
	} else if ($row['viaggio_tipo_viaggio'] === 'Viaggio di Istruzione') {
	    $tipoViaggio = "il viaggio d'istruzione";
	    $tipoViaggioNoArticolo = "viaggio d'istruzione";
	}

	$note = $row['viaggio_note'];

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataNomina = utf8_encode( strftime("%d %B %Y", strtotime($row['viaggio_data_nomina'])));
	$dataPartenza = utf8_encode( strftime("%d %B %Y", strtotime($row['viaggio_data_partenza'])));
	$dataRientro = utf8_encode( strftime("%d %B %Y", strtotime($row['viaggio_data_rientro'])));
	setlocale(LC_TIME, $oldLocale);
	$luogoIstituto = $__settings->local->luogoIstituto;
	echo '<title>';
	echo 'nomina prot '.str_replace('/','-',$row['viaggio_protocollo']).' '.$row['docente_cognome'].' '.$row['docente_nome'].' - '.$row['viaggio_destinazione'];
	echo '</title>';
?>
	<meta content="text/html; charset=UTF-8" http-equiv="content-type">
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/template-nomina.css">
</head>
<body class="c13">
	<div>
		<p class="c7">
			<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 642.82px; height: 136.00px;">
				<img alt="" src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'")); ?>" style="width: 642.82px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
			</span>
		</p>
	</div>
	<p class="c6 c10">
		<span class="c4"></span>
	</p>
	<a id="t.2983f64f2b1f99c5e93bd72decaa07795be55513"></a>
	<a id="t.0"></a>
	<table class="c11">
		<tbody>
			<tr class="c8">
				<td class="c2" colspan="1" rowspan="1"><p class="c6">
						<span class="c9">Prot. n&deg; <?php echo $row['viaggio_protocollo']; ?></span>
					</p></td>
				<td class="c2" colspan="1" rowspan="1"><p class="c6 c14">
						<span class="c4"><?php echo $luogoIstituto; ?>, <?php echo $dataNomina; ?></span>
					</p></td>
			</tr>
		</tbody>
	</table>
	<table class="c11">
		<tbody>
			<tr class="c8">
				<td class="c2" colspan="1" rowspan="1"><p class="c6">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4">Prof. <?php echo $row['docente_nome']; ?> <?php echo $row['docente_cognome']; ?></span>
					</p>
					<p class="c6 c14">
						<span class="c4"><strong>SEDE</strong></span>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	</br>
	</br>
	<p class="c3">
		<span class="c9">OGGETTO: conferimento incarico.</span>
	</p>
	<p class="c10 c12">
		<span class="c9"></span>
	</p>
	</br>
	</br>
	<p class="c12">
		<span class="c9">IL DIRIGENTE SCOLASTICO</span>
	</p>
	</br>
	<p class="c3">
		<span class="c5">VISTA </span><span class="c1">la disponibilit&agrave; della S.V. ad accompagnare in <?php echo $tipoViaggioNoArticolo; ?>;</span>
	</p>
	<p class="c3">
		<span class="c5">CONSIDERATO </span><span class="c1">che l&#39;attivit&agrave; sotto descritta &egrave; stata approvata dal consiglio di classe;</span>
	</p>
	<p class="c3">
		<span class="c5 c16">VISTA la delibera del consiglio dell&#39;Istituzione di approvazione del REGOLAMENTO PER L&rsquo;ORGANIZZAZIONE DI VISITE GUIDATE E VIAGGI DI ISTRUZIONE</span>
	</p>
	<p class="c3">
		<span class="c5">TENUTO CONTO </span><span class="c1">che <?php echo $tipoViaggio; ?> a <?php echo $row['viaggio_destinazione']; ?> sar&agrave; effettuato/a per le classi <?php echo $row['viaggio_classe']; ?></span>
	</p>
	<p class="c3">
		<span class="c1">con partenza alle ore <?php echo $row['viaggio_ora_partenza']; ?> del <?php echo $dataPartenza; ?></span>
	</p>
	<p class="c3">
		<span class="c1">e ritorno alle ore <?php echo $row['viaggio_ora_rientro']; ?> del <?php echo $dataRientro; ?></span>
	</p>
	<p class="c3">
		<span class="c5">TENUTO CONTO</span><span class="c1">&nbsp;che si autorizza l&#39;impegno FUIS, ovvero si prevede il riconoscimento delle ore nella misura prevista dalle vigenti disposizioni contrattuali, per complessivi n&deg; 1 accompagnatori</span>
	</p>
	<p class="c3">
		<span class="c5">VISTO </span><span class="c1">il CCPL vigente;</span>
	</p>
	</br>
	<p class="c12">
		<span class="c9">CONFERISCE</span>
	</p>
	</br>
	<p class="c3">
		<span class="c1">alla S.V. l&rsquo;incarico di accompagnatore degli studenti durante la predetta visita a <?php echo $row['viaggio_destinazione']; ?></span>
	</p>
	<p class="c3">
		<span class="c1">Detto incarico comporta l&rsquo;assunzione di responsabilit&agrave;, ai sensi dell&rsquo;art. 2047 CC, e quindi l&rsquo;obbligo</span>
	</p>
	<p class="c3">
		<span class="c1">di attenta e assidua vigilanza degli alunni, esercitata a tutela dell&rsquo;incolumit&agrave; degli stessi e del patrimonio</span>
	</p>
	<p class="c3">
		<span class="c1">artistico.</span>
	</p>
	<p class="c3">
		<span class="c1">Il dovere di vigilanza va esercitato per la durata dell&#39;attivit&agrave;, nei limiti esplicitati nella nota illustrativa dell&#39;attivit&agrave;</span>
	</p>
	<p class="c3">
		<span class="c1">e nelle dichiarazioni di responsabilit&agrave; sottoscritte dai genitori.</span>
	</p>
	<p class="c3">
		<span class="c1">La S.V. &egrave; tenuta ad informare il ds su eventuali anomalie, con riferimento ai servizi acquistati (vettore, vitto,</span>
	</p>
	<p class="c3">
		<span class="c1">alloggio, ecc.) prima della partenza, durante l&#39;attivit&agrave;, nonch&eacute; successivamente alla stessa.</span>
	</p>
	</br>
<?php 
if ($note != null && strlen($note) > 0) {
    echo '<p class="c3">';
    echo '<span class="c5">NOTE: </span><span class="c1">'.$note.'</span>';
    echo '</p>';
    echo '</br>';
}
?>
	<a id="t.f727949b760321cc972232d42b2d9fa1f8785d82"></a>
	<a id="t.1"></a>
	<table class="c11">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
				</td>
			</tr>
		</tbody>
	</table>
	<table class="c11">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
						<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 144.00px; height: 142.00px;">
							<img alt="" src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'timbro.png'")); ?>" style="width: 144.00px; height: 142.00px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
						</span>
					</p></td>
				<td class="c2" colspan="1" rowspan="1"><p class="c19">
						<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 248.00px; height: 98.00px;">
							<img alt="" src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'firma.png'")); ?>" style="width: 248.00px; height: 98.00px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
						</span>
					</p></td>
			</tr>
		</tbody>
	</table>
</body>
</html>


<?php



?>