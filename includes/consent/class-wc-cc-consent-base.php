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
    }

    /**
     * Initialize hooks.
     */
    public function init(): void {
        // Hooks for Registration and Account pages
        add_action('woocommerce_register_form', [$this, 'add_consent_to_registration_form']);

        add_action('woocommerce_edit_account_form', [$this, 'add_consent_checkbox_to_account_page']);


        // Save consent hooks
        add_action('woocommerce_checkout_create_order', [$this, 'save_consent_to_order_or_customer'], 10, 2);

        add_action('woocommerce_created_customer', [$this, 'save_consent_from_registration_form'], 10, 1);

        add_action('woocommerce_save_account_details', [$this, 'save_consent_from_account_page'], 12, 1);

        // This hook might be redundant if checkout/registration covers account creation scenarios
        add_action('woocommerce_created_customer', [$this, 'save_consent_when_account_is_created'], 10, 3);
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
     * Save consent to order or customer.
     *
     * @param \WC_Order $order
     * @param array $data
     */
    public function save_consent_to_order_or_customer(\WC_Order $order, array $data): void {
        if (!$this->is_consent_enabled()) {
            return;
        }

        $consent = isset($_POST["{$this->consent_type}_consent"]) ? 'yes' : 'no';
        $order->update_meta_data("{$this->consent_type}_consent", $consent);

        // If user is logged in, also save to user meta
        $user_id = $order->get_customer_id();
        if ($user_id > 0) {
            update_user_meta($user_id, "{$this->consent_type}_consent", sanitize_text_field($consent));
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
     * Check if the current checkout page is using WooCommerce Blocks.
     * Note: This detection might need refinement as WooCommerce evolves.
     *
     * @return bool True if Blocks checkout is detected, false otherwise.
     */
    protected function is_blocks_checkout(): bool {
        // Check 1: If the specific checkout block is rendered on the page
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
            return true;
        }

        // Check 2: Look for body classes specific to block checkout themes/pages
        // This is less reliable as classes can change
        // $body_classes = get_body_class();
        // if (in_array('is-block-theme', $body_classes) && is_checkout()) {
        //     return true;
        // }

        // Check 3: Check if the block checkout script is enqueued (might run too late)
        // if (wp_script_is('wc-blocks-checkout', 'enqueued')) {
        //     return true;
        // }

        return false; // Default to classic if no block indicators found
    }
} 