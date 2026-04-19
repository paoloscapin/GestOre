<?php

/**
 *  This file is part of GestOre
 */

require_once '../common/checkSession.php';
require_once '../common/ticket_eventi_lib.php';

ruoloRichiesto('admin');
ticketEventiEnsureSchema();

$flash = null;
$flashType = 'success';

$formData = [
    'titolo' => '',
    'descrizione' => '',
    'luogo' => '',
    'data_evento' => '',
    'apertura_prenotazioni' => '',
    'chiusura_prenotazioni' => '',
    'max_posti_per_utente' => '1',
    'max_posti_totali' => '',
    'visibile_studenti' => '1',
    'visibile_docenti' => '1',
    'visibile_ata' => '1',
    'stato' => 'bozza',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['form_action'] ?? ''));

    if ($action === 'create_event') {
        $formData = [
            'titolo' => trim((string)($_POST['titolo'] ?? '')),
            'descrizione' => trim((string)($_POST['descrizione'] ?? '')),
            'luogo' => trim((string)($_POST['luogo'] ?? '')),
            'data_evento' => trim((string)($_POST['data_evento'] ?? '')),
            'apertura_prenotazioni' => trim((string)($_POST['apertura_prenotazioni'] ?? '')),
            'chiusura_prenotazioni' => trim((string)($_POST['chiusura_prenotazioni'] ?? '')),
            'max_posti_per_utente' => trim((string)($_POST['max_posti_per_utente'] ?? '1')),
            'max_posti_totali' => trim((string)($_POST['max_posti_totali'] ?? '')),
            'visibile_studenti' => !empty($_POST['visibile_studenti']) ? '1' : '',
            'visibile_docenti' => !empty($_POST['visibile_docenti']) ? '1' : '',
            'visibile_ata' => !empty($_POST['visibile_ata']) ? '1' : '',
            'stato' => trim((string)($_POST['stato'] ?? 'bozza')),
        ];

        $result = ticketEventiSaveEvent($_POST, (int)$__anno_scolastico_corrente_id, (int)$__utente_id);
        $flash = (string)$result['message'];
        $flashType = !empty($result['ok']) ? 'success' : 'danger';

        if (!empty($result['ok'])) {
            $formData = [
                'titolo' => '',
                'descrizione' => '',
                'luogo' => '',
                'data_evento' => '',
                'apertura_prenotazioni' => '',
                'chiusura_prenotazioni' => '',
                'max_posti_per_utente' => '1',
                'max_posti_totali' => '',
                'visibile_studenti' => '1',
                'visibile_docenti' => '1',
                'visibile_ata' => '1',
                'stato' => 'bozza',
            ];
        }
    } elseif ($action === 'set_status') {
        $result = ticketEventiUpdateEventStatus(
            (int)($_POST['evento_id'] ?? 0),
            (int)$__anno_scolastico_corrente_id,
            (string)($_POST['nuovo_stato'] ?? ''),
            (int)$__utente_id
        );
        $flash = (string)$result['message'];
        $flashType = !empty($result['ok']) ? 'success' : 'danger';
    } elseif ($action === 'delete_event') {
        $result = ticketEventiDeleteEvent(
            (int)($_POST['evento_id'] ?? 0),
            (int)$__anno_scolastico_corrente_id
        );
        $flash = (string)$result['message'];
        $flashType = !empty($result['ok']) ? 'success' : 'danger';
    }
}

