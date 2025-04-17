<?php
declare(strict_types=1);

namespace ConvertCart\Analytics;

use ConvertCart\Analytics\Admin\WC_CC_Admin;
use ConvertCart\Analytics\API\WC_CC_REST_Controller;
use ConvertCart\Analytics\Consent\WC_CC_SMS_Consent;
use ConvertCart\Analytics\Consent\WC_CC_Email_Consent;
use ConvertCart\Analytics\Tracking\WC_CC_Analytics_Tracking;
use ConvertCart\Analytics\Events\Event_Manager;
use WC_Integration;

/**
 * Main plugin class for Convert Cart Analytics.
 *
 * @package ConvertCart\Analytics
 */
class WC_CC_Analytics extends WC_Integration {

    /**
     * @var WC_CC_Admin
     */
    private WC_CC_Admin $admin;

    /**
     * @var WC_CC_REST_Controller
     */
    private WC_CC_REST_Controller $rest_controller;

    /**
     * @var WC_CC_SMS_Consent
     */
    private WC_CC_SMS_Consent $sms_consent;

    /**
     * @var WC_CC_Email_Consent
     */
    private WC_CC_Email_Consent $email_consent;

    /**
     * @var WC_CC_Analytics_Tracking
     */
    private WC_CC_Analytics_Tracking $tracking;

    /**
     * @var Event_Manager|null
     */
    private ?Event_Manager $event_manager = null;

    /** @var string Plugin base URL. */
    private string $plugin_url;

    /** @var string Plugin base path. */
    private string $plugin_path;

    /** @var string Plugin version. */
    private string $plugin_version;

    /** @var string Client ID */
    private $cc_client_id;

    /** @var string Debug mode */
    private $debug_mode;

    /**
     * Initialize the integration.
     */
    public function __construct() {
        $this->id = 'cc_analytics';
        $this->method_title = __('Convert Cart Analytics', 'woocommerce_cc_analytics');
        $this->method_description = __('Integrate Convert Cart Analytics with your WooCommerce store.', 'woocommerce_cc_analytics');

        // Store plugin info
        $this->plugin_url = CONVERTCART_ANALYTICS_URL;
        $this->plugin_path = CONVERTCART_ANALYTICS_PATH;
        $this->plugin_version = CONVERTCART_ANALYTICS_VERSION;

        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();

        // Get settings values
        $this->cc_client_id = $this->get_option('cc_client_id');
        $this->debug_mode = $this->get_option('debug_mode');

        // Initialize components
        $this->init_components();

        // Save settings
        add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields.
     */
    public function init_form_fields(): void {
        $this->form_fields = [
            'cc_client_id' => [
                'title'       => __('Client ID / Domain Id', 'woocommerce_cc_analytics'),
                'type'        => 'text',
                'description' => __('Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics'),
                'desc_tip'    => true,
                'default'     => '',
            ],
            'debug_mode' => [
                'title'       => __('Enable Debug Mode', 'woocommerce_cc_analytics'),
                'type'        => 'checkbox',
                'label'       => __('Enable Debugging for Meta Info', 'woocommerce_cc_analytics'),
                'default'     => 'no',
                'description' => __('If enabled, WooCommerce & WordPress plugin versions will be included in tracking metadata.', 'woocommerce_cc_analytics'),
            ],
            'enable_sms_consent' => [
                'title'       => __('Enable SMS Consent', 'woocommerce_cc_analytics'),
                'type'        => 'select',
                'options'     => [
                    'disabled' => __('Disabled', 'woocommerce_cc_analytics'),
                    'draft'    => __('Draft Mode', 'woocommerce_cc_analytics'),
                    'live'     => __('Live Mode', 'woocommerce_cc_analytics'),
                ],
                'default'     => 'disabled',
                'description' => __('Control SMS consent collection functionality.', 'woocommerce_cc_analytics'),
            ],
            'enable_email_consent' => [
                'title'       => __('Enable Email Consent', 'woocommerce_cc_analytics'),
                'type'        => 'select',
                'options'     => [
                    'disabled' => __('Disabled', 'woocommerce_cc_analytics'),
                    'draft'    => __('Draft Mode', 'woocommerce_cc_analytics'),
                    'live'     => __('Live Mode', 'woocommerce_cc_analytics'),
                ],
                'default'     => 'disabled',
                'description' => __('Control email consent collection functionality.', 'woocommerce_cc_analytics'),
            ],
        ];
    }

    /**
     * Initialize components.
     */
    private function init_components(): void {
        // Initialize admin
        $this->admin = new WC_CC_Admin($this);
        $this->admin->init();

        // Initialize REST controller
        $this->rest_controller = new WC_CC_REST_Controller($this);
        $this->rest_controller->init();

        // Initialize consent handlers
        $this->sms_consent = new WC_CC_SMS_Consent($this, $this->plugin_url, $this->plugin_path, $this->plugin_version);
        $this->sms_consent->init();
        $this->email_consent = new WC_CC_Email_Consent($this, $this->plugin_url, $this->plugin_path, $this->plugin_version);
        $this->email_consent->init();

        // Initialize tracking
        $this->tracking = new WC_CC_Analytics_Tracking($this);
        $this->tracking->init();

        // Add action for standard script enqueuing
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);
    }

    /**
     * Enqueue scripts and localize data for the checkout page.
     * Renamed from register_block_checkout_integration for clarity.
     */
    public function enqueue_checkout_scripts(): void {
        if (!is_checkout()) {
            return;
        }

        $sms_consent_option = $this->get_option('enable_sms_consent', 'disabled');
        $email_consent_option = $this->get_option('enable_email_consent', 'disabled');

        $sms_consent_enabled = $sms_consent_option !== 'disabled';
        $email_consent_enabled = $email_consent_option !== 'disabled';

        if (!$sms_consent_enabled && !$email_consent_enabled) {
            return;
        }

        wp_register_script(
            'convertcart-blocks-integration',
            $this->plugin_url . 'assets/js/block-checkout-integration.js',
            ['jquery'],
            $this->plugin_version,
            true
        );

        $sms_html = $sms_consent_enabled ? $this->sms_consent->get_checkout_html() : '';
        $email_html = $email_consent_enabled ? $this->email_consent->get_checkout_html() : '';

        $data = [
            'sms_enabled' => $sms_consent_enabled,
            'email_enabled' => $email_consent_enabled,
            'sms_consent_html' => $sms_html,
            'email_consent_html' => $email_html,
            'insertion_point' => '.woocommerce-terms-and-conditions-wrapper'
        ];

        wp_localize_script('convertcart-blocks-integration', 'convertcart_consent_data', $data);
        wp_enqueue_script('convertcart-blocks-integration');
    }

    public function get_plugin_url(): string {
        return $this->plugin_url;
    }

    public function get_plugin_path(): string {
        return $this->plugin_path;
    }

    public function get_plugin_version(): string {
        return $this->plugin_version;
    }
}
