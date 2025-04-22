<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Blocks;

/**
 * Manages WooCommerce Blocks integration initialization.
 */
class Blocks_Integration_Manager {
    /** @var \ConvertCart\Analytics\WC_CC_Analytics Main plugin instance */
    private $plugin;
    
    /** @var Blocks_Registration_Service */
    private $registration_service;

    public function __construct(\ConvertCart\Analytics\WC_CC_Analytics $plugin) {
        $this->plugin = $plugin;
        $this->registration_service = new Blocks_Registration_Service($plugin);
    }

    /**
     * Initialize blocks integration.
     */
    public function init(): void {
        // Only call legacy setup_blocks_integration if needed
        add_action('init', [$this, 'setup_blocks_integration'], 90);
    }

    /**
     * Set up blocks integration if WC Blocks is active.
     */
    public function setup_blocks_integration(): void {
        error_log('[ConvertCart DEBUG] setup_blocks_integration: Checking WC Blocks status...');

        if (!$this->is_blocks_active()) {
            error_log('[ConvertCart DEBUG] setup_blocks_integration: WC Blocks not active. Skipping integration.');
            return;
        }

        error_log('[ConvertCart DEBUG] setup_blocks_integration: WC Blocks active. Initializing registration service...');
        $this->registration_service->init();
    }

    /**
     * Check if WC Blocks is active and available.
     */
    private function is_blocks_active(): bool {
        // Add check for block editor being active
        $is_block_editor = (bool) get_option('woocommerce_feature_product_block_editor_enabled', false);
        $package_exists = class_exists('\Automattic\WooCommerce\Blocks\Package');
        $wc_version_ok = defined('WC_VERSION') && version_compare(WC_VERSION, '6.0.0', '>=');
        
        return $is_block_editor && $package_exists && $wc_version_ok;
    }
} 