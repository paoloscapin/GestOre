<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/__Settings.php';
require_once '../common/__Log.php';
require_once '../common/send-mail.php';

ruoloRichiesto('admin');

use PHPMailer\PHPMailer\PHPMailer;

date_default_timezone_set('Europe/Rome');

function ticketMailTestPostValue(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return trim((string)$value);
}

function ticketMailTestBool(string $key, bool $default = false): bool
{
    if (!isset($_POST[$key])) {
        return $default;
    }
    $value = $_POST[$key];
    if (is_bool($value)) {
        return $value;
    }
    return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
}

function ticketMailTestSend(array $data): array
{
    global $__settings;

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isSMTP();
        $mail->Mailer = 'smtp';
        $mail->SMTPDebug = 0;
        $mail->Host = $__settings->local->smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $data['smtp_user'];
        $mail->Password = $data['smtp_pass'];
        $mail->SMTPSecure = $__settings->local->SMTPSecure;
        $mail->SMTPAutoTLS = false;
        $mail->Port = $__settings->local->Port;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->isHTML(true);
        $mail->setFrom($data['from_email'], $data['from_name'], true);
        $mail->addReplyTo($data['reply_to'], $data['from_name']);
        $mail->addAddress($data['to_email'], $data['to_name']);
        $mail->Subject = $data['subject'];
        $mail->msgHTML(nl2br(htmlspecialchars($data['body'], ENT_QUOTES, 'UTF-8')));
        $mail->AltBody = $data['body'];

        $ok = $mail->send();
        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }

        return [
            'ok' => (bool)$ok,
            'message' => 'Invio SMTP completato',
            'detail' => [
                'host' => $__settings->local->smtpHost,
                'port' => $__settings->local->Port,
                'username' => $data['smtp_user'],
                'from' => $data['from_email'],
                'to' => $data['to_email'],
                'subject' => $data['subject'],
            ],
        ];
    } catch (Throwable $e) {
        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }

        return [
            'ok' => false,
            'message' => 'Invio SMTP fallito',
            'error' => $e->getMessage(),
        ];
    }
}

function ticketMailTestRead(array $data): array
{
    if (!function_exists('imap_open')) {
        return [
            'ok' => false,
            'message' => 'Estensione IMAP non disponibile in PHP',
        ];
    }

    $mailbox = $data['imap_mailbox'];
    $username = $data['imap_user'];
    $password = $data['imap_pass'];
    $limit = max(1, min(20, intval($data['imap_limit'])));

    $inbox = @imap_open($mailbox, $username, $password);
    if ($inbox === false) {
        return [
            'ok' => false,
            'message' => 'Connessione IMAP fallita',
            'error' => imap_last_error(),
        ];
    }

    $count = imap_num_msg($inbox);
    $start = max(1, $count - $limit + 1);
    $messages = [];

    for ($msgNo = $count; $msgNo >= $start; $msgNo--) {
        $overviewRows = imap_fetch_overview($inbox, (string)$msgNo, 0);
        $overview = is_array($overviewRows) && !empty($overviewRows) ? $overviewRows[0] : null;
        $header = imap_headerinfo($inbox, $msgNo);
        $body = imap_fetchbody($inbox, $msgNo, '1');
        if (trim((string)$body) === '') {
            $body = imap_body($inbox, $msgNo);
        }

        $fromAddress = '';
        if ($header && !empty($header->from) && is_array($header->from)) {
            $firstFrom = $header->from[0];
            $mailboxFrom = isset($firstFrom->mailbox) ? $firstFrom->mailbox : '';
            $hostFrom = isset($firstFrom->host) ? $firstFrom->host : '';
            $fromAddress = trim($mailboxFrom . '@' . $hostFrom, '@');
        }

        $subject = isset($overview->subject) ? imap_utf8((string)$overview->subject) : '';
        $date = isset($overview->date) ? (string)$overview->date : '';
        $snippet = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$body)));
        if (mb_strlen($snippet, 'UTF-8') > 220) {
            $snippet = mb_substr($snippet, 0, 220, 'UTF-8') . '...';
        }

        $messages[] = [
            'msgno' => $msgNo,
            'subject' => $subject,
            'from' => $fromAddress,
            'date' => $date,
            'seen' => !empty($overview->seen),
            'snippet' => $snippet,
        ];
    }

    imap_close($inbox);

    return [
        'ok' => true,
        'message' => 'Lettura IMAP completata',
        'detail' => [
            'mailbox' => $mailbox,
            'username' => $username,
            'messages_total' => $count,
            'messages_loaded' => count($messages),
        ],
        'messages' => $messages,
    ];
}

