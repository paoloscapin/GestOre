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
ruoloRichiesto('docente','segreteria-docenti','dirigente');

$docente_id = $_GET['docente_id'];
$template_id = $_GET['template_id'];
$template = dbGetFirst("SELECT * FROM modulistica_template WHERE id = $template_id;");
$templateNome = $template['nome'];
$templateEmailTo = $template['email_to'];
$templateTemplate = $template['template'];
?>

<title><?php echo "$templateNome"; ?></title>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<!-- Custom JS file moved to the end -->
</head>

<body >
<?php
require_once '../common/header-docente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
    <div class="col-md-11">
			<span class="glyphicon glyphicon-folder-close"></span>&emsp;<strong><?php echo "$templateNome"; ?></strong>
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
    </div>
	</div>
</div>
<div class="panel-body">

    <div class="form-horizontal">


<?php

// carica tutti i valori noti per questo docente
$valoriNoti = [];
foreach(dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $template_id;") as $campo) {
}

$listaCampi = [];
$listaCampiId = [];
$listaEtichette = [];
$listaValoriDefault = [];
$listaTipi = [];
$listaObbligatori = [];
foreach(dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $template_id;") as $campo) {
    $id = $campo['id'];
    $nome = $campo['nome'];
    $etichetta = $campo['etichetta'];
    $valore_default = $campo['valore_default'];
    $tip = $campo['tip'];
    $tipo = $campo['tipo'];
    $obbligatorio = $campo['obbligatorio'];

    echo('<div class="form-group">');
    echo('<label class="col-sm-2 control-label" for="'.$nome.'">'.$etichetta.'</label>');
    echo('<div class="col-sm-10">');
    if ($tipo == 1) {
        echo('<input type="text" id="'.$nome.'" placeholder="'.$tip.'" class="form-control"');
        if (array_key_exists($nome, $valoriNoti)) {
            echo(' value="'. $valoriNoti[$nome] . '"');
        }
        echo ('/>');
    } else if($tipo == 2) {

    }

    echo('</div>');
    echo('</div>');

    $listaCampiId[] = $id;
    $listaCampi[] = $nome;
    $listaEtichette[] = $etichetta;
    $listaValoriDefault[] = $valore_default;
    $listaTipi[] = $tipo;
    $listaObbligatori[] = $obbligatorio;
}
?>

</div>
</div>
</div>

<!-- <div class="panel-footer"></div> -->

<!-- altro pannello -->
<div class="panel panel-teal4">
<div class="panel-heading container-fluid">
	<div class="row">
    <div class="col-md-5">
		<span class="glyphicon glyphicon-folder-close"></span>&emsp;<strong><?php echo "$templateNome"; ?></strong>
	</div>
    <div class="col-md-2 text-center">
        <button type="button" class="btn btn-xs btn-default btn-yellow4" onclick="aggiorna()" >Aggiorna</button>
    </div>
	<div class="col-md-5 text-right" id="page_refresh">
    </div>
	</div>
</div>
<div class="panel-body" id="modulo_compilato_id">
<?php echo "$templateTemplate"; ?>

</div>
<div class="panel-footer">
<div class="text-center">
    <button type="button" class="btn btn-xs btn-default btn-orange4" onclick="invia()" id="inviaBtnId">Invia</button>
</div>
</div>
</div>
<!-- fine altro pannello -->

</div>

</div>

</div>
<input type="hidden" id="hidden_template" value="<?php echo $templateTemplate; ?>">
<input type="hidden" id="hidden_template_id" value='<?php echo $template_id; ?>'>
<input type="hidden" id="hidden_template_email_to" value='<?php echo $templateEmailTo; ?>'>
<input type="hidden" id="hidden_docente_id" value='<?php echo $docente_id; ?>'>
<input type="hidden" id="hidden_docente_cognome_e_nome" value='<?php echo($__docente_nome.' '.$__docente_cognome); ?>'>
<input type="hidden" id="hidden_docente_email" value='<?php echo $__docente_email; ?>'>

<input type="hidden" id="hidden_lista_campi_id" value='<?php echo(json_encode($listaCampiId)); ?>'>
<input type="hidden" id="hidden_lista_campi" value='<?php echo(json_encode($listaCampi)); ?>'>
<input type="hidden" id="hidden_lista_etichette" value='<?php echo(json_encode($listaEtichette)); ?>'>
<input type="hidden" id="hidden_lista_tipi" value='<?php echo(json_encode($listaTipi)); ?>'>
<input type="hidden" id="hidden_lista_obbligatori" value='<?php echo(json_encode($listaObbligatori)); ?>'>

<!-- Custom JS file -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/_util.js?v=<?php echo $__software_version; ?>"></script>
<script type="text/javascript" src="js/scriptCompilaModulo.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>