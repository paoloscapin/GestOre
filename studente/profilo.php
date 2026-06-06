<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/notifichePreferenzeLib.php';
require_once '../common/profiloLogLib.php';

ruoloRichiesto('studente');

function studenteProfiloH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studenteProfiloIsMobile(): bool
{
    return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT'] ?? '') === 1;
}

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['profilo_action'] ?? '')) === 'notifiche') {
    $saveResult = notifichePreferenzeSaveFromPost('studente', (int)$__studente_id, $_POST);
    $message = (string)($saveResult['message'] ?? 'Preferenze notifiche salvate.');
    $messageType = !empty($saveResult['ok']) ? 'success' : 'danger';
    profiloLogWrite('notifiche_salvate', 'studente', (int)$__studente_id, [
        'ok' => !empty($saveResult['ok']),
        'message' => $message,
        'preferenze' => profiloLogNotificationPrefsFromPost($_POST),
    ], !empty($saveResult['ok']) ? 'info' : 'warning');
}

$studente = dbGetFirst("
    SELECT
        s.id,
        s.nome,
        s.cognome,
        s.email,
        s.codice_fiscale,
        s.sesso,
        s.attivo,
        c.classe
    FROM studente s
    LEFT JOIN studente_frequenta sf
        ON sf.id_studente = s.id
       AND sf.id_anno_scolastico = " . dbI((int)$__anno_scolastico_corrente_id) . "
    LEFT JOIN classi c ON c.id = sf.id_classe
    WHERE s.id = " . dbI((int)$__studente_id) . "
    LIMIT 1
");

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profilo Studente</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/genitore-profilo.css?v=<?php echo @filemtime(__DIR__ . '/../css/genitore-profilo.css') ?: time(); ?>">
</head>
<body class="genitore-profilo-page">
<?php
if (studenteProfiloIsMobile()) {
    require_once '../common/header-studente-mobile.php';
} else {
    require_once '../common/header-studente.php';
}
?>
<div class="container-fluid">
    <div class="genitore-profilo-shell">
        <div class="genitore-profilo-hero">
            <h1 class="genitore-profilo-hero-title">
                <span class="glyphicon glyphicon-user"></span>
                <span>Profilo studente</span>
            </h1>
            <p class="genitore-profilo-hero-subtitle">
                Consulta i dati principali e gestisci le notifiche disponibili per il tuo profilo.
            </p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo studenteProfiloH($messageType); ?>">
                <?php echo studenteProfiloH($message); ?>
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
                                <dd><?php echo studenteProfiloH($studente['nome'] ?? $__studente_nome); ?></dd>
                            </div>
                            <div class="genitore-profilo-dl-row">
                                <dt>Cognome</dt>
                                <dd><?php echo studenteProfiloH($studente['cognome'] ?? $__studente_cognome); ?></dd>
                            </div>
                            <div class="genitore-profilo-dl-row">
                                <dt>Classe</dt>
                                <dd><?php echo studenteProfiloH(($studente['classe'] ?? '') !== '' ? $studente['classe'] : 'Non assegnata'); ?></dd>
                            </div>
                            <div class="genitore-profilo-dl-row">
                                <dt>Email</dt>
                                <dd><?php echo studenteProfiloH(($studente['email'] ?? '') !== '' ? $studente['email'] : '-'); ?></dd>
                            </div>
                            <div class="genitore-profilo-dl-row">
                                <dt>Codice fiscale</dt>
                                <dd><?php echo studenteProfiloH(($studente['codice_fiscale'] ?? '') !== '' ? $studente['codice_fiscale'] : '-'); ?></dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>

            <div>
                <?php echo notifichePreferenzeRenderSection('studente', (int)$__studente_id); ?>

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
            </div>
        </div>
    </div>
</div>
</body>
</html>
