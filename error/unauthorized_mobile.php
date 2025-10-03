<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Util.php';
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- responsive -->
    <title>Non Autorizzato</title>
    <?php require_once '../common/style.php'; ?>
</head>

<body>
    <?php
    require_once '../common/header-error-mobile.php';
    ?>

    <!-- Content Section -->
    <div class="container-fluid" style="margin-top:80px">
        <div class="panel panel-success">
            <div class="panel-heading">Non Autorizzato</div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-12">
                        <h3>Non hai i diritti per collegarti a questa pagina.</h3>
                    </div>
                </div>
            </div>

            <!-- <div class="panel-footer"></div> -->
        </div>
    </div>

    <!-- Bootstrap, jquery etc (css + js) -->
    <?php
    require_once '../common/style.php';
    ?>

    <!-- Custom JS file -->
</body>

</html>