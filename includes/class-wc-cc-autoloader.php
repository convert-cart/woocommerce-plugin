<?php
declare(strict_types=1);

namespace ConvertCart\Analytics;

/**
 * Autoloader for Convert Cart Analytics plugin.
 */
class WC_CC_Autoloader {
    /**
     * The Constructor.
     */
    public function __construct() {
        spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * Auto-load classes on demand.
     *
     * @param string $class Class name.
     */
    public function autoload($class) {
        $namespace = 'ConvertCart\\Analytics\\';
        
        // Return if the class is not in our namespace
        if (strpos($class, $namespace) !== 0) {
            return;
        }

        // Remove namespace from class name and get the path
        $class_path = str_replace($namespace, '', $class);
        $class_parts = explode('\\', $class_path);
        
        // Get the actual class name (last part)
        $class_name = array_pop($class_parts);
        
        // Convert class name format to file name format
        $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
        
        // Build directory path from namespace parts
        $directory_path = strtolower(implode('/', $class_parts));
        
        // Try to load from includes/subdirectory
        if (!empty($directory_path)) {
            $file_path = CONVERTCART_ANALYTICS_PATH . 'includes/' . $directory_path . '/' . $file_name;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
        
        // Try to load from includes directory
        $file_path = CONVERTCART_ANALYTICS_PATH . 'includes/' . $file_name;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
} 