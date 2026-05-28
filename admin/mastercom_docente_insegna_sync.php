<?php

require_once '../common/checkSession.php';
require_once '../common/docente_insegna_mbapp_sync_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function docenteInsegnaSyncH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function docenteInsegnaSyncParam(string $name, string $default = ''): string
{
    return isset($_REQUEST[$name]) ? trim((string)$_REQUEST[$name]) : $default;
}

function docenteInsegnaSyncSelected($a, $b): string
{
    return intval($a) === intval($b) ? 'selected' : '';
}

function docenteInsegnaSyncDateIt($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', substr($value, 0, 10), new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->format('d/m/Y') : $value;
}

function docenteInsegnaSyncDuplicateExists(int $idDocente, int $idClasse, int $idMateria, int $idAnno, int $excludeId = 0): bool
{
    $query = "
        SELECT id
        FROM docente_insegna
        WHERE id_docente = " . dbI($idDocente) . "
          AND id_classe = " . dbI($idClasse) . "
          AND id_materia = " . dbI($idMateria) . "
          AND id_anno_scolastico = " . dbI($idAnno);
    if ($excludeId > 0) {
        $query .= " AND id <> " . dbI($excludeId);
    }
    $query .= " LIMIT 1";
    return dbGetFirst($query) !== null;
}

[$defaultFrom, $defaultTo] = docenteInsegnaMbappCurrentWeekRange();
$from = docenteInsegnaSyncParam('from', $defaultFrom);
$to = docenteInsegnaSyncParam('to', $defaultTo);
$removeObsolete = docenteInsegnaMbappSyncBool(docenteInsegnaSyncParam('rimuovi_obsoleti', '1'), true);
$action = docenteInsegnaSyncParam('action', '');
$apply = $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'sync';
$gestioneAnnoId = intval(docenteInsegnaSyncParam('gestione_anno_id', (string)docenteInsegnaMbappCurrentAnnoId()));
$gestioneDocenteId = intval(docenteInsegnaSyncParam('gestione_docente_id', '0'));
$gestioneClasseId = intval(docenteInsegnaSyncParam('gestione_classe_id', '0'));
$gestioneMateriaId = intval(docenteInsegnaSyncParam('gestione_materia_id', '0'));
$crudMessage = '';
$crudError = '';
$result = null;
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_mapping', 'update_mapping', 'delete_mapping'], true)) {
        $mappingId = intval($_POST['mapping_id'] ?? 0);
        $idDocente = intval($_POST['id_docente'] ?? 0);
        $idClasse = intval($_POST['id_classe'] ?? 0);
        $idMateria = intval($_POST['id_materia'] ?? 0);
        $idAnno = intval($_POST['id_anno_scolastico'] ?? 0);

        if ($action === 'delete_mapping') {
            if ($mappingId <= 0) {
                throw new Exception('Abbinamento non valido.');
            }
            dbExec("DELETE FROM docente_insegna WHERE id = " . dbI($mappingId) . " LIMIT 1");
            $crudMessage = 'Abbinamento cancellato.';
        } else {
            if ($idDocente <= 0 || $idClasse <= 0 || $idMateria <= 0 || $idAnno <= 0) {
                throw new Exception('Completa docente, classe, materia e anno scolastico.');
            }
            if (docenteInsegnaSyncDuplicateExists($idDocente, $idClasse, $idMateria, $idAnno, $action === 'update_mapping' ? $mappingId : 0)) {
                throw new Exception('Questo abbinamento esiste gia per l\'anno selezionato.');
            }

            if ($action === 'add_mapping') {
                dbExec("
                    INSERT INTO docente_insegna (id_docente, id_classe, id_materia, id_anno_scolastico)
                    VALUES (" . dbI($idDocente) . ", " . dbI($idClasse) . ", " . dbI($idMateria) . ", " . dbI($idAnno) . ")
                ");
                $crudMessage = 'Abbinamento aggiunto.';
            } else {
                if ($mappingId <= 0) {
                    throw new Exception('Abbinamento non valido.');
                }
                dbExec("
                    UPDATE docente_insegna
                    SET id_docente = " . dbI($idDocente) . ",
                        id_classe = " . dbI($idClasse) . ",
                        id_materia = " . dbI($idMateria) . ",
                        id_anno_scolastico = " . dbI($idAnno) . "
                    WHERE id = " . dbI($mappingId) . "
                    LIMIT 1
                ");
                $crudMessage = 'Abbinamento aggiornato.';
            }
        }
    }
} catch (Throwable $e) {
    $crudError = $e->getMessage();
}

