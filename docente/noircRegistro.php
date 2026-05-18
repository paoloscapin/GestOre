<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/noirc_lib.php';

ruoloRichiesto('docente', 'dirigente');
applicaDocenteDaParametroSeAutorizzato('docente_id');

$noircIsAdmin = false;
$noircCurrentDocenteId = intval($GLOBALS['__docente_id'] ?? 0);
$noircActionUrl = 'noircRegistro.php';
$noircExtraParams = [];
if (isset($_GET['docente_id']) && intval($_GET['docente_id']) > 0 && (string)($GLOBALS['__utente_ruolo'] ?? '') !== 'docente') {
    $noircExtraParams['docente_id'] = intval($_GET['docente_id']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registro NO IRC</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-docente.php'; ?>
<div class="container-fluid">
    <?php require '../common/mastercom/noirc_registro_content.php'; ?>
</div>
</body>
</html>
