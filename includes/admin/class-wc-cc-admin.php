<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Admin;

use ConvertCart\Analytics\Abstract\WC_CC_Base;
use ConvertCart\Analytics\Consent\WC_CC_SMS_Consent;
use ConvertCart\Analytics\Consent\WC_CC_Email_Consent;
use WC_Integration;

/**
 * Handles admin functionality.
 */
class WC_CC_Admin extends WC_CC_Base {
    /**
     * @var WC_CC_Menu
     */
    private WC_CC_Menu $menu;

    /**
     * @var WC_CC_SMS_Consent
     */
    private WC_CC_SMS_Consent $sms_consent;

    /**
     * @var WC_CC_Email_Consent
     */
    private WC_CC_Email_Consent $email_consent;

    /**
     * Constructor.
     *
     * @param WC_Integration $integration The main WC_CC_Analytics integration instance.
     */
    public function __construct(WC_Integration $integration) {
        parent::__construct($integration);
        $this->menu = new WC_CC_Menu($integration);

        // Retrieve plugin details from the main integration instance
        $plugin_url = method_exists($integration, 'get_plugin_url') ? $integration->get_plugin_url() : '';
        $plugin_path = method_exists($integration, 'get_plugin_path') ? $integration->get_plugin_path() : '';
        $plugin_version = method_exists($integration, 'get_plugin_version') ? $integration->get_plugin_version() : 'unknown';

        // Instantiate consent classes with all required arguments
        $this->sms_consent = new WC_CC_SMS_Consent($integration, $plugin_url, $plugin_path, $plugin_version);
        $this->email_consent = new WC_CC_Email_Consent($integration, $plugin_url, $plugin_path, $plugin_version);
    }

    /**
     * Initialize hooks.
     */
    public function init(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // Initialize menu
        $this->menu->init();
    }

