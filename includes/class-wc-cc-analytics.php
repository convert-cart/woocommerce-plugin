<?php
/**
 * WooCommerce Integration for Convert Cart Analytics.
 *
 * This file contains the integration class WC_CC_Analytics,
 * which handles analytics tracking and REST API endpoints.
 *
 * @package  WC_CC_Analytics
 * @category Integration
 */

namespace ConvertCart\Analytics;

use WP_REST_Request;

// Restore inheritance from WC_Integration
class WC_CC_Analytics extends \WC_Integration {

	/**
	 * Integration ID.
	 * @var string
	 */
	public $id = 'cc_analytics'; // Declare and initialize

	/**
	 * Integration method title.
	 * @var string
	 */
	public $method_title; // Declare

	/**
	 * Integration method description.
	 * @var string
	 */
	public $method_description; // Declare

	/**
	 * Form fields for settings.
	 * @var array
	 */
	public $form_fields = []; // Declare

	/**
	 * Settings values.
	 * @var array
	 */
	public $settings = []; // Declare

	/**
	 * Client ID.
	 *
	 * @var string
	 */
	public $cc_client_id;

	/**
	 * Debug mode setting.
	 * @var string ('yes' or 'no')
	 */
	public $debug_mode = 'no';

	/**
	 * SMS Consent setting.
	 * @var string ('disabled', 'draft', 'live')
	 */
	public $enable_sms_consent = 'disabled';

	/**
	 * Email Consent setting.
	 * @var string ('disabled', 'draft', 'live')
	 */
	public $enable_email_consent = 'disabled';

	/**
	 * Consent instances.
	 * @var array<string, \ConvertCart\Analytics\Consent\Base_Consent>
	 */
	public $consent_handlers = [];

	/**
	 * Flag to ensure block data filter is added only once.
	 * @var bool
	 */
	private $block_data_filter_added = false;

