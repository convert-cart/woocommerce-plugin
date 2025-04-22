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
        error_log('[ConvertCart DEBUG] Blocks_Registration_Service::init() called');
        // Register scripts immediately to guarantee registration before enqueue
        $this->register_frontend_scripts();
        // Force enqueue script on checkout page
        add_action('wp_enqueue_scripts', function() {
            error_log('[ConvertCart DEBUG] Forcing enqueue of convertcart-blocks-integration on ALL front-end pages.');
            error_log('[ConvertCart DEBUG] Script registered at enqueue: ' . (wp_script_is(self::SCRIPT_HANDLE, 'registered') ? 'yes' : 'no'));
            wp_enqueue_script(self::SCRIPT_HANDLE);
        }, 20);


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
                    
                    // Optionally, add inline script if needed for block filters (leave as is if required)
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

        // Register checkout block integration with WooCommerce Blocks
        add_action('woocommerce_blocks_checkout_block_registration', function($integration_registry) {
            error_log('[ConvertCart DEBUG] Entered woocommerce_blocks_checkout_block_registration callback');
            if (!class_exists('ConvertCart\\Analytics\\Blocks\\Checkout_Block_Integration')) {
                $integration_file = __DIR__ . '/class-checkout-block-integration.php';
                if (file_exists($integration_file)) {
                    require_once $integration_file;
                    error_log('[ConvertCart DEBUG] Required Checkout_Block_Integration class file.');
                } else {
                    error_log('[ConvertCart ERROR] Checkout_Block_Integration class file missing: ' . $integration_file);
                }
            }
            if (class_exists('ConvertCart\\Analytics\\Blocks\\Checkout_Block_Integration')) {
                $integration = new Checkout_Block_Integration($this->plugin);
                if (!$integration_registry->is_registered($integration->get_name())) {
                    $integration_registry->register($integration);
                    error_log('[ConvertCart DEBUG] Successfully registered checkout block integration.');
                } else {
                    error_log('[ConvertCart DEBUG] Integration already registered: ' . $integration->get_name());
                }
            } else {
                error_log('[ConvertCart ERROR] Checkout_Block_Integration class not found after require.');
            }
        });
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
        error_log('[ConvertCart DEBUG] About to register script with URL: ' . $script_url);
        error_log('[ConvertCart DEBUG] Script URL: ' . $script_url);
        
        wp_register_script(
            self::SCRIPT_HANDLE,
            $script_url,
            [], // No dependencies for testing
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