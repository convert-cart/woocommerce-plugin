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

		// Actions.
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		// Initialize the plugin components.
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		// Include required files.
		require_once plugin_dir_path( __DIR__ ) . 'events/class-event-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'events/class-data-handler.php';
		require_once plugin_dir_path( __DIR__ ) . 'consent/class-sms-consent.php';
		require_once plugin_dir_path( __DIR__ ) . 'consent/class-email-consent.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-admin.php';
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		// Initialize event manager.
		$event_manager = new \ConvertCart\Analytics\Events\Event_Manager( $this );
		$data_handler  = new \ConvertCart\Analytics\Events\Data_Handler( $this );

		// Initialize consent managers.
		$sms_consent   = new \ConvertCart\Analytics\Consent\SMS_Consent( $this );
		$email_consent = new \ConvertCart\Analytics\Consent\Email_Consent( $this );

		// Initialize admin.
		$admin = new \ConvertCart\Analytics\Admin\Admin( $this );
	}

	/**
	 * Get metadata for tracking.
	 *
	 * @return array Metadata.
	 */
	public function getMetaInfo() {
		global $wp_version;
		global $woocommerce;

		$meta = array(
			'platform'         => 'wordpress',
			'platform_version' => $wp_version,
			'wc_version'       => $woocommerce->version,
			'plugin_version'   => CC_PLUGIN_VERSION,
		);

		// Add debug info if enabled.
		if ( 'yes' === $this->get_option( 'debug_mode' ) ) {
			// Add additional debug information.
			$meta['debug_mode']         = true;
			$meta['php_version']        = PHP_VERSION;
			$meta['memory_limit']       = ini_get( 'memory_limit' );
			$meta['max_execution_time'] = ini_get( 'max_execution_time' );
			$meta['timezone']           = wp_timezone_string();
		}

		return $meta;
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'cc_client_id'         => array(
				'title'       => __( 'Client ID / Domain Id', 'woocommerce_cc_analytics' ),
				'type'        => 'text',
				'description' => __( 'Enter your Convert Cart client ID or domain ID.', 'woocommerce_cc_analytics' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'debug_mode'           => array(
				'title'       => __( 'Enable Debug Mode', 'woocommerce_cc_analytics' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, WooCommerce & WordPress plugin versions will be included in tracking metadata.', 'woocommerce_cc_analytics' ),
			),
			'enable_sms_consent'   => array(
				'title'       => __( 'Enable SMS Consent', 'woocommerce_cc_analytics' ),
				'type'        => 'select',  // Use a dropdown instead of a checkbox.
				'description' => __( 'Enable SMS consent collection at checkout, registration, and account pages.', 'woocommerce_cc_analytics' ),
				'default'     => 'disabled',
				'options'     => array(
					'disabled' => __( 'Disabled', 'woocommerce_cc_analytics' ),
					'draft'    => __( 'Draft Mode (Admin Only)', 'woocommerce_cc_analytics' ),
					'live'     => __( 'Live Mode', 'woocommerce_cc_analytics' ),
				),
			),
			'enable_email_consent' => array(
				'title'       => __( 'Enable Email Consent', 'woocommerce_cc_analytics' ),
				'type'        => 'select',  // Use a dropdown instead of a checkbox.
				'description' => __( 'Enable email consent collection at checkout, registration, and account pages.', 'woocommerce_cc_analytics' ),
				'default'     => 'disabled',
				'options'     => array(
					'disabled' => __( 'Disabled', 'woocommerce_cc_analytics' ),
					'draft'    => __( 'Draft Mode (Admin Only)', 'woocommerce_cc_analytics' ),
					'live'     => __( 'Live Mode', 'woocommerce_cc_analytics' ),
				),
			),
		);
	}

	/**
	 * Output the settings form.
	 */
	public function admin_options() {
		?>
		<h2><?php esc_html_e( 'Convert Cart Analytics Settings', 'woocommerce_cc_analytics' ); ?></h2>
		<p><?php esc_html_e( 'Configure your Convert Cart Analytics integration settings below.', 'woocommerce_cc_analytics' ); ?></p>
		<table class="form-table">
			<?php echo wp_kses_post( $this->generate_settings_html( null, false ) ); ?>
		</table>
		<?php
		echo wp_kses_post( '<p>' . __( 'After saving your Client ID, you can configure SMS and Email consent settings in their respective tabs.', 'woocommerce_cc_analytics' ) . '</p>' );
	}

	/**
	 * Get customer data by ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array Customer data.
	 */
	public function get_customer_data( $customer_id ) {
		$customer_data = array();
		$user          = get_userdata( $customer_id );

		if ( $user ) {
			$customer_data['id']            = $customer_id;
			$customer_data['email']         = $user->user_email;
			$customer_data['first_name']    = $user->first_name;
			$customer_data['last_name']     = $user->last_name;
			$customer_data['phone']         = get_user_meta( $customer_id, 'billing_phone', true );
			$customer_data['sms_consent']   = get_user_meta( $customer_id, 'sms_consent', true );
			$customer_data['email_consent'] = get_user_meta( $customer_id, 'email_consent', true );
		}

		return $customer_data;
	}

	/**
	 * Get customer data by email.
	 *
	 * @param string $email Customer email.
	 * @return array Customer data.
	 */
	public function get_customer_data_by_email( $email ) {
		$customer_data = array();
		$user          = get_user_by( 'email', $email );

		if ( $user ) {
			$customer_data = $this->get_customer_data( $user->ID );
		} else {
			// Try to find customer from orders.
			$orders = wc_get_orders(
				array(
					'billing_email' => $email,
					'limit'         => 1,
				)
			);

			if ( ! empty( $orders ) ) {
				$order                          = reset( $orders );
				$customer_data['email']         = $email;
				$customer_data['phone']         = $order->get_billing_phone();
				$customer_data['sms_consent']   = $order->get_meta( 'sms_consent' );
				$customer_data['email_consent'] = $order->get_meta( 'email_consent' );
			}
		}

		return $customer_data;
	}

	/**
	 * Get customer consent status.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $consent_type Consent type (sms or email).
	 * @return string Consent status.
	 */
	public function get_customer_consent( $customer_id, $consent_type ) {
		return get_user_meta( $customer_id, $consent_type . '_consent', true );
	}

	/**
	 * Get customer consent status by email.
	 *
	 * @param string $email Customer email.
	 * @param string $consent_type Consent type (sms or email).
	 * @return string Consent status.
	 */
	public function get_customer_consent_by_email( $email, $consent_type ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return $this->get_customer_consent( $user->ID, $consent_type );
		}

		// Try to find consent from orders.
		// Note: Using meta_query is necessary here for email lookup.
		$orders = get_posts(
			array(
				'post_type'   => 'shop_order',
				'post_status' => array_keys( wc_get_order_statuses() ),
				'meta_query'  => array(
					array(
						'key'   => '_billing_email',
						'value' => $email,
					),
				),
				'numberposts' => 1,
			)
		);

		if ( ! empty( $orders ) ) {
			$order = wc_get_order( $orders[0]->ID );
			return $order->get_meta( $consent_type . '_consent' );
		}

		return '';
	}

	/**
	 * Validate API key.
	 *
	 * @param array $queryparams Query parameters.
	 * @return bool Whether the API key is valid.
	 */
	public function validate_api_key( $queryparams ) {
		global $wpdb;

		if ( ! isset( $queryparams['consumer_secret'] ) ) {
			return false;
		}

		// Check if the API key exists in the database.
		// Direct DB call is necessary for API key validation.
		$key = $wpdb->get_row(
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

		if ( isset( $key['consumer_secret'] ) ) {
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

		$info                      = array();
		$info['wp_version']        = $wp_version;
		$info['wc_plugin_version'] = is_object( $woocommerce ) ? $woocommerce->version : null;
		$info['cc_plugin_version'] = defined( 'CC_PLUGIN_VERSION' ) ? CC_PLUGIN_VERSION : null;  // Add plugin version.

		// Get webhooks that match our criteria.
		// Direct DB call is necessary for webhook lookup with LIKE operators.
		$webhooks = $wpdb->get_results(
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