    /**
     * Register settings.
     */
    public function register_settings(): void {
        // Register SMS consent settings
        register_setting('cc_consent_settings', 'cc_sms_consent_checkout_html', [
            'type' => 'string',
            'sanitize_callback' => [$this->sms_consent, 'sanitize_consent_html'],
            'default' => $this->sms_consent->get_default_consent_html(),
            'show_in_rest' => false,
        ]);

        register_setting('cc_consent_settings', 'cc_sms_consent_registration_html', [
            'type' => 'string',
            'sanitize_callback' => [$this->sms_consent, 'sanitize_consent_html'],
            'default' => $this->sms_consent->get_default_consent_html(),
            'show_in_rest' => false,
        ]);

        register_setting('cc_consent_settings', 'cc_sms_consent_account_html', [
            'type' => 'string',
            'sanitize_callback' => [$this->sms_consent, 'sanitize_consent_html'],
            'default' => $this->sms_consent->get_default_consent_html(),
            'show_in_rest' => false,
        ]);

        // Register Email consent settings
        register_setting('cc_consent_settings', 'cc_email_consent_checkout_html', [
            'type' => 'string',
            'sanitize_callback' => [$this->email_consent, 'sanitize_consent_html'],
            'default' => $this->email_consent->get_default_consent_html(),
            'show_in_rest' => false,
        ]);

        register_setting('cc_consent_settings', 'cc_email_consent_registration_html', [
            'type' => 'string',
            'sanitize_callback' => [$this->email_consent, 'sanitize_consent_html'],
            'default' => $this->email_consent->get_default_consent_html(),
            'show_in_rest' => false,
        ]);

        register_setting('cc_consent_settings', 'cc_email_consent_account_html', [
            'type' => 'string',
            'sanitize_callback' => [$this->email_consent, 'sanitize_consent_html'],
            'default' => $this->email_consent->get_default_consent_html(),
            'show_in_rest' => false,
        ]);

        // Add settings saved message
        add_action('update_option_cc_sms_consent_checkout_html', function() {
            add_settings_error(
                'cc_consent_settings',
                'settings_updated',
                __('Settings saved.', 'woocommerce_cc_analytics'),
                'success'
            );
        });

        // Add error handling to sanitization for all consent fields
        foreach (['sms', 'email'] as $type) {
            foreach (['checkout', 'registration', 'account'] as $page) {
                $option_name = "cc_{$type}_consent_{$page}_html";
                add_filter("pre_update_option_{$option_name}", function($value, $old_value) use ($type, $page) {
                    if ($value === false || $value === null) {
                        add_settings_error(
                            'cc_consent_settings',
                            'invalid_html',
                            sprintf(
                                __('Invalid HTML provided for %s consent %s template.', 'woocommerce_cc_analytics'),
                                strtoupper($type),
                                $page
                            ),
                            'error'
                        );
                        return $old_value;
                    }
                    return $value;
                }, 10, 2);
            }
        }
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets(): void {
        $screen = get_current_screen();

        if (!$screen || !$this->is_plugin_page($screen->id)) {
            return;
        }

        // Enqueue WordPress code editor assets
        $editor_settings = wp_enqueue_code_editor(['type' => 'text/html']);

        // Bail if code editor assets were not enqueued successfully.
        if (false === $editor_settings) {
            error_log('ConvertCart Admin: Failed to enqueue code editor assets.');
            return;
        }

        // Enqueue HTML beautifier
        wp_enqueue_script(
            'js-beautify',
            'https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.9/beautify-html.min.js',
            [], // No dependencies needed here
            '1.14.9',
            true // Load in footer
        );

        // Enqueue our custom script
        wp_enqueue_script(
            'convertcart-admin',
            plugins_url('assets/js/admin.js', CONVERTCART_PLUGIN_FILE),
            ['jquery', 'wp-util', 'code-editor', 'js-beautify'], // Ensure code-editor and js-beautify are dependencies
            defined('CONVERTCART_ANALYTICS_VERSION') ? CONVERTCART_ANALYTICS_VERSION : '1.0.0', // Fallback version
            true // Load in footer
        );

        // --- ADD Enqueue Admin CSS ---
        wp_enqueue_style(
            'convertcart-admin-styles',
            plugins_url('assets/css/admin.css', CONVERTCART_PLUGIN_FILE),
            [], // No dependencies for this simple CSS
            defined('CONVERTCART_ANALYTICS_VERSION') ? CONVERTCART_ANALYTICS_VERSION : '1.0.0'
        );
        // --- END Enqueue Admin CSS ---

        // Pass data to our script
        wp_localize_script('convertcart-admin', 'convertCartAdminData', [
            // Pass the editor settings obtained from wp_enqueue_code_editor
            'editorSettings' => $editor_settings,
            'defaultTemplates' => [
                'sms' => $this->sms_consent->get_default_consent_html(),
                'email' => $this->email_consent->get_default_consent_html(),
            ],
            'i18n' => [ // Internationalization strings for JS
                'resetConfirm' => esc_js(__('Are you sure you want to reset this template to default? This cannot be undone.', 'woocommerce_cc_analytics')),
                'formatButton' => esc_js(__('Format HTML', 'woocommerce_cc_analytics')),
                'resetButton' => esc_js(__('Reset to Default', 'woocommerce_cc_analytics')),
            ]
        ]);
    }

    /**
     * Check if current page is a plugin page.
     * Corrected screen IDs for submenu pages.
     */
    private function is_plugin_page(string $screen_id): bool {
        // Get the actual slugs used when adding the menu pages
        // Assuming the parent slug is 'convert-cart' and submenu slugs are
        // 'convert-cart-sms-consent' and 'convert-cart-email-consent'
        // Adjust these if the slugs in WC_CC_Menu are different.
        $parent_slug = 'convert-cart'; // Replace if different
        $sms_slug = 'convert-cart-sms-consent'; // Replace if different
        $email_slug = 'convert-cart-email-consent'; // Replace if different

        $expected_screen_ids = [
            $parent_slug . '_page_' . $sms_slug,
            $parent_slug . '_page_' . $email_slug,
            // You might also want to include the main integration settings page if applicable
            // 'woocommerce_page_wc-settings', // Example if scripts needed on WC settings > Integration tab
        ];

        return in_array($screen_id, $expected_screen_ids, true);
    }

    /**
     * Show admin notices for settings updates.
     */
    public function show_admin_notices(): void {
        $screen = get_current_screen();
        if (!$screen || !$this->is_plugin_page($screen->id)) {
            return;
        }

        // Get all settings errors
        $settings_errors = get_settings_errors('cc_consent_settings');
        
        // If no errors but settings were updated, show success message
        if (empty($settings_errors) && isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error(
                'cc_consent_settings',
                'settings_updated',
                __('Settings saved successfully.', 'woocommerce_cc_analytics'),
                'success'
            );
        }

        // Display all settings errors/notices
        settings_errors('cc_consent_settings');
    }
} 