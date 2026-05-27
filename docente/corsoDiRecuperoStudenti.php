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
ruoloRichiesto('segreteria-didattica', 'dirigente');

if(! isset($_GET)) {
	return;
} else {
	$corso_di_recupero_id = $_GET['corso_di_recupero_id'];
}

// recupera dal db i dati di questo corso di recupero
$query = "	SELECT corso_di_recupero.id AS corso_di_recupero_id, corso_di_recupero.*, materia.nome AS materia_nome, docente.nome AS docente_nome, docente.cognome AS docente_cognome FROM corso_di_recupero
			INNER JOIN materia materia ON corso_di_recupero.materia_id = materia.id INNER JOIN docente docente ON corso_di_recupero.docente_id = docente.id
            WHERE corso_di_recupero.id = $corso_di_recupero_id; ";
$corsoDiRecupero = dbGetFirst($query);

$nomeMateria = $corsoDiRecupero['materia_nome'];
$nomeCognomeDocente = $corsoDiRecupero['docente_nome'] . ' ' . $corsoDiRecupero['docente_cognome'];
$codice = $corsoDiRecupero['codice'];

echo '<title>' . $codice . ' - '. $nomeCognomeDocente . '</title>';
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
</head>

<body >
<?php
	require_once '../common/header-segreteria.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-2">
			<span class="glyphicon glyphicon-file"></span>&ensp;Studenti Corso di Recupero
		</div>
		<div class="col-md-3 text-left">
			<strong>Codice: <?php echo $codice; ?></strong>
		</div>
		<div class="col-md-3 text-left">
			Materia: <?php echo $nomeMateria; ?>
		</div>
		<div class="col-md-3 text-center">
			Docente: <?php echo $nomeCognomeDocente; ?>
		</div>
		<div class="col-md-1 text-right">
            <div class="pull-right">
				<button class="btn btn-xs btn-orange4" onclick="corsoDiRecuperoStudentiGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
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

<div class="modal fade" id="corso_di_recupero_studenti_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-orange4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Studente</h5>
			</div>
			<div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="cognome">Cognome</label>
                    <div class="col-sm-6"><input type="text" id="cognome" placeholder="cognome" class="form-control"/></div>
                </div>
            </div>
			<div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="nome">Nome</label>
                    <div class="col-sm-6"><input type="text" id="nome" placeholder="nome" class="form-control"/></div>
                </div>
            </div>
			<div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classe">Classe</label>
                    <div class="col-sm-3"><input type="text" id="classe" placeholder="classe" class="form-control"/></div>
                </div>
            </div>
			<div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="email">Email</label>
                    <div class="col-sm-8"><input type="text" id="email" placeholder="email" class="form-control"/></div>
                </div>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="corsoDiRecuperoStudentiSave()" >Salva</button>
				<input type="hidden" id="hidden_corso_di_recupero_studenti_id">
				<input type="hidden" id="hidden_corso_di_recupero_id" value="<?php echo $corso_di_recupero_id; ?>">
            </div>
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Update docente details -->

<!-- Custom JS file -->
<script type="text/javascript" src="js/corsoDiRecuperoStudenti.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>