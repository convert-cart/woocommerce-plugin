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

    /**
     * Constructor.
     *
     * @param WC_Integration $integration Parent integration instance
     * @param string $consent_type Type of consent (sms or email)
     */
    public function __construct(WC_Integration $integration, string $consent_type) {
        parent::__construct($integration);
        $this->consent_type = $consent_type;
        $this->consent_mode_option = "enable_{$consent_type}_consent";
    }

    /**
     * Initialize hooks.
     */
    public function init(): void {
        // Add consent checkboxes
        add_action('woocommerce_review_order_before_submit', [$this, 'add_consent_checkbox']);
        add_action('woocommerce_register_form', [$this, 'add_consent_to_registration_form']);
        add_action('woocommerce_edit_account_form', [$this, 'add_consent_checkbox_to_account_page']);

        // Save consent
        add_action('woocommerce_checkout_create_order', [$this, 'save_consent_to_order_or_customer'], 10, 2);
        add_action('woocommerce_created_customer', [$this, 'save_consent_from_registration_form'], 10, 1);
        add_action('woocommerce_save_account_details', [$this, 'save_consent_from_account_page'], 12, 1);
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
     * Check if consent is enabled.
     *
     * @return bool
     */
    protected function is_consent_enabled(): bool {
        return $this->get_consent_mode() === 'live';
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
     * Add consent checkbox.
     */
    public function add_consent_checkbox(): void {
        if (!$this->is_consent_enabled()) {
            return;
        }

        echo wp_kses_post($this->get_consent_html('checkout'));
    }

    /**
     * Add consent to registration form.
     */
    public function add_consent_to_registration_form(): void {
        if (!$this->is_consent_enabled()) {
            return;
        }
        $html = $this->get_consent_html_for_context('registration');
        // Add this log
        error_log("ConvertCart Debug ({$this->consent_type}): Registration form HTML being generated: " . $html);
        // We echo directly, assuming the saved HTML is sanitized.
        // Filters on 'woocommerce_register_form' could still interfere.
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
            // Should not happen on account page, but good practice
            return;
        }

        // Get the base HTML template for the account context
        $html = $this->get_consent_html_for_context('account');
        error_log("ConvertCart Debug ({$this->consent_type}): Base account page HTML retrieved: " . $html);

        // Get the user's current consent status
        $current_consent = get_user_meta($user_id, "{$this->consent_type}_consent", true);
        error_log("ConvertCart Debug ({$this->consent_type}): Current consent for user {$user_id} is: '{$current_consent}'.");

        // If user has consented, add the 'checked' attribute to the input tag
        if ($current_consent === 'yes') {
            // Find the input tag - simple string replacement is often sufficient here,
            // but can be fragile if the HTML structure is complex or varies wildly.
            // This assumes a standard <input ... name="{$this->consent_type}_consent" ... > structure.
            $input_name_attr = 'name="' . $this->consent_type . '_consent"';
            $input_tag_pos = strpos($html, $input_name_attr);

            if ($input_tag_pos !== false) {
                // Find the closing '>' of the input tag starting from the name attribute position
                $input_tag_end_pos = strpos($html, '>', $input_tag_pos);
                if ($input_tag_end_pos !== false) {
                    // Check if it's a self-closing tag '/>' or just '>'
                    $tag_ending = '>';
                    if (substr($html, $input_tag_end_pos - 1, 1) === '/') {
                        $tag_ending = '/>';
                        $insertion_point = $input_tag_end_pos - 1;
                    } else {
                        $insertion_point = $input_tag_end_pos;
                    }
                    // Insert checked='checked' just before the closing bracket/slash
                    // Avoid adding if already present (unlikely but possible)
                    if (strpos(substr($html, 0, $insertion_point), 'checked=') === false) {
                         $html = substr_replace($html, ' checked="checked" ', $insertion_point, 0);
                         error_log("ConvertCart Debug ({$this->consent_type}): Added 'checked' attribute to account page HTML.");
                    } else {
                         error_log("ConvertCart Debug ({$this->consent_type}): 'checked' attribute already present in account page HTML.");
                    }
                } else {
                     error_log("ConvertCart Debug ({$this->consent_type}): Could not find closing '>' for input tag in account HTML.");
                }
            } else {
                 error_log("ConvertCart Debug ({$this->consent_type}): Could not find input tag with {$input_name_attr} in account HTML.");
            }
        }

        // Log the final HTML before echoing
        error_log("ConvertCart Debug ({$this->consent_type}): Final account page HTML being generated: " . $html);

        // Echo the potentially modified HTML
        echo $html; // Assuming HTML from get_consent_html_for_context is safe enough here
    }

    /**
     * Save consent from various contexts.
     *
     * @param int $user_id
     */
    protected function save_consent(int $user_id): void {
        if (!$this->is_consent_enabled()) {
            error_log("ConvertCart Debug ({$this->consent_type}): save_consent called for user {$user_id} but consent is disabled. Bailing.");
            return;
        }

        // Check if the specific consent checkbox name exists in POST data
        $post_key = "{$this->consent_type}_consent";
        $consent_submitted = isset($_POST[$post_key]);
        $consent_value = $consent_submitted ? 'yes' : 'no';

        // Log what we found in POST
        error_log("ConvertCart Debug ({$this->consent_type}): save_consent called for user {$user_id}. POST key '{$post_key}' exists: " . ($consent_submitted ? 'Yes' : 'No') . ". Saving value: '{$consent_value}'.");

        // Get previous value for comparison (optional logging)
        $previous_consent = get_user_meta($user_id, $post_key, true);
        error_log("ConvertCart Debug ({$this->consent_type}): Previous consent for user {$user_id} was: '{$previous_consent}'.");

        update_user_meta($user_id, $post_key, sanitize_text_field($consent_value));
        error_log("ConvertCart Debug ({$this->consent_type}): Updated user meta for user {$user_id} with key '{$post_key}' to '{$consent_value}'.");
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
             // Add this log
             error_log("ConvertCart Debug ({$this->consent_type}): Custom HTML for context '{$context}' (option '{$option_name}') is empty or invalid. Using default.");
             return $this->get_default_consent_html();
        }

        // Check if the custom HTML is missing the essential input tag
        if (strpos($custom_html, '<input') === false || strpos($custom_html, 'type="checkbox"') === false) {
             // Add this log
             error_log("ConvertCart Debug ({$this->consent_type}): Custom HTML for context '{$context}' (option '{$option_name}') is missing required <input type=\"checkbox\"> tag. Using default.");
             return $this->get_default_consent_html(); // Fallback if input is missing
        }

        // Add this log if using custom HTML
        error_log("ConvertCart Debug ({$this->consent_type}): Using custom HTML for context '{$context}' (option '{$option_name}').");
        // Return the custom HTML fetched from options
        return $custom_html;
    }
} 