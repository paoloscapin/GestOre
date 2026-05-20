<?php

require_once __DIR__ . '/connect.php';

if (function_exists('initCronLog')) {
    initCronLog('rotateLOG');
}

rotateLog();
infocron('rotateLOG completato');

echo "OK rotateLOG\n";

