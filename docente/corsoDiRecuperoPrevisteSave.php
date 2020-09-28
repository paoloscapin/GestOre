<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$id = $_POST['id'];
	$ore_recuperate = $_POST['ore_recuperate'];
	$ore_pagamento_extra = $_POST['ore_pagamento_extra'];

	// aggiorna le ore
	dbExec("UPDATE corso_di_recupero SET ore_recuperate = '$ore_recuperate', ore_pagamento_extra = '$ore_pagamento_extra' WHERE id = '$id';");
	info("aggiornata corso_di_recupero id=$id ore_recuperate=$ore_recuperate ore_pagamento_extra=$ore_pagamento_extra");

	// spostare le ore nei corsi di recupero ha impatto sia sulle previste che sulle fatte
	require_once '../docente/oreDovuteAggiornaDocente.php';
	orePrevisteAggiornaDocente($__docente_id);
	oreFatteAggiornaDocente($__docente_id);
}

?>
