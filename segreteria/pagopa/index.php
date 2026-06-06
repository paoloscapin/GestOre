<?php
declare(strict_types=1);

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '../../viaggi/index.php' . ($query !== '' ? '?' . $query : '');

header('Location: ' . $target, true, 302);
exit;