$eventi = ticketEventiGetEventsForAdmin((int)$__anno_scolastico_corrente_id);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Eventi Biglietti</title>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .ticket-page {
            padding-top: var(--header-offset, 90px);
            padding-bottom: 24px;
        }

        .ticket-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ticket-muted {
            color: #667085;
        }

        .ticket-grid {
            display: grid;
            grid-template-columns: 1.1fr 1.3fr;
            gap: 18px;
        }

        .ticket-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .ticket-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eef4ff;
            color: #244ea3;
            font-weight: 600;
            font-size: 12px;
        }

        .ticket-table th,
        .ticket-table td {
            vertical-align: middle !important;
        }

        .ticket-reservations {
            margin-top: 10px;
            background: #f8fbff;
            border: 1px solid #d9e6f7;
            border-radius: 10px;
            padding: 12px;
        }

        .ticket-reservations h5 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        @media (max-width: 1100px) {
            .ticket-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>

<div class="container-fluid ticket-page">
    <div class="ticket-grid">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <div class="ticket-toolbar">
                    <div>
                        <span class="glyphicon glyphicon-plus-sign"></span>&ensp;Nuovo evento biglietti
                    </div>
                    <div class="ticket-muted">
                        Anno scolastico: <?php echo htmlspecialchars((string)$__anno_scolastico_corrente_anno, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <?php if ($flash !== null): ?>
                    <div class="alert alert-<?php echo $flashType; ?>">
                        <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="form_action" value="create_event">

                    <div class="form-group">
                        <label for="titolo">Titolo evento</label>
                        <input type="text" class="form-control" id="titolo" name="titolo"
                               value="<?php echo htmlspecialchars($formData['titolo'], ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Es. Trentino Volley - Finale del 12 maggio">
                    </div>

                    <div class="form-group">
                        <label for="descrizione">Descrizione</label>
                        <textarea class="form-control" id="descrizione" name="descrizione" rows="3"
                                  placeholder="Informazioni per utenti e segreteria"><?php echo htmlspecialchars($formData['descrizione'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="luogo">Luogo</label>
                        <input type="text" class="form-control" id="luogo" name="luogo"
                               value="<?php echo htmlspecialchars($formData['luogo'], ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Es. ilT quotidiano Arena">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="data_evento">Data evento</label>
                                <input type="datetime-local" class="form-control" id="data_evento" name="data_evento"
                                       value="<?php echo htmlspecialchars($formData['data_evento'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="stato">Stato iniziale</label>
                                <select class="form-control" id="stato" name="stato">
                                    <option value="bozza" <?php echo $formData['stato'] === 'bozza' ? 'selected' : ''; ?>>Bozza</option>
                                    <option value="aperto" <?php echo $formData['stato'] === 'aperto' ? 'selected' : ''; ?>>Aperto</option>
                                    <option value="chiuso" <?php echo $formData['stato'] === 'chiuso' ? 'selected' : ''; ?>>Chiuso</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="apertura_prenotazioni">Apertura prenotazioni</label>
                                <input type="datetime-local" class="form-control" id="apertura_prenotazioni" name="apertura_prenotazioni"
                                       value="<?php echo htmlspecialchars($formData['apertura_prenotazioni'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="chiusura_prenotazioni">Chiusura prenotazioni</label>
                                <input type="datetime-local" class="form-control" id="chiusura_prenotazioni" name="chiusura_prenotazioni"
                                       value="<?php echo htmlspecialchars($formData['chiusura_prenotazioni'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="max_posti_per_utente">Massimo posti per utente</label>
                                <input type="number" min="1" class="form-control" id="max_posti_per_utente" name="max_posti_per_utente"
                                       value="<?php echo htmlspecialchars($formData['max_posti_per_utente'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="max_posti_totali">Massimo posti totali</label>
                                <input type="number" min="0" class="form-control" id="max_posti_totali" name="max_posti_totali"
                                       value="<?php echo htmlspecialchars($formData['max_posti_totali'], ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="Lascia vuoto per illimitato">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Utenti abilitati</label>
                        <div class="checkbox">
                            <label><input type="checkbox" name="visibile_studenti" value="1" <?php echo $formData['visibile_studenti'] === '1' ? 'checked' : ''; ?>> Studenti</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="visibile_docenti" value="1" <?php echo $formData['visibile_docenti'] === '1' ? 'checked' : ''; ?>> Docenti</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="visibile_ata" value="1" <?php echo $formData['visibile_ata'] === '1' ? 'checked' : ''; ?>> ATA</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Crea evento
                    </button>
                </form>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading">
                <span class="glyphicon glyphicon-list-alt"></span>&ensp;Flusso consigliato
            </div>
            <div class="panel-body">
                <ol style="padding-left: 18px; margin-bottom: 0;">
                    <li>Crea l'evento con data, finestre di prenotazione e destinatari.</li>
                    <li>Metti lo stato su <strong>Aperto</strong> quando vuoi raccogliere le adesioni.</li>
                    <li>Gli utenti prenotano da GestOre con il proprio account.</li>
                    <li>Quando vuoi congelare le richieste, imposta lo stato su <strong>Chiuso</strong>.</li>
                    <li>Nel passo successivo collegheremo queste prenotazioni all'assegnazione dei biglietti PDF.</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-calendar"></span>&ensp;Eventi creati
        </div>
        <div class="panel-body">
            <?php if (!$eventi): ?>
                <div class="alert alert-info">Non ci sono ancora eventi biglietti configurati.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered ticket-table">
                        <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Data</th>
                            <th>Prenotazioni</th>
                            <th>Utenti</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($eventi as $evento): ?>
                            <?php $prenotazioni = ticketEventiGetReservationsForEvent((int)$evento['id']); ?>
                            <tr>
                                <td style="min-width: 280px;">
                                    <strong><?php echo htmlspecialchars((string)$evento['titolo'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <?php if (!empty($evento['luogo'])): ?>
                                        <span class="ticket-muted"><?php echo htmlspecialchars((string)$evento['luogo'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['descrizione'])): ?>
                                        <span class="ticket-muted"><?php echo nl2br(htmlspecialchars((string)$evento['descrizione'], ENT_QUOTES, 'UTF-8')); ?></span>
                                    <?php endif; ?>
                                    <div class="ticket-stats">
                                        <span class="ticket-pill"><?php echo htmlspecialchars(ticketEventiAllowedRolesLabel($evento), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty(ticketEventiBookingWindowLabel($evento))): ?>
                                            <span class="ticket-pill"><?php echo htmlspecialchars(ticketEventiBookingWindowLabel($evento), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars(ticketEventiFormatDateTime((string)$evento['data_evento']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <strong><?php echo (int)$evento['posti_prenotati']; ?></strong> posti<br>
                                    <span class="ticket-muted"><?php echo (int)$evento['prenotazioni_attive']; ?> prenotazioni</span><br>
                                    <?php if (!empty($evento['max_posti_totali'])): ?>
                                        <span class="ticket-muted">Capienza: <?php echo (int)$evento['max_posti_totali']; ?></span>
                                    <?php else: ?>
                                        <span class="ticket-muted">Capienza: libera</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    Max utente: <?php echo (int)$evento['max_posti_per_utente']; ?>
                                </td>
                                <td>
                                    <span class="label label-<?php echo $evento['stato'] === 'aperto' ? 'success' : ($evento['stato'] === 'chiuso' ? 'default' : 'warning'); ?>">
                                        <?php echo htmlspecialchars(ticketEventiStatoLabel((string)$evento['stato']), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td style="min-width: 300px;">
                                    <div class="btn-group btn-group-sm" style="margin-bottom: 8px;">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="form_action" value="set_status">
                                            <input type="hidden" name="evento_id" value="<?php echo (int)$evento['id']; ?>">
                                            <input type="hidden" name="nuovo_stato" value="bozza">
                                            <button type="submit" class="btn btn-warning">Bozza</button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="form_action" value="set_status">
                                            <input type="hidden" name="evento_id" value="<?php echo (int)$evento['id']; ?>">
                                            <input type="hidden" name="nuovo_stato" value="aperto">
                                            <button type="submit" class="btn btn-success">Apri</button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="form_action" value="set_status">
                                            <input type="hidden" name="evento_id" value="<?php echo (int)$evento['id']; ?>">
                                            <input type="hidden" name="nuovo_stato" value="chiuso">
                                            <button type="submit" class="btn btn-default">Chiudi</button>
                                        </form>
                                    </div>
                                    <div>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Eliminare questo evento e tutte le prenotazioni collegate?');">
                                            <input type="hidden" name="form_action" value="delete_event">
                                            <input type="hidden" name="evento_id" value="<?php echo (int)$evento['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <span class="glyphicon glyphicon-trash"></span>&ensp;Elimina
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" style="background: #fff;">
                                    <div class="ticket-reservations">
                                        <h5>Prenotazioni attive</h5>
                                        <?php if (!$prenotazioni): ?>
                                            <span class="ticket-muted">Nessuna prenotazione registrata.</span>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-condensed table-bordered">
                                                    <thead>
                                                    <tr>
                                                        <th>Nominativo</th>
                                                        <th>Ruolo</th>
                                                        <th>Classe</th>
                                                        <th>Email</th>
                                                        <th>Posti</th>
                                                        <th>Note</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($prenotazioni as $prenotazione): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars((string)$prenotazione['nominativo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars(ticketEventiRoleLabel((string)$prenotazione['ruolo']), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($prenotazione['classe_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($prenotazione['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                            <td><?php echo (int)$prenotazione['numero_posti']; ?></td>
                                                            <td><?php echo htmlspecialchars((string)($prenotazione['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