	/**
	 * Constructor - Sets up initial hooks.
	 */
	public function __construct() {
		// $this->id is now initialized in property declaration
		$this->method_title       = __( 'CC Analytics Settings', 'woocommerce_cc_analytics' );
		$this->method_description = __( 'Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics' );

		// Load settings form fields
		$this->init_form_fields();

		// Load the settings values themselves
		$this->init_settings(); // This loads saved options into $this->settings

		// Assign main settings to properties
		$this->cc_client_id = $this->get_option( 'cc_client_id' );

		// Hook for saving settings in admin
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		// --- Core Hooks Setup ---
		// Defer consent initialization and hook setup until template_redirect
		add_action('template_redirect', array($this, 'initialize_consent_handlers'), 5);

		// Hooks that need to run on woocommerce_init
		add_action('woocommerce_init', array($this, 'woocommerce_init_hooks'));

		// Hooks for analytics tracking (if client ID is set)
		if ( ! empty($this->cc_client_id) ) {
		add_action( 'wp_head', array( $this, 'cc_init' ) );
		add_action( 'wp_footer', array( $this, 'addEvents' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'ordered' ) );
			// ... other tracking hooks ...
		}

		// Hooks for REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Hooks for category webhooks
		add_action( 'create_product_cat', array( $this, 'handle_category_created' ), 10, 2 );
		add_action( 'edit_product_cat', array( $this, 'handle_category_updated' ), 10, 2 );
		add_action( 'delete_product_cat', array( $this, 'handle_category_deleted' ), 10, 2 );
		add_action( 'woocommerce_admin_product_category_webhook_handler', array( $this, 'sendCategoryRelatedNotification' ), 10, 1 );

		// Hook for WP-CLI commands
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'init', array( $this, 'register_cli_commands' ) );
		}

		$this->log_debug('WC_CC_Analytics Constructor finished.');
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
				register_rest_route(
					'wc/v3',
					'cc-version',
			array( /* ... */ )
		);
				register_rest_route(
			'wc/v3',
			'cc-plugin-info',
			array( /* ... */ )
		);
		// Add other routes here
		$this->log_debug('REST routes registered.');
	}

	/**
	 * Handle category created webhook trigger.
	 */
	public function handle_category_created( $term_id, $args ) {
		do_action( 'woocommerce_admin_product_category_webhook_handler', [ /* ... */ ] );
	}
	// ... handle_category_updated, handle_category_deleted ...


	/**
	 * Initialize consent handlers and setup their hooks.
	 */
	public function initialize_consent_handlers() {
		$options = get_option('woocommerce_cc_analytics_settings', array());
		$sms_enabled = isset($options['enable_sms_consent']) && $options['enable_sms_consent'] !== 'disabled';
		$email_enabled = isset($options['enable_email_consent']) && $options['enable_email_consent'] !== 'disabled';
		
		if (!$sms_enabled && !$email_enabled) {
			return;
		}
		
		if ($sms_enabled && class_exists('ConvertCart\Analytics\Consent\SMS_Consent')) {
			new \ConvertCart\Analytics\Consent\SMS_Consent($this);
		}
		
		if ($email_enabled && class_exists('ConvertCart\Analytics\Consent\Email_Consent')) {
			new \ConvertCart\Analytics\Consent\Email_Consent($this);
		}
	}

	/**
	 * NEW METHOD: Add consent HTML for all enabled handlers to block checkout data.
	 * Hooked to woocommerce_blocks_checkout_render_block_data.
	 *
	 * @param array $data Block data.
	 * @return array Modified block data.
	 */
	public function add_all_consent_to_block_checkout_data($data) {
		$this->log_debug('Running add_all_consent_to_block_checkout_data filter callback.');
		error_log('Convert Cart: Running add_all_consent_to_block_checkout_data filter.');

		// Explicitly check if the handlers array is populated *at this point*
		if (empty($this->consent_handlers)) {
			$this->log_debug('CRITICAL: $this->consent_handlers is EMPTY inside the filter callback.');
			error_log('Convert Cart: CRITICAL - consent_handlers is EMPTY in filter callback.');
			// Log the state of the main settings just in case
			error_log('Convert Cart: Current settings in filter: ' . print_r($this->settings, true));
			return $data; // Exit early if no handlers are registered
		} else {
			$this->log_debug('Filter Callback: $this->consent_handlers contains keys: ' . implode(', ', array_keys($this->consent_handlers)));
			error_log('Convert Cart: Filter Callback - consent_handlers keys: ' . implode(', ', array_keys($this->consent_handlers)));
		}

		// Ensure the extensions structure exists
		if (!isset($data['extensions'])) {
			$data['extensions'] = array();
		}
		if (!isset($data['extensions']['convertcart-analytics'])) {
			$data['extensions']['convertcart-analytics'] = array();
		}

		// Loop through the instantiated handlers
		foreach ($this->consent_handlers as $type => $handler) {
			// Ensure $handler is a valid object
			if (!is_object($handler) || !method_exists($handler, 'is_enabled') || !method_exists($handler, 'get_consent_html')) {
				$this->log_debug("Filter Callback: Invalid handler object for type '{$type}'. Skipping.");
				error_log("Convert Cart: Filter Callback - Invalid handler object for type '{$type}'.");
				continue;
			}

			$this->log_debug("Filter Callback: Processing handler: " . $type);
			error_log("Convert Cart: Filter Callback - Processing handler: " . $type);

			// Check if this specific handler is enabled for 'live' mode
			$is_live = $handler->is_enabled('live'); // Store result
			$this->log_debug("Filter Callback: Handler '{$type}' is_enabled('live') check returned: " . ($is_live ? 'Yes' : 'No'));
			error_log("Convert Cart: Filter Callback - Handler '{$type}' is_live: " . ($is_live ? 'Yes' : 'No'));

			if ($is_live) {
				$this->log_debug("Filter Callback: Handler '{$type}' is live. Getting HTML.");
				error_log("Convert Cart: Filter Callback - Handler '{$type}' is live. Getting HTML.");
				// Get the HTML using the handler's method
				$consent_html = $handler->get_consent_html('checkout');
				// Log the raw HTML fetched
				$this->log_debug("Filter Callback: Handler '{$type}' get_consent_html('checkout') returned: " . $consent_html);
				error_log("Convert Cart: Filter Callback - Handler '{$type}' HTML: " . $consent_html);

				// Add it to the block data only if HTML is not empty
				if (!empty($consent_html)) {
					$data['extensions']['convertcart-analytics'][$type . '_consent_html'] = $consent_html;
					$this->log_debug("Filter Callback: Added {$type}_consent_html to block data.");
					error_log("Convert Cart: Filter Callback - Added {$type}_consent_html to block data.");
				} else {
					$this->log_debug("Filter Callback: Handler '{$type}' returned EMPTY HTML. Not adding to data.");
					error_log("Convert Cart: Filter Callback - Handler '{$type}' returned EMPTY HTML.");
				}

			} else {
				$this->log_debug("Filter Callback: Handler '{$type}' is not live. Skipping HTML addition.");
				error_log("Convert Cart: Filter Callback - Handler '{$type}' is not live. Skipping.");
			}
		}

		// Log the final data structure just before returning
		$this->log_debug('Filter Callback: Final block data structure: ' . print_r($data, true));
		error_log('Convert Cart: Filter Callback - Final block data: ' . print_r($data, true));

		return $data;
	}

	/**
	 * Hooks that need to run on woocommerce_init.
	 */
	public function woocommerce_init_hooks() {
		$this->log_debug('Running woocommerce_init hooks.');
		// Add hooks here that specifically depend on WC()->cart, WC()->session etc.
		// Example: add_action('woocommerce_add_to_cart', array($this, 'track_add_to_cart'));
	}

	/**
	 * Initialize the plugin. Load settings and hooks.
	 * REMOVE consent initialization from here if it was present.
	 */
	public function init() {
		// Load settings.
		$this->init_form_fields();
		$this->init_settings();

		// ... (other init logic) ...

		// ... (other hooks added in init) ...
		$this->log_debug('Core plugin init complete.');
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
	 * Initialize integration settings.
	 */
	public function init_settings() {
		// Let the parent WC_Integration handle loading settings
		parent::init_settings();

		// Now assign specific settings to our declared properties after parent loads them
		$this->cc_client_id         = $this->get_option('cc_client_id');
		$this->debug_mode           = $this->get_option('debug_mode', 'no'); // Use default if not set
		$this->enable_sms_consent   = $this->get_option('enable_sms_consent', 'disabled');
		$this->enable_email_consent = $this->get_option('enable_email_consent', 'disabled');
	}

	/**
	 * Output the settings fields.
	 */
	public function admin_options() {
		echo '<h2>' . esc_html( $this->method_title ) . '</h2>';
		echo wp_kses_post( wpautop( $this->method_description ) );
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
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
	 * Function to track orderCompleted event
	 *
	 * @param type string $data .
	 */
	public function ordered( $data ) {
		try {
			if ( is_wc_endpoint_url( 'order-received' ) ) {
				$event_info['ccEvent'] = $this->getEventType( 'orderCompleted' );
				$order                 = wc_get_order( $data );
				if ( ! is_object( $order ) ) {
					return $event_info;
				}
				$event_info['orderId']  = (string) $order->get_id();
				$event_info['total']    = $order->get_total();
				$event_info['currency'] = get_woocommerce_currency();
				$event_info['status']   = $order->get_status();
				$promos                 = $order->get_coupon_codes();

				if ( is_array( $promos ) ) {
					$event_info['coupon_code'] = isset( $promos[0] ) ? $promos[0] : null;
				}

				$line_items  = $order->get_items();
				$order_items = array();

				foreach ( $line_items as $item ) {
					$order_item = array();
					$product = wc_get_product( $item->get_product_id() );
					if ( ! is_object( $product ) ) {
						continue;
					}
					$order_item['name']     = $product->get_title();
					$order_item['price']    = $product->get_price();
					$order_item['currency'] = get_woocommerce_currency();
					$order_item['quantity'] = $item->get_quantity();
					$order_item['url']      = get_permalink( $product->get_id() );
					if ( $product->get_image_id() ) {
						$thumb_id = $product->get_image_id();
					} else {
						$thumb_id = get_post_thumbnail_id( $product->get_id() );
					}

					$thumb_url           = wp_get_attachment_image_src( $thumb_id );
					$order_item['image'] = isset( $thumb_url[0] ) ? $thumb_url[0] : null;
					$order_items[]       = $order_item;
				}
				$event_info['items'] = $order_items;
				$script              = $this->displayEventScript( $event_info );
			}
		} catch ( Exception $e ) {
			error_log( 'ConvertCart Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Function to track various events
	 */
	public function addEvents() {
		try {
			if ( is_front_page() && ! is_shop() ) {
				$event_info['ccEvent'] = $this->getEventType( 'homepageViewed' );
			} elseif ( is_shop() ) {
				$event_info['ccEvent'] = $this->getEventType( 'shopPageViewed' );
			} elseif ( is_product_category() ) {
				$event_info = $this->getCategoryViewedProps();
			} elseif ( is_product() ) {
				$event_info = $this->getProductViewedProps();
			} elseif ( is_search() ) {
				$event_info['ccEvent'] = $this->getEventType( 'productsSearched' );
				$event_info['query']   = get_search_query();
			} elseif ( is_cart() || ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) ) {
				if ( is_cart() ) {
					$event_info['ccEvent'] = $this->getEventType( 'cartViewed' );
				} elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
					$event_info['ccEvent'] = $this->getEventType( 'checkoutViewed' );
				}
				$cart                   = WC()->cart;
				$event_info['total']    = $cart->total;
				$event_info['currency'] = get_woocommerce_currency();
				$event_info['items']    = $this->getCartItems( $cart->get_cart() );
			} elseif ( is_single() || is_page() ) {
				$event_info = $this->getContentPageProps();
			}

			if ( isset( $event_info ) ) {
				$script = $this->displayEventScript( $event_info );
			}
		} catch ( Exception $e ) {
			error_log( 'ConvertCart Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Function to get properties of categoryViewed event
	 */
	public function getCategoryViewedProps() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->getEventType( 'categoryViewed' );
		global $wp_query;
		// get the query object.
		if ( ! is_object( $wp_query ) ) {
			return $event_info;
		}
		$cat_obj = $wp_query->get_queried_object();
		if ( is_object( $cat_obj ) ) {
			$event_info['name']  = $cat_obj->name;
			$event_info['url']   = get_category_link( $cat_obj->term_id );
			$event_info['id']    = $cat_obj->term_id;
			$event_info['count'] = $cat_obj->count;
		}
		return $event_info;
	}

	/**
	 * Function to get properties of productViewed event
	 */
	public function getProductViewedProps() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->getEventType( 'productViewed' );
		global $product;
		if ( ! is_object( $product ) ) {
			return $event_info;
		}

		$event_info['id']            = $product->get_id();
		$event_info['name']          = $product->get_title();
		$event_info['is_in_stock']   = $product->is_in_stock();
		$event_info['url']           = $product->get_permalink();
		$event_info['price']         = $product->get_price();
		$event_info['regular_price'] = $product->get_regular_price();
		$event_info['currency']      = get_woocommerce_currency();
		$event_info['type']          = $product->get_type();
		$thumb_id                    = get_post_thumbnail_id();
		if ( isset( $thumb_id ) ) {
			$thumb_url           = wp_get_attachment_image_src( $thumb_id, 'full' );
			$event_info['image'] = isset( $thumb_url[0] ) ? $thumb_url[0] : null;
		}
		return $event_info;
	}

	/**
	 * Function to get properties of contentPageViewed event
	 */
	public function getContentPageProps() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->getEventType( 'contentPageViewed' );
		$event_info['type']    = 'Page';
		$event_info['title']   = get_the_title();
		$event_info['url']     = get_permalink();
		return $event_info;
	}

	/**
	 * Function to get cart items
	 *
	 * @param array $items .
	 */
	public function getCartItems( $items ) {
		$cart_items = array();
		if ( ! is_array( $items ) ) {
			return $cart_items;
		}
		foreach ( $items as $item => $values ) {
			if ( ! isset( $values['data'] ) || ! is_object( $values['data'] ) ) {
				continue;
			}
			$product_id            = $values['data']->get_id();
			$cart_item['id']       = $product_id;
			$cart_item['name']     = $values['data']->get_name();
			$cart_item['quantity'] = $values['quantity'];
			$cart_item['price']    = $values['data']->get_price();
			$cart_item['currency'] = get_woocommerce_currency();
			if ( isset( $product_id ) ) {
				$cart_item['url'] = get_permalink( $product_id );
				if ( $values['data']->get_image_id() ) {
					$thumb_id = $values['data']->get_image_id();
				} else {
					$thumb_id = get_post_thumbnail_id( $product_id );
				}
				$thumb_url          = wp_get_attachment_image_src( $thumb_id, 'full' );
				$cart_item['image'] = isset( $thumb_url[0] ) ? $thumb_url[0] : null;
			}
			$cart_items[] = $cart_item;
		}
		return $cart_items;
	}

	/**
	 * Function to calculate final price of product
	 *
	 * @param type decimal|string $regular_price .
	 * @param type decimal|string $sale_price .
	 */
	public function calculateFinalPrice( $regular_price, $sale_price ) {
		if ( $sale_price < $regular_price ) {
			return $sale_price;
		} else {
			return $regular_price;
		}
	}

	/**
	 * Function to display cc tracking script
	 *
	 * @param string $event_info .
	 */
	public function displayEventScript( $event_info ) {
		$event_info['metaData'] = $this->getMetaInfo();
		$event_json             = wp_json_encode( $event_info );
		if ( isset( $event_json ) && '' !== $event_json ) {
			?>
			<!-- ConvertCart -->
			<script type='text/javascript'>
				window.ccLayer = window.ccLayer || [];
				ccLayer.push(<?php echo $event_json; ?>);
			</script>
			<!-- ConvertCart -->
			<?php
		}
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
	 * Function declaring all cc event names
	 *
	 * @param type string $event .
	 */
	public function getEventType( $event ) {
		$event_map = array(
			'homepageViewed'    => 'homepageViewed',
			'shopPageViewed'    => 'shopPageViewed',
			'productsSearched'  => 'productsSearched',
			'contentPageViewed' => 'contentPageViewed',
			'categoryViewed'    => 'categoryViewed',
			'productViewed'     => 'productViewed',
			'cartViewed'        => 'cartViewed',
			'checkoutViewed'    => 'checkoutViewed',
			'orderCompleted'    => 'orderCompleted',
		);
		if ( isset( $event_map[ $event ] ) ) {
			return $event_map[ $event ];
		} else {
			return 'default';
		}
	}
	/**
	 * Sends notification for category-related events.
	 *
	 * @param array $args Arguments containing event data
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

	public function maximum_api_filter( $query_params ) {
		$query_params['per_page']['maximum'] = 5000;
		return $query_params;
	}

	/**
	 * Function get version list
	 *
	 * @param type mixed $request
	 *
	 * @return array
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
	 * Function get version list
	 *
	 * @param type array $prepared_args
	 * @param type mixed $request
	 *
	 * @return array
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
	 * @param type mixed $request
	 *
	 * @return bool
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
	 * Adds SMS consent checkbox to the checkout form.
	 *
	 * @return void
	 */
	public function add_sms_consent_checkbox() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );

		// Inject the code only if SMS consent is enabled and in 'live' mode
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' ) ) {
			// Get custom HTML or use default
			$checkout_html = get_option(
				'cc_sms_consent_checkout_html',
				'<div class="sms-consent-checkbox">
					<label for="sms_consent">
						<input type="checkbox" name="sms_consent" id="sms_consent" />
						I consent to receive SMS communications.
					</label>
				</div>'
			);
			if ( is_user_logged_in() ) { // if user is logged in & consent is given then it will be checked by default
				$user_id       = get_current_user_id();
				$sms_consent   = get_user_meta( $user_id, 'sms_consent', true );
				$checkout_html = str_replace( 'id="sms_consent"', 'id="sms_consent" ' . checked( $sms_consent, 'yes', false ), $checkout_html );
			}
			echo $checkout_html;
		}
	}

    /**
     * Saves SMS consent to order or customer.
     *
     * @param int $order_id The order ID.
     * @return void
     */
	public function add_email_consent_checkbox() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			$checkout_html = get_option(
				'cc_email_consent_checkout_html',
				'<div class="email-consent-checkbox">
					<label for="email_consent">
						<input type="checkbox" name="email_consent" id="email_consent" />
						I consent to receive email communications.
					</label>
				</div>'
			);
			if( is_user_logged_in() ){ // if user is logged in & consent is given then it will be checked by default
				$user_id     = get_current_user_id();
				$email_consent = get_user_meta( $user_id, 'email_consent', true );
				$checkout_html = str_replace( 'id="email_consent"', 'id="email_consent" ' . checked( $email_consent, 'yes', false ), $checkout_html );
			}
			echo $checkout_html;
		}
	}


	/**
	 * Saves SMS consent to order or customer.
	 *
	 * @param int $order_id The order ID.
	 * @return void
	 */
	public function save_sms_consent_to_order_or_customer( $order, $data ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && $options['enable_sms_consent'] === 'live' ) {
			if ( is_user_logged_in() ) {
				// Logged-in users: Save consent to user meta
				$user_id = get_current_user_id();
				if ( isset( $_POST['sms_consent'] ) ) {
					update_user_meta( $user_id, 'sms_consent', 'yes' );
				}
			} else {
				// Guest users: Save consent to order meta
				if ( isset( $_POST['sms_consent'] ) ) {
					$order->update_meta_data( 'sms_consent', 'yes' );
				} else {
					$order->update_meta_data( 'sms_consent', 'no' );
				}
			}
		}
	}

	/**
     * Saves email consent to order or customer.
     *
     * @param WC_Order $order The order object
     * @param array $data The checkout data
     * @return void
     */
	public function save_email_consent_to_order_or_customer( $order, $data ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && 'live' === $options['enable_email_consent'] ) {
			if ( is_user_logged_in() ) {
				// Logged-in users: Save consent to user meta
				$user_id = get_current_user_id();
				if ( isset( $_POST['email_consent'] ) ) {
					update_user_meta( $user_id, 'email_consent', 'yes' );
				}
			} else {
				// Guest users: Save consent to order meta
				if ( isset( $_POST['email_consent'] ) ) {
					$order->update_meta_data( 'email_consent', 'yes' );
				} else {
					$order->update_meta_data( 'email_consent', 'no' );
				}
			}
		}
	}

	/**
	 * Saves SMS consent when account is created.
	 *
	 * @param int $customer_id The customer ID.
	 * @return void
	 */
	public function save_sms_consent_when_account_is_created( $customer_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && $options['enable_sms_consent'] === 'live' ) {
			if ( isset( $_POST['sms_consent'] ) ) {
				update_user_meta( $customer_id, 'sms_consent', 'yes' );
			} else {
				update_user_meta( $customer_id, 'sms_consent', 'no' );
			}
		}
	}

    /**
     * Saves email consent when account is created.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function save_email_consent_when_account_is_created( $customer_id, $new_customer_data, $password_generated ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && 'live' === $options['enable_email_consent'] ) {
			if ( isset( $_POST['email_consent'] ) ) {
				update_user_meta( $customer_id, 'email_consent', 'yes' );
			} else {
				update_user_meta( $customer_id, 'email_consent', 'no' );
			}
		}
	}

	/**
	 * Adds SMS consent checkbox to account page.
	 *
	 * @return void
	 */
	public function add_sms_consent_checkbox_to_account_page() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' ) ) {
			$user_id     = get_current_user_id();
			$sms_consent = get_user_meta( $user_id, 'sms_consent', true );

			$default_html = '
