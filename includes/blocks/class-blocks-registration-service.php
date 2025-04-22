<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;

class Blocks_Registration_Service {
    /** @var string Script handle */
    const SCRIPT_HANDLE = 'convertcart-blocks-integration';

    /** @var \ConvertCart\Analytics\WC_CC_Analytics */
    private $plugin;

    /** @var bool */
    private $dependencies_registered = false;

    public function __construct(\ConvertCart\Analytics\WC_CC_Analytics $plugin) {
        $this->plugin = $plugin;
    }

    public function init(): void {
        // Register scripts first, before any hooks
        add_action('init', function() {
            error_log('[ConvertCart DEBUG] Registering scripts on init...');
            
            // Check if files exist
            $js_file = $this->plugin->get_plugin_path() . 'assets/build/js/block-checkout-integration.js';
            $asset_file = $this->plugin->get_plugin_path() . 'assets/build/js/block-checkout-integration.asset.php';
            
            error_log('[ConvertCart DEBUG] Checking files:');
            error_log('[ConvertCart DEBUG] JS file exists: ' . (file_exists($js_file) ? 'yes' : 'no') . ' at ' . $js_file);
            error_log('[ConvertCart DEBUG] Asset file exists: ' . (file_exists($asset_file) ? 'yes' : 'no') . ' at ' . $asset_file);
            
            // Get plugin URLs for verification
            error_log('[ConvertCart DEBUG] Plugin URL: ' . $this->plugin->get_plugin_url());
            error_log('[ConvertCart DEBUG] Plugin Path: ' . $this->plugin->get_plugin_path());
            
            $this->register_frontend_scripts();
        }, 5);

        // Then hook into WooCommerce Blocks initialization
        add_action('woocommerce_blocks_loaded', function() {
            error_log('[ConvertCart DEBUG] WooCommerce Blocks loaded, setting up integration...');
            
            // Check if script is registered at this point
            error_log('[ConvertCart DEBUG] Script registered status: ' . 
                (wp_script_is(self::SCRIPT_HANDLE, 'registered') ? 'yes' : 'no'));
            
            // Register checkout filters
            if (function_exists('wc_get_container') && class_exists('\Automattic\WooCommerce\Blocks\Package')) {
                add_action('wp_enqueue_scripts', function() {
                    if (!is_checkout()) return;
                    
                    // Check script status before adding inline script
                    error_log('[ConvertCart DEBUG] Before inline script - Script registered: ' . 
                        (wp_script_is(self::SCRIPT_HANDLE, 'registered') ? 'yes' : 'no'));
                    
                    wp_add_inline_script(
                        'wc-blocks-checkout',
                        'window.wc = window.wc || {}; window.wc.blocksCheckout = window.wc.blocksCheckout || {};
                        window.wc.blocksCheckout.registerCheckoutFilters && window.wc.blocksCheckout.registerCheckoutFilters("convertcart", {
                            additionalCartCheckoutInnerBlockTypes: function(value) {
                                value.push("convertcart/consent");
                                return value;
                            }
                        });',
                        'after'
                    );
                });
            }

            // Register block from metadata if exists
            if (function_exists('register_block_type_from_metadata')) {
                $block = register_block_type_from_metadata(
                    $this->plugin->get_plugin_path() . 'assets/build/js/block-checkout-integration'
                );
                error_log('[ConvertCart DEBUG] Registered block from metadata');
            }

            // Hook into checkout block registration
            add_action(
                'woocommerce_blocks_checkout_block_registration',
                [$this, 'register_checkout_block']
            );

            // Hook into script dependencies filter
            if (!$this->dependencies_registered) {
                add_filter(
                    'woocommerce_blocks_register_script_dependencies',
                    [$this, 'register_script_dependencies'],
                    10,
                    1
                );
                $this->dependencies_registered = true;
                error_log('[ConvertCart DEBUG] Added script dependencies filter');
            }
        });

        // Enqueue scripts on checkout page
        add_action('wp_enqueue_scripts', function() {
            if (!is_checkout()) return;
            
            error_log('[ConvertCart DEBUG] Checkout page detected');
            
            if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
                error_log('[ConvertCart DEBUG] Script not registered! Attempting registration...');
                $this->register_frontend_scripts();
                
                // Check registration status after attempt
                error_log('[ConvertCart DEBUG] Script registration status after attempt: ' . 
                    (wp_script_is(self::SCRIPT_HANDLE, 'registered') ? 'success' : 'failed'));
            }

            if (function_exists('wc_get_page_id') && has_block('woocommerce/checkout', get_post(wc_get_page_id('checkout')))) {
                error_log('[ConvertCart DEBUG] Block checkout detected, enqueueing script...');
                wp_enqueue_script(self::SCRIPT_HANDLE);
                
                // Verify enqueue
                error_log('[ConvertCart DEBUG] Script enqueued status: ' . 
                    (wp_script_is(self::SCRIPT_HANDLE, 'enqueued') ? 'yes' : 'no'));
            }
        }, 20);
    }

    private function register_frontend_scripts(): void {
        error_log('[ConvertCart DEBUG] Registering frontend scripts...');
        
        $asset_file = $this->plugin->get_plugin_path() . 'assets/build/js/block-checkout-integration.asset.php';
        error_log('[ConvertCart DEBUG] Looking for asset file at: ' . $asset_file);
        
        if (!file_exists($asset_file)) {
            error_log('[ConvertCart ERROR] Asset file not found at: ' . $asset_file);
            return;
        }
        
        $asset_data = require($asset_file);
        error_log('[ConvertCart DEBUG] Asset data: ' . print_r($asset_data, true));

        $script_url = $this->plugin->get_plugin_url() . 'assets/build/js/block-checkout-integration.js';
        error_log('[ConvertCart DEBUG] Script URL: ' . $script_url);
        
        wp_register_script(
            self::SCRIPT_HANDLE,
            $script_url,
            array_merge($asset_data['dependencies'] ?? [], [
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-components',
                'wc-blocks-checkout',
                'wc-settings',
                'wp-data',
                'react',
                'wp-polyfill'
            ]),
            $asset_data['version'] ?? $this->plugin->get_plugin_version(),
            true
        );

        error_log('[ConvertCart DEBUG] Script registered at: ' . $script_url);
        error_log('[ConvertCart DEBUG] Registration status: ' . 
            (wp_script_is(self::SCRIPT_HANDLE, 'registered') ? 'success' : 'failed'));
    }

    public function register_checkout_block(IntegrationRegistry $registry): void {
        error_log('[ConvertCart DEBUG] Registering checkout block with WooCommerce Blocks...');
        
        try {
            $integration = new Checkout_Block_Integration($this->plugin, [
                'smsEnabled' => $this->plugin->get_option('enable_sms_consent', 'disabled') !== 'disabled',
                'emailEnabled' => $this->plugin->get_option('enable_email_consent', 'disabled') !== 'disabled',
            ]);
            
            if (!$registry->is_registered($integration->get_name())) {
                $registry->register($integration);
                error_log('[ConvertCart DEBUG] Successfully registered checkout block integration.');
            }
        } catch (\Exception $e) {
            error_log('[ConvertCart ERROR] Failed to register checkout block: ' . $e->getMessage());
        }
    }

    public function register_script_dependencies(array $dependencies): array {
        static $already_registered = false;
        
        if ($already_registered) {
            return $dependencies;
        }
        
        error_log('[ConvertCart DEBUG] Adding script dependencies for blocks (first time only)...');
        $already_registered = true;
        
        return array_merge($dependencies, [
            'wp-blocks',
            'wp-i18n',
            'wp-element',
            'wp-components',
            'wc-blocks-checkout',
            'wc-settings',
            'wp-data',
            'react',
            'wp-polyfill'
        ]);
    }
} 