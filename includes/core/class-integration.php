<?php
/**
 * WooCommerce Integration for Convert Cart Analytics.
 *
 * This file contains the integration class Integration,
 * which handles the main plugin integration with WooCommerce.
 *
 * @package  ConvertCart\Analytics\Core
 * @category Integration
 */

namespace ConvertCart\Analytics\Core;

use WP_REST_Request;

class Integration extends \WC_Integration {

	/**
	 * Client ID.
	 *
	 * @var string
	 */
	public $cc_client_id;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;
		$this->id                 = 'cc_analytics';
		$this->method_title       = __( 'CC Analytics Settings', 'woocommerce_cc_analytics' );
		$this->method_description = __( 'Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->cc_client_id = $this->get_option( 'cc_client_id' );
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( ! isset( $this->cc_client_id ) || '' === $this->cc_client_id ) {
			return;
		}

		// Load dependencies
		$this->load_dependencies();
		
		// Setup hooks
		$this->setup_hooks();
	}

	/**
	 * Load dependencies.
	 */
	private function load_dependencies() {
		// Include required files
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'events/class-event-manager.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'events/class-data-handler.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'consent/class-sms-consent.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'consent/class-email-consent.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-admin.php';
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Actions added below.
		add_action( 'wp_head', array( $this, 'cc_init' ) );
		
		// REST API filters
		add_filter(
			'woocommerce_rest_product_object_query',
			function ( array $args, \WP_REST_Request $request ) {
				$modified_after = $request->get_param( 'modified_after' );

				if ( ! $modified_after ) {
					return $args;
				}

				$args['date_query'][0]['column'] = 'post_modified';
				$args['date_query'][0]['after']  = $modified_after;

				return $args;
			},
			10,
			2
		);

		add_filter(
			'woocommerce_rest_orders_prepare_object_query',
			function ( array $args, \WP_REST_Request $request ) {
				$modified_after = $request->get_param( 'modified_after' );
				if ( ! $modified_after ) {
					return $args;
				}

				$args['date_query'][0]['column'] = 'post_modified';
				$args['date_query'][0]['after']  = $modified_after;
				return $args;
			},
			10,
			2
		);

		add_filter( 'woocommerce_rest_customer_query', array( $this, 'addUpdatedSinceFilterToRESTApi' ), 10, 2 );
		add_filter( 'rest_product_collection_params', array( $this, 'maximum_api_filter' ) );
		
		// REST API endpoints
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'wc/v3',
					'cc-version',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'getVersionList' ),
						'permission_callback' => array( $this, 'permissionCallback' ),
					)
				);
			}
		);
		
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'wc/v3', // Namespace
					'cc-plugin-info', // Route
					array(
						'methods'             => 'GET', // HTTP method
						'callback'            => array( $this, 'get_plugin_info' ), // Callback function
						'permission_callback' => array( $this, 'permissionCallback' ), // Permission check
					)
				);
			}
		);
		
		// Category webhooks
		add_action(
			'create_product_cat',
			function ( $term_id, $args ) {
				do_action(
					'woocommerce_admin_product_category_webhook_handler',
					array(
						'term'     => $term_id,
						'topic'    => 'category.created',
						'category' => $args,
					)
				);
			},
			10,
			3
		);
		
		add_action(
			'edit_product_cat',
			function ( $term_id, $args ) {
				do_action(
					'woocommerce_admin_product_category_webhook_handler',
					array(
						'term'     => $term_id,
						'topic'    => 'category.updated',
						'category' => $args,
					)
				);
			},
			10,
			3
		);
		
		add_action(
			'delete_product_cat',
			function ( $term_id, $tt_id = '' ) {
				do_action(
					'woocommerce_admin_product_category_webhook_handler',
					array(
						'term'  => $term_id,
						'topic' => 'category.deleted',
					)
				);
			},
			10,
			3
		);
		
		add_action( 'woocommerce_admin_product_category_webhook_handler', array( $this, 'sendCategoryRelatedNotification' ), 10, 1 );
	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'cc_client_id'       => array(
				'title'       => __( 'Client ID / Domain Id', 'woocommerce_cc_analytics' ),
				'type'        => 'text',
				'description' => __( 'Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'debug_mode'         => array(
				'title'       => __( 'Enable Debug Mode', 'woocommerce_cc_analytics' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Debugging for Meta Info', 'woocommerce_cc_analytics' ),
				'default'     => 'no',
				'description' => __( 'If enabled, WooCommerce & WordPress plugin versions will be included in tracking metadata.', 'woocommerce_cc_analytics' ),
			),
			'enable_sms_consent' => array(
				'title'       => __( 'Enable SMS Consent', 'woocommerce_cc_analytics' ),
				'type'        => 'select',  // Use a dropdown instead of a checkbox
				'options'     => array(
					'disabled' => __( 'Disabled', 'woocommerce_cc_analytics' ),
					'draft'    => __( 'Draft Mode (Editable, not displayed on frontend)', 'woocommerce_cc_analytics' ),
					'live'     => __( 'Live Mode (Editable, displayed on frontend)', 'woocommerce_cc_analytics' ),
				),
				'description' => __( 'Select the mode for SMS Consent: Draft to edit without injecting code, Live to edit with code injection.', 'woocommerce_cc_analytics' ),
				'default'     => 'disabled',
			),
			'enable_email_consent' => array(
				'title'       => __( 'Enable Email Consent', 'woocommerce_cc_analytics' ),
				'type'        => 'select',
				'options'     => array(
					'disabled' => __( 'Disabled', 'woocommerce_cc_analytics' ),
					'draft'    => __( 'Draft Mode (Editable, not displayed on frontend)', 'woocommerce_cc_analytics' ),
					'live'     => __( 'Live Mode (Editable, displayed on frontend)', 'woocommerce_cc_analytics' ),
				),
				'description' => __( 'Select the mode for Email Consent: Draft to edit without injecting code, Live to edit with code injection.', 'woocommerce_cc_analytics' ),
				'default'     => 'disabled',
			),
		);
	}

	/**
	 * Initialize the CC Analytics script.
	 */
	public function cc_init() {
		if ( ! $this->cc_client_id ) {
			return;
		}

		echo "<script data-cfasync='false'>
			(function(c, o, n, v, e, r, t, s) {
				s = c.fetch ? 'f' : '';
				c.ccartObj = e;
				c[e] = c[e] || function() {
					(c[e].q = c[e].q || []).push(arguments)
				};
				c[e].t = Date.now();
				r = o.createElement(n);
				r.async = 1;
				r.src = v + s + '.js';
				t = o.getElementsByTagName(n)[0];
				t.parentNode.insertBefore(r, t);
			})(window, document, 'script', '//cdn.convertcart.com/" . $this->cc_client_id . "', 'ccart');
		</script>";
	}

	/**
	 * Function to add meta information to cc tracking script
	 */
	public function getMetaInfo() {
		global $wp_version;
		global $woocommerce;
		global $current_user;

		$meta_data = array();

		if ( is_object( $current_user ) ) {
			if ( isset( $current_user->user_email ) ) {
				$meta_data['customer_status'] = 'logged_in';
				$meta_data['customer_email']  = $current_user->user_email;
			} else {
				$meta_data['customer_status'] = 'guest';
			}
		}

		$meta_data['date']     = gmdate( 'Y-m-d H:i:s' );
		$meta_data['currency'] = get_woocommerce_currency();

		$debug_mode = $this->get_option( 'debug_mode' );
		if ( 'yes' === $debug_mode ) {
			$meta_data['pv'] = is_object( $woocommerce ) ? $woocommerce->version : null;
			$meta_data['wv'] = $wp_version;
		}
		$meta_data['pgv'] = defined( 'CC_PLUGIN_VERSION' ) ? CC_PLUGIN_VERSION : null;

		return $meta_data;
	}

	/**
	 * Sends notification for category-related events.
	 *
	 * @param array $args Event arguments
	 * @return void
	 */
	public function sendCategoryRelatedNotification( $args ) {
		global $wpdb;

		$id         = isset( $args['term'] ) ? $args['term'] : 0;
		$topic      = isset( $args['topic'] ) ? $args['topic'] : null;
		$body       = $args;
		$body['id'] = $id;

		$data              = explode( '.', $topic );
		$original_resource = 'product';
		$modified_resource = $data[0];
		$event             = $data[1];
		$sql               = "SELECT webhook_id, `name`, delivery_url FROM {$wpdb->prefix}wc_webhooks WHERE topic='{$original_resource}.{$event}' AND `name` LIKE 'convertcart%' AND delivery_url LIKE '%data-warehouse%'";
		$cache_key         = 'cc_plugin_info_' . md5( $sql );

		$results = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key ) : false;

		if ( false === $results ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
					'cc_%'
				)
			);
			if ( function_exists( 'wp_cache_set' ) ) {
				wp_cache_set( $cache_key, $results );
			}
		}

		foreach ( $results as $result ) {
			$model_delivery_url = $result['delivery_url'];
			$target_url         = preg_replace( "/{$original_resource}/", "{$modified_resource}", $model_delivery_url );

			$opts = array(
				'body'        => wp_json_encode( $body ),
				'timeout'     => '120',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'cookies'     => array(),
				'url'         => $target_url,
			);

			$response = wp_remote_post( $target_url, $opts );
		}
	}

	/**
	 * Set maximum API filter
	 *
	 * @param array $query_params Query parameters
	 * @return array Modified query parameters
	 */
	public function maximum_api_filter( $query_params ) {
		$query_params['per_page']['maximum'] = 5000;
		return $query_params;
	}

	/**
	 * Function get version list
	 *
	 * @param WP_REST_Request $request Request object
	 * @return array Version information
	 */
	public function getVersionList( $request ) {
		global $wp_version;
		global $woocommerce;
		$info               = array();
		$info['wp_version'] = $wp_version;
		$info['wc_version'] = is_object( $woocommerce ) ? $woocommerce->version : null;
		return $info;
	}

	/**
	 * Add updated since filter to REST API
	 *
	 * @param array $prepared_args Prepared arguments
	 * @param WP_REST_Request $request Request object
	 * @return array Modified arguments
	 */
	public function addUpdatedSinceFilterToRESTApi( $prepared_args, $request ) {
		if ( $request->get_param( 'modified_after' ) ) {
			$prepared_args['meta_query'] = array(
				array(
					'key'     => 'last_update',
					'value'   => (int) strtotime( $request->get_param( 'modified_after' ) ),
					'compare' => '>=',
				),
			);
		}
		return $prepared_args;
	}

	/**
	 * Function authentication of our custom endpoints
	 *
	 * @param WP_REST_Request $request Request object
	 * @return bool Whether the request is authorized
	 */
	public function permissionCallback( $request ) {
		global $wpdb;
		$queryparams = $request->get_params();
		$key         = $wpdb->get_row(
			$wpdb->prepare(
				"
			SELECT consumer_secret
			FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE consumer_secret = %s
		",
				$queryparams['consumer_secret']
			),
			ARRAY_A
		);

		if ( $key['consumer_secret'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Gets plugin information.
	 *
	 * @return array Plugin information.
	 */
	public function get_plugin_info() {
		global $wpdb;
		global $wp_version;
		global $woocommerce;
		$info               = array();
		$info['wp_version'] = $wp_version;
		$info['wc_plugin_version'] = is_object( $woocommerce ) ? $woocommerce->version : null;
		$info['cc_plugin_version'] = defined( 'CC_PLUGIN_VERSION' ) ? CC_PLUGIN_VERSION : null;  // Add plugin version
		$webhooks           = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT webhook_id, `name`, delivery_url, status FROM {$wpdb->prefix}wc_webhooks WHERE `name` LIKE %s AND delivery_url LIKE %s",
				'convertcart%',
				'%data-warehouse%'
			)
		);

		$info['webhooks'] = $webhooks;
		return $info;
	}
} 