<p class="form-row form-row-wide">
	<label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label>
	<input type="checkbox" name="sms_consent" id="sms_consent" ' . checked( $sms_consent, 'yes', false ) . ' />
</p>';

			$account_html = get_option( 'cc_sms_consent_account_html', $default_html );

			$account_html = str_replace( 'id="sms_consent"', 'id="sms_consent" ' . checked( $sms_consent, 'yes', false ), $account_html );

			echo $account_html;
		}
	}

    /**
     * Saves SMS consent from account page.
     *
     * @param int $user_id The user ID.
     * @return void
     */
	public function save_sms_consent_from_account_page( $user_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' ) ) {
			if ( isset( $_POST['sms_consent'] ) ) {
				update_user_meta( $user_id, 'sms_consent', 'yes' );
			}
		}
	}

    /**
     * Saves email consent from account page.
     *
     * @param int $user_id The user ID.
     * @return void
     */
	public function save_email_consent_from_account_page( $user_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			if ( isset( $_POST['email_consent'] ) ) {
				update_user_meta( $user_id, 'email_consent', 'yes' );
			}
		}
	}

    /**
     * Adds SMS consent checkbox to registration form.
     *
     * @return void
     */
	public function add_sms_consent_to_registration_form() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' ) ) {
			// Default HTML as a string
			$default_html = '
