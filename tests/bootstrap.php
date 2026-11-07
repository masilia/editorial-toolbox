<?php

declare(strict_types=1);

// Standalone run (`composer install` in the package) or, as fallback, the host project's autoloader
// (set HOST_AUTOLOAD, defaults to the IMAL DDEV layout).
$candidates = [
    __DIR__ . '/../vendor/autoload.php',
    getenv('HOST_AUTOLOAD') ?: '/var/www/html/ibexa/vendor/autoload.php',
];

foreach ($candidates as $autoload) {
    if (is_file($autoload)) {
        $loader = require $autoload;
        $loader->addPsr4('Masilia\\Tests\\EditorialToolbox\\', __DIR__ . '/lib');
        $loader->addPsr4('Masilia\\Tests\\Bundle\\EditorialToolbox\\', __DIR__ . '/bundle');

        return;
    }
}

throw new RuntimeException('No Composer autoloader found; run "composer install" or set HOST_AUTOLOAD.');
