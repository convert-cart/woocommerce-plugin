<?php
namespace ConvertCart\Analytics\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use WC_Order;
use WC_Store_API_Request;
use ConvertCart\Analytics\WC_CC_Analytics; // Need access to main class type hint

class Checkout_Block_Integration implements IntegrationInterface {

    /** @var string Script handle */
    const SCRIPT_HANDLE = 'convertcart-blocks-integration';

    /** @var WC_CC_Analytics Main plugin instance */
    private $plugin; // Changed: No longer nullable, must be provided

    // Modify constructor to accept the plugin instance
    public function __construct( WC_CC_Analytics $plugin_instance ) {
        error_log('[ConvertCart Blocks DEBUG] Checkout_Block_Integration constructor called.');
        $this->plugin = $plugin_instance; // Assign the passed instance
        error_log('[ConvertCart Blocks DEBUG] Main plugin instance successfully passed to constructor.');
        // Remove the old lookup logic:
        // $integrations = class_exists('WC_Integrations') ? \WC()->integrations->get_integrations() : [];
        // $this->plugin = $integrations['cc_analytics'] ?? null;
        // if (!$this->plugin) { ... }
    }

    public function get_name(): string {
        error_log('[ConvertCart Blocks DEBUG] get_name() called, returning: convertcart-analytics');
        return 'convertcart-analytics';
    }

    public function initialize(): void {
        error_log('[ConvertCart DEBUG] Initializing block integration...');
        
        // Check if we have data to pass to the script
        $script_data = $this->get_script_data();
        error_log('[ConvertCart DEBUG] Script data: ' . print_r($script_data, true));

        if (empty($script_data)) {
            error_log('[ConvertCart DEBUG] No script data available. Skipping initialization.');
            return;
        }

        // Add script data
        wp_localize_script(
            self::SCRIPT_HANDLE,
            'convertCartBlocksData',
            $script_data
        );
        error_log('[ConvertCart DEBUG] Script data localized');

        // Add consent processing hook
        add_action(
            'woocommerce_store_api_checkout_update_order_meta',
            [$this, 'process_consent_from_extension_data'],
            10,
            2
        );
    }

    private function register_frontend_script(): void {
        $asset_file = $this->plugin->get_plugin_path() . 'assets/build/js/block-checkout-integration.asset.php';
        
        error_log('[ConvertCart DEBUG] Looking for asset file at: ' . $asset_file);
        
        $asset_data = file_exists($asset_file) 
            ? require($asset_file)
            : ['dependencies' => [], 'version' => $this->plugin->get_plugin_version()];

        error_log('[ConvertCart DEBUG] Asset data found: ' . print_r($asset_data, true));

        $script_url = $this->plugin->get_plugin_url() . 'assets/build/js/block-checkout-integration.js';
        
        if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
            wp_register_script(
                self::SCRIPT_HANDLE,
                $script_url,
                $asset_data['dependencies'],
                $asset_data['version'],
                true
            );
            error_log('[ConvertCart DEBUG] Registered script: ' . $script_url);
        }

