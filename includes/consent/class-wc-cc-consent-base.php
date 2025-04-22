<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Consent;

use ConvertCart\Analytics\Abstract\WC_CC_Base;
use WC_Integration;

/**
 * Base class for consent handling.
 */
abstract class WC_CC_Consent_Base extends WC_CC_Base {
    /**
     * @var string Consent type (sms or email)
     */
    protected string $consent_type;

    /**
     * @var string Option name for consent mode
     */
    protected string $consent_mode_option;

    /** @var string Plugin base URL. */
    protected string $plugin_url;

    /** @var string Plugin base path. */
    protected string $plugin_path;

    /** @var string Plugin version. */
    protected string $plugin_version;

    /**
     * Constructor.
     *
     * @param WC_Integration $integration Parent integration instance
     * @param string $consent_type Type of consent (sms or email)
     * @param string $plugin_url Base URL of the plugin
     * @param string $plugin_path Base path of the plugin
     * @param string $plugin_version Plugin version
     */
    public function __construct(
        WC_Integration $integration,
        string $consent_type,
        string $plugin_url,
        string $plugin_path,
        string $plugin_version
    ) {
        parent::__construct($integration);
        $this->consent_type = $consent_type;
        $this->consent_mode_option = "enable_{$consent_type}_consent";

        // Store passed values
        $this->plugin_url = $plugin_url;
        $this->plugin_path = $plugin_path;
        $this->plugin_version = $plugin_version;

        // Comment out the validation hook
        // add_action('woocommerce_after_checkout_validation', [$this, 'validate_checkout_consent'], 10, 2);
        error_log("[ConvertCart] Consent validation hook TEMPORARILY DISABLED for {$this->consent_type}");
    }

