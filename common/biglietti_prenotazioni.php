<?php

/**
 *  This file is part of GestOre
 */

require_once __DIR__ . '/checkSession.php';
require_once __DIR__ . '/ticket_eventi_lib.php';

ticketEventiEnsureSchema();

if (!haRuolo('studente') && !haRuolo('docente') && !haRuolo('personale-ata') && !haRuolo('admin')) {
    redirect('/error/unauthorized.php');
    exit();
}

$actor = ticketEventiCurrentActor();
$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $actor !== null) {
    $action = trim((string)($_POST['form_action'] ?? ''));
    $eventoId = (int)($_POST['evento_id'] ?? 0);
    $evento = ticketEventiGetEventById($eventoId, (int)$__anno_scolastico_corrente_id);

    if (!$evento) {
        $flash = 'Evento non trovato.';
        $flashType = 'danger';
    } elseif ($action === 'reserve') {
        $result = ticketEventiUpsertReservation(
            $actor,
            $evento,
            (int)($_POST['numero_posti'] ?? 1),
            trim((string)($_POST['note'] ?? '')),
            (int)$__anno_scolastico_corrente_id
        );
        $flash = (string)$result['message'];
        $flashType = !empty($result['ok']) ? 'success' : 'danger';
    } elseif ($action === 'cancel') {
        $result = ticketEventiCancelReservation($actor, $eventoId);
        $flash = (string)$result['message'];
        $flashType = !empty($result['ok']) ? 'success' : 'danger';
    }
}

$eventi = $actor !== null
    ? ticketEventiGetVisibleEventsForActor($actor, (int)$__anno_scolastico_corrente_id)
    : [];