function ticketMailTestListMailboxes(array $data): array
{
    if (!function_exists('imap_open') || !function_exists('imap_getmailboxes')) {
        return [
            'ok' => false,
            'message' => 'Funzioni IMAP non disponibili in PHP',
        ];
    }

    $mailbox = $data['imap_mailbox'];
    $username = $data['imap_user'];
    $password = $data['imap_pass'];

    if (!preg_match('/^\{[^}]+\}/', $mailbox, $matches)) {
        return [
            'ok' => false,
            'message' => 'Formato mailbox IMAP non valido',
            'error' => 'Atteso formato tipo {host:porta/opzioni}INBOX',
        ];
    }

    $server = $matches[0];
    $inbox = @imap_open($mailbox, $username, $password);
    if ($inbox === false) {
        return [
            'ok' => false,
            'message' => 'Connessione IMAP fallita',
            'error' => imap_last_error(),
        ];
    }

    $mailboxes = @imap_getmailboxes($inbox, $server, '*');
    if ($mailboxes === false) {
        imap_close($inbox);
        return [
            'ok' => false,
            'message' => 'Lettura cartelle IMAP fallita',
            'error' => imap_last_error(),
        ];
    }

    $items = [];
    foreach ($mailboxes as $box) {
        $name = str_replace($server, '', (string)($box->name ?? ''));
        $items[] = [
            'name' => $name,
            'full' => (string)($box->name ?? ''),
            'attributes' => (string)($box->attributes ?? ''),
        ];
    }

    imap_close($inbox);

    return [
        'ok' => true,
        'message' => 'Elenco cartelle IMAP caricato',
        'detail' => [
            'server' => $server,
            'count' => count($items),
        ],
        'mailboxes' => $items,
    ];
}

$defaultAlias = 'gestore@buonarroti.tn.it';
$defaultMailboxUser = trim((string)($__settings->local->smtpMail ?? ''));
$defaultMailboxPass = trim((string)($__settings->local->AppPassword ?? ''));

$sendForm = [
    'smtp_user' => ticketMailTestPostValue('smtp_user', $defaultMailboxUser),
    'smtp_pass' => ticketMailTestPostValue('smtp_pass', $defaultMailboxPass),
    'from_email' => ticketMailTestPostValue('from_email', $defaultAlias),
    'from_name' => ticketMailTestPostValue('from_name', 'GestOre test ticket'),
    'reply_to' => ticketMailTestPostValue('reply_to', $defaultAlias),
    'to_email' => ticketMailTestPostValue('to_email', $defaultMailboxUser),
    'to_name' => ticketMailTestPostValue('to_name', 'Test destinatario'),
    'subject' => ticketMailTestPostValue('subject', 'Test GestOre ticket via mail'),
    'body' => ticketMailTestPostValue('body', "Messaggio di test inviato da GestOre.\nSe ricevi questa mail, SMTP funziona."),
];

$imapForm = [
    'imap_user' => ticketMailTestPostValue('imap_user', $defaultMailboxUser),
    'imap_pass' => ticketMailTestPostValue('imap_pass', $defaultMailboxPass),
    'imap_mailbox' => ticketMailTestPostValue('imap_mailbox', '{imap.gmail.com:993/imap/ssl}INBOX'),
    'imap_limit' => ticketMailTestPostValue('imap_limit', '5'),
];

