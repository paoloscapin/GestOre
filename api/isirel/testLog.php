<?php
header('Content-Type: text/plain; charset=utf-8');

echo "__DIR__ = " . __DIR__ . "\n";

$logFile = __DIR__ . '/../../log/isirel_pagopa_debug.log';
echo "logFile = " . $logFile . "\n";

$ok = file_put_contents(
    $logFile,
    date('Y-m-d H:i:s') . " - TEST LOG\n",
    FILE_APPEND
);

var_dump($ok);

require_once __DIR__ . '/../../common/connect.php';

echo "connect caricato\n";

$n = dbGetValue("SELECT 1");

echo "dbGetValue SELECT 1 = " . $n . "\n";