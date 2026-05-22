<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/noirc_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

$noircIsAdmin = true;
$noircCurrentDocenteId = 0;
$noircActionUrl = 'mastercom_noirc_registro.php';
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
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <?php require '../common/mastercom/noirc_registro_content.php'; ?>
</div>
</body>
</html>
