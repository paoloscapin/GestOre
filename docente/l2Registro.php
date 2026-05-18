<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/l2_lib.php';

ruoloRichiesto('docente', 'dirigente');
applicaDocenteDaParametroSeAutorizzato('docente_id');

$l2IsAdmin = false;
$l2CurrentDocenteId = intval($GLOBALS['__docente_id'] ?? 0);
$l2ActionUrl = 'l2Registro.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registro L2</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-docente.php'; ?>
<div class="container-fluid">
    <?php require '../common/mastercom/l2_registro_content.php'; ?>
</div>
</body>
</html>
