<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('personale-ata');

?>

<!DOCTYPE html>
<html lang="it">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>GestOre ATA</title>
	<?php
	require_once '../common/header-common.php';
	require_once '../common/style.php';
	?>
	<style>
		.ata-home {
			max-width: 520px;
			margin: 0 auto;
			padding: 10px 6px 30px 6px;
		}

		.ata-home-title {
			font-size: 28px;
			font-weight: 700;
			text-align: center;
			margin: 8px 0 6px 0;
			color: #2d3340;
		}

		.ata-home-subtitle {
			font-size: 16px;
			text-align: center;
			color: #6b7280;
			margin-bottom: 18px;
			line-height: 1.4;
		}

		.ata-big-btn {
			display: block;
			width: 100%;
			border-radius: 18px;
			padding: 22px 18px;
			margin: 0 0 14px 0;
			text-decoration: none !important;
			box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
			border: 1px solid #d9dde3;
			background: #fff;
			color: #2d3340 !important;
		}

		.ata-big-btn:active,
		.ata-big-btn:focus,
		.ata-big-btn:hover {
			text-decoration: none !important;
			background: #f8fafc;
		}

		.ata-big-btn-icon {
			font-size: 34px;
			display: block;
			margin-bottom: 10px;
			text-align: center;
		}

		.ata-big-btn-title {
			font-size: 24px;
			font-weight: 700;
			text-align: center;
			line-height: 1.2;
			margin-bottom: 8px;
		}

		.ata-big-btn-desc {
			font-size: 16px;
			line-height: 1.4;
			text-align: center;
			color: #6b7280;
		}

		.ata-home-user {
			text-align: center;
			font-size: 15px;
			color: #4b5563;
			margin-top: 8px;
			margin-bottom: 20px;
		}

		@media (min-width: 768px) {
			.ata-home {
				max-width: 700px;
			}
		}

		@media (max-width: 767px) {
			.ata-btn-orario {
				display: none !important;
			}
		}
	</style>
</head>

<body>
	<?php require_once '../common/header-ata.php'; ?>

	<div class="container-fluid">
		<div class="ata-home">
			<div class="ata-home-title">GestOre ATA</div>
			<div class="ata-home-subtitle">
				Accesso rapido alle funzioni principali
			</div>

			<div class="ata-home-user">
				<?php echo htmlspecialchars($__ata_nome . ' ' . $__ata_cognome); ?>
			</div>

			<a href="../ata/permessi.php" class="ata-big-btn">
				<div class="ata-big-btn-icon">
					<span class="glyphicon glyphicon-folder-open"></span>
				</div>
				<div class="ata-big-btn-title">I miei permessi</div>
				<div class="ata-big-btn-desc">
					Inserisci una nuova richiesta oppure consulta quelle già inviate
				</div>
			</a>

			<a href="../orario/orario.php" class="ata-big-btn ata-btn-orario">
				<div class="ata-big-btn-icon">
					<span class="glyphicon glyphicon-time"></span>
				</div>
				<div class="ata-big-btn-title">Orario ed eventi</div>
				<div class="ata-big-btn-desc">
					Visualizza orario, eventi, assenze e sostituzioni
				</div>
			</a>
		</div>
	</div>
</body>

</html>