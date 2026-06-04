<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
?>

<link rel="stylesheet" href="../css/header-style.css?v=<?php echo @filemtime(__DIR__ . '/../css/header-style.css') ?: time(); ?>">

<?php
require_once __DIR__ . '/programmiPubbliciLib.php';

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$isStudenteHome = ($currentScript === 'index.php');

$studenteImpersonato = isset($session)
    && intval($session->get('impersona_attiva') ?? 0) === 1
    && (string)($session->get('impersona_ruolo') ?? '') === 'studente';

$logoutHref = $studenteImpersonato
    ? '../common/logout.php?impersona=stop&close=1'
    : '../common/logout.php?base=studente';
?>

<div class="mobile-role-header">
    <div class="mobile-role-header-top">
        <div class="mobile-role-logo">
            <a href="../studente/index.php" class="top-navbar-brand">
                <img src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'logo.png'")); ?>" alt="Logo">
            </a>
        </div>

        <div class="mobile-role-user">
            <div class="mobile-role-label">
                <?php if (haRuolo('admin')): ?><span class="mobile-role-admin">Admin</span><?php endif; ?>
                <span>Studente</span>
            </div>
            <div class="mobile-role-name">
                <?php echo htmlspecialchars($__studente_nome . ' ' . $__studente_cognome, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>

        <a class="mobile-role-logout" href="<?php echo htmlspecialchars($logoutHref, ENT_QUOTES, 'UTF-8'); ?>" title="Esci">
            <span class="glyphicon glyphicon-log-out"></span>
        </a>
    </div>

    <?php if (!$isStudenteHome): ?>
        <div class="mobile-role-nav">
            <a href="../studente/index.php" class="mobile-role-btn">
                <span class="glyphicon glyphicon-home"></span>
                <span>Torna alla home</span>
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
    }

    body {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .container-fluid {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .mobile-role-header {
        background: linear-gradient(180deg, #6cc3ea 0%, #14aae2 100%);
        box-shadow: 0 2px 8px rgba(0, 0, 0, .10);
        color: #fff;
        margin-bottom: 10px;
        padding: 12px 14px;
    }

    .mobile-role-header-top {
        align-items: center;
        display: flex;
        gap: 10px;
        justify-content: space-between;
    }

    .mobile-role-logo img {
        display: block;
        height: 36px;
        width: auto;
    }

    .mobile-role-logo a {
        align-items: center;
        background: rgba(255, 255, 255, .14);
        border-radius: 12px;
        display: inline-flex;
        height: 48px;
        justify-content: center;
        padding: 6px;
        width: 48px;
    }

    .mobile-role-user {
        color: #fff;
        flex: 1;
        line-height: 1.2;
        min-width: 0;
        overflow: hidden;
        text-align: left;
    }

    .mobile-role-label {
        align-items: center;
        color: rgba(255, 255, 255, .82);
        display: flex;
        font-size: 12px;
        font-weight: 600;
        gap: 6px;
        letter-spacing: .2px;
        margin-bottom: 3px;
        text-transform: uppercase;
    }

    .mobile-role-admin {
        background: rgba(255, 255, 255, .22);
        border-radius: 999px;
        color: #fff;
        padding: 2px 7px;
        text-transform: none;
    }

    .mobile-role-name {
        color: #fff;
        font-size: 18px;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mobile-role-logout {
        align-items: center;
        background: rgba(255, 255, 255, .16);
        border-radius: 12px;
        color: #fff;
        display: inline-flex;
        font-size: 18px;
        height: 42px;
        justify-content: center;
        text-decoration: none;
        width: 42px;
    }

    .mobile-role-logout:hover,
    .mobile-role-logout:focus {
        background: rgba(255, 255, 255, .24);
        color: #fff;
        text-decoration: none;
    }

    .mobile-role-nav {
        display: grid;
        gap: 10px;
        grid-template-columns: 1fr;
        margin-top: 12px;
    }

    .mobile-role-btn {
        align-items: center;
        background: #f3e58c;
        border-radius: 16px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .10);
        color: #2d3340;
        display: flex;
        font-size: 20px;
        font-weight: 700;
        gap: 10px;
        justify-content: center;
        min-height: 54px;
        padding: 12px 14px;
        text-decoration: none;
    }

    .mobile-role-btn:hover,
    .mobile-role-btn:focus {
        background: #f0df72;
        color: #2d3340;
        text-decoration: none;
    }
</style>