try {
    $result = docenteInsegnaMbappSync([
        'from' => $from,
        'to' => $to,
        'apply' => $apply,
        'rimuovi_obsoleti' => $removeObsolete,
        'preserva_se_vuoto' => true,
    ]);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$summary = [
    'righe_mbapp' => is_array($result) ? count($result['mbapp_rows'] ?? []) : 0,
    'gia_presenti' => is_array($result) ? count($result['already_present'] ?? []) : 0,
    'da_inserire' => is_array($result) ? count($result['to_insert'] ?? []) : 0,
    'da_rimuovere' => is_array($result) ? count($result['to_remove'] ?? []) : 0,
    'anomalie' => is_array($result) ? count($result['errors'] ?? []) : 0,
];

$anniRows = dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC, id DESC") ?: [];
$docentiRows = dbGetAll("SELECT id, cognome, nome, username FROM docente WHERE attivo = 1 ORDER BY cognome ASC, nome ASC") ?: [];
$classiRows = dbGetAll("SELECT id, classe FROM classi WHERE attiva = 1 ORDER BY classe ASC") ?: [];
$materieRows = dbGetAll("SELECT id, codice, nome FROM materia ORDER BY nome ASC") ?: [];

$mappingsQuery = "
    SELECT
        di.id,
        di.id_docente,
        di.id_classe,
        di.id_materia,
        di.id_anno_scolastico,
        d.cognome AS docente_cognome,
        d.nome AS docente_nome,
        d.username AS docente_username,
        c.classe,
        m.codice AS materia_codice,
        m.nome AS materia_nome,
        a.anno AS anno_label
    FROM docente_insegna di
    INNER JOIN docente d ON d.id = di.id_docente
    INNER JOIN classi c ON c.id = di.id_classe
    INNER JOIN materia m ON m.id = di.id_materia
    LEFT JOIN anno_scolastico a ON a.id = di.id_anno_scolastico
    WHERE 1=1";
if ($gestioneAnnoId > 0) {
    $mappingsQuery .= " AND di.id_anno_scolastico = " . dbI($gestioneAnnoId);
}
if ($gestioneDocenteId > 0) {
    $mappingsQuery .= " AND di.id_docente = " . dbI($gestioneDocenteId);
}
if ($gestioneClasseId > 0) {
    $mappingsQuery .= " AND di.id_classe = " . dbI($gestioneClasseId);
}
if ($gestioneMateriaId > 0) {
    $mappingsQuery .= " AND di.id_materia = " . dbI($gestioneMateriaId);
}
$mappingsQuery .= " ORDER BY c.classe ASC, d.cognome ASC, d.nome ASC, m.nome ASC";
$mappingRows = dbGetAll($mappingsQuery) ?: [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Docente insegna</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .sync-summary .well { min-height: 92px; }
        .sync-table-wrap { max-height: 420px; overflow: auto; border: 1px solid #ddd; }
        .sync-table-wrap table { margin-bottom: 0; }
        .sync-muted { color: #666; }
        #docenteInsegnaWaitOverlay {
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,.78);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #docenteInsegnaWaitOverlay .wait-box {
            width: 420px;
            max-width: calc(100vw - 40px);
            padding: 24px 28px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #d7e3f0;
            box-shadow: 0 12px 34px rgba(15,23,42,.18);
            text-align: center;
        }
        #docenteInsegnaWaitOverlay .wait-title {
            font-size: 18px;
            font-weight: 800;
            color: #1f5e6b;
            margin-bottom: 8px;
        }
        #docenteInsegnaWaitOverlay .progress {
            margin: 16px 0 0;
            height: 10px;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div id="docenteInsegnaWaitOverlay">
    <div class="wait-box">
        <div class="wait-title">Caricamento abbinamenti</div>
        <div id="docenteInsegnaWaitText">Attendi, sto preparando i dati...</div>
        <div class="progress progress-striped active">
            <div class="progress-bar progress-bar-info" style="width:100%;"></div>
        </div>
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-link"></span>&emsp;Sync docente_insegna da MBApp
        </div>
        <div class="panel-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo docenteInsegnaSyncH($error); ?></div>
            <?php endif; ?>
            <?php if ($crudError !== ''): ?>
                <div class="alert alert-danger"><?php echo docenteInsegnaSyncH($crudError); ?></div>
            <?php endif; ?>
            <?php if ($crudMessage !== ''): ?>
                <div class="alert alert-success"><?php echo docenteInsegnaSyncH($crudMessage); ?></div>
            <?php endif; ?>

            <?php if (is_array($result) && !empty($result['skipped'])): ?>
                <div class="alert alert-warning">
                    <?php echo docenteInsegnaSyncH($result['skip_reason'] ?? 'Nessuna modifica effettuata.'); ?>
                </div>
            <?php elseif ($apply): ?>
                <div class="alert alert-success">
                    Sincronizzazione completata. Inseriti <?php echo intval($summary['da_inserire']); ?> record, rimossi <?php echo intval($summary['da_rimuovere']); ?> record.
                </div>
            <?php endif; ?>

            <form method="get" action="mastercom_docente_insegna_sync.php" class="form-inline docente-insegna-wait-form" data-wait-text="Aggiorno la preview della sincronizzazione..." style="margin-bottom: 15px;">
                <div class="form-group">
                    <label for="from">Dal</label>
                    <input type="date" class="form-control" id="from" name="from" value="<?php echo docenteInsegnaSyncH($from); ?>">
                </div>
                <div class="form-group">
                    <label for="to">Al</label>
                    <input type="date" class="form-control" id="to" name="to" value="<?php echo docenteInsegnaSyncH($to); ?>">
                </div>
                <div class="form-group">
                    <label for="rimuovi_obsoleti">Rimuovi obsoleti</label>
                    <select class="form-control" id="rimuovi_obsoleti" name="rimuovi_obsoleti">
                        <option value="1" <?php echo $removeObsolete ? 'selected' : ''; ?>>Si</option>
                        <option value="0" <?php echo !$removeObsolete ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-info">
                    <span class="glyphicon glyphicon-eye-open"></span> Preview
                </button>
            </form>

            <?php if (is_array($result)): ?>
                <div class="row sync-summary">
                    <div class="col-md-2"><div class="well"><strong>Periodo</strong><br><?php echo docenteInsegnaSyncH($result['from']); ?> - <?php echo docenteInsegnaSyncH($result['to']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Righe MBApp</strong><br><?php echo intval($summary['righe_mbapp']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Da inserire</strong><br><?php echo intval($summary['da_inserire']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Gia presenti</strong><br><?php echo intval($summary['gia_presenti']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Da rimuovere</strong><br><?php echo intval($summary['da_rimuovere']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Anomalie</strong><br><?php echo intval($summary['anomalie']); ?></div></div>
                </div>

                <p class="sync-muted">
                    Colonna data MBApp usata: <code><?php echo docenteInsegnaSyncH($result['date_column'] ?? ''); ?></code>.
                    Se a giugno non risultano ore nel periodo scelto, la sincronizzazione non svuota la tabella.
                </p>

                <?php if (!$apply): ?>
                    <form method="post" action="mastercom_docente_insegna_sync.php" class="docente-insegna-wait-form" data-wait-text="Sincronizzazione in corso..." style="margin-bottom: 20px;" onsubmit="return confirm('Eseguire la sincronizzazione docente_insegna con questi dati?');">
                        <input type="hidden" name="action" value="sync">
                        <input type="hidden" name="from" value="<?php echo docenteInsegnaSyncH($from); ?>">
                        <input type="hidden" name="to" value="<?php echo docenteInsegnaSyncH($to); ?>">
                        <input type="hidden" name="rimuovi_obsoleti" value="<?php echo $removeObsolete ? '1' : '0'; ?>">
                        <button type="submit" class="btn btn-success">
                            <span class="glyphicon glyphicon-refresh"></span> Esegui sincronizzazione
                        </button>
                    </form>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <h4>Da inserire<?php echo $apply ? ' / inseriti' : ''; ?></h4>
                        <?php docenteInsegnaSyncRenderRows($result['to_insert'] ?? []); ?>
                    </div>
                    <div class="col-md-6">
                        <h4>Da rimuovere<?php echo $apply ? ' / rimossi' : ''; ?></h4>
                        <?php docenteInsegnaSyncRenderRows($result['to_remove'] ?? []); ?>
                    </div>
                </div>

                <h4>Anomalie</h4>
                <?php docenteInsegnaSyncRenderErrors($result['errors'] ?? []); ?>

                <h4>Diagnostica MBApp</h4>
                <table class="table table-bordered table-condensed">
                    <tbody>
                    <tr>
                        <th style="width: 320px;">Range date presenti in MBApp</th>
                        <td><?php echo docenteInsegnaSyncH(docenteInsegnaSyncDateIt($result['debug']['range_date']['min_data'] ?? '') . ' - ' . docenteInsegnaSyncDateIt($result['debug']['range_date']['max_data'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <th>Incroci docente/classe/materia senza filtro data</th>
                        <td><?php echo docenteInsegnaSyncH($result['debug']['count_senza_filtro_data']['n'] ?? '0'); ?></td>
                    </tr>
                    <tr>
                        <th>Incroci docente/classe/materia nel periodo</th>
                        <td><?php echo docenteInsegnaSyncH($result['debug']['count_con_filtro_data']['n'] ?? '0'); ?></td>
                    </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <hr>
            <h3><span class="glyphicon glyphicon-edit"></span> Gestione manuale abbinamenti</h3>

            <form method="get" action="mastercom_docente_insegna_sync.php" class="form-inline docente-insegna-wait-form" data-wait-text="Filtro gli abbinamenti..." style="margin-bottom: 15px;">
                <input type="hidden" name="from" value="<?php echo docenteInsegnaSyncH($from); ?>">
                <input type="hidden" name="to" value="<?php echo docenteInsegnaSyncH($to); ?>">
                <input type="hidden" name="rimuovi_obsoleti" value="<?php echo $removeObsolete ? '1' : '0'; ?>">
                <div class="form-group">
                    <label for="gestione_anno_id">Anno</label>
                    <select class="form-control" id="gestione_anno_id" name="gestione_anno_id">
                        <option value="0">Tutti</option>
                        <?php foreach ($anniRows as $annoRow): ?>
                            <option value="<?php echo intval($annoRow['id']); ?>" <?php echo docenteInsegnaSyncSelected($gestioneAnnoId, $annoRow['id']); ?>>
                                <?php echo docenteInsegnaSyncH($annoRow['anno']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="gestione_docente_id">Docente</label>
                    <select class="form-control" id="gestione_docente_id" name="gestione_docente_id">
                        <option value="0">Tutti</option>
                        <?php foreach ($docentiRows as $docenteRow): ?>
                            <option value="<?php echo intval($docenteRow['id']); ?>" <?php echo docenteInsegnaSyncSelected($gestioneDocenteId, $docenteRow['id']); ?>>
                                <?php echo docenteInsegnaSyncH(trim($docenteRow['cognome'] . ' ' . $docenteRow['nome'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="gestione_classe_id">Classe</label>
                    <select class="form-control" id="gestione_classe_id" name="gestione_classe_id">
                        <option value="0">Tutte</option>
                        <?php foreach ($classiRows as $classeRow): ?>
                            <option value="<?php echo intval($classeRow['id']); ?>" <?php echo docenteInsegnaSyncSelected($gestioneClasseId, $classeRow['id']); ?>>
                                <?php echo docenteInsegnaSyncH($classeRow['classe']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="gestione_materia_id">Materia</label>
                    <select class="form-control" id="gestione_materia_id" name="gestione_materia_id">
                        <option value="0">Tutte</option>
                        <?php foreach ($materieRows as $materiaRow): ?>
                            <option value="<?php echo intval($materiaRow['id']); ?>" <?php echo docenteInsegnaSyncSelected($gestioneMateriaId, $materiaRow['id']); ?>>
                                <?php echo docenteInsegnaSyncH($materiaRow['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-info">
                    <span class="glyphicon glyphicon-filter"></span> Filtra
                </button>
            </form>

            <div class="panel panel-default">
                <div class="panel-heading"><strong>Nuovo abbinamento</strong></div>
                <div class="panel-body">
                    <form method="post" action="mastercom_docente_insegna_sync.php" class="form-inline docente-insegna-wait-form" data-wait-text="Aggiungo l'abbinamento...">
                        <input type="hidden" name="action" value="add_mapping">
                        <input type="hidden" name="from" value="<?php echo docenteInsegnaSyncH($from); ?>">
                        <input type="hidden" name="to" value="<?php echo docenteInsegnaSyncH($to); ?>">
                        <input type="hidden" name="rimuovi_obsoleti" value="<?php echo $removeObsolete ? '1' : '0'; ?>">
                        <input type="hidden" name="gestione_anno_id" value="<?php echo intval($gestioneAnnoId); ?>">
                        <input type="hidden" name="gestione_docente_id" value="<?php echo intval($gestioneDocenteId); ?>">
                        <input type="hidden" name="gestione_classe_id" value="<?php echo intval($gestioneClasseId); ?>">
                        <input type="hidden" name="gestione_materia_id" value="<?php echo intval($gestioneMateriaId); ?>">
                        <?php docenteInsegnaSyncRenderSelect('id_anno_scolastico', $anniRows, $gestioneAnnoId, 'anno', 'Anno'); ?>
                        <?php docenteInsegnaSyncRenderSelect('id_docente', $docentiRows, $gestioneDocenteId, 'docente', 'Docente'); ?>
                        <?php docenteInsegnaSyncRenderSelect('id_classe', $classiRows, $gestioneClasseId, 'classe', 'Classe'); ?>
                        <?php docenteInsegnaSyncRenderSelect('id_materia', $materieRows, $gestioneMateriaId, 'materia', 'Materia'); ?>
                        <button type="submit" class="btn btn-success">
                            <span class="glyphicon glyphicon-plus"></span> Aggiungi
                        </button>
                    </form>
                </div>
            </div>

            <div class="sync-table-wrap">
                <table class="table table-bordered table-striped table-condensed">
                    <thead>
                    <tr>
                        <th class="text-center">Anno</th>
                        <th class="text-center">Classe</th>
                        <th class="text-center">Docente</th>
                        <th class="text-center">Materia</th>
                        <th class="text-center">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($mappingRows)): ?>
                        <tr><td colspan="5" class="text-center sync-muted">Nessun abbinamento trovato.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($mappingRows as $mappingRow): ?>
                        <?php $rowFormId = 'mapping_form_' . intval($mappingRow['id']); ?>
                        <tr>
                            <td><?php docenteInsegnaSyncRenderSelect('row_id_anno_scolastico_' . intval($mappingRow['id']), $anniRows, intval($mappingRow['id_anno_scolastico']), 'anno', '', 'id_anno_scolastico'); ?></td>
                            <td><?php docenteInsegnaSyncRenderSelect('row_id_classe_' . intval($mappingRow['id']), $classiRows, intval($mappingRow['id_classe']), 'classe', '', 'id_classe'); ?></td>
                            <td><?php docenteInsegnaSyncRenderSelect('row_id_docente_' . intval($mappingRow['id']), $docentiRows, intval($mappingRow['id_docente']), 'docente', '', 'id_docente'); ?></td>
                            <td><?php docenteInsegnaSyncRenderSelect('row_id_materia_' . intval($mappingRow['id']), $materieRows, intval($mappingRow['id_materia']), 'materia', '', 'id_materia'); ?></td>
                            <td class="text-center" style="white-space:nowrap;">
                                <form id="<?php echo docenteInsegnaSyncH($rowFormId); ?>" method="post" action="mastercom_docente_insegna_sync.php" class="docente-insegna-wait-form" data-wait-text="Salvo la modifica..." style="display:inline;">
                                    <input type="hidden" name="mapping_id" value="<?php echo intval($mappingRow['id']); ?>">
                                    <input type="hidden" name="from" value="<?php echo docenteInsegnaSyncH($from); ?>">
                                    <input type="hidden" name="to" value="<?php echo docenteInsegnaSyncH($to); ?>">
                                    <input type="hidden" name="rimuovi_obsoleti" value="<?php echo $removeObsolete ? '1' : '0'; ?>">
                                    <input type="hidden" name="gestione_anno_id" value="<?php echo intval($gestioneAnnoId); ?>">
                                    <input type="hidden" name="gestione_docente_id" value="<?php echo intval($gestioneDocenteId); ?>">
                                    <input type="hidden" name="gestione_classe_id" value="<?php echo intval($gestioneClasseId); ?>">
                                    <input type="hidden" name="gestione_materia_id" value="<?php echo intval($gestioneMateriaId); ?>">
                                    <input type="hidden" name="id_docente" value="">
                                    <input type="hidden" name="id_classe" value="">
                                    <input type="hidden" name="id_materia" value="">
                                    <input type="hidden" name="id_anno_scolastico" value="">
                                    <button type="submit" name="action" value="update_mapping" class="btn btn-primary btn-xs" onclick="return docenteInsegnaSyncPrepareRow(this);">
                                        <span class="glyphicon glyphicon-floppy-disk"></span>
                                    </button>
                                    <button type="submit" name="action" value="delete_mapping" class="btn btn-danger btn-xs" onclick="return confirm('Cancellare questo abbinamento?');">
                                        <span class="glyphicon glyphicon-trash"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
function docenteInsegnaSyncWait(text) {
    if (text) {
        $('#docenteInsegnaWaitText').text(text);
    }
    $('#docenteInsegnaWaitOverlay').css('display', 'flex');
}

function docenteInsegnaSyncWaitHide() {
    $('#docenteInsegnaWaitOverlay').fadeOut(120);
}

function docenteInsegnaSyncPrepareRow(button) {
    var row = $(button).closest('tr');
    var form = $(button).closest('form');
    form.find('input[name="id_docente"]').val(row.find('select[data-field="id_docente"]').val() || '0');
    form.find('input[name="id_classe"]').val(row.find('select[data-field="id_classe"]').val() || '0');
    form.find('input[name="id_materia"]').val(row.find('select[data-field="id_materia"]').val() || '0');
    form.find('input[name="id_anno_scolastico"]').val(row.find('select[data-field="id_anno_scolastico"]').val() || '0');
    return true;
}

$(window).on('load', function () {
    docenteInsegnaSyncWaitHide();
});

$(document).on('submit', '.docente-insegna-wait-form', function () {
    docenteInsegnaSyncWait($(this).data('wait-text') || 'Operazione in corso...');
});

$(document).on('click', 'a[href*="mastercom_docente_insegna_sync.php"]', function () {
    docenteInsegnaSyncWait('Caricamento pagina...');
});
</script>
</body>
</html>
<?php

function docenteInsegnaSyncRenderRows(array $rows): void
{
    if (empty($rows)) {
        echo '<p class="sync-muted">Nessun record.</p>';
        return;
    }

    echo '<div class="sync-table-wrap"><table class="table table-striped table-condensed">';
    echo '<thead><tr><th>Username</th><th>Classe</th><th>Materia</th><th>ID docente</th><th>ID classe</th><th>ID materia</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . docenteInsegnaSyncH($row['username'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['classe'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['materia'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['id_docente'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['id_classe'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['id_materia'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function docenteInsegnaSyncRenderSelect(string $name, array $rows, int $selectedId, string $type, string $placeholder = '', string $dataField = ''): void
{
    echo '<select class="form-control input-sm" name="' . docenteInsegnaSyncH($name) . '" ' . ($placeholder !== '' ? 'title="' . docenteInsegnaSyncH($placeholder) . '"' : '') . ($dataField !== '' ? ' data-field="' . docenteInsegnaSyncH($dataField) . '"' : '') . '>';
    echo '<option value="0">' . docenteInsegnaSyncH($placeholder !== '' ? $placeholder : '-') . '</option>';
    foreach ($rows as $row) {
        $id = intval($row['id'] ?? 0);
        if ($type === 'docente') {
            $label = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
            $username = trim((string)($row['username'] ?? ''));
            if ($username !== '') {
                $label .= ' [' . $username . ']';
            }
        } elseif ($type === 'classe') {
            $label = (string)($row['classe'] ?? '');
        } elseif ($type === 'materia') {
            $label = (string)($row['nome'] ?? '');
            $codice = trim((string)($row['codice'] ?? ''));
            if ($codice !== '') {
                $label .= ' [' . $codice . ']';
            }
        } else {
            $label = (string)($row['anno'] ?? '');
        }
        echo '<option value="' . $id . '" ' . docenteInsegnaSyncSelected($selectedId, $id) . '>' . docenteInsegnaSyncH($label) . '</option>';
    }
    echo '</select>';
}

function docenteInsegnaSyncRenderErrors(array $rows): void
{
    if (empty($rows)) {
        echo '<p class="text-success"><strong>Nessuna anomalia.</strong></p>';
        return;
    }

    echo '<div class="sync-table-wrap"><table class="table table-bordered table-condensed">';
    echo '<thead><tr><th>Tipo</th><th>Username</th><th>Classe MBApp</th><th>Materia MBApp</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td><span class="label label-warning">' . docenteInsegnaSyncH($row['tipo'] ?? '') . '</span></td>';
        echo '<td>' . docenteInsegnaSyncH($row['username'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['classe'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['materia'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
