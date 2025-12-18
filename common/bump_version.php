<?php
$path = dirname(__DIR__) . '/version.php';

$src = file_get_contents($path);
if ($src === false) {
    fwrite(STDERR, "Cannot read $path\n");
    exit(1);
}

if (!preg_match('/\\$__software_version\\s*=\\s*\'([^\']+)\'\\s*;/', $src, $m)) {
    fwrite(STDERR, "Version not found\n");
    exit(1);
}
$ver = $m[1];

$parts = explode('.', $ver);
if (count($parts) !== 3) {
    fwrite(STDERR, "Version must be x.y.z\n");
    exit(1);
}

$parts[2] = (string)((int)$parts[2] + 1); // bump patch
$newVer = implode('.', $parts);

// data italiana tipo "29 ago 2024"
$months = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
$newDate = date('j') . ' ' . $months[(int)date('n') - 1] . ' ' . date('Y');

$src = preg_replace(
    '/\\$__software_version\\s*=\\s*\'([^\']+)\'\\s*;/',
    "\$__software_version = '$newVer';",
    $src,
    1
);

$src = preg_replace(
    '/\\$__software_release_date\\s*=\\s*\'([^\']+)\'\\s*;/',
    "\$__software_release_date = '$newDate';",
    $src,
    1
);

if (file_put_contents($path, $src) === false) {
    fwrite(STDERR, "Cannot write $path\n");
    exit(1);
}

echo "Bumped version: $ver -> $newVer ($newDate)\n";
