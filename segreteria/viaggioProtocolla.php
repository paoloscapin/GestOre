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
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');

$viaggio_id = $_GET['viaggio_id'];

$viaggio = dbGetFirst("SELECT * FROM viaggio INNER JOIN docente ON viaggio.docente_id = docente.id WHERE viaggio.id = $viaggio_id;");
$destinazione = $viaggio['destinazione'];
$protocollo = $viaggio['protocollo'];
$docenteNomeCognome = $viaggio['nome'] . $viaggio['cognome'];
?>

<title><?php echo "P3 $destinazione $docenteNomeCognome"; ?></title>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
<!-- Custom JS file moved to the end -->

<style>
.file-upload {
    position: absolute;
    top: 0;
    left: 0;
    width:100%;
    height:100%;
    opacity: 0;
    cursor: pointer;
}

#progressBar {
	background-color: #3E6FAD;
	color: #ffffff;
	width: 0px;
	height: 30px;
	margin-top: 10px;
	margin-bottom: 10px;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
	-o-border-radius: 5px;
	border-radius: 5px;
	-moz-transition: .25s ease-out;
	-webkit-transition: .25s ease-out;
	-o-transition: .25s ease-out;
	transition: .25s ease-out;
}
</style>

</head>

<body >
<?php
require_once '../common/header-segreteria.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
    <div class="col-md-11">
			<span class="glyphicon glyphicon-folder-close"></span>&emsp;<strong><?php echo "Viaggio $destinazione di $docenteNomeCognome"; ?></strong>
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
    </div>
	</div>
</div>
<div class="panel-body">

    <div class="form-horizontal">
<?php

$infoValueTest = 'Test';
$fileUploadName = 'fileUploadName';
$fileUploadId = 'fileToUpload';
$fileUploadProgressBar = 'progressBar';
$fileNameId = 'filename';

$fileUploadFileNameValue = 'fileNameValue';
$fileUploadFilePathValue = 'filePathValue';

echo('
    <form id="upload-widget1" method="post" action="viaggioProtocollaUpload.php" enctype="multipart/form-data">
        <div class="input-group">
            <input type="hidden" name="info" value="'.$infoValueTest.'">
            <input type="text" class="form-control file-upload-text" disabled placeholder="seleziona il documento da caricare..." />
            <span class="input-group-btn">
                <button type="button" class="btn btn-info file-upload-btn"><span class="glyphicon glyphicon-folder-open"></span>
                    <input type="file" class="file-upload" name="'.$fileUploadName.'" id="'.$fileUploadId.'" />&nbsp;&nbsp;Seleziona
                </button>
                <!-- <button type="submit" class="btn btn-success"><span class="glyphicon glyphicon-open"></span> Carica</button> -->
            </span>
        </div>
    </form>
    <div id="'.$fileNameId.'"><b>&nbsp;</b></div>
    <div id="'.$fileUploadProgressBar.'" hidden="hidden"></div>
    <div id="'.$fileUploadFileNameValue.'" hidden="hidden"></div>
    <div id="'.$fileUploadFilePathValue.'" hidden="hidden"></div>
');

echo('</div>');
echo('</div>');
?>

</div>
</div>
</div>

<!-- <div class="panel-footer"></div> -->

</div>

</div>

</div>
<input type="hidden" id="hidden_viaggio_id" value='<?php echo $viaggio_id; ?>'>

<!-- Custom JS file -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/_util.js?v=<?php echo $__software_version; ?>"></script>

<script type="text/javascript" src="js/scriptViaggioProtocolla.js?v=<?php echo $__software_version; ?>"></script>

<script type="text/javascript"> setProtocollo("<?php echo($protocollo); ?>"); setViaggioId("<?php echo($viaggio_id); ?>"); </script>

<script type="text/javascript"></script>

<script type="text/javascript" src="../common/simpleUpload-1.1.0/js/simpleUpload.min.js"> </script>

</body>
</html>