<p class="form-row form-row-wide">
	<label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label>
	<input type="checkbox" name="sms_consent" id="sms_consent" />
</p>';

			// Get custom HTML or use default
			$registration_html = get_option( 'cc_sms_consent_registration_html', $default_html );

			echo $registration_html;
		}
	}

    /**
     * Saves SMS consent from registration form.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function add_email_consent_to_registration_form() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			// Default HTML as a string
			$default_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';

			// Get custom HTML or use default
			$registration_html = get_option( 'cc_email_consent_registration_html', $default_html );

			echo $registration_html;
		}
	}

    /**
     * Saves email consent from registration form.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function save_email_consent_from_registration_form( $customer_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && 'live' === $options['enable_email_consent'] ) {
			if ( isset( $_POST['email_consent'] ) ) {
				update_user_meta( $customer_id, 'email_consent', 'yes' );
			} else {
				update_user_meta( $customer_id, 'email_consent', 'no' );
			}
		}
	}

    /**
     * Saves SMS consent from registration form.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function save_sms_consent_from_registration_form( $customer_id ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && $options['enable_sms_consent'] === 'live' ) {
			if ( isset( $_POST['sms_consent'] ) ) {
				update_user_meta( $customer_id, 'sms_consent', 'yes' );
			} else {
				update_user_meta( $customer_id, 'sms_consent', 'no' );
			}
		}
	}

    /**
     * Updates SMS consent from previous orders.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function update_consent_from_previous_orders( $customer_id, $new_customer_data, $password_generated ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && $options['enable_sms_consent'] === 'live' ) {
			$user_email = $new_customer_data['user_email'];

			// Search for guest orders placed with the same email
			$orders = wc_get_orders(
				array(
					'billing_email' => $user_email,
					'limit'         => -1,  // Retrieve all orders
					'customer_id'   => 0,   // Only guest orders
				)
			);

			foreach ( $orders as $order ) {
				$sms_consent = $order->get_meta( 'sms_consent' );

				// If the guest had consented in any order, save it to the user profile
				if ( $sms_consent === 'yes' ) {
					update_user_meta( $customer_id, 'sms_consent', 'yes' );
					break;  // Exit the loop once consent is found
				}
			}
		}
    }

    /**
     * Updates email consent from previous orders.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function update_consent_from_previous_orders_email( $customer_id, $new_customer_data, $password_generated ) {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && 'live' === $options['enable_email_consent'] ) {
			$user_email = $new_customer_data['user_email'];

			// Search for guest orders placed with the same email
			$orders = wc_get_orders(
				array(
					'billing_email' => $user_email,
					'limit'         => -1,  // Retrieve all orders
					'customer_id'   => 0,   // Only guest orders
				)
			);

			foreach ( $orders as $order ) {
				$email_consent = $order->get_meta( 'email_consent' );

				// If the guest had consented in any order, save it to the user profile
				if ( $email_consent === 'yes' ) {
					update_user_meta( $customer_id, 'email_consent', 'yes' );
					break;  // Exit the loop once consent is found
				}
			}
		}
	}

    /**
     * Adds Convert Cart menu to the admin dashboard.
     *
     * @return void
     */
	public function add_convert_cart_menu() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );

		// Only show the menu if SMS consent is enabled
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' || $options['enable_sms_consent'] === 'draft' ) ) {
			add_menu_page(
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				'manage_options',
				'convert-cart-settings',
				array( $this, 'render_convert_cart_settings_page' ),
				'dashicons-edit',
				60
			);
		}

		// Only show the menu if email consent is enabled
		if ( isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' ) ) {
			add_menu_page(
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				'manage_options',
				'convert-cart-settings',
				array( $this, 'render_convert_cart_settings_page' ),
				'dashicons-edit',
				60
			);
		}
	}

    /**
     * Enqueues CodeMirror assets for the Convert Cart settings page.
     *
     * @return void
     */
	public function enqueue_codemirror_assets() {
		// Only load on our plugin's admin page
		$screen = get_current_screen();
		if ( $screen->id !== 'toplevel_page_convert-cart-settings' ) {
			return;
		}
		
		wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
		
		// Add inline script to initialize CodeMirror
		wp_add_inline_script( 'wp-theme-plugin-editor', '
			jQuery(document).ready(function($) {
				// Initialize CodeMirror for each textarea
				var editor_settings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
				editor_settings.codemirror = _.extend(
					{},
					editor_settings.codemirror,
					{
						mode: "htmlmixed",
						indentUnit: 2,
						tabSize: 2,
						lineNumbers: true,
						theme: "default"
					}
				);
				
				// Initialize editors for SMS consent
				if ($("#cc_sms_consent_checkout_html").length) {
					wp.codeEditor.initialize($("#cc_sms_consent_checkout_html"), editor_settings);
					wp.codeEditor.initialize($("#cc_sms_consent_registration_html"), editor_settings);
					wp.codeEditor.initialize($("#cc_sms_consent_account_html"), editor_settings);
				}
				
				// Initialize editors for Email consent
				if ($("#cc_email_consent_checkout_html").length) {
					wp.codeEditor.initialize($("#cc_email_consent_checkout_html"), editor_settings);
					wp.codeEditor.initialize($("#cc_email_consent_registration_html"), editor_settings);
					wp.codeEditor.initialize($("#cc_email_consent_account_html"), editor_settings);
				}
			});
		' );
	}

	/**
	 * Renders the Convert Cart settings page.
	 *
	 * @return void
	 */
	public function render_convert_cart_settings_page() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' || $options['enable_sms_consent'] === 'draft' ) ) {
			if ( isset( $_POST['save_convert_cart_html'] ) && isset( $_POST['cc_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cc_nonce'] ) ), 'save_convert_cart_html' ) ) {
				// PHP Validation to ensure the sms_consent checkbox is present
				$cc_sms_consent_checkout_html     = wp_kses_post( stripslashes( $_POST['cc_sms_consent_checkout_html'] ) );
				$cc_sms_consent_registration_html = wp_kses_post( stripslashes( $_POST['cc_sms_consent_registration_html'] ) );
				$cc_sms_consent_account_html      = wp_kses_post( stripslashes( $_POST['cc_sms_consent_account_html'] ) );
				
				// Save custom HTML snippets to options if valid
				update_option( 'cc_sms_consent_checkout_html', $cc_sms_consent_checkout_html );
				update_option( 'cc_sms_consent_registration_html', $cc_sms_consent_registration_html );
				update_option( 'cc_sms_consent_account_html', $cc_sms_consent_account_html );

				echo '<div class="updated"><p>HTML Snippets saved successfully!</p></div>';
			}

			// Add nonce field to the form
			echo '<form method="POST" id="convert-cart-form">';
			wp_nonce_field( 'save_convert_cart_html', 'cc_nonce' );

			// Default HTML snippets as fallback
			$default_checkout_html = '<div class="sms-consent-checkbox"><label for="sms_consent"><input type="checkbox" name="sms_consent" id="sms_consent"> I consent to receive SMS communications.</label></div>';

			$default_registration_html = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';

			$default_account_html = '<p class="form-row form-row-wide"><label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label><input type="checkbox" name="sms_consent" id="sms_consent"></p>';

			// Get the saved HTML snippets or use defaults
			$checkout_html     = get_option( 'cc_sms_consent_checkout_html', $default_checkout_html );
			$registration_html = get_option( 'cc_sms_consent_registration_html', $default_registration_html );
			$account_html      = get_option( 'cc_sms_consent_account_html', $default_account_html );

			?>
			<div class="wrap">
				<h1><?php _e( 'Convert Cart HTML Snippets', 'woocommerce_cc_analytics' ); ?></h1>
				<h2><?php _e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_sms_consent_checkout_html" name="cc_sms_consent_checkout_html" rows="10"
					cols="50"><?php echo esc_textarea( $checkout_html ); ?></textarea>

				<h2><?php _e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_sms_consent_registration_html" name="cc_sms_consent_registration_html" rows="10"
					cols="50"><?php echo esc_textarea( $registration_html ); ?></textarea>

				<h2><?php _e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_sms_consent_account_html" name="cc_sms_consent_account_html" rows="10"
					cols="50"><?php echo esc_textarea( $account_html ); ?></textarea>

				<p><input type="submit" name="save_convert_cart_html" value="Save SMS Consent HTML Snippets" class="button-primary"></p>
			</div>
			<?php
		}
		if ( isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' ) ) {
			if ( isset( $_POST['save_convert_cart_email_html'] ) && isset( $_POST['cc_email_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cc_email_nonce'] ) ), 'save_convert_cart_email_html' ) ) {
				// PHP Validation to ensure the email_consent checkbox is present
				$cc_email_consent_checkout_html = wp_kses_post( stripslashes( $_POST['cc_email_consent_checkout_html'] ) );
				$cc_email_consent_registration_html = wp_kses_post( stripslashes( $_POST['cc_email_consent_registration_html'] ) );
				$cc_email_consent_account_html = wp_kses_post( stripslashes( $_POST['cc_email_consent_account_html'] ) );
				
				// Server-side validation to ensure email_consent checkbox is present
				if (
					strpos( $cc_email_consent_checkout_html, 'name="email_consent"' ) === false ||
					strpos( $cc_email_consent_registration_html, 'name="email_consent"' ) === false ||
					strpos( $cc_email_consent_account_html, 'name="email_consent"' ) === false
				) {
					echo '<div class="error"><p>Error: The "email_consent" checkbox must be present in all snippets.</p></div>';
				} else {
					// Save custom HTML snippets to options if valid
					update_option( 'cc_email_consent_checkout_html', $cc_email_consent_checkout_html );
					update_option( 'cc_email_consent_registration_html', $cc_email_consent_registration_html );
					update_option( 'cc_email_consent_account_html', $cc_email_consent_account_html );
					
					echo '<div class="updated"><p>HTML Snippets saved successfully!</p></div>';
				}
			}
			
			// Add form with nonce field
			echo '<form method="POST" id="convert-cart-email-form">';
			wp_nonce_field( 'save_convert_cart_email_html', 'cc_email_nonce' );
			
			// Default HTML snippets as fallback
			$default_checkout_html = '<div class="email-consent-checkbox"><label for="email_consent"><input type="checkbox" name="email_consent" id="email_consent"> I consent to receive email communications.</label></div>';

			$default_registration_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';

			$default_account_html = '<p class="form-row form-row-wide"><label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label><input type="checkbox" name="email_consent" id="email_consent"></p>';

			// Get the saved HTML snippets or use defaults
			$checkout_html     = get_option( 'cc_email_consent_checkout_html', $default_checkout_html );
			$registration_html = get_option( 'cc_email_consent_registration_html', $default_registration_html );
			$account_html      = get_option( 'cc_email_consent_account_html', $default_account_html );

			?>
			<div class="wrap">
				<h1><?php _e( 'Convert Cart Email Consent', 'woocommerce_cc_analytics' ); ?></h1>
				<h2><?php _e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_email_consent_checkout_html" name="cc_email_consent_checkout_html" rows="10"
					cols="50"><?php echo esc_textarea( $checkout_html ); ?></textarea>

				<h2><?php _e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_email_consent_registration_html" name="cc_email_consent_registration_html" rows="10"
					cols="50"><?php echo esc_textarea( $registration_html ); ?></textarea>

				<h2><?php _e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<textarea id="cc_email_consent_account_html" name="cc_email_consent_account_html" rows="10"
					cols="50"><?php echo esc_textarea( $account_html ); ?></textarea>

				<p><input type="submit" name="save_convert_cart_email_html" value="Save Email Consent HTML Snippets" class="button-primary"></p>
			</div>
			<?php
		}
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

	/**
	 * Debug logging helper specific to this integration class.
	 *
	 * @param string $message Message to log.
	 */
	public function log_debug($message) {
		if (defined('WP_DEBUG') && WP_DEBUG === true) {
			if (is_array($message) || is_object($message)) {
				$message = print_r($message, true);
			}
			error_log('Convert Cart Integration: ' . $message);
		}
	}

	/**
	 * Check if WooCommerce version is compatible
	 */
	public function check_woocommerce_version() {
		if (!defined('WC_VERSION')) {
			return false;
		}
		
		if (version_compare(WC_VERSION, '7.0.0', '<')) {
			add_action('admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					esc_html__('Convert Cart Analytics requires WooCommerce 7.0.0 or higher.', 'woocommerce_cc_analytics') . 
					'</p></div>';
			});
			return false;
		}
		
		return true;
	}
}
