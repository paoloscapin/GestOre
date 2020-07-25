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
	<title>GestOre admin</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('admin');

require_once '../common/_include_bootstrap-notify.php';
?>
<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>

<script type="text/javascript" src="js/annoScolastico.js"></script>
</head>

<?php
// prepara l'elenco degli anni scolastici successivi a quello corrente
$annoOptionList = '				<option value="0"></option>';
$query = "	SELECT * FROM anno_scolastico WHERE anno_scolastico.id > $__anno_scolastico_corrente_id ORDER BY anno_scolastico.id ASC;";
foreach(dbGetAll($query) as $annoRow) {
	$annoOptionList .= '<option value="'.$annoRow['id'].'">'.$annoRow['anno'].'</option>';
}
?>

<body >
<?php require_once '../common/header-admin.php'; ?>
<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<div class="panel panel-yellow4">
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>
	<a data-toggle="collapse" href="#collapse_40">&ensp;Annno Scolastico </a>
</div>
<div id="collapse_40" class="panel-collapse collapse  collapse in">
<div class="panel-body">
<div class="form-horizontal">

<div class="form-group">
    <input type="hidden" id="hidden_anno_scolastico_corrente_id" value="<?php echo $__anno_scolastico_corrente_id; ?>">
    <input type="hidden" id="hidden_anno_scolastico_corrente_anno" value="<?php echo $__anno_scolastico_corrente_anno; ?>">

    <h2 >Anno Scolastico corrente: <span class='annoCorrente' id="annoCorrente"><Strong><?php echo $__anno_scolastico_corrente_anno; ?></Strong></span></h2>
    
    <h2></h2>
    
    <div class="form-group anno_selector">
        <label class="col-sm-2 control-label" for="anno">Nuovo Anno Scolastico</label>
        <div class="col-sm-2">
            <select id="anno" name="anno" class="anno selectpicker" data-style="btn-success" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" data-selectOnTab="true" >
            <?php echo $annoOptionList ?>
            </select>
        </div>
    </div>

</div>
</div>
<hr>
<div class="text-center">
    <button type="button" class="btn btn-success" onclick="salvaAnnoScolastico()">Modifica Anno Scolastico</button>
</div>
</div>

</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

</div>

</body>
</html>