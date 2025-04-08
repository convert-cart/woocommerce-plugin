<?php
declare(strict_types=1);

namespace ConvertCart\Analytics;

/**
 * Autoloader for Convert Cart Analytics plugin.
 */
class WC_CC_Autoloader {
    /**
     * Path to the includes directory.
     *
     * @var string
     */
    private string $include_path;

    /**
     * The Constructor.
     */
    public function __construct() {
        $this->include_path = dirname(__FILE__);
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Get the file name from the class name.
     * e.g. WC_CC_Integration -> class-wc-cc-integration.php
     *
     * @param string $class Class name.
     * @return string File name.
     */
    private function get_file_name_from_class(string $class): string {
        return 'class-' . str_replace('_', '-', strtolower($class)) . '.php';
    }

    /**
     * Include a class file.
     *
     * @param string $path File path.
     * @return bool Success or failure.
     */
    private function load_file(string $path): bool {
        if ($path && is_readable($path)) {
            include_once $path;
            return true;
        }
        return false;
    }

    /**
     * Auto-load WC classes on demand to reduce memory consumption.
     *
     * @param string $class Class name.
     */
    public function autoload(string $class): void {
        // Only autoload classes from this plugin's namespace
        if (0 !== strpos($class, __NAMESPACE__ . '\\')) {
            return;
        }

        // Remove the root namespace
        $relative_class = substr($class, strlen(__NAMESPACE__ . '\\'));

        // Convert namespace separators to directory separators
        $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);

        // Get the file name
        $class_name = basename($relative_path);
        $file_name = $this->get_file_name_from_class($class_name);

        // Build the full path
        $path = $this->include_path;
        $dir_path = dirname($relative_path);
        if ($dir_path !== '.') {
            $path .= DIRECTORY_SEPARATOR . strtolower($dir_path); // Ensure subdirectories are lowercase
        }

        $full_path = $path . DIRECTORY_SEPARATOR . $file_name;

        // Try to load the file
        $this->load_file($full_path);
    }
} 