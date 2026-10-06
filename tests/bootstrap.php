<?php

// The unit tests cover src/Api, which has no Silverstripe dependencies: they run with the
// module's own vendor/ (composer install) or with only PHPUnit available.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    foreach (['Supertext\\Silverstripe\\Tests\\' => __DIR__ . '/', 'Supertext\\Silverstripe\\' => __DIR__ . '/../src/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
