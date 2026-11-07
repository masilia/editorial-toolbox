<?php

declare(strict_types=1);

// Standalone run (`composer install` in the package) or, as fallback, the host project's autoloader
// (set HOST_AUTOLOAD, defaults to the IMAL DDEV layout).
$candidates = [
    __DIR__ . '/../vendor/autoload.php',
    getenv('HOST_AUTOLOAD') ?: '/var/www/html/ibexa/vendor/autoload.php',
];

foreach ($candidates as $autoload) {
    if (!is_file($autoload)) {
        continue;
    }

    $loader = require $autoload;
    $loader->addPsr4('Masilia\\Tests\\EditorialToolbox\\', __DIR__ . '/lib');
    $loader->addPsr4('Masilia\\Tests\\Bundle\\EditorialToolbox\\', __DIR__ . '/bundle');

    // Always test the sources of THIS working copy, never a copy of the package installed in the host's vendor/.
    // A host with an optimized autoloader resolves classes from its class map before PSR-4, so this has to be
    // an autoloader of its own, registered first.
    $sources = [
        'Masilia\\EditorialToolbox\\' => __DIR__ . '/../src/lib/',
        'Masilia\\Bundle\\EditorialToolbox\\' => __DIR__ . '/../src/bundle/',
        'Masilia\\Contracts\\EditorialToolbox\\' => __DIR__ . '/../src/contracts/',
    ];
    spl_autoload_register(static function (string $class) use ($sources): void {
        foreach ($sources as $prefix => $directory) {
            if (str_starts_with($class, $prefix)) {
                $file = $directory . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }

                return;
            }
        }
    }, true, true);

    return;
}

throw new RuntimeException('No Composer autoloader found; run "composer install" or set HOST_AUTOLOAD.');
