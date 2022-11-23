<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html lang="it">
<head>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" integrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">

<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-notify.php';
require_once '../common/_include_summernote.php';
ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

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

echo '<title>Piano di Lavoro  ' . $nomeClasse . ' - '. $materiaNome . ' - ' . $annoScolasticoNome . '</title>';

?>

</head>

<body >
<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<?php
	require_once '../common/header-docente.php';

// prima pagina
$dataCopertina = '';
$dataCopertina .= '<h2 style="text-align: center; padding-bottom: 1cm;"><img style="text-align: center;" alt="" src="data:image/png;base64,' . base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'")) . '" title=""></h2>';
$dataCopertina .= '<h3 style="text-align: center; padding-bottom: 3cm;">' . getSettingsValue('local','nomeIstituto', '') . '</h3>';
$dataCopertina .= '<h2 style="text-align: center;">Piano di lavoro di ' . $pianoDiLavoro['materia_nome'] . ' - anno scolastico ' . $pianoDiLavoro['anno_scolastico_anno'] . '</h2>';
$dataCopertina .= '<h3 style="text-align: center;">docente: ' . $nomeCognomeDocente . ' anno scolastico '.$pianoDiLavoro['anno_scolastico_anno'] . '</h3>';
$dataCopertina .= '<h3 style="text-align: center;">stato: ' . $pianoDiLavoro['stato'] . '</h3>';

?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-teal4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-1">
			<span class="glyphicon glyphicon-file"></span>&ensp;Piano di lavoro
		</div>
		<div class="col-md-2 text-left">
			<strong><?php echo $materiaNome; ?></strong>
		</div>
		<div class="col-md-1 text-left">
			Classe: <strong><?php echo $nomeClasse; ?></strong>
		</div>
		<div class="col-md-2 text-left">
			Docente: <?php echo $nomeCognomeDocente; ?>
		</div>
		<div class="col-md-2 text-center">
			Anno scolastico: <?php echo $annoScolasticoNome; ?>
		</div>
		<div class="col-md-2 text-center">
			Stato: <?php echo ($statoMarker . '&nbsp;' . $templateMarker); ?>
		</div>
		<div class="col-md-1 text-center">
		<div class="pull-right">
			<button class="btn btn-xs btn-teal4" onclick="pianoDiLavoroPreview(<?php echo $piano_di_lavoro_id; ?>)" ><span class="glyphicon glyphicon-blackboard"></span>&ensp;preview</button>
        </div>
		</div>
		<div class="col-md-1 text-right">
            <div class="pull-right">
				<button class="btn btn-xs btn-teal4" onclick="pianoDiLavoroDocumentoGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
            </div>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
            <div class="records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

</div>

<div class="modal fade" id="piano_di_lavoro_documento_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Sezione</h5>
			</div>
			<div class="panel-body">
                <div class="form-group">
                    <label for="titolo">Titolo</label>
                    <input type="text" id="titolo" placeholder="titolo" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="testo">Testo</label>
					<div class="summernote" rows="10" id="testo" placeholder="testo" ></div>
                </div>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="pianoDiLavoroDocumentoSave()" >Salva</button>
				<input type="hidden" id="hidden_piano_di_lavoro_documento_id">
				<input type="hidden" id="hidden_piano_di_lavoro_id" value="<?php echo $piano_di_lavoro_id; ?>">
            </div>
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Update docente details -->

<!-- Custom JS file -->
<script type="text/javascript" src="js/pianoDiLavoroDocumento.js"></script>

</body>
</html>
