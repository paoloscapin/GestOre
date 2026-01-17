<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+
 */

require_once '../common/checkSession.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true
]);
