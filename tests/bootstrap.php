<?php
require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefixes = [
        'OCA\\Analytics\\' => __DIR__ . '/../lib/',
        'OCA\\Analytics\\Tests\\' => __DIR__ . '/',
        'OCP\\' => __DIR__ . '/Stubs/OCP/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    }
});
