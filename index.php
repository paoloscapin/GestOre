<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/common/checkSession.php';

// Se la session contiene il ruolo, vai alla home corrispondente
if (haRuolo('admin')) {
    redirect('/admin/index.php');
} else if (haRuolo('docente')) {
    redirect('/docente/index.php');
} else if (haRuolo('dirigente')) {
    redirect('/dirigente/index.php');
} else if (haRuolo('segreteria-docenti')) {
    redirect('/segreteria/index.php');
} else if (haRuolo('segreteria-didattica')) {
    redirect('/didattica/index.php');
} else if (haRuolo('genitore')) {
    redirect('/genitore/index.php');
} else if (haRuolo('studente')) {
    redirect('/studente/index.php');
} else if (haRuolo('esterno')) {
    redirect('/esterno/index.php'); // quando pronta
}

require_once __DIR__ . '/common/header-common.php';
require_once __DIR__ . '/common/style.php';

// redirect edu.it -> tn.it (come nel vecchio)
$protocollo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$url_completo = $protocollo . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

if (($url_completo == 'https://www.buonarroti.edu.it/GestOre/') || ($url_completo == 'https://www.buonarroti.edu.it/GestOre/index.php')) {
    header("Location: https://www.buonarroti.tn.it/GestOre/");
    exit();
}

// ------------------------------------------------------
// IMPORTANTE:
// - Qui NON inizializziamo Google.
// - L'URL (o output HTML) viene gestito da checkSession.php
//   (come nel flusso storico).
// ------------------------------------------------------

$googleButtonHtml = '';