$headerFile = __DIR__ . '/header-admin.php';
if (haRuolo('studente')) {
    $headerFile = __DIR__ . '/header-studente.php';
} elseif (haRuolo('docente')) {
    $headerFile = __DIR__ . '/header-docente.php';
} elseif (haRuolo('personale-ata')) {
    $headerFile = __DIR__ . '/header-ata.php';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Prenotazione Biglietti</title>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <?php require_once __DIR__ . '/header-common.php'; ?>
    <?php require_once __DIR__ . '/style.php'; ?>
    <style>
        .ticket-booking-page {
            padding-top: var(--header-offset, 90px);
            padding-bottom: 24px;
        }

        .ticket-booking-hero {
            border-radius: 22px;
            background: linear-gradient(135deg, #1c56d6 0%, #4f86f7 100%);
            color: #fff;
            padding: 28px 30px;
            margin-bottom: 18px;
            box-shadow: 0 16px 40px rgba(41, 91, 198, .20);
        }

        .ticket-booking-hero h1 {
            margin: 0 0 10px 0;
            font-size: 36px;
            font-weight: 600;
        }

        .ticket-booking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 18px;
        }

        .ticket-booking-card {
            background: #fff;
            border: 1px solid #dde7f5;
            border-radius: 18px;
            box-shadow: 0 10px 24px rgba(16, 24, 40, .06);
            overflow: hidden;
            position: relative;
        }

        .ticket-booking-card.is-booked {
            border-color: #38a169;
            box-shadow: 0 18px 40px rgba(34, 139, 86, .18);
            transform: translateY(-2px);
        }

        .ticket-booked-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #198754 0%, #35b36f 100%);
            color: #fff;
        }

        .ticket-booked-banner-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
        }

        .ticket-booked-banner-meta {
            text-align: right;
            font-size: 13px;
            font-weight: 600;
        }

        .ticket-booking-card-header {
            padding: 18px 20px 14px 20px;
            border-bottom: 1px solid #eef2f7;
        }

        .ticket-booking-card-body {
            padding: 18px 20px 20px 20px;
        }

        .ticket-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .ticket-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: #edf3ff;
            color: #224b94;
            font-size: 12px;
            font-weight: 600;
        }

        .ticket-meta {
            color: #5f6c7b;
            margin-bottom: 10px;
        }

        .ticket-current {
            background: linear-gradient(135deg, #f2fff6 0%, #ecfff4 100%);
            border: 2px solid #9fe0b9;
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 16px;
            color: #155b39;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.4);
        }

        .ticket-current strong {
            font-size: 24px;
        }

        .ticket-current-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 20px;
            font-weight: 700;
        }

        .ticket-current-notes {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #83caa2;
        }

        .ticket-closed {
            color: #8b1e3f;
            background: #fff1f3;
            border: 1px solid #f4c7d1;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }

        .ticket-note {
            color: #667085;
            font-size: 13px;
        }
    </style>
</head>
<body>
<?php require_once $headerFile; ?>

<div class="container-fluid ticket-booking-page">
    <div class="ticket-booking-hero">
        <h1>Prenotazione biglietti eventi</h1>
        <?php if ($actor !== null): ?>
            <div>
                Prenoti con il tuo account GestOre come
                <strong><?php echo htmlspecialchars(ticketEventiRoleLabel((string)$actor['ruolo']), ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php if (!empty($actor['classe_label'])): ?>
                    della classe <strong><?php echo htmlspecialchars((string)$actor['classe_label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php endif; ?>.
            </div>
        <?php else: ?>
            <div>Il profilo attuale non ha un'anagrafica prenotabile collegata.</div>
        <?php endif; ?>
    </div>

    <?php if ($flash !== null): ?>
        <div class="alert alert-<?php echo $flashType; ?>">
            <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($actor === null): ?>
        <div class="alert alert-warning">
            Il tuo account non e collegato a un profilo studente, docente o ATA. Se vuoi, posso aggiungere anche un profilo admin di test per le prenotazioni.
        </div>
    <?php elseif (!$eventi): ?>
        <div class="alert alert-info">
            Al momento non ci sono eventi prenotabili per il tuo profilo.
        </div>
    <?php else: ?>
        <div class="ticket-booking-grid">
            <?php foreach ($eventi as $evento): ?>
                <?php
                $bookable = ticketEventiIsBookable($evento);
                $maxTotali = (int)($evento['max_posti_totali'] ?? 0);
                $postiPrenotati = (int)($evento['posti_prenotati'] ?? 0);
                $postiResidui = $maxTotali > 0 ? max(0, $maxTotali - $postiPrenotati) : null;
                $currentSeats = max(1, (int)($evento['prenotazione_numero_posti'] ?? 1));
                $hasReservation = !empty($evento['prenotazione_id']);
                ?>
                <div class="ticket-booking-card<?php echo $hasReservation ? ' is-booked' : ''; ?>">
                    <?php if ($hasReservation): ?>
                        <div class="ticket-booked-banner">
                            <div class="ticket-booked-banner-title">
                                <span class="glyphicon glyphicon-ok-sign"></span>
                                <span>Prenotazione registrata</span>
                            </div>
                            <div class="ticket-booked-banner-meta">
                                <?php echo (int)$evento['prenotazione_numero_posti']; ?> posto/i confermati
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="ticket-booking-card-header">
                        <h3 style="margin: 0;"><?php echo htmlspecialchars((string)$evento['titolo'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="ticket-badge-row">
                            <span class="ticket-badge"><?php echo htmlspecialchars(ticketEventiStatoLabel((string)$evento['stato']), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="ticket-badge"><?php echo htmlspecialchars(ticketEventiFormatDateTime((string)$evento['data_evento']), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($evento['luogo'])): ?>
                                <span class="ticket-badge"><?php echo htmlspecialchars((string)$evento['luogo'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ticket-booking-card-body">
                        <?php if (!empty($evento['descrizione'])): ?>
                            <div class="ticket-meta"><?php echo nl2br(htmlspecialchars((string)$evento['descrizione'], ENT_QUOTES, 'UTF-8')); ?></div>
                        <?php endif; ?>

                        <?php if (!empty(ticketEventiBookingWindowLabel($evento))): ?>
                            <div class="ticket-meta"><?php echo htmlspecialchars(ticketEventiBookingWindowLabel($evento), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <div class="ticket-meta">
                            Massimo posti per te: <strong><?php echo (int)$evento['max_posti_per_utente']; ?></strong>
                            <?php if ($maxTotali > 0): ?>
                                <br>Posti residui stimati: <strong><?php echo $postiResidui; ?></strong> su <?php echo $maxTotali; ?>
                            <?php else: ?>
                                <br>Capienza: <strong>non limitata</strong>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasReservation): ?>
                            <div class="ticket-current">
                                <div class="ticket-current-title">
                                    <span class="glyphicon glyphicon-check"></span>
                                    <span>Hai gia prenotato questo evento</span>
                                </div>
                                <div>
                                    Prenotazione attiva per <strong><?php echo (int)$evento['prenotazione_numero_posti']; ?></strong> posto/i.
                                </div>
                                <?php if (!empty($evento['prenotazione_note'])): ?>
                                    <div class="ticket-current-notes">
                                        Note salvate: <?php echo htmlspecialchars((string)$evento['prenotazione_note'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$bookable): ?>
                            <div class="ticket-closed">
                                Le prenotazioni non sono aperte in questo momento.
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="form_action" value="reserve">
                            <input type="hidden" name="evento_id" value="<?php echo (int)$evento['id']; ?>">

                            <div class="form-group">
                                <label for="numero_posti_<?php echo (int)$evento['id']; ?>">Numero posti</label>
                                <input type="number"
                                       min="1"
                                       max="<?php echo (int)$evento['max_posti_per_utente']; ?>"
                                       class="form-control"
                                       id="numero_posti_<?php echo (int)$evento['id']; ?>"
                                       name="numero_posti"
                                       value="<?php echo $currentSeats; ?>"
                                    <?php echo $bookable ? '' : 'disabled'; ?>>
                            </div>

                            <div class="form-group">
                                <label for="note_<?php echo (int)$evento['id']; ?>">Note</label>
                                <textarea class="form-control"
                                          id="note_<?php echo (int)$evento['id']; ?>"
                                          name="note"
                                          rows="3"
                                    <?php echo $bookable ? '' : 'disabled'; ?>><?php echo htmlspecialchars((string)($evento['prenotazione_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" <?php echo $bookable ? '' : 'disabled'; ?>>
                                <span class="glyphicon glyphicon-ok-circle"></span>&ensp;<?php echo $hasReservation ? 'Aggiorna prenotazione' : 'Prenota'; ?>
                            </button>

                            <?php if ($hasReservation): ?>
                                <button type="submit" class="btn btn-default" name="form_action" value="cancel">
                                    <span class="glyphicon glyphicon-remove-circle"></span>&ensp;Annulla
                                </button>
                            <?php endif; ?>
                        </form>

                        <div class="ticket-note" style="margin-top: 10px;">
                            I dati arrivano dal tuo profilo GestOre. Se servono preferenze piu specifiche, nel prossimo passo possiamo aggiungere campi dedicati.
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
