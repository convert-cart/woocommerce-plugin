<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Abstract;

use WC_Integration;

/**
 * Base abstract class for Convert Cart functionality.
 */
abstract class WC_CC_Base {
    /**
     * @var WC_Integration Parent integration instance
     */
    protected WC_Integration $integration;

    /**
     * Constructor.
     *
     * @param WC_Integration $integration Parent integration instance
     */
    public function __construct(WC_Integration $integration) {
        $this->integration = $integration;
    }

    /**
     * Initialize hooks.
     */
    abstract public function init(): void;

    /**
     * Get an option from the main integration settings.
     *
     * @param string $key Option key.
     * @param mixed|null $default Default value if option not found.
     * @return mixed Option value.
     */
    protected function get_option(string $key, $default = null) {
        // Delegate to the main integration's get_option method
        return $this->integration->get_option($key, $default);
    }

    /**
     * Log an error message if debug mode is enabled.
     * Uses WC_Logger if available.
     *
     * @param string $message The error message.
     */
    protected function log_error(string $message): void {
        // Check if debug mode is enabled in the main integration settings
        if ($this->get_option('debug_mode') === 'yes') {
            if (function_exists('wc_get_logger')) {
                // Use WooCommerce logger if available
                wc_get_logger()->error($message, ['source' => 'convertcart-analytics']);
            } else {
                // Fallback to standard PHP error log
                error_log("ConvertCart ERROR: " . $message);
            }
        }
        // If debug mode is off, do nothing.
    }
} 