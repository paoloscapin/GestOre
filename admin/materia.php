<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
?>

<!DOCTYPE html>
<html>
<head>
	<title>Materie</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php


require_once '../common/header-common.php';
require_once '../common/style.php';
ruoloRichiesto('dirigente');

function materiaAdminTableExists(string $tableName): bool
{
    return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

function materiaAdminColumnExists(string $tableName, string $columnName): bool
{
    $row = dbGetFirst("SHOW COLUMNS FROM `$tableName` LIKE " . dbQ($columnName));
    return is_array($row) && !empty($row);
}

$materiaDipartimentiEnabled = materiaAdminTableExists('dipartimenti');
if ($materiaDipartimentiEnabled && !materiaAdminColumnExists('materia', 'id_dipartimento')) {
    dbExec("ALTER TABLE materia ADD COLUMN id_dipartimento INT NULL AFTER codice");
}
$dipartimentoOptions = '<option value="0">Nessun dipartimento</option>';
if ($materiaDipartimentiEnabled) {
    foreach (dbGetAll("SELECT id, nome FROM dipartimenti ORDER BY nome ASC") ?: [] as $dipRow) {
        $dipartimentoOptions .= '<option value="' . intval($dipRow['id']) . '">' . htmlspecialchars((string)$dipRow['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
}
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">

<script type="text/javascript" src="js/materia.js?v=<?php echo filemtime(__DIR__ . '/js/materia.js'); ?>"></script>
</head>

<body >
<?php require_once '../common/header-admin.php'; ?>

<!-- Content Section -->
<div class="container-fluid">
<div class="panel panel-yellow4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-6">
			<span class="glyphicon glyphicon-education"></span>&emsp;Gestione Materie
		</div>
        <div class="col-md-6">
            <div class="pull-right">
				<button class="btn btn-xs btn-yellow4" onclick="materiaGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
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

<!-- Bootstrap Modals -->
<!-- Modal - Add/Update Record -->
<div class="modal fade" id="update_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="myModalLabel">Materia</h5>
            </div>
            <div class="modal-body">

                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" placeholder="nome" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="codice">Codice</label>
                    <input type="text" id="codice" placeholder="codice" class="form-control"/>
                </div>

                <?php if ($materiaDipartimentiEnabled) : ?>
                <div class="form-group">
                    <label for="id_dipartimento">Dipartimento</label>
                    <select id="id_dipartimento" class="form-control">
                        <?php echo $dipartimentoOptions; ?>
                    </select>
                </div>
                <?php else : ?>
                <div class="alert alert-warning">Tabella dipartimenti non configurata.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="materiaSave()">Salva</button>
				<input type="hidden" id="hidden_record_id">
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->

</body>
</html>
