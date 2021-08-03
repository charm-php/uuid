<?php
/**
 * Autoloader to use when not using composer
 */
spl_autoload_register(function(string $className) {
    if (substr($className, 0, 6) !== "Charm\\") {
        return;
    }
    $filename = __DIR__.'/src/'.substr(str_replace('\\', '/', $className), 6).'.php';
    if (file_exists($filename)) {
        require($filename);
    }
});
