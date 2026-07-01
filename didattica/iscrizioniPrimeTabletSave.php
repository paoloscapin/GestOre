<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function iscrizioniPrimeTabletSaveFail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    iscrizioniPrimeEnsureSchema();
    $id = intval($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $recipients = $_POST['recipients'] ?? null;
    if ($recipients !== null && !is_array($recipients)) {
        $recipients = [$recipients];
    }

    if ($id <= 0) {
        iscrizioniPrimeTabletSaveFail('Pratica non valida.');
    }

    if ($action === 'rinuncia') {
        $sendMail = intval($_POST['send_mail'] ?? 0) === 1;
        $result = iscrizioniPrimeTabletRenounce(
            $id,
            $note,
            $_FILES['allegato'] ?? null,
            $sendMail,
            trim((string)($_POST['mail_subject'] ?? 'Conferma rinuncia classe tablet')),
            trim((string)($_POST['mail_message'] ?? '')),
            trim((string)($_POST['mail_signature'] ?? '')),
            $recipients
        );
    } elseif ($action === 'stato') {
        $result = iscrizioniPrimeTabletSetStatus(
            $id,
            intval($_POST['tablet_scelto'] ?? 0),
            trim((string)($_POST['tablet_stato'] ?? '')),
            trim((string)($_POST['tablet_gruppo'] ?? '')),
            $note
        );
    } elseif ($action === 'acquistato') {
        $result = iscrizioniPrimeTabletSetPurchase($id, true, $note);
    } elseif ($action === 'non_acquistato') {
        $result = iscrizioniPrimeTabletSetPurchase($id, false, $note);
    } elseif ($action === 'note') {
        dbExec("
            UPDATE iscrizioni_prime_pratiche
            SET tablet_note = " . dbQ($note) . ",
                updated_at = NOW()
            WHERE id = " . dbI($id) . "
        ");
        iscrizioniPrimeTabletRecordEvent($id, 'Note tablet aggiornate', ['note' => $note]);
        $result = ['ok' => true, 'message' => 'Note tablet aggiornate.'];
    } else {
        iscrizioniPrimeTabletSaveFail('Azione tablet non valida.');
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    iscrizioniPrimeTabletSaveFail($e->getMessage(), 500);
}
