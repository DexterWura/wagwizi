<?php 
    
/**
 * Require helper file
 */
require_once __DIR__ . '/src/helper.php';

/**
 * Root namespace for the application
 */
define('ROOT_NAMESPACE', 'Paynow\\');

/**
 * Simple PSR-4 compliant autoloader
 * 
 * @link (GitHub Gist, https://gist.github.com/melmups/a0800b07e58089297c1735cfcc9fd382)
 */
spl_autoload_register(function ($class) {
    if (strncmp($class, ROOT_NAMESPACE, strlen(ROOT_NAMESPACE)) !== 0) {
        return false;
    }

    $relative = substr($class, strlen(ROOT_NAMESPACE));
    $filename = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (! is_file($filename)) {
        return false;
    }

    require_once $filename;

    return class_exists($class, false);
});
