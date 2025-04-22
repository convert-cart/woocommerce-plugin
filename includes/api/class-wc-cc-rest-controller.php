<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\API;

use ConvertCart\Analytics\Abstract\WC_CC_Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for Convert Cart Analytics.
 */
class WC_CC_REST_Controller extends WC_CC_Base {
    /**
     * Initialize REST API functionality.
     */
    public function init(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        
        // Add filters for WooCommerce REST API
        add_filter('woocommerce_rest_product_object_query', [$this, 'filter_products_by_modified_date'], 10, 2);
        add_filter('woocommerce_rest_orders_prepare_object_query', [$this, 'filter_orders_by_modified_date'], 10, 2);
        add_filter('woocommerce_rest_customer_query', [$this, 'filter_customers_by_modified_date'], 10, 2);
        add_filter('rest_product_collection_params', [$this, 'add_modified_after_param']);
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void {
        register_rest_route('wc/v3', 'cc-version', [
            'methods' => 'GET',
            'callback' => [$this, 'get_version_info'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route('wc/v3', 'cc-plugin-info', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plugin_info'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Check API permissions.
     *
     * @return bool|WP_Error
     */
    public function check_permission() {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('You do not have permissions to view this data.', 'woocommerce_cc_analytics'),
                ['status' => rest_authorization_required_code()]
            );
        }
        return true;
    }

    /**
     * Get version information.
     *
     * @return WP_REST_Response
     */
    public function get_version_info(): WP_REST_Response {
        return new WP_REST_Response([
            'wordpress' => get_bloginfo('version'),
            'woocommerce' => WC()->version,
            'php' => PHP_VERSION,
            'convertcart' => CONVERTCART_VERSION,
        ]);
    }

    /**
     * Get plugin information.
     *
     * @return WP_REST_Response
     */
    public function get_plugin_info(): WP_REST_Response {
        return new WP_REST_Response([
            'client_id' => $this->get_option('cc_client_id'),
            'debug_mode' => $this->get_option('debug_mode'),
            'sms_consent' => $this->get_option('enable_sms_consent'),
            'email_consent' => $this->get_option('enable_email_consent'),
        ]);
    }

    /**
     * Filter products by modified date.
     *
     * @param array $args Query arguments
     * @param WP_REST_Request $request Request object
     * @return array
     */
    public function filter_products_by_modified_date(array $args, WP_REST_Request $request): array {
        $modified_after = $request->get_param('modified_after');
        if (!$modified_after) {
            return $args;
        }

        $args['date_query'][0] = [
            'column' => 'post_modified',
            'after' => $modified_after,
        ];

        return $args;
    }

    /**
     * Filter orders by modified date.
     *
     * @param array $args Query arguments
     * @param WP_REST_Request $request Request object
     * @return array
     */
    public function filter_orders_by_modified_date(array $args, WP_REST_Request $request): array {
        $modified_after = $request->get_param('modified_after');
        if (!$modified_after) {
            return $args;
        }

        $args['date_query'][0] = [
            'column' => 'post_modified',
            'after' => $modified_after,
        ];

        return $args;
    }

    /**
     * Filter customers by modified date.
     *
     * @param array $args Query arguments
     * @param WP_REST_Request $request Request object
     * @return array
     */
    public function filter_customers_by_modified_date(array $args, WP_REST_Request $request): array {
        $modified_after = $request->get_param('modified_after');
        if (!$modified_after) {
            return $args;
        }

        $args['date_query'][0] = [
            'column' => 'user_modified',
            'after' => $modified_after,
        ];

        return $args;
    }

    /**
     * Add modified_after parameter to product collection parameters.
     *
     * @param array $params Collection parameters
     * @return array
     */
    public function add_modified_after_param(array $params): array {
        $params['modified_after'] = [
            'description' => __('Limit response to resources modified after a given ISO8601 compliant date.', 'woocommerce_cc_analytics'),
            'type' => 'string',
            'format' => 'date-time',
        ];

        return $params;
    }
} 