    /**
     * Initialize hooks.
     */
    public function init(): void {
        // Add checkout-specific hooks for both classic and block checkout
        add_action('woocommerce_checkout_create_order', [$this, 'save_consent_to_order'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_consent_to_customer'], 10, 2);
        
        // Block checkout hooks with different signature
        add_action('woocommerce_store_api_checkout_update_order_meta', function($order) {
            $this->save_consent_to_order($order, []);
        }, 10, 1);
        
        // Add validation
        // add_action('woocommerce_checkout_process', [$this, 'validate_checkout_consent']); // Disable classic validation for testing
        // add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'validate_checkout_consent']); // Disable block validation for testing
        error_log("[ConvertCart] Consent validation hooks in init() TEMPORARILY DISABLED for {$this->consent_type}");
        
        // Other hooks remain the same
        add_action('woocommerce_register_form', [$this, 'add_consent_to_registration_form']);
        add_action('woocommerce_edit_account_form', [$this, 'add_consent_checkbox_to_account_page']);
        add_action('woocommerce_created_customer', [$this, 'save_consent_from_registration_form'], 10, 1);
        add_action('woocommerce_save_account_details', [$this, 'save_consent_from_account_page'], 12, 1);
    }

    /**
     * Get consent mode.
     *
     * @return string
     */
    protected function get_consent_mode(): string {
        return $this->integration->get_option($this->consent_mode_option, 'disabled');
    }

    /**
     * Check if consent functionality should be active (Live or Draft mode).
     *
     * @return bool
     */
    protected function is_consent_enabled(): bool {
        $mode = $this->get_consent_mode();
        $enabled = ($mode === 'live' || $mode === 'draft');
        return $enabled;
    }

    /**
     * Get default consent HTML.
     *
     * @return string
     */
    public function get_default_consent_html(): string {
        // This should be implemented by child classes
        return '';
    }

    /**
     * Get consent HTML for specific context.
     *
     * @param string $context Context (checkout, registration, account)
     * @return string
     */
    protected function get_consent_html(string $context): string {
        $option_name = "cc_{$this->consent_type}_consent_{$context}_html";
        $html = get_option($option_name);
        
        if (empty($html)) {
            $html = $this->get_default_consent_html();
        }

        return $html;
    }

    /**
     * Add consent checkbox. (DEPRECATED for checkout hook)
     * This method might still be called if something else hooks into it, but it's
     * no longer the primary way the checkout checkbox is added by this plugin.
     */
    public function add_consent_checkbox(): void {
        // Original logic commented out as it's handled by child classes now.
        // if (!$this->is_consent_enabled() || !is_checkout()) {
        //     return;
        // }
        // echo wp_kses_post($this->get_consent_html_for_context('checkout'));
    }

    /**
     * Add consent to registration form.
     */
    public function add_consent_to_registration_form(): void {
        if (!$this->is_consent_enabled()) {
            return;
        }
        $html = $this->get_consent_html_for_context('registration');
        echo $html;
    }

    /**
     * Add consent checkbox to account page, reflecting current user status.
     */
    public function add_consent_checkbox_to_account_page(): void {
        if (!$this->is_consent_enabled()) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        // Get the base HTML template for the account context
        $html = $this->get_consent_html_for_context('account');

        // Get the user's current consent status
        $current_consent = get_user_meta($user_id, "{$this->consent_type}_consent", true);
        $is_checked = ($current_consent === 'yes');


        // Modify the HTML to set the 'checked' attribute if needed
        // This is a bit fragile; assumes a standard input structure.
        if ($is_checked) {
            // Find the input tag and add 'checked="checked"'
            // Ensure we don't add it twice if it's already there in the template
            if (strpos($html, 'checked=') === false) {
                 $html = preg_replace('/<input([^>]*type=["\']checkbox["\'][^>]*)>/i', '<input$1 checked="checked">', $html, 1);
            }
        } else {
            // Ensure 'checked' attribute is NOT present if consent is 'no' or empty
             $html = preg_replace('/<input([^>]*?)(\schecked(=["\']checked["\'])?)([^>]*?)>/i', '<input$1$4>', $html, 1);
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is modified but based on trusted source or default. preg_replace is used carefully.
        echo $html;
    }

    /**
     * Save consent from various contexts.
     *
     * @param int $user_id
     */
    protected function save_consent(int $user_id): void {
        if (!$this->is_consent_enabled()) {
            return;
        }

        // Check if the specific consent checkbox name exists in POST data
        $post_key = "{$this->consent_type}_consent";
        $consent_submitted = isset($_POST[$post_key]);
        $consent_value = $consent_submitted ? 'yes' : 'no';

        // Get previous value for comparison (optional logging)
        $previous_consent = get_user_meta($user_id, $post_key, true);

        update_user_meta($user_id, $post_key, sanitize_text_field($consent_value));
    }

    /**
     * Save consent from registration form.
     *
     * @param int $user_id
     */
    public function save_consent_from_registration_form(int $user_id): void {
        $this->save_consent($user_id);
    }

    /**
     * Save consent from account page.
     *
     * @param int $user_id
     */
    public function save_consent_from_account_page(int $user_id): void {
        $this->save_consent($user_id);
    }

    /**
     * Save consent when account is created.
     *
     * @param int $user_id
     * @param array $new_customer_data
     * @param bool $password_generated
     */
    public function save_consent_when_account_is_created(int $user_id, array $new_customer_data, bool $password_generated): void {
        $this->save_consent($user_id);
    }

    /**
     * Save consent to order.
     *
     * @param \WC_Order $order
     * @param array $data Optional data array
     */
    public function save_consent_to_order(\WC_Order $order, array $data = []): void {
        error_log("[ConvertCart] Attempting to save {$this->consent_type} consent to order: " . $order->get_id());
        
        if (!$this->is_consent_enabled()) {
            error_log("[ConvertCart] {$this->consent_type} consent not enabled, skipping save");
            return;
        }

        $consent_key = "{$this->consent_type}_consent";
        
        // Try to get consent value from multiple sources
        $consent = null;
        
        // Check POST data first
        if (isset($_POST[$consent_key])) {
            $consent = $_POST[$consent_key] === 'yes' ? 'yes' : 'no';
            error_log("[ConvertCart] Found consent in POST data: $consent");
        }
        
        // Check extension data from blocks
        if ($consent === null && !empty($data['extension_data']['convertcart-analytics']['consent_data'][$consent_key])) {
            $consent = $data['extension_data']['convertcart-analytics']['consent_data'][$consent_key] === 'yes' ? 'yes' : 'no';
            error_log("[ConvertCart] Found consent in extension data: $consent");
        }
        
        // Default to 'no' if no consent found
        if ($consent === null) {
            $consent = 'no';
            error_log("[ConvertCart] No consent found, defaulting to: $consent");
        }

        error_log("[ConvertCart] Saving {$this->consent_type} consent value: {$consent}");
        $order->update_meta_data($consent_key, $consent);
        
        try {
            $order->save();
            error_log("[ConvertCart] Successfully saved consent to order");
        } catch (\Exception $e) {
            error_log("[ConvertCart] Error saving consent to order: " . $e->getMessage());
        }
    }

    /**
     * Save consent data to customer
     */
    public function save_consent_to_customer(int $order_id, array $data): void {
        error_log("Attempting to save {$this->consent_type} consent to customer for order: {$order_id}");
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Order not found: {$order_id}");
            return;
        }
        
        if (!$this->is_consent_enabled()) {
            error_log("{$this->consent_type} consent not enabled, skipping save");
            return;
        }

        $user_id = $order->get_customer_id();
        error_log("Customer ID: {$user_id}");
        
        if ($user_id > 0) {
            $consent_key = "{$this->consent_type}_consent";
            $consent = isset($_POST[$consent_key]) ? 'yes' : 'no';
            error_log("Saving {$this->consent_type} consent value: {$consent} for user: {$user_id}");
            update_user_meta($user_id, $consent_key, sanitize_text_field($consent));
        }
    }

    /**
     * Sanitize consent HTML.
     *
     * @param string|null $html HTML to sanitize
     * @return string
     */
    public function sanitize_consent_html(?string $html): string {
        // If null or empty, return default HTML
        if ($html === null || trim($html) === '') {
            return $this->get_default_consent_html();
        }

        // Allow specific HTML tags including input elements with their attributes
        $allowed_html = [
            'div' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'span' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'label' => [
                'for' => true,
                'class' => true,
                'style' => true,
            ],
            'input' => [
                'type' => true,
                'name' => true,
                'id' => true,
                'class' => true,
                'value' => true,
                'checked' => true,
                'style' => true,
            ],
            'p' => [
                'class' => true,
                'style' => true,
            ],
            'br' => [],
            'strong' => [],
            'em' => [],
            'a' => [
                'href' => true,
                'target' => true,
                'rel' => true,
                'class' => true,
            ],
        ];

        // Use wp_kses with our allowed HTML tags
        $sanitized_html = wp_kses($html, $allowed_html);

        // Check if the required checkbox exists
        if (strpos($sanitized_html, 'name="' . $this->consent_type . '_consent"') === false ||
            strpos($sanitized_html, 'type="checkbox"') === false) {
            // If checkbox is missing, return the default HTML
            return $this->get_default_consent_html();
        }

        return $sanitized_html;
    }

    /**
     * Get consent HTML for a specific context.
     *
     * @param string $context Context (checkout, registration, account)
     * @return string
     */
    protected function get_consent_html_for_context(string $context): string {
        $option_name = "cc_{$this->consent_type}_consent_{$context}_html";
        $custom_html = get_option($option_name);

        // Check if the custom HTML is empty, invalid, or just whitespace
        if (empty($custom_html) || !is_string($custom_html) || trim($custom_html) === '') {
             return $this->get_default_consent_html();
        }

        // Check if the custom HTML is missing the essential input tag
        if (strpos($custom_html, '<input') === false || strpos($custom_html, 'type="checkbox"') === false) {
             return $this->get_default_consent_html(); // Fallback if input is missing
        }

        // Return the custom HTML fetched from options
        return $custom_html;
    }

    /**
     * Check if the current checkout page uses blocks.
     */
    protected function is_blocks_checkout(): bool {
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
            return true;
        }

        return false;
    }

    /**
     * Get checkout HTML template
     */
    public function get_checkout_html(): string {
        $html = get_option('cc_' . $this->consent_type . '_consent_checkout_html', '');
        return !empty($html) ? $html : $this->get_default_consent_html();
    }

    /**
     * Validate consent during checkout
     */
    public function validate_checkout_consent(): void {
        error_log("Validating {$this->consent_type} consent");
        
        if (!$this->is_consent_enabled() || $this->get_consent_mode() !== 'live') {
            return;
        }

        $consent_key = "{$this->consent_type}_consent";
        $has_consent = false;
        
        // Check Store API data first
        if (function_exists('wc_get_raw_store_api_checkout_data')) {
            $checkout_data = wc_get_raw_store_api_checkout_data();
            error_log('[ConvertCart] Store API checkout data: ' . print_r($checkout_data, true));
            
            if (!empty($checkout_data['extensions']['convertcart-analytics'][$consent_key])) {
                $has_consent = $checkout_data['extensions']['convertcart-analytics'][$consent_key] === 'yes';
                error_log("[ConvertCart] Found consent in Store API: " . ($has_consent ? 'yes' : 'no'));
            }
        }
        
        // Check POST data as backup
        if (!$has_consent && isset($_POST[$consent_key])) {
            $has_consent = $_POST[$consent_key] === 'yes';
            error_log("[ConvertCart] Found consent in POST: " . ($has_consent ? 'yes' : 'no'));
        }
        
        if (!$has_consent) {
            wc_add_notice(
                sprintf(
                    __('Please review and accept the %s consent checkbox.', 'woocommerce_cc_analytics'),
                    strtoupper($this->consent_type)
                ),
                'error'
            );
        }
    }
} 