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

    /**
     * Initialize the integration.
     */
    public function __construct() {
        $this->id                 = 'cc_analytics';
        $this->method_title       = __('Convert Cart Analytics', 'woocommerce_cc_analytics');
        $this->method_description = __('Integration with Convert Cart Analytics service.', 'woocommerce_cc_analytics');

        // Store plugin path and URL from constants
        $this->plugin_url = defined('CONVERTCART_ANALYTICS_URL') ? CONVERTCART_ANALYTICS_URL : '';
        $this->plugin_path = defined('CONVERTCART_ANALYTICS_PATH') ? CONVERTCART_ANALYTICS_PATH : '';
        $this->plugin_version = defined('CONVERTCART_ANALYTICS_VERSION') ? CONVERTCART_ANALYTICS_VERSION : 'unknown';

        // Load settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Initialize plugin components.
     */
    private function init_components(): void {
        // Example: Initialize Admin
        if (is_admin()) {
            $this->admin = new WC_CC_Admin($this);
        }

        // Example: Initialize Consent Modules
        $this->sms_consent = new WC_CC_SMS_Consent($this, $this->plugin_url, $this->plugin_path, $this->plugin_version);
        $this->email_consent = new WC_CC_Email_Consent($this, $this->plugin_url, $this->plugin_path, $this->plugin_version);

        // Example: Initialize Tracking
        $this->tracking = new WC_CC_Analytics_Tracking($this);

        // Initialize Event Manager only if Client ID is set
        $client_id = $this->get_option('cc_client_id');
        if (!empty($client_id)) {
            $this->event_manager = new Event_Manager($this); // EventManager constructor calls its setup_hooks
        } else {
            error_log("ConvertCart Info (Main Integration): Event Manager not initialized because Client ID is empty.");
        }
    }

    /**
     * Initialize hooks for components.
     */
    private function init_hooks(): void {
        // Initialize component hooks only if Client ID is set
        $client_id = $this->get_option('cc_client_id');
        if (empty($client_id)) {
            // Keep this important configuration log
            error_log("ConvertCart Info: Client ID missing - frontend tracking disabled.");
            return;
        }

        // Call init() on components that need to add hooks
        if (isset($this->admin) && is_admin()) { // Admin hooks only needed in admin area
            $this->admin->init();
        }
        if (isset($this->sms_consent)) {
            $this->sms_consent->init();
        }
        if (isset($this->email_consent)) {
            $this->email_consent->init();
        }
        if (isset($this->tracking)) {
            $this->tracking->init(); // Tracking hooks (wp_head, woocommerce_thankyou) added here
        }
        // EventManager constructor already calls its setup_hooks, so no ->init() needed here.

        // REST Controller hooks (if used)
        // if (isset($this->rest_controller)) {
        //     $this->rest_controller->register_routes(); // Assuming this adds the necessary hooks
        // }
    }

    /**
     * Initialize integration settings form fields.
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

    // *** Add getters if needed elsewhere, though direct passing is preferred ***
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