        // Register the script data
        wp_localize_script(
            self::SCRIPT_HANDLE,
            'convertCartBlocksData',
            $this->get_script_data()
        );
        error_log('[ConvertCart DEBUG] Localized script data');
    }

    public function get_script_handles(): array {
        error_log('[ConvertCart Blocks DEBUG] get_script_handles() called.');
        return [self::SCRIPT_HANDLE];
    }

    public function get_editor_script_handles(): array {
        error_log('[ConvertCart Blocks DEBUG] get_editor_script_handles() called.');
        return [];
    }

    public function get_script_data(): array {
        $sms_enabled = $this->plugin->get_option('enable_sms_consent', 'disabled') !== 'disabled';
        $email_enabled = $this->plugin->get_option('enable_email_consent', 'disabled') !== 'disabled';

        error_log('[ConvertCart DEBUG] Consent states - SMS: ' . ($sms_enabled ? 'enabled' : 'disabled') . 
                  ', Email: ' . ($email_enabled ? 'enabled' : 'disabled'));

        if (!$sms_enabled && !$email_enabled) {
            error_log('[ConvertCart DEBUG] No consent types enabled');
            return [];
        }

        return [
            'sms_enabled' => $sms_enabled,
            'email_enabled' => $email_enabled,
            'sms_consent_html' => $sms_enabled ? $this->plugin->sms_consent->get_checkout_html() : '',
            'email_consent_html' => $email_enabled ? $this->plugin->email_consent->get_checkout_html() : '',
            'namespace' => 'convertcart-analytics'
        ];
    }

    public function process_consent_from_extension_data(WC_Order $order, WC_Store_API_Request $request): void {
        $current_hook = current_filter();
        error_log("==> [ConvertCart Blocks DEBUG] process_consent_from_extension_data called for Order ID: " . $order->get_id() . " via hook: " . $current_hook);

        // Check if already processed to prevent duplicate processing if hook fires multiple times
        if ($order->get_meta('_convertcart_consent_processed', true)) {
            error_log('[ConvertCart Blocks DEBUG] Consent already processed for order #' . $order->get_id() . ". Skipping.");
            return;
        }

        try {
            $extension_data = $request->get_extension_data();
            $namespace = $this->get_name(); // Use the dynamic name
            $consent_data = $extension_data[$namespace] ?? null;

            error_log("==> [ConvertCart Blocks DEBUG] Request Extension Data: " . print_r($extension_data, true));

            if (empty($consent_data)) {
                error_log('[ConvertCart Blocks DEBUG] No consent data found in extension data for namespace: ' . $namespace);
                // Even if no data, mark as processed to avoid re-checking unnecessarily on subsequent updates
                $order->update_meta_data('_convertcart_consent_processed', true);
                $order->save();
                error_log('[ConvertCart Blocks DEBUG] Marked order #' . $order->get_id() . ' as processed (no consent data found).');
                return; // Exit early if no relevant data
            } else {
                error_log('[ConvertCart Blocks DEBUG] Found consent data for namespace ' . $namespace . ': ' . print_r($consent_data, true));
            }

            $consent_updated = false;

            foreach (['sms_consent', 'email_consent'] as $consent_type) {
                $value = $consent_data[$consent_type] ?? null; // Default to null if not set

                if ($value !== null) {
                    error_log("[ConvertCart Blocks DEBUG] Found {$consent_type} in Extension Data: '{$value}' (type: " . gettype($value) . ")");
                } else {
                    error_log("[ConvertCart Blocks DEBUG] {$consent_type} not found in Extension Data. Will default to 'no'.");
                }

                // Explicitly check for 'yes', otherwise default to 'no'
                $final_value = ($value === 'yes') ? 'yes' : 'no';
                error_log("[ConvertCart Blocks DEBUG] Determined final value for {$consent_type}: '{$final_value}'");

                $existing_meta = $order->get_meta($consent_type);
                error_log("[ConvertCart Blocks DEBUG] Existing meta for {$consent_type}: " . ($existing_meta !== '' ? "'{$existing_meta}'" : "(empty)"));

                if ($existing_meta !== $final_value) {
                    error_log("[ConvertCart Blocks DEBUG] Updating meta for {$consent_type} from '{$existing_meta}' to: '{$final_value}'");
                    $order->update_meta_data($consent_type, $final_value);
                    $consent_updated = true;
                } else {
                    error_log("[ConvertCart Blocks DEBUG] Meta for {$consent_type} already set to '{$final_value}', no update needed.");
                }
            }

            // Mark as processed and save if updates were made or if it wasn't marked before
            if ($consent_updated || !$order->get_meta('_convertcart_consent_processed')) {
                error_log('[ConvertCart Blocks DEBUG] Marking order #' . $order->get_id() . ' as processed and saving meta data.');
                $order->update_meta_data('_convertcart_consent_processed', true);
                $order->save(); // Save all meta updates
                error_log('[ConvertCart Blocks DEBUG] Order meta data saved.');
            } else {
                error_log('[ConvertCart Blocks DEBUG] No consent meta data needed updating, and already marked processed.');
            }

        } catch (\Exception $e) {
            error_log('[ConvertCart Blocks ERROR] Error processing consent from extension data: ' . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
        }
    }
} 