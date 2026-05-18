<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

$missingTables = mastercomAdminMissingTables();
$message = trim((string)($_GET['message'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));
$classRows = empty($missingTables) ? mastercomAdminOperationalClassRows('*') : [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Dashboard</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-transfer"></span>&emsp;MasterCom
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">
                    Mancano le tabelle MasterCom nel database: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.
                    Esegui prima lo script SQL in <code>doc/mastercom_schema.sql</code>.
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-3">
                        <div class="well">
                            <h4>Docenti</h4>
                            <p><?php echo intval(dbGetValue("SELECT COUNT(*) FROM mastercom_docenti")); ?> record</p>
                            <form method="post" action="mastercom_sync.php">
                                <input type="hidden" name="entity" value="teachers">
                                <button class="btn btn-primary btn-block" type="submit">Sincronizza docenti</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="well">
                            <h4>Classi</h4>
                            <p><?php echo intval(dbGetValue("SELECT COUNT(*) FROM mastercom_classi")); ?> record</p>
                            <form method="post" action="mastercom_sync.php">
                                <input type="hidden" name="entity" value="classes">
                                <button class="btn btn-primary btn-block" type="submit">Sincronizza classi</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="well">
                            <h4>Genitori</h4>
                            <p><?php echo intval(dbGetValue("SELECT COUNT(*) FROM mastercom_genitori")); ?> record</p>
                            <form method="post" action="mastercom_sync.php">
                                <input type="hidden" name="entity" value="parents">
                                <button class="btn btn-primary btn-block" type="submit">Sincronizza genitori</button>
                            </form>
                            <hr>
                            <form method="post" action="mastercom_sync.php">
                                <input type="hidden" name="entity" value="rebuild_parent_student_links">
                                <button class="btn btn-default btn-block" type="submit">Ricalcola collegamenti genitori-studenti</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="well">
                            <h4>Studenti</h4>
                            <p><?php echo intval(dbGetValue("SELECT COUNT(*) FROM mastercom_studenti")); ?> record</p>
                            <form method="post" action="mastercom_sync.php">
                                <input type="hidden" name="entity" value="students">
                                <div class="form-group">
                                    <label for="class_id">Classe</label>
                                    <?php if (!empty($classRows)): ?>
                                        <select name="class_id" id="class_id" class="form-control">
                                            <?php foreach ($classRows as $classRow): ?>
                                                <option value="<?php echo intval($classRow['mastercom_id_classe']); ?>">
                                                    <?php echo htmlspecialchars(($classRow['nome'] ?? '') . ' [' . ($classRow['mastercom_id_classe'] ?? '') . ']'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="number" name="class_id" id="class_id" class="form-control" placeholder="Inserisci class_id MasterCom">
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-primary btn-block" type="submit">Sincronizza studenti classe</button>
                            </form>
                            <hr>
                            <form method="post" action="mastercom_sync.php">
                                <input type="hidden" name="entity" value="students_all">
                                <button class="btn btn-default btn-block" type="submit">Sincronizza studenti di tutte le classi</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="well">
                            <h4>Snapshot docenti</h4>
                            <p>Classi con docenti, materia e fascia oraria corrente da <code>get_user_info</code>.</p>
                            <a class="btn btn-info btn-block" href="mastercom_teacher_snapshot.php">
                                <span class="glyphicon glyphicon-eye-open"></span> Apri snapshot docenti
                            </a>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    I pulsanti di allineamento "viceversa" in questa prima versione aggiornano la scheda locale <strong>mastercom_*</strong> da GestOre.
                    Non scrivono ancora sui server MasterCom, perché non abbiamo endpoint di scrittura anagrafica confermati.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
