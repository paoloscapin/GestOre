<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/__Settings.php';
require_once '../common/newsletter_lib.php';

ruoloRichiesto('admin');

$missingTables = newsletterMissingTables();
$message = '';
$messageType = 'info';
$draftDays = max(1, min(30, intval(newsletterPostValue('draft_days', '15'))));
$changelogMaxDates = max(1, min(20, intval(newsletterPostValue('changelog_max_dates', '4'))));
$changelogMaxItems = max(1, min(20, intval(newsletterPostValue('changelog_max_items', '3'))));

$draft = newsletterBuildDraftFromChangelog($changelogMaxDates, $changelogMaxItems);
$sendForm = [
    'title' => $draft['title'],
    'intro' => $draft['intro'],
    'body' => $draft['body'],
    'audience' => newsletterPostValue('audience', 'tutti'),
    'send_telegram' => newsletterPostBool('send_telegram', true),
    'send_mail' => newsletterPostBool('send_mail', true),
    'period_start' => $draft['period_start'],
    'period_end' => $draft['period_end'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($missingTables)) {
    $action = newsletterPostValue('action');

    if ($action === 'add_news_item') {
        $newId = newsletterSaveNewsItem([
            'title' => newsletterPostValue('news_title'),
            'body' => newsletterPostValue('news_body'),
            'audience' => newsletterPostValue('news_audience', 'tutti'),
            'channels' => array_filter([
                newsletterPostBool('news_channel_mail') ? 'mail' : '',
                newsletterPostBool('news_channel_telegram') ? 'telegram' : '',
            ]),
            'news_date' => newsletterPostValue('news_date', date('Y-m-d')),
            'created_by_user_id' => intval($__utente_id ?? 0),
        ]);

        if ($newId > 0) {
            $message = 'Novità registrata correttamente.';
            $messageType = 'success';
            $draft = newsletterBuildDraft($draftDays);
            $sendForm['title'] = $draft['title'];
            $sendForm['intro'] = $draft['intro'];
            $sendForm['body'] = $draft['body'];
            $sendForm['period_start'] = $draft['period_start'];
            $sendForm['period_end'] = $draft['period_end'];
        } else {
            $message = 'Compila almeno titolo e descrizione della novità.';
            $messageType = 'warning';
        }
    }

    if ($action === 'build_draft') {
        $draft = newsletterBuildDraft($draftDays);
        $sendForm['title'] = $draft['title'];
        $sendForm['intro'] = $draft['intro'];
        $sendForm['body'] = $draft['body'];
        $sendForm['period_start'] = $draft['period_start'];
        $sendForm['period_end'] = $draft['period_end'];
        $message = 'Bozza aggiornata con le ultime novità registrate.';
        $messageType = 'success';
    }

    if ($action === 'build_changelog_draft') {
        $draft = newsletterBuildDraftFromChangelog($changelogMaxDates, $changelogMaxItems);
        $sendForm['title'] = $draft['title'];
        $sendForm['intro'] = $draft['intro'];
        $sendForm['body'] = $draft['body'];
        $sendForm['period_start'] = $draft['period_start'];
        $sendForm['period_end'] = $draft['period_end'];
        $archive = newsletterArchiveDraft($draft, 'bozza');
        if ($archive !== null) {
            $message = 'Bozza aggiornata dal changelog.md e archiviata in /newsletter.';
            $messageType = 'success';
        } else {
            $message = 'Bozza aggiornata dal changelog.md, ma non sono riuscito ad archiviarla in /newsletter.';
            $messageType = 'warning';
        }
    }

    if (in_array($action, ['preview_mail', 'preview_telegram', 'send_newsletter'], true)) {
        $sendForm['title'] = newsletterPostValue('title');
        $sendForm['intro'] = newsletterPostValue('intro');
        $sendForm['body'] = newsletterPostValue('body');
        $sendForm['audience'] = newsletterNormalizeAudience(newsletterPostValue('audience', 'tutti'));
        $sendForm['send_telegram'] = newsletterPostBool('send_telegram', false);
        $sendForm['send_mail'] = newsletterPostBool('send_mail', false);
        $sendForm['period_start'] = newsletterPostValue('period_start', $draft['period_start']);
        $sendForm['period_end'] = newsletterPostValue('period_end', $draft['period_end']);

        if ($sendForm['title'] === '' || $sendForm['body'] === '') {
            $message = 'Titolo e contenuto newsletter sono obbligatori.';
            $messageType = 'warning';
        } elseif ($action === 'preview_mail') {
            $previewRes = newsletterSendMailPreview(
                (string)($__useremail ?? ''),
                trim((string)(($__utente_nome ?? '') . ' ' . ($__utente_cognome ?? ''))),
                $sendForm['title'],
                $sendForm['intro'],
                $sendForm['body']
            );
            if (!empty($previewRes['ok'])) {
                $message = 'Anteprima mail inviata solo a ' . (string)($__useremail ?? '') . '.';
                $messageType = 'success';
            } else {
                $message = 'Anteprima mail non inviata: ' . (string)($previewRes['error'] ?? 'errore sconosciuto');
                $messageType = 'warning';
            }
        } elseif ($action === 'preview_telegram') {
            $previewRes = newsletterSendTelegramPreview(
                newsletterBuildTelegramText($sendForm['title'], $sendForm['intro'], $sendForm['body']),
                (string)($__useremail ?? ''),
                (string)($__username ?? '')
            );
            if (!empty($previewRes['ok'])) {
                $message = 'Anteprima Telegram inviata solo al tuo account.';
                $messageType = 'success';
            } else {
                $message = 'Anteprima Telegram non inviata: ' . (string)($previewRes['error'] ?? 'errore sconosciuto') . '. Puoi comunque controllare l\'anteprima a video.';
                $messageType = 'warning';
            }
        } elseif (!$sendForm['send_telegram'] && !$sendForm['send_mail']) {
            $message = 'Seleziona almeno un canale di invio.';
            $messageType = 'warning';
        } else {
            $telegramRes = ['ok' => false, 'sent' => 0, 'errors' => []];
            $mailRes = ['ok' => false, 'sent' => 0, 'errors' => []];

            if ($sendForm['send_telegram']) {
                $telegramMessage = newsletterFormatTelegramNewsletter($sendForm['title'], $sendForm['intro'], $sendForm['body']);
                $telegramRes = newsletterSendTelegramBroadcast($telegramMessage, $sendForm['audience']);
            }

            if ($sendForm['send_mail']) {
                $mailRes = newsletterSendMailBroadcast($sendForm['title'], $sendForm['intro'], $sendForm['body'], $sendForm['audience']);
            }

            newsletterSaveDispatch([
                'title' => $sendForm['title'],
                'intro' => $sendForm['intro'],
                'body' => $sendForm['body'],
                'period_start' => $sendForm['period_start'],
                'period_end' => $sendForm['period_end'],
                'channels' => implode(',', array_filter([
                    $sendForm['send_telegram'] ? 'telegram' : '',
                    $sendForm['send_mail'] ? 'mail' : '',
                ])),
                'audience' => $sendForm['audience'],
                'telegram_sent_count' => intval($telegramRes['sent'] ?? 0),
                'mail_sent_count' => intval($mailRes['sent'] ?? 0),
                'status' => 'INVIATA',
                'created_by_user_id' => intval($__utente_id ?? 0),
                'sent_at' => true,
            ]);

            newsletterArchiveDraft([
                'title' => $sendForm['title'],
                'intro' => $sendForm['intro'],
                'body' => $sendForm['body'],
                'version' => '',
            ], 'inviata');

            $parts = [];
            if ($sendForm['send_telegram']) {
                $parts[] = 'Telegram inviati: ' . intval($telegramRes['sent'] ?? 0);
            }
            if ($sendForm['send_mail']) {
                $parts[] = 'Mail inviate: ' . intval($mailRes['sent'] ?? 0);
            }
            $message = 'Newsletter inviata. ' . implode(' | ', $parts);

            $errors = array_merge($telegramRes['errors'] ?? [], $mailRes['errors'] ?? []);
            if (!empty($errors)) {
                $message .= ' | Alcuni errori: ' . implode('; ', array_slice($errors, 0, 10));
                $messageType = 'warning';
            } else {
                $messageType = 'success';
            }
        }
    }
}

$recentItems = empty($missingTables) ? newsletterGetRecentItems(30) : [];
$latestNewsletters = empty($missingTables) ? newsletterGetLatestSent(20) : [];
$archivedNewsletters = newsletterArchiveList(30);
$telegramCount = count(newsletterTelegramRecipients($sendForm['audience']));
$mailCount = count(newsletterMailRecipients($sendForm['audience']));
$previewMailHtml = newsletterBuildHtml($sendForm['title'], $sendForm['intro'], $sendForm['body']);
$previewTelegramText = newsletterBuildTelegramText($sendForm['title'], $sendForm['intro'], $sendForm['body']);
$previewMailTarget = (string)($__useremail ?? '');
$previewTelegramTarget = newsletterFindPreviewTelegramTarget((string)($__useremail ?? ''), (string)($__username ?? ''));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Newsletter GestOre</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-bullhorn"></span>&emsp;Newsletter e Novità GestOre</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-danger">
                    Mancano le tabelle: <strong><?php echo htmlspecialchars(implode(', ', $missingTables)); ?></strong>.
                    Esegui prima la SQL in <code>doc/newsletter_schema.sql</code>.
                </div>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-5">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Registra una novità</strong></div>
                        <div class="panel-body">
                            <form method="post">
                                <input type="hidden" name="action" value="add_news_item">
                                <div class="form-group">
                                    <label for="news_title">Titolo</label>
                                    <input type="text" class="form-control" id="news_title" name="news_title" required>
                                </div>
                                <div class="form-group">
                                    <label for="news_body">Descrizione</label>
                                    <textarea class="form-control" id="news_body" name="news_body" rows="5" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="news_date">Data novità</label>
                                            <input type="date" class="form-control" id="news_date" name="news_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="news_audience">Pubblico</label>
                                            <select class="form-control" id="news_audience" name="news_audience">
                                                <option value="tutti">Tutti</option>
                                                <option value="docenti">Docenti</option>
                                                <option value="ata">ATA</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Canali</label>
                                        <div class="checkbox"><label><input type="checkbox" name="news_channel_mail" value="1" checked> Mail</label></div>
                                        <div class="checkbox"><label><input type="checkbox" name="news_channel_telegram" value="1" checked> Telegram</label></div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Salva novità</button>
                            </form>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Ultime novità registrate</strong></div>
                        <div class="panel-body" style="max-height:420px; overflow:auto;">
                            <?php if (empty($recentItems)): ?>
                                <div class="alert alert-warning" style="margin-bottom:0;">Nessuna novità registrata.</div>
                            <?php else: ?>
                                <table class="table table-striped table-bordered table-condensed">
                                    <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Titolo</th>
                                        <th>Pubblico</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($recentItems as $item): ?>
                                        <tr>
                                            <td style="white-space:nowrap;"><?php echo htmlspecialchars((string)$item['data_novita']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars((string)$item['titolo']); ?></strong><br>
                                                <small><?php echo nl2br(htmlspecialchars((string)$item['contenuto'])); ?></small>
                                            </td>
                                            <td style="text-align:center;"><?php echo htmlspecialchars((string)$item['audience']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>1. Genera e archivia newsletter</strong></div>
                        <div class="panel-body">
                            <div class="alert alert-info">
                                Questa sezione genera solo la bozza dal <code>changelog.md</code> e la archivia nella cartella <code>/newsletter</code>.
                                Non invia nulla agli utenti.
                            </div>
                            <form method="post" class="form-inline" style="margin-bottom:15px;">
                                <input type="hidden" name="action" value="build_changelog_draft">
                                <div class="form-group">
                                    <label for="changelog_max_dates">Giorni dal changelog</label>
                                    <input type="number" min="1" max="20" class="form-control" id="changelog_max_dates" name="changelog_max_dates" value="<?php echo intval($changelogMaxDates); ?>" style="width:90px;">
                                </div>
                                <div class="form-group" style="margin-left:10px;">
                                    <label for="changelog_max_items">Voci per area</label>
                                    <input type="number" min="1" max="20" class="form-control" id="changelog_max_items" name="changelog_max_items" value="<?php echo intval($changelogMaxItems); ?>" style="width:90px;">
                                </div>
                                <button type="submit" class="btn btn-default" style="margin-left:10px;">Genera bozza dal changelog</button>
                                <span class="help-block" style="display:inline-block; margin-left:10px; margin-bottom:0;">
                                    Prima aggiorna <code>changelog.md</code> con lo script manuale, poi genera qui la newsletter.
                                </span>
                            </form>

                            <form method="post" class="form-inline" style="margin-bottom:15px;">
                                <input type="hidden" name="action" value="build_draft">
                                <div class="form-group">
                                    <label for="draft_days">Oppure novità manuali ultimi giorni</label>
                                    <input type="number" min="1" max="30" class="form-control" id="draft_days" name="draft_days" value="<?php echo intval($draftDays); ?>" style="width:100px;">
                                </div>
                                <button type="submit" class="btn btn-link" style="margin-left:10px;">Usa novità registrate a mano</button>
                            </form>

                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>2. Prepara invio agli utenti</strong></div>
                        <div class="panel-body">
                            <div class="alert alert-warning">
                                L'invio parte solo da questa sezione. Prima controlla testo, destinatari e canali selezionati.
                            </div>

                            <form method="post" onsubmit="if (event.submitter && event.submitter.value === 'send_newsletter') { return confirm('Confermi invio della newsletter ai destinatari selezionati sui canali scelti?'); } return true;">
                                <input type="hidden" name="period_start" value="<?php echo htmlspecialchars((string)$sendForm['period_start']); ?>">
                                <input type="hidden" name="period_end" value="<?php echo htmlspecialchars((string)$sendForm['period_end']); ?>">

                                <div class="form-group">
                                    <label for="title">Titolo newsletter</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars((string)$sendForm['title']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="intro">Introduzione</label>
                                    <textarea class="form-control" id="intro" name="intro" rows="3"><?php echo htmlspecialchars((string)$sendForm['intro']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="body">Contenuto</label>
                                    <textarea class="form-control" id="body" name="body" rows="12" required><?php echo htmlspecialchars((string)$sendForm['body']); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="audience">Destinatari</label>
                                            <select class="form-control" id="audience" name="audience">
                                                <option value="tutti" <?php echo $sendForm['audience'] === 'tutti' ? 'selected' : ''; ?>>Tutti</option>
                                                <option value="docenti" <?php echo $sendForm['audience'] === 'docenti' ? 'selected' : ''; ?>>Docenti</option>
                                                <option value="ata" <?php echo $sendForm['audience'] === 'ata' ? 'selected' : ''; ?>>ATA</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Canali</label>
                                        <div class="checkbox"><label><input type="checkbox" name="send_telegram" value="1" <?php echo !empty($sendForm['send_telegram']) ? 'checked' : ''; ?>> Telegram</label></div>
                                        <div class="checkbox"><label><input type="checkbox" name="send_mail" value="1" <?php echo !empty($sendForm['send_mail']) ? 'checked' : ''; ?>> Mail</label></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="well well-sm" style="margin-top:24px; margin-bottom:0;">
                                            <strong>Stima invio</strong><br>
                                            Telegram: <?php echo intval($telegramCount); ?><br>
                                            Mail: <?php echo intval($mailCount); ?><br>
                                            <small>Telegram oggi copre i profili già abilitati. ATA Telegram lo aggiungiamo nel prossimo step.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="panel panel-info" style="margin-top:15px;">
                                    <div class="panel-heading"><strong>Anteprima mail</strong></div>
                                    <div class="panel-body" style="background:#eef2f7;">
                                        <?php echo $previewMailHtml; ?>
                                    </div>
                                </div>

                                <div class="panel panel-info">
                                    <div class="panel-heading"><strong>Anteprima Telegram</strong></div>
                                    <div class="panel-body">
                                        <pre style="white-space:pre-wrap; margin-bottom:0;"><?php echo htmlspecialchars($previewTelegramText); ?></pre>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <strong>Destinatari anteprima:</strong><br>
                                    Mail: <?php echo $previewMailTarget !== '' ? htmlspecialchars($previewMailTarget) : '<span class="text-danger">email utente non disponibile</span>'; ?><br>
                                    Telegram:
                                    <?php if ($previewTelegramTarget !== null): ?>
                                        <?php echo htmlspecialchars((string)$previewTelegramTarget['label']); ?>
                                        <small>(<?php echo htmlspecialchars((string)$previewTelegramTarget['source']); ?>)</small>
                                    <?php else: ?>
                                        <span class="text-warning">chat Telegram personale non trovata</span>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" name="action" value="preview_mail" class="btn btn-default">
                                    Invia anteprima mail solo a me
                                </button>
                                <button type="submit" name="action" value="preview_telegram" class="btn btn-default">
                                    Invia anteprima Telegram solo a me
                                </button>
                                <button type="submit" name="action" value="send_newsletter" class="btn btn-success pull-right">
                                    Invia newsletter agli utenti selezionati
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Storico invii</strong></div>
                        <div class="panel-body" style="max-height:320px; overflow:auto;">
                            <?php if (empty($latestNewsletters)): ?>
                                <div class="alert alert-warning" style="margin-bottom:0;">Nessuna newsletter inviata o registrata.</div>
                            <?php else: ?>
                                <table class="table table-striped table-bordered table-condensed">
                                    <thead>
                                    <tr>
                                        <th>Quando</th>
                                        <th>Titolo</th>
                                        <th>Pubblico</th>
                                        <th>Canali</th>
                                        <th>Invii</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($latestNewsletters as $row): ?>
                                        <tr>
                                            <td style="white-space:nowrap;"><?php echo htmlspecialchars((string)($row['sent_at'] ?: $row['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['titolo']); ?></td>
                                            <td style="text-align:center;"><?php echo htmlspecialchars((string)$row['audience']); ?></td>
                                            <td style="text-align:center;"><?php echo htmlspecialchars((string)$row['channels']); ?></td>
                                            <td style="white-space:nowrap;">TG <?php echo intval($row['telegram_sent_count'] ?? 0); ?> | Mail <?php echo intval($row['mail_sent_count'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Archivio file newsletter</strong></div>
                        <div class="panel-body" style="max-height:260px; overflow:auto;">
                            <?php if (empty($archivedNewsletters)): ?>
                                <div class="alert alert-warning" style="margin-bottom:0;">Nessuna newsletter archiviata nella cartella <code>/newsletter</code>.</div>
                            <?php else: ?>
                                <table class="table table-striped table-bordered table-condensed">
                                    <thead>
                                    <tr>
                                        <th>Data file</th>
                                        <th>File</th>
                                        <th>Dimensione</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($archivedNewsletters as $row): ?>
                                        <tr>
                                            <td style="white-space:nowrap;"><?php echo htmlspecialchars((string)$row['date']); ?></td>
                                            <td><a href="<?php echo htmlspecialchars((string)$row['url']); ?>" target="_blank"><?php echo htmlspecialchars((string)$row['file']); ?></a></td>
                                            <td style="white-space:nowrap;"><?php echo number_format(((int)$row['size']) / 1024, 1, ',', '.'); ?> KB</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
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
