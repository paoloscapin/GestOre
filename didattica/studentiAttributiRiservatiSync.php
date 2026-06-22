<?php

require_once '../common/checkSession.php';
require_once '../common/studentiAttributiRiservatiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

studentiAttrEnsureTables();

$message = '';
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = studentiAttrSyncFromMbapp();
        $message = 'Sync completato.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$map = studentiAttrMap();
$summaryRows = dbGetAll("
    SELECT codice_attributo, attivo, fonte, COUNT(*) AS totale
    FROM studente_attributi_riservati
    GROUP BY codice_attributo, attivo, fonte
    ORDER BY codice_attributo ASC, attivo DESC, fonte ASC
") ?: [];

function sar_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Attributi riservati studenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>
<div class="container">
    <div class="panel panel-lightblue4">
        <div class="panel-heading"><span class="glyphicon glyphicon-lock"></span>&emsp;Attributi riservati studenti</div>
        <div class="panel-body">
            <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo sar_h($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo sar_h($error); ?></div><?php endif; ?>

            <div class="alert alert-info">
                La tabella salva codici opachi e non colonne parlanti. Le note testuali di MBApp non vengono copiate:
                viene salvato solo un hash tecnico della nota sorgente.
            </div>

            <form method="post" onsubmit="return confirm('Sincronizzare gli attributi riservati da MBAPP?');" style="margin-bottom: 16px;">
                <button type="submit" class="btn btn-primary">
                    <span class="glyphicon glyphicon-refresh"></span>&ensp;Sincronizza da MBAPP
                </button>
            </form>

            <?php if (is_array($result)): ?>
                <h4>Risultato ultimo sync</h4>
                <div class="row">
                    <div class="col-sm-3"><div class="well"><strong><?php echo intval($result['mbapp_rows'] ?? 0); ?></strong><br>righe MBAPP</div></div>
                    <div class="col-sm-3"><div class="well"><strong><?php echo intval($result['matched_students'] ?? 0); ?></strong><br>studenti abbinati</div></div>
                    <div class="col-sm-3"><div class="well"><strong><?php echo intval($result['unmatched_students'] ?? 0); ?></strong><br>non abbinati</div></div>
                    <div class="col-sm-3"><div class="well"><strong><?php echo intval($result['updated_attributes'] ?? 0); ?></strong><br>attributi aggiornati</div></div>
                </div>
                <?php if (!empty($result['unmatched_examples'])): ?>
                    <div class="alert alert-warning">
                        Primi non abbinati: <?php echo sar_h(implode(', ', (array)$result['unmatched_examples'])); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <h4>Conteggi salvati</h4>
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Codice DB</th>
                    <th>Etichetta applicativa</th>
                    <th>Attivo</th>
                    <th>Fonte</th>
                    <th class="text-right">Totale</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summaryRows as $row): ?>
                    <?php $code = (string)($row['codice_attributo'] ?? ''); ?>
                    <tr>
                        <td><code><?php echo sar_h($code); ?></code></td>
                        <td><?php echo sar_h($map[$code]['label'] ?? 'n/d'); ?></td>
                        <td><?php echo intval($row['attivo'] ?? 0) === 1 ? 'si' : 'no'; ?></td>
                        <td><?php echo sar_h($row['fonte'] ?? ''); ?></td>
                        <td class="text-right"><?php echo intval($row['totale'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($summaryRows)): ?>
                    <tr><td colspan="5" class="text-center text-muted">Nessun attributo ancora sincronizzato.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

