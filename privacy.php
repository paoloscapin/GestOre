<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/common/path.php';
require_once __DIR__ . '/common/__Settings.php';

function privacySetting(string $name, string $default = ''): string
{
    global $__settings;
    $privacy = $__settings->local->privacy ?? null;
    $value = (is_object($privacy) && isset($privacy->{$name})) ? $privacy->{$name} : $default;
    return trim((string)$value);
}

function privacyHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$nomeIstituto = privacySetting('nomeIstituto', (string)($__settings->local->nomeIstituto ?? 'Istituto scolastico'));
$titolare = privacySetting('titolareTrattamento', $nomeIstituto);
$indirizzo = privacySetting('indirizzo', '');
$emailPrivacy = privacySetting('emailPrivacy', (string)($__settings->local->emailNoReplyFrom ?? ''));
$pec = privacySetting('pec', '');
$dpo = privacySetting('dpo', '');
$dpoEmail = privacySetting('dpoEmail', '');
$ultimoAggiornamento = privacySetting('ultimoAggiornamento', date('d/m/Y'));

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestOre - Privacy</title>
    <link rel="icon" href="<?php echo privacyHtml($__application_base_path); ?>/ore-32.png" type="image/png">
    <link rel="stylesheet" href="<?php echo privacyHtml($__application_base_path); ?>/common/bootstrap-3.3.7-dist/css/bootstrap.min.css">
    <style>
        :root {
            --bg: #f6f8fb;
            --paper: #ffffff;
            --text: #182230;
            --muted: #667085;
            --line: rgba(16, 24, 40, 0.12);
            --blue: #0b6fbf;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-size: 16px;
            line-height: 1.55;
        }

        .privacy-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 34px 16px 54px;
        }

        .privacy-header {
            margin-bottom: 22px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 18px;
        }

        .privacy-logo {
            height: 58px;
            width: auto;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 32px;
            font-weight: 800;
        }

        h2 {
            margin: 28px 0 10px;
            font-size: 21px;
            font-weight: 800;
        }

        h3 {
            margin: 18px 0 8px;
            font-size: 17px;
            font-weight: 800;
        }

        .privacy-card {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 10px 28px rgba(16, 24, 40, 0.08);
        }

        .privacy-muted {
            color: var(--muted);
        }

        .privacy-meta {
            display: grid;
            grid-template-columns: 190px 1fr;
            gap: 6px 14px;
            margin: 18px 0 0;
        }

        .privacy-meta dt {
            color: var(--muted);
            font-weight: 700;
        }

        .privacy-meta dd {
            margin: 0;
        }

        .privacy-alert {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #7c2d12;
            border-radius: 8px;
            padding: 12px 14px;
            margin: 18px 0;
        }

        .privacy-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 15px;
        }

        .privacy-table th,
        .privacy-table td {
            border: 1px solid var(--line);
            padding: 10px;
            vertical-align: top;
        }

        .privacy-table th {
            background: #eef6ff;
            color: #123b62;
        }

        .privacy-actions {
            margin-top: 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .privacy-button,
        .privacy-actions a {
            color: var(--blue);
            font-weight: 700;
        }

        .privacy-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 18px;
            padding: 10px 14px;
            border-radius: 8px;
            background: #0b6fbf;
            color: #ffffff;
            text-decoration: none;
            border: 1px solid #095a9b;
        }

        .privacy-button:hover,
        .privacy-button:focus {
            color: #ffffff;
            text-decoration: none;
            background: #095a9b;
        }

        @media (max-width: 640px) {
            .privacy-card {
                padding: 18px;
            }

            h1 {
                font-size: 26px;
            }

            .privacy-meta {
                grid-template-columns: 1fr;
            }

            .privacy-table {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <main class="privacy-shell">
        <section class="privacy-card">
            <a class="privacy-button" href="<?php echo privacyHtml($__application_base_path); ?>/index.php">
                <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
                Torna alla login
            </a>

            <div class="privacy-header">
                <img class="privacy-logo" src="<?php echo privacyHtml($__application_base_path); ?>/img/logo_Buonarroti.png" alt="<?php echo privacyHtml($nomeIstituto); ?>">
                <h1>Informativa privacy GestOre</h1>
                <p class="privacy-muted">
                    Informativa sul trattamento dei dati personali ai sensi degli articoli 13 e 14 del Regolamento UE 2016/679.
                </p>
                <dl class="privacy-meta">
                    <dt>Titolare</dt>
                    <dd><?php echo privacyHtml($titolare); ?></dd>
                    <?php if ($indirizzo !== ''): ?>
                        <dt>Sede</dt>
                        <dd><?php echo privacyHtml($indirizzo); ?></dd>
                    <?php endif; ?>
                    <?php if ($emailPrivacy !== ''): ?>
                        <dt>Contatto privacy</dt>
                        <dd><a href="mailto:<?php echo privacyHtml($emailPrivacy); ?>"><?php echo privacyHtml($emailPrivacy); ?></a></dd>
                    <?php endif; ?>
                    <?php if ($pec !== ''): ?>
                        <dt>PEC</dt>
                        <dd><a href="mailto:<?php echo privacyHtml($pec); ?>"><?php echo privacyHtml($pec); ?></a></dd>
                    <?php endif; ?>
                    <?php if ($dpo !== '' || $dpoEmail !== ''): ?>
                        <dt>RPD/DPO</dt>
                        <dd>
                            <?php if ($dpo !== ''): ?>
                                <?php echo privacyHtml($dpo); ?>
                                <?php if ($dpoEmail !== ''): ?> - <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($dpoEmail !== ''): ?>
                                <a href="mailto:<?php echo privacyHtml($dpoEmail); ?>"><?php echo privacyHtml($dpoEmail); ?></a>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>
                    <dt>Ultimo aggiornamento</dt>
                    <dd><?php echo privacyHtml($ultimoAggiornamento); ?></dd>
                </dl>
            </div>

            <?php if ($emailPrivacy === '' || $dpoEmail === ''): ?>
                <div class="privacy-alert">
                    Completare in <code>GestOre.json</code> i dati privacy dell'istituto, in particolare contatto privacy e RPD/DPO se previsto.
                </div>
            <?php endif; ?>

            <h2>Finalita del trattamento</h2>
            <p>
                GestOre supporta l'organizzazione scolastica nella gestione di attivita didattiche e amministrative:
                ore dei docenti, sostituzioni, sportelli, permessi, uscite didattiche, programmi, comunicazioni operative,
                notifiche e servizi collegati agli account istituzionali.
            </p>

            <h2>Categorie di dati trattati</h2>
            <table class="privacy-table">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Esempi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Dati identificativi e di contatto</td>
                        <td>Nome, cognome, username, email istituzionale o email comunicata alla scuola.</td>
                    </tr>
                    <tr>
                        <td>Dati scolastici e organizzativi</td>
                        <td>Classe, materia, orario, aula, incarichi, sportelli, sostituzioni, corsi, attivita e programmi.</td>
                    </tr>
                    <tr>
                        <td>Dati amministrativi</td>
                        <td>Richieste di permesso, ferie, rendicontazioni, partecipazioni a uscite o impegni in istituto.</td>
                    </tr>
                    <tr>
                        <td>Dati tecnici</td>
                        <td>Log applicativi, data e ora di accesso, indirizzo IP, identificativi di sessione, preferenze tecniche.</td>
                    </tr>
                    <tr>
                        <td>Dati di comunicazione</td>
                        <td>Email, notifiche, messaggi Telegram o altri avvisi inviati tramite i canali configurati dall'istituto.</td>
                    </tr>
                    <tr>
                        <td>Dati relativi a pagamenti e contributi scolastici</td>
                        <td>Avvisi pagoPA, importi, scadenze, identificativi di pagamento e stato dei versamenti.</td>
                    </tr>
                </tbody>
            </table>

            <h2>Base giuridica</h2>
            <p>
                Il trattamento e svolto per l'esecuzione di compiti di interesse pubblico e di obblighi istituzionali
                connessi all'organizzazione del servizio scolastico, nonche per obblighi di legge, regolamento e gestione
                del rapporto con personale, studenti e famiglie. Alcune funzioni tecniche opzionali, come notifiche push
                o canali aggiuntivi di comunicazione, possono richiedere una scelta attiva dell'utente o specifica
                configurazione dell'istituto.
            </p>

            <h2>Servizi integrati</h2>
            <p>
                GestOre puo integrarsi con servizi esterni necessari all'erogazione delle funzionalita: autenticazione
                Google, Registro elettronico MasterCom, Google Calendar, Google Drive, Gmail, Telegram, servizi email SMTP
                e sistemi di notifica. I dati scambiati sono limitati a quanto necessario per la funzione attivata.
            </p>

            <h2>Estensione Chrome "GestOre - Import ISIREL pagoPA"</h2>

            <p>
                L'Istituto può utilizzare l'estensione Chrome "GestOre - Import ISIREL pagoPA"
                per consentire al personale autorizzato di importare nel sistema GestOre gli
                avvisi di pagamento presenti nel portale ISIREL della Provincia Autonoma di Trento.
            </p>

            <p>
                L'estensione può trattare dati identificativi degli studenti e informazioni
                relative agli avvisi di pagamento, quali importi, scadenze, identificativi
                pagoPA e stato dei pagamenti, esclusivamente per le finalità amministrative
                e organizzative connesse alla gestione delle attività scolastiche.
            </p>

            <p>
                L'estensione accede esclusivamente ai dati già disponibili all'utente autenticato
                nel portale ISIREL e li trasferisce a GestOre per consentire la gestione
                centralizzata delle attività e dei relativi pagamenti.
            </p>

            <p>
                L'estensione non effettua profilazione degli utenti, non mostra pubblicità,
                non vende dati personali e non trasferisce dati a soggetti terzi per finalità
                commerciali.
            </p>

            <h2>Cookie, sessione e memorizzazione locale</h2>
            <p>
                L'applicazione utilizza cookie tecnici e identificativi di sessione necessari al login e alla sicurezza.
                Puo inoltre usare memoria locale del browser per ricordare preferenze tecniche, come la scelta sulle
                notifiche. Non sono previsti cookie di profilazione pubblicitaria.
            </p>

            <h2>Conservazione</h2>
            <p>
                I dati sono conservati per il tempo necessario alle finalita scolastiche, amministrative, contabili,
                documentali e di sicurezza per cui sono raccolti, nel rispetto delle policy dell'istituto e degli obblighi
                di conservazione applicabili.
            </p>

            <h2>Destinatari</h2>
            <p>
                I dati possono essere consultati da personale autorizzato dell'istituto in base al ruolo ricoperto
                e possono essere trattati da fornitori tecnici o responsabili del trattamento coinvolti nella gestione
                dell'infrastruttura, degli account, della posta, dei calendari e degli altri servizi collegati.
            </p>

            <h2>Diritti degli interessati</h2>
            <p>
                Gli interessati possono esercitare, nei casi previsti dal GDPR, i diritti di accesso, rettifica,
                cancellazione, limitazione, opposizione e portabilita, oltre al diritto di proporre reclamo al Garante
                per la protezione dei dati personali.
            </p>

            <h2>Come esercitare i diritti</h2>
            <p>
                Per richieste privacy e chiarimenti sul trattamento dei dati personali e possibile contattare il titolare
                o il RPD/DPO usando i recapiti indicati in apertura.
            </p>

            <div class="privacy-actions">
                <a href="<?php echo privacyHtml($__application_base_path); ?>/index.php">Torna al login</a>
                <a href="https://www.garanteprivacy.it/" target="_blank" rel="noopener">Garante privacy</a>
                <a href="https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=it" target="_blank" rel="noopener">Regolamento UE 2016/679</a>
            </div>
        </section>
    </main>
</body>

</html>