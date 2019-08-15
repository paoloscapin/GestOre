<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$query = "	SELECT id FROM `fuis_assegnato_tipo`;
			";
$resultArrayFuisAssegnatoTipo = dbGetAll($query);

$resultArray = dbGetAll($query);
echo json_encode($resultArray);
?>