$sendResult = null;
$imapResult = null;
$imapBoxesResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = ticketMailTestPostValue('action');
    if ($action === 'send_test') {
        $sendResult = ticketMailTestSend($sendForm);
    } elseif ($action === 'read_test') {
        $imapResult = ticketMailTestRead($imapForm);
    } elseif ($action === 'list_mailboxes') {
        $imapBoxesResult = ticketMailTestListMailboxes($imapForm);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Mail Ticket</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-envelope"></span>&emsp;Test Mail Ticket</div>
        <div class="panel-body">
            <div class="alert alert-info">
                Pagina di test per verificare SMTP e IMAP sul canale ticket mail.
                Alias previsto: <strong><?php echo htmlspecialchars($defaultAlias); ?></strong>.
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Invio SMTP</strong></div>
                        <div class="panel-body">
                            <?php if ($sendResult !== null): ?>
                                <div class="alert alert-<?php echo $sendResult['ok'] ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars($sendResult['message']); ?>
                                    <?php if (!empty($sendResult['error'])): ?>
                                        <br><small><?php echo htmlspecialchars((string)$sendResult['error']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <form method="post">
                                <input type="hidden" name="action" value="send_test">
                                <div class="form-group">
                                    <label>Utente SMTP</label>
                                    <input type="text" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($sendForm['smtp_user']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Password SMTP / App Password</label>
                                    <input type="text" class="form-control" name="smtp_pass" value="<?php echo htmlspecialchars($sendForm['smtp_pass']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>From</label>
                                    <input type="email" class="form-control" name="from_email" value="<?php echo htmlspecialchars($sendForm['from_email']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Nome mittente</label>
                                    <input type="text" class="form-control" name="from_name" value="<?php echo htmlspecialchars($sendForm['from_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Reply-To</label>
                                    <input type="email" class="form-control" name="reply_to" value="<?php echo htmlspecialchars($sendForm['reply_to']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Destinatario</label>
                                    <input type="email" class="form-control" name="to_email" value="<?php echo htmlspecialchars($sendForm['to_email']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Nome destinatario</label>
                                    <input type="text" class="form-control" name="to_name" value="<?php echo htmlspecialchars($sendForm['to_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Oggetto</label>
                                    <input type="text" class="form-control" name="subject" value="<?php echo htmlspecialchars($sendForm['subject']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Corpo</label>
                                    <textarea class="form-control" rows="6" name="body"><?php echo htmlspecialchars($sendForm['body']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Invia mail di test</button>
                            </form>

                            <?php if ($sendResult !== null && !empty($sendResult['detail'])): ?>
                                <hr>
                                <h5>Dettaglio connessione</h5>
                                <table class="table table-bordered table-condensed">
                                    <tbody>
                                    <?php foreach ($sendResult['detail'] as $key => $value): ?>
                                        <tr>
                                            <th><?php echo htmlspecialchars((string)$key); ?></th>
                                            <td><?php echo htmlspecialchars((string)$value); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Lettura IMAP</strong></div>
                        <div class="panel-body">
                            <?php if ($imapResult !== null): ?>
                                <div class="alert alert-<?php echo $imapResult['ok'] ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars($imapResult['message']); ?>
                                    <?php if (!empty($imapResult['error'])): ?>
                                        <br><small><?php echo htmlspecialchars((string)$imapResult['error']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($imapBoxesResult !== null): ?>
                                <div class="alert alert-<?php echo $imapBoxesResult['ok'] ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars($imapBoxesResult['message']); ?>
                                    <?php if (!empty($imapBoxesResult['error'])): ?>
                                        <br><small><?php echo htmlspecialchars((string)$imapBoxesResult['error']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <form method="post">
                                <input type="hidden" name="action" value="read_test">
                                <div class="form-group">
                                    <label>Mailbox IMAP</label>
                                    <input type="text" class="form-control" name="imap_mailbox" value="<?php echo htmlspecialchars($imapForm['imap_mailbox']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Utente IMAP</label>
                                    <input type="text" class="form-control" name="imap_user" value="<?php echo htmlspecialchars($imapForm['imap_user']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Password IMAP / App Password</label>
                                    <input type="text" class="form-control" name="imap_pass" value="<?php echo htmlspecialchars($imapForm['imap_pass']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Numero messaggi da leggere</label>
                                    <input type="number" min="1" max="20" class="form-control" name="imap_limit" value="<?php echo htmlspecialchars($imapForm['imap_limit']); ?>">
                                </div>
                                <button type="submit" class="btn btn-info">Leggi inbox</button>
                            </form>

                            <form method="post" style="margin-top:10px;">
                                <input type="hidden" name="action" value="list_mailboxes">
                                <input type="hidden" name="imap_mailbox" value="<?php echo htmlspecialchars($imapForm['imap_mailbox']); ?>">
                                <input type="hidden" name="imap_user" value="<?php echo htmlspecialchars($imapForm['imap_user']); ?>">
                                <input type="hidden" name="imap_pass" value="<?php echo htmlspecialchars($imapForm['imap_pass']); ?>">
                                <input type="hidden" name="imap_limit" value="<?php echo htmlspecialchars($imapForm['imap_limit']); ?>">
                                <button type="submit" class="btn btn-default">Elenca cartelle IMAP</button>
                            </form>

                            <?php if ($imapResult !== null && !empty($imapResult['detail'])): ?>
                                <hr>
                                <h5>Dettaglio connessione</h5>
                                <table class="table table-bordered table-condensed">
                                    <tbody>
                                    <?php foreach ($imapResult['detail'] as $key => $value): ?>
                                        <tr>
                                            <th><?php echo htmlspecialchars((string)$key); ?></th>
                                            <td><?php echo htmlspecialchars((string)$value); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <?php if ($imapResult !== null && !empty($imapResult['messages'])): ?>
                                <hr>
                                <h5>Ultimi messaggi</h5>
                                <div style="max-height: 520px; overflow:auto;">
                                    <table class="table table-striped table-bordered table-condensed">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Data</th>
                                            <th>Mittente</th>
                                            <th>Oggetto</th>
                                            <th>Snippet</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($imapResult['messages'] as $message): ?>
                                            <tr>
                                                <td style="text-align:center;">
                                                    <?php echo intval($message['msgno']); ?><br>
                                                    <small><?php echo !empty($message['seen']) ? 'letta' : 'non letta'; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars((string)$message['date']); ?></td>
                                                <td><?php echo htmlspecialchars((string)$message['from']); ?></td>
                                                <td><?php echo htmlspecialchars((string)$message['subject']); ?></td>
                                                <td><?php echo htmlspecialchars((string)$message['snippet']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if ($imapBoxesResult !== null && !empty($imapBoxesResult['mailboxes'])): ?>
                                <hr>
                                <h5>Cartelle IMAP</h5>
                                <div style="max-height: 420px; overflow:auto;">
                                    <table class="table table-striped table-bordered table-condensed">
                                        <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Nome completo IMAP</th>
                                            <th>Attributi</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($imapBoxesResult['mailboxes'] as $box): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string)$box['name']); ?></td>
                                                <td><?php echo htmlspecialchars((string)$box['full']); ?></td>
                                                <td><?php echo htmlspecialchars((string)$box['attributes']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