if (!empty($authUrl)) {
    $googleButtonHtml = '<a class="btnx btn-google" href="' . filter_var($authUrl, FILTER_SANITIZE_URL) . '">
        <span class="gicon"></span>
        Continua con Google
    </a>';
} else if (!empty($output)) {
    // $output spesso contiene già <a href="..."><img ...></a>
    $googleButtonHtml = '<div class="google-output">' . $output . '</div>';
}

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestOre - Login</title>

    <link rel="icon" href="/GestOre/ore-32.png" type="image/png">
    <link rel="shortcut icon" href="/GestOre/ore-32.png" type="image/png">

    <style>
        :root {
            --bg: #f6f8fb;
            --card: #ffffff;
            --border: rgba(16, 24, 40, 0.10);
            --shadow: 0 14px 46px rgba(16, 24, 40, 0.12);
            --text: #101828;
            --muted: #667085;
            --blue: #0ea5e9;
            --blue2: #0284c7;
            --red: #ef4444;
            --red2: #dc2626;
            --radius: 18px;
        }

        body {
            background:
                radial-gradient(circle at 10% 10%, rgba(14, 165, 233, 0.18), transparent 35%),
                radial-gradient(circle at 90% 0%, rgba(239, 68, 68, 0.10), transparent 35%),
                radial-gradient(circle at 60% 100%, rgba(16, 185, 129, 0.10), transparent 40%),
                var(--bg);
            color: var(--text);
            margin: 0;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px 16px;
        }

        .shell {
            width: 100%;
            max-width: 1080px;
        }

        /* ===== HEADER PRINCIPALE UNICO ===== */
        .main-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 26px;
        }

        /* Contenitore con margine bianco “come il logo quadrato” */
        .logo-main-wrap {
            background: #ffffff;
            border-radius: 22px;
            padding: 22px 34px; /* più margine bianco attorno al logo */
            box-shadow: 0 18px 40px rgba(16, 24, 40, 0.18);
            border: 1px solid rgba(16, 24, 40, 0.10);
            margin-bottom: 14px;
        }

        .logo-main {
            height: 78px;
            width: auto;
            display: block;
        }

        .main-title {
            margin: 6px 0 4px 0;
            font-size: 34px;
            font-weight: 950;
            letter-spacing: -0.02em;
        }

        .main-subtitle {
            color: var(--muted);
            font-size: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            align-items: stretch; /* colonne allineate */
        }

        /* ✅ Ogni colonna è un flex verticale */
        .col {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        /* ===== Card ===== */
        .cardx {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px;

            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .cardx .push-bottom {
            margin-top: auto;
        }

        .tag {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 850;
            background: rgba(14, 165, 233, 0.12);
            color: #0369a1;
        }

        .tag.red {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }

        .cardx h2 {
            margin: 10px 0 6px 0;
            font-size: 18px;
            font-weight: 950;
            letter-spacing: -0.01em;
        }

        .muted {
            color: var(--muted);
            font-size: 16px;
        }

        .divider {
            height: 1px;
            background: rgba(16, 24, 40, 0.10);
            margin: 14px 0;
        }

        .rolelist {
            margin: 10px 0 0 0;
            padding-left: 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.45;
        }

        .btnx {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            font-weight: 950;
            border: 0;
            text-decoration: none !important;
            user-select: none;
        }

        .btn-google {
            background: linear-gradient(180deg, var(--blue), var(--blue2));
            color: #fff;
        }

        .btn-google:hover {
            filter: brightness(0.97);
            color: #fff;
        }

        .btn-mastercom {
            background: linear-gradient(180deg, var(--red), var(--red2));
            color: #fff;
        }

        .btn-mastercom:hover {
            filter: brightness(0.97);
            color: #fff;
        }

        .gicon {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.92);
            position: relative;
        }

        .gicon:after {
            content: "G";
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            color: #111827;
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            height: 46px;
            border-radius: 14px;
            border: 1px solid rgba(16, 24, 40, 0.18);
            padding: 0 12px;
            outline: none;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: rgba(14, 165, 233, 0.7);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.14);
        }

        .help {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.4;
            margin-top: 10px;
        }

        .warn {
            border-radius: 14px;
            padding: 10px 12px;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: #92400e;
            font-size: 13px;
            margin-top: 10px;
        }

        .smallnote {
            margin-top: 16px;
            text-align: center;
            color: #98a2b3;
            font-size: 12px;
        }

        /* output legacy (quando checkSession fornisce <a><img glogin.png</a>) */
        .google-output a {
            display: block;
            text-align: center;
        }

        .google-output img {
            max-width: 100%;
            height: auto;
        }

        /* Responsive */
        @media (max-width: 980px) {
            .grid {
                grid-template-columns: 1fr;
            }
            .logo-main {
                height: 58px;
            }
            .logo-main-wrap {
                padding: 18px 22px;
            }
            .main-title {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="shell">

            <!-- HEADER UNICO SOPRA LE DUE CARD -->
            <div class="main-header">
                <div class="logo-main-wrap">
                    <img class="logo-main" src="/GestOre/img/Buonarroti_SiPayoff.svg" alt="Istituto Buonarroti">
                </div>
                <h1 class="main-title">GestOre</h1>
                <div class="main-subtitle">Sportelli • Permessi • Corsi • Carenze • Gestione Ore • Programmi</div>
            </div>

            <div class="grid">

                <!-- COLONNA SINISTRA: GOOGLE -->
                <div class="col">
                    <div class="cardx">
                        <span class="tag">Accesso con account Google</span>
                        <h2>Accesso rapido</h2>
                        <div class="muted">
                            Accesso con account Google (telefono / PC). Se la mail è presente in GestOre, l’utente viene riconosciuto.
                        </div>

                        <ul class="rolelist">
                            <li><b>Docenti</b>, <b>Dirigente</b>, <b>Amministratori</b></li>
                            <li><b>Studenti</b></li>
                            <li><b>Genitori</b> (mail <code>Google</code> presente in <code>GestOre</code>)</li>
                            <li><b>Esterni</b> (con account <code>@buonarroti</code>)</li>
                        </ul>

                        <div class="divider"></div>

                        <?php if (!empty($googleButtonHtml)): ?>
                            <?php echo $googleButtonHtml; ?>
                            <div class="help">
                                Se sei <b>genitore</b> e vuoi usare <code>Google </code> devi aver fornito la tua mail <code>Google</code>.
                            </div>
                        <?php else: ?>
                            <div class="warn">
                                Login Google non disponibile in questo momento (configurazione mancante o libreria non caricata).
                                Contatta l’amministratore.
                            </div>
                        <?php endif; ?>

                        <div class="push-bottom"></div>
                    </div>
                </div>

                <!-- COLONNA DESTRA: MASTERCOM -->
                <div class="col">
                    <div class="cardx">
                        <span class="tag red">Genitori • Registro elettronico</span>
                        <h2>Accesso con MasterCom</h2>
                        <div class="muted">
                            Usa le credenziali del registro elettronico.
                        </div>

                        <div class="divider"></div>

                        <form action="./common/checkSession.php" method="post" autocomplete="on">
                            <div style="margin-bottom:10px;">
                                <input type="text" class="form-control" name="username" placeholder="Username (MasterCom)" required>
                            </div>
                            <div style="margin-bottom:12px;">
                                <input type="password" class="form-control" name="password" placeholder="Password (MasterCom)" required>
                            </div>

                            <button type="submit" class="btnx btn-mastercom">
                                <span class="glyphicon glyphicon-log-in" aria-hidden="true"></span>
                                Entra con MasterCom
                            </button>
                        </form>

                        <div class="help push-bottom">
                            Se hai un account Google e la tua email è presente in anagrafica, puoi usare anche “Continua con Google”.
                        </div>
                    </div>
                </div>

            </div>

            <div class="smallnote">
                © <?php echo date('Y'); ?> GestOre — ITT Buonarroti
            </div>

        </div>
    </div>
</body>

</html>
