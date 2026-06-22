<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo 'PHP version: ' . PHP_VERSION . '<br>';

echo 'Loading autoload...<br>';
try {
    require __DIR__ . '/vendor/autoload.php';
    echo 'Autoload: OK<br>';
} catch (\Throwable $e) {
    echo 'Autoload ERROR: ' . $e->getMessage() . '<br>';
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . '<br>';
    exit;
}

$paths = [
    '/storage/logs',
    '/storage/framework/sessions',
    '/storage/framework/views',
    '/storage/framework/cache',
    '/bootstrap/cache',
];
foreach ($paths as $p) {
    echo $p . ': ' . (is_writable(__DIR__ . $p) ? 'writable ✓' : 'NOT WRITABLE ✗') . '<br>';
}
