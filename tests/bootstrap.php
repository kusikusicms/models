<?php

// Bootstrap for package tests: require the monorepo vendor autoload.
// This file is referenced by phpunit.xml as the bootstrap script.

$paths = [
    // If running from repository root with vendor at root
    dirname(__DIR__, 3).'/vendor/autoload.php',
    // Fallback: if running inside package with a local vendor (not typical in monorepo)
    dirname(__DIR__, 1).'/vendor/autoload.php',
];

foreach ($paths as $path) {
    if (is_file($path)) {
        require_once $path;

        return;
    }
}

fwrite(STDERR, "Could not locate Composer autoload.php for tests.\n");
exit(1);
