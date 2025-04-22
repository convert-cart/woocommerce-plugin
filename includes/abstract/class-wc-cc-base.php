<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Abstract;

use WC_Integration;

/**
 * Base abstract class for Convert Cart functionality.
 */
abstract class WC_CC_Base {
    /**
     * @var WC_Integration
     */
    protected $integration;

    /**
     * Constructor.
     *
     * @param WC_Integration $integration
     */
    public function __construct(WC_Integration $integration) {
        $this->integration = $integration;
    }

    /**
     * Initialize hooks.
     */
    abstract public function init(): void;

    /**
     * Get integration option.
     *
     * @param string $key Option key.
     * @param mixed $default Default value.
     * @return mixed
     */
    protected function get_option(string $key, $default = '') {
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