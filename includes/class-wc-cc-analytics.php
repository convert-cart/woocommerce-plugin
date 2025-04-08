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
        error_log("ConvertCart Debug (Main Integration): CONSTRUCTOR START");

        // *** Store constants in properties ***
        // Ensure constants are defined globally before this runs
        $this->plugin_url = defined('CONVERTCART_ANALYTICS_URL') ? CONVERTCART_ANALYTICS_URL : '';
        $this->plugin_path = defined('CONVERTCART_ANALYTICS_PATH') ? CONVERTCART_ANALYTICS_PATH : '';
        $this->plugin_version = defined('CONVERTCART_ANALYTICS_VERSION') ? CONVERTCART_ANALYTICS_VERSION : '1.0.0'; // Default version

        if (empty($this->plugin_url) || empty($this->plugin_path)) {
             error_log("ConvertCart Debug (Main Integration): WARNING - CONVERTCART_ANALYTICS_URL or _PATH constants not defined!");
        } else {
             error_log("ConvertCart Debug (Main Integration): Stored Plugin URL: " . $this->plugin_url);
             error_log("ConvertCart Debug (Main Integration): Stored Plugin Path: " . $this->plugin_path);
        }

        global $woocommerce;
        
        $this->id = 'cc_analytics';
        $this->method_title = __('CC Analytics Settings', 'woocommerce_cc_analytics');
        $this->method_description = __('Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics');

        // Initialize components
        error_log("ConvertCart Debug (Main Integration): Constructor - Before init_components()");
        $this->init_components();
        error_log("ConvertCart Debug (Main Integration): Constructor - After init_components()");

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Hook into actions and filters
        error_log("ConvertCart Debug (Main Integration): Constructor - Before init_hooks()");
        $this->init_hooks();
        error_log("ConvertCart Debug (Main Integration): Constructor - After init_hooks()");

        // Log that the main integration class is constructed
        error_log("ConvertCart Debug (Main Integration): __construct finished for {$this->id}.");
    }

    /**
     * Initialize plugin components.
     */
    private function init_components(): void {
        error_log("ConvertCart Debug (Main Integration): INIT_COMPONENTS START");

        // Example: Initialize Admin
        if (is_admin()) {
            error_log("ConvertCart Debug (Main Integration): init_components - Initializing Admin...");
            $this->admin = new WC_CC_Admin($this);
            error_log("ConvertCart Debug (Main Integration): Admin initialized.");
        }

        // Example: Initialize Consent Modules
        error_log("ConvertCart Debug (Main Integration): init_components - Initializing SMS Consent...");
        $this->sms_consent = new WC_CC_SMS_Consent($this, $this->plugin_url, $this->plugin_path, $this->plugin_version);
        error_log("ConvertCart Debug (Main Integration): SMS Consent initialized.");

        error_log("ConvertCart Debug (Main Integration): init_components - Initializing Email Consent...");
        $this->email_consent = new WC_CC_Email_Consent($this, $this->plugin_url, $this->plugin_path, $this->plugin_version);
        error_log("ConvertCart Debug (Main Integration): Email Consent initialized.");

        // Example: Initialize Tracking
        error_log("ConvertCart Debug (Main Integration): init_components - Initializing Tracking...");
        $this->tracking = new WC_CC_Analytics_Tracking($this);
        error_log("ConvertCart Debug (Main Integration): Tracking initialized.");

        // *** Add Event Manager Initialization HERE ***
        $client_id = $this->get_option('cc_client_id');
        if (!empty($client_id)) {
            error_log("ConvertCart Debug (Main Integration): init_components - Client ID found. Initializing Event Manager...");
            $this->event_manager = new Event_Manager($this); // EventManager constructor calls its setup_hooks
            error_log("ConvertCart Debug (Main Integration): Event Manager initialized.");
        } else {
             error_log("ConvertCart Debug (Main Integration): init_components - Event Manager NOT initialized (No Client ID).");
        }

        // Example: Initialize REST Controller if you have one
        // error_log("ConvertCart Debug (Main Integration): init_components - Initializing REST Controller...");
        // $this->rest_controller = new WC_CC_REST_Controller($this);
        // error_log("ConvertCart Debug (Main Integration): REST Controller initialized.");

        error_log("ConvertCart Debug (Main Integration): init_components finished.");
    }

    /**
     * Initialize hooks.
     * This is where component hooks should be added, AFTER components are instantiated.
     */
    private function init_hooks(): void {
         error_log("ConvertCart Debug (Main Integration): INIT_HOOKS START");

        // Hook for saving settings
        add_action('woocommerce_update_options_integration_' . $this->id, [$this, 'process_admin_options']);

        // Initialize component hooks only if Client ID is set (applies to frontend tracking/events)
        $client_id = $this->get_option('cc_client_id');
        if (empty($client_id)) {
             error_log("ConvertCart Debug (Main Integration - init_hooks): Bailing component hooks because Client ID is empty.");
            return; // Don't add frontend hooks if tracking isn't configured
        }
         error_log("ConvertCart Debug (Main Integration - init_hooks): Client ID found, proceeding to init component hooks.");

        // Call init() on components that need to add hooks
        if (isset($this->admin) && is_admin()) { // Admin hooks only needed in admin area
             $this->admin->init();
             error_log("ConvertCart Debug (Main Integration - init_hooks): Called admin->init().");
        }
        if (isset($this->sms_consent)) {
            $this->sms_consent->init();
             error_log("ConvertCart Debug (Main Integration - init_hooks): Called sms_consent->init().");
        }
        if (isset($this->email_consent)) {
            $this->email_consent->init();
             error_log("ConvertCart Debug (Main Integration - init_hooks): Called email_consent->init().");
        }
        if (isset($this->tracking)) {
            $this->tracking->init(); // Tracking hooks (wp_head, wp_footer) added here
             error_log("ConvertCart Debug (Main Integration - init_hooks): Called tracking->init().");
        }
        // EventManager constructor already calls its setup_hooks, so no ->init() needed here.

        // REST Controller hooks (if used)
        // if (isset($this->rest_controller)) {
        //     $this->rest_controller->register_routes(); // Assuming this adds the necessary hooks
        //     error_log("ConvertCart Debug (Main Integration - init_hooks): Called rest_controller->register_routes().");
        // }

         error_log("ConvertCart Debug (Main Integration - init_hooks): Finished.");
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
