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

	// deve capire se le ore recuperate erano state usate per fare le 10 obbligatorie, per cui le ricalcola togliendo dal totale quelle extra
	$ore_firmate = dbGetValue("SELECT COALESCE(SUM(lezione_corso_di_recupero.numero_ore),0) FROM `lezione_corso_di_recupero` WHERE corso_di_recupero_id = $id AND firmato=true;");
	$ore_recuperate = min($ore_recuperate, $ore_firmate);

	$ore_firmate = $ore_firmate - $ore_recuperate;
	// todo: serve a qualcosa quest'ultima istruzione? forse vanno aggiustate di conseguenza quelle extra?

	// aggiorna le ore
	dbExec("UPDATE corso_di_recupero SET ore_recuperate = '$ore_recuperate', ore_pagamento_extra = '$ore_pagamento_extra' WHERE id = '$id';");
	info("aggiornata corso_di_recupero id=$id ore_recuperate=$ore_recuperate ore_pagamento_extra=$ore_pagamento_extra");
}

?>
