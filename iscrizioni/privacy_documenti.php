<?php

require_once '../common/path.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$token = trim((string)($_GET['t'] ?? ''));
$backUrl = $token !== ''
    ? 'conferma.php?t=' . rawurlencode($token)
    : 'conferma.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informativa privacy documenti iscrizione</title>
    <link rel="icon" href="<?php echo h($__application_base_path); ?>/ore-32.png" type="image/png">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f7fb; color: #172033; line-height: 1.55; }
        .page { max-width: 900px; margin: 0 auto; padding: 18px; }
        .card { background: #fff; border: 1px solid #d9e0ea; border-radius: 8px; box-shadow: 0 8px 28px rgba(23,32,51,.08); padding: 20px; margin: 14px 0; }
        h1 { font-size: 26px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 22px 0 8px; }
        p { margin: 8px 0; }
        ul { margin: 8px 0 8px 22px; padding: 0; }
        li { margin: 5px 0; }
        .muted { color: #64748b; }
        .notice { border-left: 5px solid #0ea5e9; background: #eaf6fc; padding: 12px; border-radius: 6px; }
        .back { display: inline-block; margin-top: 12px; color: #0369a1; font-weight: 700; }
    </style>
</head>
<body>
<main class="page">
    <article class="card">
        <h1>Informativa privacy per i documenti di iscrizione</h1>
        <p class="muted">Future classi prime - ITT Buonarroti</p>

        <div class="notice">
            Questa pagina spiega in modo sintetico perche vengono richiesti i documenti, come vengono usati e chi puo consultarli.
        </div>

        <h2>Perche chiediamo i documenti</h2>
        <p>I documenti caricati o consegnati in segreteria didattica servono esclusivamente per completare la procedura di iscrizione alle future classi prime, verificare i dati dichiarati e predisporre le attivita scolastiche successive.</p>

        <h2>Quali documenti possono essere richiesti</h2>
        <ul>
            <li>pagella o documento di valutazione;</li>
            <li>diploma o licenza conclusiva del primo ciclo;</li>
            <li>certificazione delle competenze;</li>
            <li>documentazione INVALSI;</li>
            <li>documenti di identita dello studente e dei responsabili;</li>
            <li>codici fiscali dello studente e dei responsabili;</li>
            <li>eventuale attestazione dell'erogazione liberale PagoPA, se richiesta o disponibile;</li>
            <li>eventuali altri documenti necessari alla pratica di iscrizione.</li>
        </ul>

        <h2>Come vengono usati</h2>
        <p>I documenti vengono usati dalla segreteria didattica e dal personale scolastico autorizzato per la gestione amministrativa dell'iscrizione, la verifica della completezza della pratica e, dove previsto, per supportare le attivita di accoglienza e formazione delle classi.</p>

        <h2>Consultazione e accesso</h2>
        <p>L'accesso ai documenti e limitato al personale autorizzato della scuola. I documenti non sono pubblici e non vengono usati per finalita diverse da quelle connesse alla procedura di iscrizione e agli adempimenti scolastici.</p>

        <h2>Caricamento online o consegna cartacea</h2>
        <p>La famiglia puo caricare i documenti tramite questa pagina oppure indicare che consegnera una fotocopia in segreteria didattica. In entrambi i casi l'informazione viene registrata nella pratica di iscrizione.</p>

        <h2>Conservazione</h2>
        <p>I documenti vengono conservati per il tempo necessario alla gestione della pratica di iscrizione e secondo i tempi previsti dagli obblighi amministrativi e dalla normativa scolastica applicabile.</p>

        <h2>Contatti</h2>
        <p>Per chiarimenti sulla procedura o sui documenti richiesti e possibile contattare la segreteria didattica dell'istituto.</p>

        <a class="back" href="<?php echo h($backUrl); ?>">Torna alla pagina di iscrizione</a>
    </article>
</main>
</body>
</html>
