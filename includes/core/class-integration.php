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

defined( 'ABSPATH' ) || exit;

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
		// Make sure WooCommerce is active
		if ( ! class_exists( 'WC_Integration' ) ) {
			return;
		}

		global $woocommerce;
		$this->id                 = 'cc_analytics';
		$this->method_title       = __( 'Convert Cart Analytics', 'woocommerce_cc_analytics' );
		$this->method_description = __( 'Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->cc_client_id = $this->get_option( 'cc_client_id' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_items' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'parent_file', array( $this, 'highlight_menu_item' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu_item' ) );

		// Initialize the plugin components.
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( __DIR__ ) . 'consent/class-base-consent.php';
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
		$event_manager = new \ConvertCart\Analytics\Events\Event_Manager( $this );
		$data_handler  = new \ConvertCart\Analytics\Events\Data_Handler( $this );
		$sms_consent   = new \ConvertCart\Analytics\Consent\SMS_Consent( $this );
		$email_consent = new \ConvertCart\Analytics\Consent\Email_Consent( $this );
		$admin         = new \ConvertCart\Analytics\Admin\Admin( $this );
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

		if ( 'yes' === $this->get_option( 'debug_mode' ) ) {
			$meta['debug_mode']         = true;
			$meta['php_version']        = PHP_VERSION;
			$meta['memory_limit']       = ini_get( 'memory_limit' );
			$meta['max_execution_time'] = ini_get( 'max_execution_time' );
			$meta['timezone']           = wp_timezone_string();
		}

		return $meta;
	}

	/**
	 * Initialize integration form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'cc_client_id' => array(
				'title'       => __( 'Client ID / Domain Id', 'woocommerce_cc_analytics' ),
				'type'        => 'text',
				'description' => __( 'Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'debug_mode' => array(
				'title'       => __( 'Enable Debug Mode', 'woocommerce_cc_analytics' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Debugging for Meta Info', 'woocommerce_cc_analytics' ),
				'default'     => 'no',
				'description' => __( 'If enabled, WooCommerce & WordPress plugin versions will be included in tracking metadata.', 'woocommerce_cc_analytics' ),
			),
			'enable_sms_consent' => array(
				'title'       => __( 'Enable SMS Consent', 'woocommerce_cc_analytics' ),
				'type'        => 'select',
				'options'     => array(
					'disabled' => __( 'Disabled', 'woocommerce_cc_analytics' ),
					'draft'   => __( 'Draft Mode (Editable, not displayed on frontend)', 'woocommerce_cc_analytics' ),
					'live'    => __( 'Live Mode (Editable, displayed on frontend)', 'woocommerce_cc_analytics' ),
				),
				'description' => __( 'Select the mode for SMS Consent: Draft to edit without injecting code, Live to edit with code injection.', 'woocommerce_cc_analytics' ),
				'default'     => 'disabled',
			),
			'enable_email_consent' => array(
				'title'       => __( 'Enable Email Consent', 'woocommerce_cc_analytics' ),
				'type'        => 'select',
				'options'     => array(
					'disabled' => __( 'Disabled', 'woocommerce_cc_analytics' ),
					'draft'   => __( 'Draft Mode (Editable, not displayed on frontend)', 'woocommerce_cc_analytics' ),
					'live'    => __( 'Live Mode (Editable, displayed on frontend)', 'woocommerce_cc_analytics' ),
				),
				'description' => __( 'Select the mode for Email Consent: Draft to edit without injecting code, Live to edit with code injection.', 'woocommerce_cc_analytics' ),
				'default'     => 'disabled',
			),
		);
	}

	/**
	 * Output the settings form.
	 */
	public function admin_options() {
		echo '<h2>' . esc_html( $this->method_title ) . '</h2>';
		echo wp_kses_post( wpautop( $this->method_description ) );
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
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
	 * @param string $email Email address.
	 * @return array Customer data.
	 */
	public function get_customer_data_by_email( $email ) {
		$customer_data = array();
		$user          = get_user_by( 'email', $email );

		if ( $user ) {
			$customer_data['email']         = $email;
			$customer_data['phone']         = get_user_meta( $user->ID, 'billing_phone', true );
			$customer_data['sms_consent']   = get_user_meta( $user->ID, 'sms_consent', true );
			$customer_data['email_consent'] = get_user_meta( $user->ID, 'email_consent', true );
		} else {
			// Try to find guest order data.
			$orders = wc_get_orders(
				array(
					'billing_email' => $email,
					'limit'         => 1, // Get the most recent order for this email.
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
	 * Get consent status for a given email and consent type.
	 * Checks user meta first, then falls back to the latest order meta.
	 *
	 * @param string $email Email address.
	 * @param string $consent_type Consent type ('sms' or 'email').
	 * @return string Consent status ('yes', 'no', or '').
	 */
	public function get_consent_status( $email, $consent_type ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$consent = get_user_meta( $user->ID, $consent_type . '_consent', true );
			if ( ! empty( $consent ) ) {
				return $consent;
			}
		}

		// Fallback: Check the latest order for this email.
		$orders = wc_get_orders(
			array(
				'limit'         => 1, // We only need the latest order's consent status ideally.
				'status'        => array_keys( wc_get_order_statuses() ),
				'customer'      => $email, // wc_get_orders uses 'customer' for email lookup.
				'type'          => 'shop_order',
				'orderby'       => 'date',
				'order'         => 'DESC',
			)
		);


		if ( ! empty( $orders ) ) {
			$order = $orders[0];
			return $order->get_meta( $consent_type . '_consent', true ); // Get single value.
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
		$info['cc_plugin_version'] = defined( 'CC_PLUGIN_VERSION' ) ? CC_PLUGIN_VERSION : null;

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

	/**
	 * Add menu items.
	 */
	public function add_menu_items() {
		$parent_slug = 'convert-cart';

		// Add top-level menu
		add_menu_page(
			__( 'Convert Cart Analytics', 'woocommerce_cc_analytics' ),
			__( 'Convert Cart', 'woocommerce_cc_analytics' ),
			'manage_woocommerce',
			$parent_slug,
			array( $this, 'render_settings_page' ),
			'none'  // We'll use custom CSS for the icon
		);

		// Add custom menu icon CSS
		add_action('admin_head', array($this, 'add_menu_icon_styles'));

		// Add Settings submenu
		add_submenu_page(
			$parent_slug,
			__( 'Convert Cart Settings', 'woocommerce_cc_analytics' ),
			__( 'Domain Settings', 'woocommerce_cc_analytics' ),
			'manage_woocommerce',
			$parent_slug,
			array( $this, 'render_settings_page' )
		);

		// SMS Consent submenu
		add_submenu_page(
			$parent_slug,
			__( 'SMS Consent', 'woocommerce_cc_analytics' ),
			__( 'SMS Consent', 'woocommerce_cc_analytics' ),
			'manage_woocommerce',
			'convert-cart-sms-consent',
			array( $this, 'render_sms_consent_page' )
		);

		// Email Consent submenu
		add_submenu_page(
			$parent_slug,
			__( 'Email Consent', 'woocommerce_cc_analytics' ),
			__( 'Email Consent', 'woocommerce_cc_analytics' ),
			'manage_woocommerce',
			'convert-cart-email-consent',
			array( $this, 'render_email_consent_page' )
		);
	}

	/**
	 * Render the main settings page
	 */
	public function render_settings_page() {
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=integration&section=' . $this->id ) );
		exit;
	}

	/**
	 * Highlight the correct parent menu item
	 *
	 * @param string $parent_file The parent file.
	 * @return string
	 */
	public function highlight_menu_item( $parent_file ) {
		global $current_screen;
		$plugin_page = isset($_GET['page']) ? $_GET['page'] : '';
		
		if (in_array($plugin_page, array(
			'convert-cart',
			'convert-cart-sms-consent',
			'convert-cart-email-consent'
		))) {
			return 'convert-cart';
		}
		
		if ( isset( $_GET['page'] ) && 
			 $_GET['page'] === 'wc-settings' && 
			 isset( $_GET['tab'] ) && 
			 $_GET['tab'] === 'integration' && 
			 isset( $_GET['section'] ) && 
			 $_GET['section'] === $this->id 
		) {
			return 'convert-cart';
		}
		
		return $parent_file;
	}

	/**
	 * Highlight the correct submenu item
	 *
	 * @param string $submenu_file The submenu file.
	 * @return string
	 */
	public function highlight_submenu_item( $submenu_file ) {
		$plugin_page = isset($_GET['page']) ? $_GET['page'] : '';
		
		if ( isset( $_GET['page'] ) && 
			 $_GET['page'] === 'wc-settings' && 
			 isset( $_GET['tab'] ) && 
			 $_GET['tab'] === 'integration' && 
			 isset( $_GET['section'] ) && 
			 $_GET['section'] === $this->id 
		) {
			return 'convert-cart';
		}
		
		return $submenu_file;
	}

	/**
	 * Render SMS consent settings page
	 */
	public function render_sms_consent_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woocommerce_cc_analytics' ) );
		}

		$consent_mode = $this->get_option( 'enable_sms_consent', 'disabled' );
		$checkout_html = get_option( 'cc_sms_consent_checkout_html', $this->get_default_sms_consent_html() );
		$registration_html = get_option( 'cc_sms_consent_registration_html', $this->get_default_sms_consent_html() );
		$account_html = get_option( 'cc_sms_consent_account_html', $this->get_default_sms_consent_html() );

		include plugin_dir_path( __DIR__ ) . 'admin/views/html-consent-settings.php';
	}

	/**
	 * Render Email consent settings page
	 */
	public function render_email_consent_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woocommerce_cc_analytics' ) );
		}

		$consent_mode = $this->get_option( 'enable_email_consent', 'disabled' );
		$checkout_html = get_option( 'cc_email_consent_checkout_html', $this->get_default_email_consent_html() );
		$registration_html = get_option( 'cc_email_consent_registration_html', $this->get_default_email_consent_html() );
		$account_html = get_option( 'cc_email_consent_account_html', $this->get_default_email_consent_html() );

		include plugin_dir_path( __DIR__ ) . 'admin/views/html-consent-settings.php';
	}

	/**
	 * Get default SMS consent HTML
	 */
	private function get_default_sms_consent_html() {
		return '<div class="sms-consent-checkbox">
			<label for="sms_consent">
				<input type="checkbox" name="sms_consent" id="sms_consent" />
				<span>' . esc_html__( 'I consent to receive SMS communications.', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
	}

	/**
	 * Get default Email consent HTML
	 */
	private function get_default_email_consent_html() {
		return '<div class="email-consent-checkbox">
			<label for="email_consent">
				<input type="checkbox" name="email_consent" id="email_consent" />
				<span>' . esc_html__( 'I consent to receive email communications.', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
	}

	/**
	 * Add custom menu icon styles
	 */
	public function add_menu_icon_styles() {
		?>
		<style>
			#adminmenu .toplevel_page_convert-cart .wp-menu-image {
				background-image: url('<?php echo esc_url(CC_PLUGIN_URL . 'assets/images/icon.svg'); ?>');
				background-repeat: no-repeat;
				background-position: center center;
				background-size: 20px auto;
			}
			#adminmenu .toplevel_page_convert-cart .wp-menu-image:before {
				content: none;
			}
			.code-editor-wrapper {
				border: 1px solid #ddd;
				margin-bottom: 20px;
			}
			.code-editor-wrapper .CodeMirror {
				height: auto;
				min-height: 150px;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();
		
		// Only load on our consent pages
		if ( strpos( $screen->id, 'convert-cart-sms-consent' ) !== false || 
			 strpos( $screen->id, 'convert-cart-email-consent' ) !== false ) {
			
			// Check if code editor is available and user hasn't disabled it
			$settings = wp_enqueue_code_editor(array(
				'type' => 'text/html',
				'codemirror' => array(
					'lineNumbers' => true,
					'lineWrapping' => true,
					'styleActiveLine' => true,
					'matchBrackets' => true,
					'autoCloseBrackets' => true,
					'autoCloseTags' => true,
					'mode' => 'htmlmixed',
					'tabSize' => 4,
					'indentUnit' => 4,
					'indentWithTabs' => true,
				),
			));

			// Bail if user disabled CodeMirror
			if ( false === $settings ) {
				return;
			}
			
			wp_enqueue_script('jquery');
			
			// Load the beautifier library
			wp_enqueue_script(
				'js-beautify',
				'https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.9/beautify-html.min.js',
				array(),
				'1.14.9',
				true
			);

			wp_add_inline_script('code-editor', '
				jQuery(function($) {
					var editorSettings = ' . wp_json_encode( $settings ) . ';
					var beautifyOptions = {
						indent_size: 4,
						indent_with_tabs: true,
						wrap_line_length: 0,
						preserve_newlines: false,
						max_preserve_newlines: 2,
						indent_inner_html: true,
						wrap_attributes: "auto",
						wrap_attributes_indent_size: 4,
						end_with_newline: false,
						indent_empty_lines: false,
						unformatted: ["code", "pre"],
						content_unformatted: ["pre"],
						extra_liners: [],
						indent_scripts: "normal"
					};

					editorSettings.codemirror.extraKeys = {
						"Tab": function(cm) {
							if (cm.somethingSelected()) {
								cm.indentSelection("add");
							} else {
								cm.replaceSelection(cm.getOption("indentWithTabs")? "\t":
									Array(cm.getOption("indentUnit") + 1).join(" "), "end", "+input");
							}
						},
						"Shift-Tab": "indentLess"
					};

					$(".consent-html-editor").each(function(i, textarea) {
						// Prevent default tab behavior
						$(textarea).on("keydown", function(e) {
							if (e.keyCode === 9) { // tab key
								e.preventDefault();
							}
						});

						var editor = wp.codeEditor.initialize(textarea, editorSettings);
						
						// Set tab handling message
						var message = $("<p>", {
							text: "Use Tab key for indentation. Shift+Tab to decrease indent.",
							class: "description",
							css: { "margin-top": "5px", "font-style": "italic" }
						});
						$(textarea).closest(".code-editor-wrapper").prepend(message);

						// Format the initial content
						var initialContent = editor.codemirror.getValue();
						if (window.html_beautify && initialContent.trim()) {
							var formattedContent = window.html_beautify(initialContent, beautifyOptions);
							editor.codemirror.setValue(formattedContent);
						}

						editor.codemirror.on("change", function() {
							textarea.value = editor.codemirror.getValue();
						});

						// Add format button
						var formatButton = $("<button>", {
							text: "Format HTML",
							class: "button format-html-button",
							css: { "margin-top": "5px" }
						});

						formatButton.on("click", function(e) {
							e.preventDefault();
							var content = editor.codemirror.getValue();
							if (window.html_beautify && content.trim()) {
								var formatted = window.html_beautify(content, beautifyOptions);
								editor.codemirror.setValue(formatted);
							}
						});

						$(textarea).closest(".code-editor-wrapper").append(formatButton);
					});
				});
			' );

			wp_enqueue_style('code-editor');
			wp_enqueue_style('wp-codemirror');
		}
	}
}