<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__MasterCom.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('genitore');

if (!getSettingsValue('config', 'profiloGenitore', false) || !getSettingsValue('profiloGenitore', 'visibile_genitori', false)) {
    http_response_code(403);
    die('Profilo genitore non abilitato.');
}

function genitoreProfiloH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function genitoreProfiloCleanPhone(string $value): string
{
    return trim(preg_replace('/[^\d+()\s.\-]/', '', $value) ?? '');
}

function genitoreProfiloMirror(int $genitoreId): ?array
{
    if (!mastercomAdminTableExists('mastercom_genitori')) {
        return null;
    }

    $row = dbGetFirst("
        SELECT *
        FROM mastercom_genitori
        WHERE id_genitore_gestore = " . dbI($genitoreId) . "
        LIMIT 1
    ");
    return is_array($row) ? $row : null;
}

function genitoreProfiloStudentContext(int $mastercomParentId): ?array
{
    if ($mastercomParentId <= 0 || !mastercomAdminTableExists('mastercom_genitori_studenti') || !mastercomAdminTableExists('mastercom_studenti')) {
        return null;
    }

    $hasClassTable = mastercomAdminTableExists('mastercom_classi');
    $hasClassAddressColumn = $hasClassTable && mastercomAdminTableColumnExists('mastercom_classi', 'id_indirizzo');
    $classJoin = $hasClassTable
        ? ' LEFT JOIN mastercom_classi c ON c.mastercom_id_classe = s.mastercom_id_classe_corrente '
        : '';
    $classSelect = $hasClassTable
        ? ' c.nome AS classe, ' . ($hasClassAddressColumn ? 'c.id_indirizzo' : "''") . ' AS id_indirizzo, '
        : " '' AS classe, '' AS id_indirizzo, ";

    $row = dbGetFirst("
        SELECT
            s.mastercom_id_studente AS id_studente,
            s.mastercom_id_classe_corrente AS id_classe,
            $classSelect
            s.cognome,
            s.nome
        FROM mastercom_genitori_studenti gs
        INNER JOIN mastercom_studenti s ON s.mastercom_id_studente = gs.mastercom_id_studente
        $classJoin
        WHERE gs.mastercom_id_parente = " . dbI($mastercomParentId) . "
        ORDER BY s.cognome ASC, s.nome ASC
        LIMIT 1
    ");

    return is_array($row) ? $row : null;
}

function genitoreProfiloSyncMasterCom(array $mirror, string $email, string $telefono, string $cellulare): array
{
    if (!getSettingsValue('profiloGenitore', 'sincronizza_mastercom', true)) {
        return ['ok' => true, 'skipped' => true, 'message' => 'Sincronizzazione MasterCom disabilitata.'];
    }

    $mastercomParentId = intval($mirror['mastercom_id_parente'] ?? 0);
    $studentContext = genitoreProfiloStudentContext($mastercomParentId);
    if (!$studentContext) {
        return ['ok' => false, 'message' => 'Impossibile individuare uno studente collegato per aggiornare MasterCom.'];
    }

    $authResult = mastercomAuthenticateService(['profile' => 'MasterComAuth']);
    if (empty($authResult['ok'])) {
        return ['ok' => false, 'message' => 'Autenticazione MasterCom non riuscita: ' . ($authResult['error'] ?? 'AUTH_FAILED')];
    }

    $htmlResult = mastercomLoadStudentAdminProfileHtml($authResult, $studentContext);
    if (empty($htmlResult['ok']) || trim((string)($htmlResult['body'] ?? '')) === '') {
        return ['ok' => false, 'message' => 'Lettura anagrafica MasterCom non riuscita: ' . ($htmlResult['error'] ?? 'LOAD_FAILED')];
    }

    $form = mastercomAdminExtractFormValuesByName((string)$htmlResult['body']);
    if (!$form) {
        return ['ok' => false, 'message' => 'Form anagrafica MasterCom non interpretabile.'];
    }

    $prefix = 'parente_' . $mastercomParentId . '_';
    $form[$prefix . 'email'] = $email;
    $form[$prefix . 'telefono_abitazione'] = $telefono;
    $form[$prefix . 'telefono_cellulare'] = $cellulare;
    $form['id_studente'] = (string)($studentContext['id_studente'] ?? '');
    $form['studente_id_studente'] = (string)($studentContext['id_studente'] ?? '');
    $form['id_classe'] = (string)($studentContext['id_classe'] ?? '');
    $form['classe'] = (string)($studentContext['classe'] ?? '');
    $form['id_indirizzo'] = (string)($studentContext['id_indirizzo'] ?? '');

    $submit = mastercomSubmitStudentAdminProfile($authResult, $form);
    if (empty($submit['ok'])) {
        return ['ok' => false, 'message' => 'Salvataggio MasterCom non riuscito: ' . ($submit['error'] ?? 'SAVE_FAILED')];
    }

    return ['ok' => true, 'message' => 'Anagrafica aggiornata anche su MasterCom.'];
}

function genitoreProfiloSendMails(array $genitore, string $email, string $telefono, string $cellulare): void
{
    $toName = trim((string)($genitore['nome'] ?? '') . ' ' . (string)($genitore['cognome'] ?? ''));
    $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
        . kvRow('Email', $email)
        . kvRow('Telefono', $telefono !== '' ? $telefono : '-')
        . kvRow('Cellulare', $cellulare !== '' ? $cellulare : '-')
        . '</table>';
    $html = mailWrap('Profilo genitore aggiornato', $toName, 'I dati di contatto del tuo profilo sono stati aggiornati.', $content, 'Se non hai richiesto tu questa modifica, contatta la segreteria didattica.', 'default');
    sendMail($email, $toName !== '' ? $toName : $email, 'GestOre - profilo genitore aggiornato', $html);

    $segreteriaEmail = trim((string)getSettingsValue('profiloGenitore', 'email_segreteria_didattica', 'segr.didattica@buonarroti.tn.it'));
    if ($segreteriaEmail !== '' && filter_var($segreteriaEmail, FILTER_VALIDATE_EMAIL)) {
        $adminHtml = mailWrap('Aggiornamento profilo genitore', 'Segreteria didattica', 'Un genitore ha aggiornato i propri dati di contatto.', $content, 'Messaggio informativo automatico da GestOre.', 'warning');
        sendMail($segreteriaEmail, 'Segreteria didattica', 'GestOre - aggiornamento profilo genitore', $adminHtml);
    }
}

$message = '';
$messageType = 'info';

$genitore = dbGetFirst("
    SELECT id, nome, cognome, email, codice_fiscale
    FROM genitori
    WHERE id = " . dbI((int)$__genitore_id) . "
    LIMIT 1
");
$mirror = genitoreProfiloMirror((int)$__genitore_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $telefono = genitoreProfiloCleanPhone((string)($_POST['telefono'] ?? ''));
    $cellulare = genitoreProfiloCleanPhone((string)($_POST['cellulare'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Inserisci un indirizzo email valido.';
        $messageType = 'danger';
    } else {
        $existing = dbGetFirst("
            SELECT id
            FROM genitori
            WHERE LOWER(TRIM(email)) = " . dbQ(strtolower($email)) . "
              AND id <> " . dbI((int)$__genitore_id) . "
            LIMIT 1
        ");

        if ($existing) {
            $message = 'Questa mail e gia associata a un altro profilo genitore.';
            $messageType = 'warning';
        } else {
            $syncResult = $mirror ? genitoreProfiloSyncMasterCom($mirror, $email, $telefono, $cellulare) : ['ok' => true, 'skipped' => true];

            if (empty($syncResult['ok'])) {
                $message = $syncResult['message'] ?? 'Aggiornamento MasterCom non riuscito.';
                $messageType = 'danger';
            } else {
                dbExec("
                    UPDATE genitori
                    SET email = " . dbQ($email) . "
                    WHERE id = " . dbI((int)$__genitore_id) . "
                ");

                if ($mirror && mastercomAdminTableExists('mastercom_genitori')) {
                    dbExec("
                        UPDATE mastercom_genitori
                        SET email = " . dbQ($email) . ",
                            telefono = " . dbQ($telefono) . ",
                            cellulare = " . dbQ($cellulare) . ",
                            last_sync_at = NOW()
                        WHERE id = " . dbI((int)$mirror['id']) . "
                    ");
                }

                $_SESSION['genitore_email'] = $email;
                $_SESSION['__useremail'] = $email;
                $GLOBALS['__genitore_email'] = $email;
                $genitore['email'] = $email;
                $mirror = genitoreProfiloMirror((int)$__genitore_id);

                genitoreProfiloSendMails($genitore ?: [], $email, $telefono, $cellulare);

                $message = 'Profilo aggiornato correttamente.' . (!empty($syncResult['skipped']) ? '' : ' ' . ($syncResult['message'] ?? ''));
                $messageType = 'success';
            }
        }
    }
}

$telefono = trim((string)($mirror['telefono'] ?? ''));
$cellulare = trim((string)($mirror['cellulare'] ?? ''));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profilo Genitore</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
</head>
<body>
<?php
function genitoreIsMobileProfile()
{
    return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT'] ?? '');
}

if (genitoreIsMobileProfile()) {
    require_once '../common/header-genitore-mobile.php';
} else {
    require_once '../common/header-genitore.php';
}
?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-user"></span>&ensp;Profilo genitore</div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo genitoreProfiloH($messageType); ?>">
                    <?php echo genitoreProfiloH($message); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-5">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Dati profilo</strong></div>
                        <div class="panel-body">
                            <table class="table table-bordered table-condensed">
                                <tbody>
                                <tr><th>Nome</th><td><?php echo genitoreProfiloH($genitore['nome'] ?? ''); ?></td></tr>
                                <tr><th>Cognome</th><td><?php echo genitoreProfiloH($genitore['cognome'] ?? ''); ?></td></tr>
                                <tr><th>Codice fiscale</th><td><?php echo genitoreProfiloH($genitore['codice_fiscale'] ?? ''); ?></td></tr>
                                <?php if ($mirror): ?>
                                    <tr><th>ID MasterCom</th><td><?php echo genitoreProfiloH($mirror['mastercom_id_parente'] ?? ''); ?></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Contatti</strong></div>
                        <div class="panel-body">
                            <p class="text-muted">
                                Questi dati vengono usati per le comunicazioni GestOre e sono sincronizzati con l'anagrafica MasterCom quando disponibile.
                            </p>
                            <form method="post">
                                <div class="form-group">
                                    <label for="email">Indirizzo email</label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo genitoreProfiloH($genitore['email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="telefono">Telefono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono"
                                           value="<?php echo genitoreProfiloH($telefono); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="cellulare">Cellulare</label>
                                    <input type="text" class="form-control" id="cellulare" name="cellulare"
                                           value="<?php echo genitoreProfiloH($cellulare); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Salva profilo</button>
                                <a href="telegram.php" class="btn btn-default" style="margin-left:8px;">Gestisci Telegram</a>
                            </form>
                            <?php if (!$mirror): ?>
                                <div class="alert alert-warning" style="margin-top:14px;">
                                    Anagrafica MasterCom non collegata: verrà aggiornata solo la mail del profilo GestOre.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
