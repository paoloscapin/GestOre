<?php

require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__MasterCom.php';
require_once '../common/mastercom/admin_lib.php';
require_once '../common/profiloLogLib.php';

function gea_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gea_ensure_table(): void
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

function gea_mirror(int $genitoreId): ?array
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

function gea_student_context(int $mastercomParentId): ?array
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

function gea_sync_mastercom_email(array $mirror, string $email, string $telefono, string $cellulare): bool
{
    if (!getSettingsValue('profiloGenitore', 'sincronizza_mastercom', true)) {
        return true;
    }

    $mastercomParentId = intval($mirror['mastercom_id_parente'] ?? 0);
    $studentContext = gea_student_context($mastercomParentId);
    if (!$studentContext) {
        return false;
    }

    $authResult = mastercomAuthenticateService(['profile' => 'MasterComAuth']);
    if (empty($authResult['ok'])) {
        return false;
    }

    $htmlResult = mastercomLoadStudentAdminProfileHtml($authResult, $studentContext);
    if (empty($htmlResult['ok']) || trim((string)($htmlResult['body'] ?? '')) === '') {
        return false;
    }

    $form = mastercomAdminExtractFormValuesByName((string)$htmlResult['body']);
    if (!$form) {
        return false;
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
    return !empty($submit['ok']);
}

$token = preg_replace('/[^a-fA-F0-9]/', '', (string)($_GET['token'] ?? '')) ?? '';
$message = '';
$type = 'danger';

gea_ensure_table();

if ($token === '' || strlen($token) !== 64) {
    $message = 'Link di annullamento non valido.';
    profiloLogWrite('email_rollback_token_non_valido', 'genitore', 0, [], 'warning');
} else {
    $tokenHash = hash('sha256', $token);
    $row = dbGetFirst("
        SELECT t.*, g.nome, g.cognome, g.email AS current_email
        FROM genitori_email_change_token t
        INNER JOIN genitori g ON g.id = t.id_genitore
        WHERE t.token_hash = " . dbQ($tokenHash) . "
        LIMIT 1
    ");

    if (!$row) {
        $message = 'Link di annullamento non valido o gia utilizzato.';
        profiloLogWrite('email_rollback_token_non_trovato', 'genitore', 0, [], 'warning');
    } elseif (!empty($row['used_at'])) {
        $message = 'Questo link di annullamento e gia stato utilizzato.';
        profiloLogWrite('email_rollback_token_gia_usato', 'genitore', (int)($row['id_genitore'] ?? 0), [
            'token_id' => (int)($row['id'] ?? 0),
            'used_at' => (string)($row['used_at'] ?? ''),
        ], 'warning');
    } elseif (strtotime((string)$row['expires_at']) < time()) {
        $message = 'Questo link di annullamento e scaduto.';
        profiloLogWrite('email_rollback_token_scaduto', 'genitore', (int)($row['id_genitore'] ?? 0), [
            'token_id' => (int)($row['id'] ?? 0),
            'expires_at' => (string)($row['expires_at'] ?? ''),
        ], 'warning');
    } else {
        $genitoreId = intval($row['id_genitore']);
        $oldEmail = trim((string)$row['old_email']);
        $newEmail = trim((string)$row['new_email']);
        $currentEmail = trim((string)$row['current_email']);

        if (strcasecmp($currentEmail, $newEmail) !== 0) {
            dbExec("
                UPDATE genitori_email_change_token
                SET used_at = NOW()
                WHERE id = " . dbI((int)$row['id']) . "
            ");
            $message = 'Il profilo non usa piu la mail indicata dal cambio: annullamento non applicato.';
            $type = 'warning';
            profiloLogWrite('email_rollback_non_applicato_email_diversa', 'genitore', $genitoreId, [
                'token_id' => (int)($row['id'] ?? 0),
                'current_email' => $currentEmail,
                'expected_email' => $newEmail,
                'old_email' => $oldEmail,
            ], 'warning');
        } else {
            dbExec("
                UPDATE genitori
                SET email = " . dbQ($oldEmail) . "
                WHERE id = " . dbI($genitoreId) . "
            ");

            $mirror = gea_mirror($genitoreId);
            if ($mirror && mastercomAdminTableExists('mastercom_genitori')) {
                $telefono = trim((string)($row['telefono'] ?? ''));
                $cellulare = trim((string)($row['cellulare'] ?? ''));
                $mastercomOk = gea_sync_mastercom_email($mirror, $oldEmail, $telefono, $cellulare);
                dbExec("
                    UPDATE mastercom_genitori
                    SET email = " . dbQ($oldEmail) . ",
                        last_sync_at = NOW()
                    WHERE id = " . dbI((int)$mirror['id']) . "
                ");
                if (!$mastercomOk) {
                    info('Annulla cambio email genitore: sync MasterCom non riuscito per id_genitore=' . $genitoreId);
                    profiloLogWrite('email_rollback_sync_mastercom_fallito', 'genitore', $genitoreId, [
                        'token_id' => (int)($row['id'] ?? 0),
                        'old_email' => $oldEmail,
                        'new_email' => $newEmail,
                    ], 'error');
                }
            }

            dbExec("
                UPDATE genitori_email_change_token
                SET used_at = NOW()
                WHERE id = " . dbI((int)$row['id']) . "
            ");

            $toName = trim((string)($row['nome'] ?? '') . ' ' . (string)($row['cognome'] ?? ''));
            $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
                . kvRow('Email ripristinata', $oldEmail)
                . kvRow('Email annullata', $newEmail)
                . '</table>';
            $html = mailWrap('Cambio email annullato', $toName, 'Il cambio email del profilo genitore GestOre e stato annullato.', $content, 'Se non hai richiesto tu questa operazione, contatta la segreteria didattica.', 'annullamento');
            if (filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
                sendMail($oldEmail, $toName !== '' ? $toName : $oldEmail, 'GestOre - cambio email annullato', $html);
            }

            $message = 'Cambio email annullato correttamente. Il profilo e tornato alla mail precedente.';
            $type = 'success';
            profiloLogWrite('email_rollback_applicato', 'genitore', $genitoreId, [
                'token_id' => (int)($row['id'] ?? 0),
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
                'mastercom_sync_ok' => !isset($mastercomOk) || !empty($mastercomOk),
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Annulla cambio email</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <style>
        body { background:#f3f6f8; }
        .rollback-box {
            max-width: 680px;
            margin: 70px auto;
            background: #fff;
            border: 1px solid #d7e1e7;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .08);
        }
        .rollback-box h1 {
            margin-top: 0;
            color: #0b4f71;
            font-size: 24px;
            font-weight: 800;
        }
    </style>
</head>
<body>
<div class="rollback-box">
    <h1>Annulla cambio email</h1>
    <div class="alert alert-<?php echo gea_h($type); ?>"><?php echo gea_h($message); ?></div>
    <a href="index.php" class="btn btn-default">Torna alla home</a>
</div>
</body>
</html>
