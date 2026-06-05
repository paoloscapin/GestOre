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

function genitoreProfiloEnsureEmailTokenTable(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS genitori_email_change_token (
            id INT NOT NULL AUTO_INCREMENT,
            id_genitore INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            old_email VARCHAR(255) NOT NULL,
            new_email VARCHAR(255) NOT NULL,
            telefono VARCHAR(64) NULL,
            cellulare VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_genitori_email_change_token_hash (token_hash),
            KEY idx_genitori_email_change_token_genitore (id_genitore),
            KEY idx_genitori_email_change_token_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function genitoreProfiloCreateEmailRollbackToken(int $genitoreId, string $oldEmail, string $newEmail, string $telefono, string $cellulare): string
{
    genitoreProfiloEnsureEmailTokenTable();

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $hours = max(1, intval(getSettingsValue('profiloGenitore', 'annulla_cambio_email_ore', 48)));

    dbExec("
        INSERT INTO genitori_email_change_token
            (id_genitore, token_hash, old_email, new_email, telefono, cellulare, expires_at)
        VALUES
            (" . dbI($genitoreId) . ",
             " . dbQ($tokenHash) . ",
             " . dbQ($oldEmail) . ",
             " . dbQ($newEmail) . ",
             " . dbQ($telefono) . ",
             " . dbQ($cellulare) . ",
             DATE_ADD(NOW(), INTERVAL " . dbI($hours) . " HOUR))
    ");

    return $rawToken;
}

function genitoreProfiloAbsoluteUrl(string $path): string
{
    global $__http_base_link;
    $base = trim((string)($__http_base_link ?? ''));
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $base = $host !== '' ? $scheme . '://' . $host . '/GestOre' : '';
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
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

function genitoreProfiloSendMails(array $genitore, string $oldEmail, string $email, string $telefono, string $cellulare, ?string $rollbackToken): void
{
    $toName = trim((string)($genitore['nome'] ?? '') . ' ' . (string)($genitore['cognome'] ?? ''));
    $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
        . kvRow('Email', $email)
        . kvRow('Telefono', $telefono !== '' ? $telefono : '-')
        . kvRow('Cellulare', $cellulare !== '' ? $cellulare : '-')
        . '</table>';
    $html = mailWrap('Profilo genitore aggiornato', $toName, 'I dati di contatto del tuo profilo sono stati aggiornati.', $content, 'Se non hai richiesto tu questa modifica, contatta la segreteria didattica.', 'default');
    sendMail($email, $toName !== '' ? $toName : $email, 'GestOre - profilo genitore aggiornato', $html);

    if ($rollbackToken !== null && $oldEmail !== '' && filter_var($oldEmail, FILTER_VALIDATE_EMAIL) && strcasecmp($oldEmail, $email) !== 0) {
        $rollbackUrl = genitoreProfiloAbsoluteUrl('genitore/profiloEmailAnnulla.php?token=' . urlencode($rollbackToken));
        $oldContent = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
            . kvRow('Vecchia email', $oldEmail)
            . kvRow('Nuova email', $email)
            . '</table>'
            . '<div style="margin-top:16px;text-align:center;">'
            . '<a href="' . genitoreProfiloH($rollbackUrl) . '" style="display:inline-block;background:#b91c1c;color:#ffffff;text-decoration:none;padding:11px 16px;border-radius:10px;font-weight:800;">Annulla cambio email</a>'
            . '</div>';
        $oldFooter = 'Il link puo essere usato una sola volta e scade automaticamente. Se il cambio email e corretto, puoi ignorare questo messaggio.';
        $oldHtml = mailWrap('Cambio email profilo genitore', $toName, 'La mail del tuo profilo genitore GestOre e stata modificata.', $oldContent, $oldFooter, 'annullamento');
        sendMail($oldEmail, $toName !== '' ? $toName : $oldEmail, 'GestOre - cambio email profilo genitore', $oldHtml);
    }

    $segreteriaEmail = trim((string)getSettingsValue('profiloGenitore', 'email_segreteria_didattica', 'segr.didattica@buonarroti.tn.it'));
    if ($segreteriaEmail !== '' && filter_var($segreteriaEmail, FILTER_VALIDATE_EMAIL)) {
        $adminHtml = mailWrap('Aggiornamento profilo genitore', 'Segreteria didattica', 'Un genitore ha aggiornato i propri dati di contatto.', $content, 'Messaggio informativo automatico da GestOre.', 'warning');
        sendMail($segreteriaEmail, 'Segreteria didattica', 'GestOre - aggiornamento profilo genitore', $adminHtml);
    }
}

function genitoreProfiloIsMobile(): bool
{
    return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT'] ?? '') === 1;
}

$message = '';
$messageType = 'info';
$profiloTelegramVisibile = (bool)getSettingsValue('profiloGenitore', 'visibile_telegram', false);

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
    $oldEmail = trim((string)($genitore['email'] ?? ''));

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
            $oldTelefono = genitoreProfiloCleanPhone((string)($mirror['telefono'] ?? ''));
            $oldCellulare = genitoreProfiloCleanPhone((string)($mirror['cellulare'] ?? ''));
            $hasChanges =
                strcasecmp($oldEmail, $email) !== 0 ||
                $oldTelefono !== $telefono ||
                $oldCellulare !== $cellulare;

            if (!$hasChanges) {
                $message = 'Nessuna modifica da salvare.';
                $messageType = 'info';
            } else {
                $syncResult = $mirror ? genitoreProfiloSyncMasterCom($mirror, $email, $telefono, $cellulare) : ['ok' => true, 'skipped' => true];

                if (empty($syncResult['ok'])) {
                    $message = $syncResult['message'] ?? 'Aggiornamento MasterCom non riuscito.';
                    $messageType = 'danger';
                } else {
                    $emailChanged = strcasecmp($oldEmail, $email) !== 0;
                    $rollbackToken = $emailChanged ? genitoreProfiloCreateEmailRollbackToken((int)$__genitore_id, $oldEmail, $email, $telefono, $cellulare) : null;

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

                    genitoreProfiloSendMails($genitore ?: [], $oldEmail, $email, $telefono, $cellulare, $rollbackToken);

                    $message = 'Profilo aggiornato correttamente.' . (!empty($syncResult['skipped']) ? '' : ' ' . ($syncResult['message'] ?? ''));
                    $messageType = 'success';
                }
            }
        }
    }
}

$telefono = trim((string)($mirror['telefono'] ?? ''));
$cellulare = trim((string)($mirror['cellulare'] ?? ''));

$studentiProfilo = dbGetAll("
    SELECT
        s.id,
        s.nome,
        s.cognome,
        s.email,
        s.attivo,
        c.classe
    FROM genitori_studenti gs
    INNER JOIN studente s ON s.id = gs.id_studente
    LEFT JOIN studente_frequenta sf
        ON sf.id_studente = s.id
       AND sf.id_anno_scolastico = " . dbI((int)$__anno_scolastico_corrente_id) . "
    LEFT JOIN classi c ON c.id = sf.id_classe
    WHERE gs.id_genitore = " . dbI((int)$__genitore_id) . "
    ORDER BY s.attivo DESC, s.cognome ASC, s.nome ASC
");
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
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/genitore-profilo.css?v=<?php echo @filemtime(__DIR__ . '/../css/genitore-profilo.css') ?: time(); ?>">
</head>
<body class="genitore-profilo-page">
<?php
if (genitoreProfiloIsMobile()) {
    require_once '../common/header-genitore-mobile.php';
} else {
    require_once '../common/header-genitore.php';
}
?>
<div class="container-fluid">
    <div class="genitore-profilo-shell">
        <div class="genitore-profilo-hero">
            <h1 class="genitore-profilo-hero-title">
                <span class="glyphicon glyphicon-user"></span>
                <span>Profilo genitore</span>
            </h1>
            <p class="genitore-profilo-hero-subtitle">
                Controlla i dati anagrafici e aggiorna i contatti usati per le comunicazioni di GestOre.
            </p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo genitoreProfiloH($messageType); ?>">
                <?php echo genitoreProfiloH($message); ?>
            </div>
        <?php endif; ?>

        <div class="genitore-profilo-grid">
            <div>
                <section class="genitore-profilo-card">
                    <div class="genitore-profilo-card-heading">
                        <span class="glyphicon glyphicon-credit-card"></span>
                        <span>Dati profilo</span>
                    </div>
                    <div class="genitore-profilo-card-body">
                        <dl class="genitore-profilo-dl">
                            <div class="genitore-profilo-dl-row">
                                <dt>Nome</dt>
                                <dd><?php echo genitoreProfiloH($genitore['nome'] ?? ''); ?></dd>
                            </div>
                            <div class="genitore-profilo-dl-row">
                                <dt>Cognome</dt>
                                <dd><?php echo genitoreProfiloH($genitore['cognome'] ?? ''); ?></dd>
                            </div>
                            <div class="genitore-profilo-dl-row">
                                <dt>Codice fiscale</dt>
                                <dd><?php echo genitoreProfiloH($genitore['codice_fiscale'] ?? ''); ?></dd>
                            </div>
                            <?php if ($mirror): ?>
                                <div class="genitore-profilo-dl-row">
                                    <dt>ID MasterCom</dt>
                                    <dd><?php echo genitoreProfiloH($mirror['mastercom_id_parente'] ?? ''); ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    </div>
                </section>

                <section class="genitore-profilo-card">
                    <div class="genitore-profilo-card-heading">
                        <span class="glyphicon glyphicon-education"></span>
                        <span>Studenti collegati</span>
                    </div>
                    <div class="genitore-profilo-card-body">
                        <?php if (!$studentiProfilo): ?>
                            <div class="alert alert-warning" style="margin-bottom:0;">Non risultano studenti collegati al profilo.</div>
                        <?php else: ?>
                            <div class="genitore-profilo-student-list">
                                <?php foreach ($studentiProfilo as $studenteProfilo): ?>
                                    <?php $isActiveStudent = (int)($studenteProfilo['attivo'] ?? 0) === 1; ?>
                                    <div class="genitore-profilo-student">
                                        <div>
                                            <div class="genitore-profilo-student-name">
                                                <?php echo genitoreProfiloH(trim((string)($studenteProfilo['cognome'] ?? '') . ' ' . (string)($studenteProfilo['nome'] ?? ''))); ?>
                                            </div>
                                            <div class="genitore-profilo-student-class">
                                                <?php echo genitoreProfiloH(($studenteProfilo['classe'] ?? '') !== '' ? 'Classe ' . $studenteProfilo['classe'] : 'Classe non assegnata per l\'anno corrente'); ?>
                                            </div>
                                        </div>
                                        <span class="genitore-profilo-student-status <?php echo $isActiveStudent ? 'is-active' : 'is-inactive'; ?>">
                                            <?php echo $isActiveStudent ? 'Attivo' : 'Non attivo'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div>
                <section class="genitore-profilo-card">
                    <div class="genitore-profilo-card-heading">
                        <span class="glyphicon glyphicon-envelope"></span>
                        <span>Contatti</span>
                    </div>
                    <div class="genitore-profilo-card-body">
                        <p class="genitore-profilo-help">
                            Email, telefono e cellulare vengono usati per le comunicazioni GestOre e, quando possibile, sono sincronizzati anche con l'anagrafica MasterCom.
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
                            <div class="genitore-profilo-actions">
                                <button type="submit" class="btn btn-primary">
                                    <span class="glyphicon glyphicon-floppy-disk"></span>
                                    Salva profilo
                                </button>
                                <?php if ($profiloTelegramVisibile): ?>
                                    <a href="telegram.php" class="btn btn-default">
                                        <span class="glyphicon glyphicon-send"></span>
                                        Gestisci Telegram
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if (!$mirror): ?>
                            <div class="alert alert-warning" style="margin-top:14px;">
                                Anagrafica MasterCom non collegata: verra aggiornata solo la mail del profilo GestOre.
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($profiloTelegramVisibile): ?>
                    <section class="genitore-profilo-card">
                        <div class="genitore-profilo-card-heading">
                            <span class="glyphicon glyphicon-send"></span>
                            <span>Notifiche Telegram</span>
                        </div>
                        <div class="genitore-profilo-card-body">
                            <p class="genitore-profilo-help">
                                Puoi collegare Telegram al profilo genitore per ricevere notifiche e comunicazioni abilitate dalla scuola.
                            </p>
                            <div class="genitore-profilo-actions">
                                <a href="telegram.php" class="btn btn-info">
                                    <span class="glyphicon glyphicon-cog"></span>
                                    Apri gestione Telegram
                                </a>
                                <a href="index.php" class="btn btn-default">
                                    <span class="glyphicon glyphicon-home"></span>
                                    Torna alla home
                                </a>
                            </div>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="genitore-profilo-card">
                        <div class="genitore-profilo-card-heading">
                            <span class="glyphicon glyphicon-home"></span>
                            <span>Navigazione</span>
                        </div>
                        <div class="genitore-profilo-card-body">
                            <div class="genitore-profilo-actions">
                                <a href="index.php" class="btn btn-default">
                                    <span class="glyphicon glyphicon-home"></span>
                                    Torna alla home
                                </a>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
