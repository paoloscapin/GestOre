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
	<title>Modulistica Docenti</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');
?>

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
			<span class="glyphicon glyphicon-folder-close"></span>&emsp;<strong>Modulistica docenti</strong>
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
    <div class="col-md-12">
    <div class="table-wrapper"><table id="modulistica_docenti_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
        <th class="text-center col-md-12">Modulo</th>
		</tr>
    </thead>
    <tbody>
<?php

$openTabMode = getSettingsValue('interfaccia','apriModuloInNuovoTab', false) ? '_blank' : '_self';
$docente_id = $__docente_id;

foreach(dbGetAll("SELECT * FROM modulistica_template WHERE modulistica_template.valido = true ORDER BY posizione;") as $template) {
    $template_id = $template['id'];
    $templateNome = $template['nome'];

    $marker = '';

    echo '<tr>';
    echo '<td><a href="../docente/modulisticaCompilaModulo.php?docente_id='.$docente_id.'&template_id='.$template_id.'" target="'.$openTabMode.'">&ensp;'.$templateNome.' '.$marker.' </a></td>';
}
?>
        </tbody>
        </table>
        </div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>

</body>
</html>