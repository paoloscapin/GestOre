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

function printableVoto($voto) {
	if ($voto != 0) {
		if ($voto == 1) {
			return 'Assente';
		}
		return $voto;
	}
	return null;
}

function printableDate($data) {
	if ($data != null) {
		return strftime("%d/%m/%Y", strtotime($data));
	}
	return null;
}

$studente_corso_id = $_GET["id"];
$query = "
			SELECT studente_per_corso_di_recupero.*,
				docente_voto_settembre.id AS docente_voto_settembre_id,
				docente_voto_settembre.nome AS docente_voto_settembre_nome,
				docente_voto_settembre.cognome AS docente_voto_settembre_cognome,
				docente_voto_novembre.id AS docente_voto_novembre_id,
				docente_voto_novembre.nome AS docente_voto_novembre_nome,
				docente_voto_novembre.cognome AS docente_voto_novembre_cognome,
				materia.nome AS materia_nome

			FROM
				`studente_per_corso_di_recupero` AS studente_per_corso_di_recupero

			LEFT JOIN docente as docente_voto_settembre
			ON docente_voto_settembre.id = studente_per_corso_di_recupero.docente_voto_settembre_id

			LEFT JOIN docente as docente_voto_novembre
			ON docente_voto_novembre.id = studente_per_corso_di_recupero.docente_voto_novembre_id

			INNER JOIN corso_di_recupero corso_di_recupero
			ON studente_per_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
			INNER JOIN materia materia
			ON corso_di_recupero.materia_id = materia.id

			WHERE studente_per_corso_di_recupero.id=$studente_corso_id";

$studente_corso = dbGetFirst($query);

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataLettera = utf8_encode( strftime("%d %B %Y", strtotime('today GMT')));
setlocale(LC_TIME, $oldLocale);

// calcola il voto (controlla se passato a settembre o novembre)
$voto = $studente_corso['voto_settembre'];
$data_voto = $studente_corso['data_voto_settembre'];
if ($studente_corso['voto_novembre'] > 0) {
	$voto = $studente_corso['voto_novembre'];
	$data_voto = $studente_corso['data_voto_novembre'];
}
$luogoIstituto = $__settings->local->luogoIstituto;
$superata = ($voto >= 6)? '<span class="c39" style="color:#08661a;">superata</span>' : '<span class="c39" style="color:#cc0000;">NON superata</span>';
?>

	<title><?php echo $studente_corso['cognome'] . " " . $studente_corso['nome']; ?> - Lettera Carenza <?php echo $studente_corso['materia_nome']; ?></title>
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
	</br>
	</br>
	<p class="c12">
		<span class="c9 c20">COMUNICAZIONE ESITO CARENZA ANNO SCOLASTICO PRECEDENTE</span>
	</p>
	</br>
	<p class="c12">
		<span class="c9 c20">&#x2161; sessione di recupero</span>
	</p>
	</br>
	</br>
	</br>
	</br>
	<table class="c11">
		<tbody>
			<tr class="c8">
				<td class="c2" colspan="1" rowspan="1"><p class="c6 c4 c21"><?php echo $luogoIstituto; ?>, <?php echo $dataLettera; ?>
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4 c21">Ai genitori dell’alunno/a</span>
					</p>
				</td>
			</tr>
			<tr class="c8">
				<td class="c2" colspan="1" rowspan="1"><p class="c6">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4 c21"><strong><?php echo $studente_corso['cognome'] . " " . $studente_corso['nome']; ?></strong></span>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	</br>
	</br>
	</br>
	</br>
	<p class="c3">
		<span class="c1 c21">
		Il Consiglio di classe comunica che, a seguito della
		<span class="c5 c21">Seconda Sessione</span>
		 di prova di recupero della carenza maturata alla fine dell&#39;anno scolastico scorso, l&#39;alunno/a ha conseguito i seguenti risultati:
		</span>
	</p>
	</br>
	</br>
		<table class="c31">
			<tbody>
				<tr class="c33">
					<td class="c34" colspan="1" rowspan="1">
						<p class="c32"><span class="c38">Materia</span></p>
					</td>
					<td class="c35" colspan="1" rowspan="1">
						<p class="c32"><span class="c38">Data</span></p>
					</td>
					<td class="c36" colspan="1" rowspan="1">
						<p class="c32 c100C"><span class="c38">Esito</span></p>
					</td>
					<td class="c37" colspan="1" rowspan="1">
						<p class="c32 c100C"><span class="c38">Voto</span></p>
					</td>
				</tr>
				<tr class="c33">
					<td class="c34" colspan="1" rowspan="1">
						<p class="c40"><span class="c39"><?php echo $studente_corso['materia_nome']; ?></span></p>
					</td>
					<td class="c35" colspan="1" rowspan="1">
						<p class="c40"><span class="c39"><?php echo printableDate($data_voto); ?></span></p>
					</td>
					<td class="c36" colspan="1" rowspan="1">
						<p class="c40 c100C"><?php echo $superata; ?></p>
					</td>
					<td class="c37 colspan="1" rowspan="1">
						<p class="c40 c100C"><span class="c39"><?php echo printableVoto($voto); ?></span></p>
					</td>
				</tr>
			</tbody>
		</table>

	</br>
	</br>
	</br>
	</br>
	</br>
	<a id="t.f727949b760321cc972232d42b2d9fa1f8785d82"></a>
	<a id="t.1"></a>
	<table class="c11 c1 c21">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">Il coordinatore del Consiglio di Classe
				</td>
			</tr>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<?php
					if(getSettingsValue('config','corsiDiRecuperoFirmaDocente', true)) {
						echo $__docente_nome . ' ' . $__docente_cognome;
					} else {
						echo ' ';
					}
					?>
				</td>
			</tr>
		</tbody>
	</table>
<script>
// function myFunction() {
//     window.print();
// }
// myFunction();
</script>
</body>
</html>
