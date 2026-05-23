<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
?>
<link rel="stylesheet" href="../css/header-style.css">

<?php
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');

function ataIsActive($fileName)
{
	global $currentScript;
	return ($currentScript === $fileName);
}

$displayName = '';
if (haRuolo('portineria')) {
	$displayName = $__portineria_nome . ' ' . $__portineria_cognome;
} else {
	$displayName = $__ata_nome . ' ' . $__ata_cognome;
}

/*
 * Metti qui il nome reale della home ATA se diverso da index.php
 */
$isAtaHome = in_array($currentScript, ['index.php'], true);
$bigliettiVisibili = getSettingsValue('config', 'biglietti', true);
?>
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

	.ata-mobile-header {
		margin-top: 0 !important;
	}

	.ata-mobile-header {
		background: linear-gradient(180deg, #6cc3ea 0%, #14aae2 100%);
		color: #fff;
		padding: 10px 12px 12px 12px;
		margin-bottom: 10px;
		margin-top: 0 !important;
		box-shadow: 0 2px 8px rgba(0, 0, 0, .10);
	}

	.ata-mobile-header-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
	}

	.ata-mobile-logo img {
		height: 34px;
		width: auto;
		display: block;
	}

	.ata-mobile-user {
		flex: 1;
		text-align: left;
		font-size: 16px;
		font-weight: 600;
		line-height: 1.2;
		color: #ffffff;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.ata-mobile-logout {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 42px;
		height: 42px;
		border-radius: 12px;
		background: rgba(255, 255, 255, .16);
		color: #fff;
		text-decoration: none;
		font-size: 18px;
	}

	.ata-mobile-logout:hover,
	.ata-mobile-logout:focus {
		color: #fff;
		text-decoration: none;
		background: rgba(255, 255, 255, .24);
	}

	.ata-mobile-nav {
		display: grid;
		grid-template-columns: 1fr;
		gap: 10px;
		margin-top: 12px;
	}

	.ata-mobile-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		min-height: 54px;
		padding: 12px 14px;
		border-radius: 16px;
		background: #f3e58c;
		color: #2d3340;
		font-size: 20px;
		font-weight: 700;
		text-decoration: none;
		box-shadow: 0 2px 6px rgba(0, 0, 0, .10);
	}

	.ata-mobile-btn:hover,
	.ata-mobile-btn:focus {
		text-decoration: none;
		color: #2d3340;
		background: #f0df72;
	}

	.ata-mobile-btn.is-active {
		background: #e9b04d;
		color: #fff;
	}

	.ata-mobile-btn .glyphicon {
		font-size: 18px;
	}


	@media (min-width: 768px) {
		.ata-mobile-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
		}

		.ata-mobile-header-top {
			flex: 1;
			min-width: 0;
		}

		.ata-mobile-nav {
			margin-top: 0;
			display: flex;
			justify-content: flex-end;
			flex: 0 0 auto;
		}

		.ata-mobile-btn {
			min-width: 220px;
			width: auto;
			white-space: nowrap;
		}
	}
</style>

<div class="ata-mobile-header">
	<div class="ata-mobile-header-top">
		<div class="ata-mobile-logo">
			<?php require_once '../common/header-_logo.php'; ?>
		</div>

		<div class="ata-mobile-user">
			<?php if (haRuolo('admin')) echo '(A) '; ?>
			<?php echo htmlspecialchars('[' . $displayName . ']', ENT_QUOTES, 'UTF-8'); ?>
		</div>

		<a class="ata-mobile-logout"
			href="../common/logout.php?base=ata"
			title="Esci">
			<span class="glyphicon glyphicon-log-out"></span>
		</a>
	</div>

	<?php if (!$isAtaHome): ?>
		<div class="ata-mobile-nav">
			<?php if ($bigliettiVisibili): ?>
				<a href="../common/biglietti_prenotazioni.php" class="ata-mobile-btn<?php echo ataIsActive('biglietti_prenotazioni.php') ? ' is-active' : ''; ?>">
					<span class="glyphicon glyphicon-barcode"></span>
					<span>Biglietti</span>
				</a>
			<?php endif; ?>
			<a href="../ata/index.php" class="ata-mobile-btn">
				<span class="glyphicon glyphicon-home"></span>
				<span>Torna alla home</span>
			</a>
		</div>
	<?php else: ?>
		<?php if ($bigliettiVisibili): ?>
			<div class="ata-mobile-nav">
				<a href="../common/biglietti_prenotazioni.php" class="ata-mobile-btn<?php echo ataIsActive('biglietti_prenotazioni.php') ? ' is-active' : ''; ?>">
					<span class="glyphicon glyphicon-barcode"></span>
					<span>Biglietti</span>
				</a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
