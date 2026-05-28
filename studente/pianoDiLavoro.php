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
	<title>Piano di Lavoro</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
ruoloRichiesto('studente','segreteria-didattica','dirigente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<!-- Custom JS file moved to the end -->

<style>
    .icon-play{
        background-image : url('../img/pdf-256.png');
        background-size: cover;
        display: inline-block;
        height: 16px;
        width: 16px;
    }
</style>

<script>
function pianoDiLavoroSavePdf(piano_di_lavoro_id) {
    window.open('/GestOre/docente/pianoDiLavoroPreview.php?piano_di_lavoro_id=' + piano_di_lavoro_id + '&print=true', '_blank');
}
</script>
</head>

<body >
<?php
require_once '../common/header-studente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lima4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-11">
			<span class="glyphicon glyphicon-dashboard"></span>&emsp;<strong>Piani di Lavoro</strong>
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
            <button onclick="refreshPagina()" class="btn btn-xs btn-teal4"><span class="glyphicon glyphicon-refresh"></span></button>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
    <div class="col-md-12">
    <div class="table-wrapper"><table id="piano_di_lavoro_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-3">Materia</th>
            <th class="text-center col-md-3">Docente</th>
            <th class="text-center col-md-2">Ultima Modifica</th>
            <th class="text-center col-md-2">Stato</th>
            <th class="text-center col-md-2"></th>
		</tr>
    </thead>
    <tbody>
<?php

// deve ricavare il nome della classe dello studente
$nomeClasse = dbGetValue("SELECT classe FROM studente WHERE id=$__studente_id ;");

$query = "  SELECT piano_di_lavoro.id AS id, piano_di_lavoro.stato AS stato, DATE_FORMAT(piano_di_lavoro.ultima_modifica, '%d %b %Y') AS ultima_modifica,
            materia.nome AS materia_nome, docente.nome AS docente_nome, docente.cognome AS docente_cognome
            FROM `piano_di_lavoro` INNER JOIN materia ON materia.id = piano_di_lavoro.materia_id
            INNER JOIN docente ON docente.id = piano_di_lavoro.docente_id
            WHERE stato = 'pubblicato' AND template = 0 AND carenza = 0
            AND nome_classe = '$nomeClasse' AND anno_scolastico_id = $__anno_scolastico_corrente_id
            ORDER BY materia_nome;";

foreach(dbGetAll($query) as $pianoDiLavoro) {
    $pianoDiLavoroId = $pianoDiLavoro['id'];
    $docenteNomeCognome = $pianoDiLavoro['docente_nome'].' '.$pianoDiLavoro['docente_cognome'];
    $materia = $pianoDiLavoro['materia_nome'];
    $ultima_modifica = $pianoDiLavoro['ultima_modifica'];
    $stato = $pianoDiLavoro['stato'];

	$statoMarker = '';
	if ($stato == 'finale') {
		$statoMarker .= '<span class="label label-success">finale</span>';
	} elseif ($stato == 'pubblicato') {
		$statoMarker .= '<span class="label label-info">pubblicato</span>';
	}

    echo '<tr>';
    echo '<td class="text-left">'.$materia.'</td>';
    echo '<td class="text-left">'.$docenteNomeCognome.'</td>';
    echo '<td class="text-left">'.$ultima_modifica.'</td>';
    echo '<td class="text-center">'.$statoMarker.'</td>';
    echo '<td class="text-center">';
    echo '<button onclick="pianoDiLavoroSavePdf('.$pianoDiLavoroId.')" class="btn btn-orange4 btn-xs" style="display: inline-flex;align-items: center;"><i class="icon-play"></i>&nbsp;Pdf</button>';
    echo '</td></tr>';
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
<input type="hidden" id="hidden_fuis_totale_previsto" value="<?php echo $fuis_totale_previsto; ?>">

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/scriptPrevisteDirigente.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>