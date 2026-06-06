<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__MasterCom.php';
require_once '../common/mastercom/admin_lib.php';
require_once '../common/notifichePreferenzeLib.php';
require_once '../common/profiloLogLib.php';

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

function genitoreProfiloCleanCodiceFiscale(string $value): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value) ?? '');
}

function genitoreProfiloCodiceFiscaleValido(string $value): bool
{
    return preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $value) === 1;
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

function genitoreProfiloSyncMasterCom(array $mirror, string $email, string $telefono, string $cellulare, string $codiceFiscale): array
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
    $form[$prefix . 'codice_fiscale'] = $codiceFiscale;
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

function genitoreProfiloSendMails(array $genitore, string $oldEmail, string $email, string $telefono, string $cellulare, string $codiceFiscale, ?string $rollbackToken): void
{
    $toName = trim((string)($genitore['nome'] ?? '') . ' ' . (string)($genitore['cognome'] ?? ''));
    $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
        . kvRow('Email', $email)
        . kvRow('Telefono', $telefono !== '' ? $telefono : '-')
        . kvRow('Cellulare', $cellulare !== '' ? $cellulare : '-')
        . kvRow('Codice fiscale', $codiceFiscale !== '' ? $codiceFiscale : '-')
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
$profiloAction = trim((string)($_POST['profilo_action'] ?? 'contatti'));

$genitore = dbGetFirst("
    SELECT id, nome, cognome, email, codice_fiscale
    FROM genitori
    WHERE id = " . dbI((int)$__genitore_id) . "
    LIMIT 1
");
$mirror = genitoreProfiloMirror((int)$__genitore_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profiloAction === 'notifiche') {
    $saveResult = notifichePreferenzeSaveFromPost('genitore', (int)$__genitore_id, $_POST);
    $message = (string)($saveResult['message'] ?? 'Preferenze notifiche salvate.');
    $messageType = !empty($saveResult['ok']) ? 'success' : 'danger';
    profiloLogWrite('notifiche_salvate', 'genitore', (int)$__genitore_id, [
        'ok' => !empty($saveResult['ok']),
        'message' => $message,
        'preferenze' => profiloLogNotificationPrefsFromPost($_POST),
    ], !empty($saveResult['ok']) ? 'info' : 'warning');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $telefono = genitoreProfiloCleanPhone((string)($_POST['telefono'] ?? ''));
    $cellulare = genitoreProfiloCleanPhone((string)($_POST['cellulare'] ?? ''));
    $codiceFiscale = genitoreProfiloCleanCodiceFiscale((string)($_POST['codice_fiscale'] ?? ''));
    $oldEmail = trim((string)($genitore['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Inserisci un indirizzo email valido.';
        $messageType = 'danger';
        profiloLogWrite('contatti_rifiutati_email_non_valida', 'genitore', (int)$__genitore_id, [
            'email_inserita' => $email,
        ], 'warning');
    } elseif ($codiceFiscale === '' || !genitoreProfiloCodiceFiscaleValido($codiceFiscale)) {
        $message = 'Inserisci un codice fiscale valido.';
        $messageType = 'danger';
        profiloLogWrite('contatti_rifiutati_codice_fiscale_non_valido', 'genitore', (int)$__genitore_id, [
            'codice_fiscale_inserito' => $codiceFiscale,
        ], 'warning');
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
            profiloLogWrite('contatti_rifiutati_email_duplicata', 'genitore', (int)$__genitore_id, [
                'email_inserita' => $email,
                'genitore_esistente_id' => (int)($existing['id'] ?? 0),
            ], 'warning');
        } else {
            $existingCf = dbGetFirst("
                SELECT id
                FROM genitori
                WHERE UPPER(REPLACE(TRIM(codice_fiscale), ' ', '')) = " . dbQ($codiceFiscale) . "
                  AND id <> " . dbI((int)$__genitore_id) . "
                LIMIT 1
            ");

            if ($existingCf) {
                $message = 'Questo codice fiscale e gia associato a un altro profilo genitore.';
                $messageType = 'warning';
                profiloLogWrite('contatti_rifiutati_codice_fiscale_duplicato', 'genitore', (int)$__genitore_id, [
                    'codice_fiscale_inserito' => $codiceFiscale,
                    'genitore_esistente_id' => (int)($existingCf['id'] ?? 0),
                ], 'warning');
            } else {
                $oldTelefono = genitoreProfiloCleanPhone((string)($mirror['telefono'] ?? ''));
                $oldCellulare = genitoreProfiloCleanPhone((string)($mirror['cellulare'] ?? ''));
                $oldCodiceFiscale = genitoreProfiloCleanCodiceFiscale((string)($genitore['codice_fiscale'] ?? ($mirror['codice_fiscale'] ?? '')));
                $changes = profiloLogChangedFields(
                    ['email' => $oldEmail, 'telefono' => $oldTelefono, 'cellulare' => $oldCellulare, 'codice_fiscale' => $oldCodiceFiscale],
                    ['email' => $email, 'telefono' => $telefono, 'cellulare' => $cellulare, 'codice_fiscale' => $codiceFiscale]
                );
                $hasChanges =
                    strcasecmp($oldEmail, $email) !== 0 ||
                    $oldTelefono !== $telefono ||
                    $oldCellulare !== $cellulare ||
                    $oldCodiceFiscale !== $codiceFiscale;

                if (!$hasChanges) {
                    $message = 'Nessuna modifica da salvare.';
                    $messageType = 'info';
                    profiloLogWrite('contatti_nessuna_modifica', 'genitore', (int)$__genitore_id, [
                        'email' => $email,
                    ]);
                } else {
                    $syncResult = $mirror ? genitoreProfiloSyncMasterCom($mirror, $email, $telefono, $cellulare, $codiceFiscale) : ['ok' => true, 'skipped' => true];

                    if (empty($syncResult['ok'])) {
                        $message = $syncResult['message'] ?? 'Aggiornamento MasterCom non riuscito.';
                        $messageType = 'danger';
                        profiloLogWrite('contatti_sync_mastercom_fallito', 'genitore', (int)$__genitore_id, [
                            'changes' => $changes,
                            'message' => $message,
                        ], 'error');
                    } else {
                        $emailChanged = strcasecmp($oldEmail, $email) !== 0;
                        $isImpersonatingProfile = profiloLogIsImpersonatingTarget('genitore');
                        $rollbackToken = ($emailChanged && !$isImpersonatingProfile) ? genitoreProfiloCreateEmailRollbackToken((int)$__genitore_id, $oldEmail, $email, $telefono, $cellulare) : null;

                        dbExec("
                            UPDATE genitori
                            SET email = " . dbQ($email) . ",
                                codice_fiscale = " . dbQ($codiceFiscale) . "
                            WHERE id = " . dbI((int)$__genitore_id) . "
                        ");

                        if ($mirror && mastercomAdminTableExists('mastercom_genitori')) {
                            dbExec("
                                UPDATE mastercom_genitori
                                SET email = " . dbQ($email) . ",
                                    telefono = " . dbQ($telefono) . ",
                                    cellulare = " . dbQ($cellulare) . ",
                                    codice_fiscale = " . dbQ($codiceFiscale) . ",
                                    last_sync_at = NOW()
                                WHERE id = " . dbI((int)$mirror['id']) . "
                            ");
                        }

                        $_SESSION['genitore_email'] = $email;
                        $_SESSION['genitore_codice_fiscale'] = $codiceFiscale;
                        $_SESSION['__useremail'] = $email;
                        $GLOBALS['__genitore_email'] = $email;
                        $GLOBALS['__genitore_codice_fiscale'] = $codiceFiscale;
                        $genitore['email'] = $email;
                        $genitore['codice_fiscale'] = $codiceFiscale;
                        $mirror = genitoreProfiloMirror((int)$__genitore_id);

                        if (!$isImpersonatingProfile) {
                            genitoreProfiloSendMails($genitore ?: [], $oldEmail, $email, $telefono, $cellulare, $codiceFiscale, $rollbackToken);
                        }

                        $message = 'Profilo aggiornato correttamente.' . (!empty($syncResult['skipped']) ? '' : ' ' . ($syncResult['message'] ?? ''));
                        $messageType = 'success';
                        profiloLogWrite('contatti_salvati', 'genitore', (int)$__genitore_id, [
                            'changes' => $changes,
                            'email_changed' => $emailChanged,
                            'rollback_token_created' => $rollbackToken !== null,
                            'impersonamento' => $isImpersonatingProfile,
                            'mail_profilo_inviate' => !$isImpersonatingProfile,
                            'mastercom_sync' => [
                                'skipped' => !empty($syncResult['skipped']),
                                'message' => (string)($syncResult['message'] ?? ''),
                            ],
                        ]);
                    }
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
                            <input type="hidden" name="profilo_action" value="contatti">
                            <div class="form-group">
                                <label for="email">Indirizzo email</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo genitoreProfiloH($genitore['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="codice_fiscale">Codice fiscale</label>
                                <input type="text" class="form-control" id="codice_fiscale" name="codice_fiscale" required
                                       maxlength="16" pattern="[A-Za-z]{6}[0-9LMNPQRSTUVlmnpqrstuv]{2}[ABCDEHLMPRSTabcdehlmprst][0-9LMNPQRSTUVlmnpqrstuv]{2}[A-Za-z][0-9LMNPQRSTUVlmnpqrstuv]{3}[A-Za-z]"
                                       style="text-transform: uppercase;"
                                       oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                                       value="<?php echo genitoreProfiloH(genitoreProfiloCleanCodiceFiscale((string)($genitore['codice_fiscale'] ?? ''))); ?>">
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
                            </div>
                        </form>
                        <?php if (!$mirror): ?>
                            <div class="alert alert-warning" style="margin-top:14px;">
                                Anagrafica MasterCom non collegata: verra aggiornata solo la mail del profilo GestOre.
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php echo notifichePreferenzeRenderSection('genitore', (int)$__genitore_id); ?>

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
