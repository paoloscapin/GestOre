<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<?php
if($__admin_ruolo === 'admin') {
    require_once '../common/header-admin.php';
}elseif($__admin_ruolo === 'dirigente') {
    require_once '../common/header-dirigente.php';
}elseif($__admin_ruolo === 'docente') {
    require_once '../common/header-docente.php';
}elseif($__admin_ruolo === 'studente') {
    require_once '../common/header-studente.php';
}elseif($__admin_ruolo === 'segreteria-docenti') {
    require_once '../common/header-segreteria.php';
}elseif($__admin_ruolo === 'segreteria-didattica') {
    require_once '../common/header-didattica.php';
}elseif($__admin_ruolo === 'collaboratore') {
    require_once '../common/header-collaboratore.php';
}elseif($__admin_ruolo === 'modulistica') {
    require_once '../common/header-modulistica.php';
} else {

}
?>