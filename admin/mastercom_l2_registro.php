<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/l2_lib.php';

ruoloRichiesto('admin');

$l2IsAdmin = true;
$l2CurrentDocenteId = 0;
$l2ActionUrl = 'mastercom_l2_registro.php';
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
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <?php require '../common/mastercom/l2_registro_content.php'; ?>
</div>
</body>
</html>
