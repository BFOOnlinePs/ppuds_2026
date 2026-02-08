<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$moduleName = 'PPUDS';
$path = module_path($moduleName, 'Lang');

echo 'Path: '.$path.PHP_EOL;
echo 'Exists: '.(is_dir($path) ? 'Yes' : 'No').PHP_EOL;

$jsonPath = $path.'/ar.json';
if (file_exists($jsonPath)) {
    echo 'JSON file exists.'.PHP_EOL;
    $content = file_get_contents($jsonPath);
    $json = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo 'JSON is valid.'.PHP_EOL;
        echo 'Student direct load: '.($json['Student'] ?? 'Not found').PHP_EOL;
    } else {
        echo 'JSON Error: '.json_last_error_msg().PHP_EOL;
    }
} else {
    echo "JSON file not found at $jsonPath".PHP_EOL;
}

echo 'Initial Locale: '.app()->getLocale().PHP_EOL;

app()->setLocale('ar');
echo 'Set Locale to: '.app()->getLocale().PHP_EOL;

echo "Translation check via app(__('Student')): ".__('Student').PHP_EOL;
