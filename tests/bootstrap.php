<?php
require __DIR__ . '/../vendor/autoload.php';

$prefixes = [
	'Psr\\Log\\' => __DIR__.'/Stubs/Psr/Log/',
];

spl_autoload_register(function ($class) {
    $prefixes = [
        'OCA\\Analytics\\' => __DIR__ . '/../lib/',
        'OCA\\Analytics\\Tests\\' => __DIR__ . '/',
        'OCP\\' => __DIR__ . '/Stubs/OCP/',
		'OCP\\' => __DIR__ . '/Stubs/Psr/',
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
