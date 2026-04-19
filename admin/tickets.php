<?php

/**
 *  This file is part of GestOre
 */

require_once '../common/checkSession.php';

ruoloRichiesto('admin');

header('Location: ../common/tickets/index.php');
exit();
