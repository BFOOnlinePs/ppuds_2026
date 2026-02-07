<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$moduleName = 'PPUDS';
$path = module_path($moduleName, 'Lang');

echo "Path: " . $path . "\n";
echo "Exists: " . (is_dir($path) ? 'Yes' : 'No') . "\n";

$jsonPath = $path . '/ar.json';
if (file_exists($jsonPath)) {
    echo "JSON file exists.\n";
    $content = file_get_contents($jsonPath);
    $json = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "JSON is valid.\n";
        echo "Student translation: " . ($json['Student'] ?? 'Not found') . "\n";
    } else {
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "JSON file not found at $jsonPath\n";
}

echo "Current Locale: " . app()->getLocale() . "\n";
echo "Testing translation via app: " . __('Student') . "\n";
