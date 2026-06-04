<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="../css/header-style.css">

<?php
require_once __DIR__ . '/programmiPubbliciLib.php';

$genitoreImpersonato = isset($session)
	&& intval($session->get('impersona_attiva') ?? 0) === 1
	&& (string)($session->get('impersona_ruolo') ?? '') === 'genitore';
?>

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav genitore-navbar-nav">
			<?php if ((getSettingsValue('config', 'sportelli', false)) && (getSettingsValue('sportelli', 'visibile_genitori', false))): ?>
				<li><a href="../genitore/sportello.php" class="nav-btn btn btn-default btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli</a></li>
			<?php endif; ?>
			<?php if ((getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_genitori', false))): ?>
				<li><a href="../genitore/carenze.php" class="nav-btn btn btn-default btn-teal4" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze</a></li>
			<?php endif; ?>
			<?php if ((getSettingsValue('config', 'permessi', false)) && (getSettingsValue('permessi', 'visibile_genitori', false))): ?>
				<li><a href="../genitore/permessi.php" class="nav-btn btn btn-default btn-yellow4" role="button"><span class="glyphicon glyphicon-log-out"></span>&ensp;Permessi di uscita</a></li>
			<?php endif; ?>
			<?php if ((getSettingsValue('config', 'profiloGenitore', false)) && (getSettingsValue('profiloGenitore', 'visibile_genitori', false))): ?>
				<li><a href="../genitore/profilo.php" class="nav-btn btn btn-default btn-lightblue4" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Profilo</a></li>
			<?php endif; ?>
			<?php if (programmiPubbliciVisibleForRole('materie', 'genitore')): ?>
				<li><a href="../genitore/programmi.php" class="nav-btn btn btn-default btn-lightblue4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Programmi didattica</a></li>
			<?php endif; ?>
			<?php if (programmiPubbliciVisibleForRole('minimi', 'genitore')): ?>
				<li><a href="../genitore/programmiMinimi.php" class="nav-btn btn btn-default btn-lightblue4" role="button"><span class="glyphicon glyphicon-check"></span>&ensp;Programmi obiettivi minimi</a></li>
			<?php endif; ?>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a><span class=""></span>
					<?php if (haRuolo('admin')) echo "(A)" ?>
					<?php echo $__genitore_nome . ' ' . $__genitore_cognome ?></a></li>
			<li>
				<?php
				if ($genitoreImpersonato) {
					echo '<a href="../common/logout.php?impersona=stop&close=1"><span class="glyphicon glyphicon-log-out"></span></a>';
				}
				else
				{
					echo '<a href="../common/logout.php?base=genitore"><span class="glyphicon glyphicon-log-out"></span></a>';
				}
				?>
			</li>
		</ul>
	</div>
</nav>
