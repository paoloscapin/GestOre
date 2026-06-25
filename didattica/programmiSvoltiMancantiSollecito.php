<?php

require_once '../common/checkSession.php';
require_once '../common/programmiSvoltiCompletezzaLib.php';
require_once '../common/send-mail.php';
ruoloRichiesto('admin', 'dirigente', 'segreteria-didattica');

header('Content-Type: application/json; charset=utf-8');

$filters = [
    'anno_id' => intval($_POST['anni_id'] ?? 0),
    'classe_id' => intval($_POST['classi_id'] ?? 0),
    'materia_id' => intval($_POST['materia_id'] ?? 0),
    'docente_id' => intval($_POST['docenti_id'] ?? 0),
];
$dryRun = !empty($_POST['dry_run']);
$singleDocenteId = intval($_POST['single_docente_id'] ?? 0);
if ($singleDocenteId > 0) {
    $filters['docente_id'] = $singleDocenteId;
}

function pss_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function pss_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $rows = programmiSvoltiCompletezzaRighe($filters);
    $byDocente = programmiSvoltiCompletezzaRighePerDocente($rows);
    if ($dryRun) {
        $docenti = [];
        foreach ($byDocente as $docenteId => $docente) {
            $docenti[] = [
                'id' => intval($docenteId),
                'docente' => (string)$docente['docente'],
                'email' => (string)$docente['email'],
                'righe' => count($docente['righe']),
            ];
        }
        pss_out([
            'ok' => true,
            'docenti' => count($byDocente),
            'docenti_list' => $docenti,
            'righe' => count($rows),
            'message' => count($byDocente) . ' docenti da sollecitare, ' . count($rows) . ' righe mancanti/non compilate.',
        ]);
    }

    $sent = 0;
    $skipped = 0;
    $errors = [];
    $dispatches = [];
    foreach ($byDocente as $docente) {
        $email = strtolower(trim((string)$docente['email']));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            $errors[] = 'Email mancante/non valida per ' . $docente['docente'];
            continue;
        }

        $righeHtml = '';
        foreach ($docente['righe'] as $row) {
            $righeHtml .= '<tr>'
                . '<td style="border:1px solid #d9e2ec;padding:7px;">' . pss_h($row['classe']) . '</td>'
                . '<td style="border:1px solid #d9e2ec;padding:7px;">' . pss_h($row['materia']) . '</td>'
                . '<td style="border:1px solid #d9e2ec;padding:7px;">' . pss_h($row['stato']) . '</td>'
                . '</tr>';
        }

        $body = '
            <div style="font-family:Arial,Helvetica,sans-serif;color:#172033;background:#f4f7fb;padding:18px;">
                <div style="max-width:760px;margin:0 auto;background:#fff;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden;">
                    <div style="background:#1f4e79;color:#fff;padding:18px 20px;">
                        <div style="font-size:22px;font-weight:800;">Sollecito programmi svolti</div>
                    </div>
                    <div style="padding:20px;">
                        <p>Gentile <strong>' . pss_h($docente['docente']) . '</strong>,</p>
                        <p>dal controllo della Segreteria didattica risultano programmi svolti non ancora inseriti o senza moduli compilati.</p>
                        <table style="border-collapse:collapse;width:100%;margin:14px 0;">
                            <thead>
                                <tr>
                                    <th style="border:1px solid #d9e2ec;padding:7px;background:#eaf2f8;text-align:left;">Classe</th>
                                    <th style="border:1px solid #d9e2ec;padding:7px;background:#eaf2f8;text-align:left;">Materia</th>
                                    <th style="border:1px solid #d9e2ec;padding:7px;background:#eaf2f8;text-align:left;">Stato</th>
                                </tr>
                            </thead>
                            <tbody>' . $righeHtml . '</tbody>
                        </table>
                        <p>Si chiede di completare l\'inserimento in GestOre appena possibile.</p>
                        <p>Cordiali saluti<br><strong>Segreteria didattica</strong></p>
                    </div>
                </div>
            </div>
        ';

        $ok = sendMail($email, $docente['docente'], 'GestOre - Sollecito programmi svolti da completare', $body);
        $dispatch = function_exists('sendMailLastDispatchResult') ? sendMailLastDispatchResult() : [];
        $dispatches[] = [
            'docente' => $docente['docente'],
            'email' => $email,
            'ok' => !empty($ok),
            'transport' => (string)($dispatch['transport'] ?? ''),
            'sender' => (string)($dispatch['sender'] ?? ''),
            'error' => (string)($dispatch['error'] ?? ''),
        ];
        if ($ok) {
            $sent++;
            info('[programmi svolti] sollecito completezza inviato a ' . $email . ' righe=' . count($docente['righe']) . ' transport=' . (string)($dispatch['transport'] ?? '') . ' sender=' . (string)($dispatch['sender'] ?? ''));
        } else {
            $err = (string)($dispatch['error'] ?? '');
            $errors[] = 'Errore invio a ' . $email . ($err !== '' ? ': ' . $err : '');
        }
    }

    pss_out([
        'ok' => count($errors) === 0,
        'sent' => $sent,
        'skipped' => $skipped,
        'errors' => $errors,
        'dispatches' => $dispatches,
        'message' => 'Solleciti inviati: ' . $sent . ' - saltati: ' . $skipped,
    ], count($errors) === 0 ? 200 : 500);
} catch (Throwable $e) {
    pss_out(['ok' => false, 'message' => $e->getMessage()], 500);
}
