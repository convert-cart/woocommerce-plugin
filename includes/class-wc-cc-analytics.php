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

class WC_CC_Analytics extends \WC_Integration {

	/**
	 * Client ID.
	 *
	 * @var string
	 */
	public $cc_client_id;

	/**
	 * Debug mode.
	 *
	 * @var string
	 */
	public $debug_mode;

	/**
	 * SMS consent mode.
	 *
	 * @var string
	 */
	public $enable_sms_consent;

	/**
	 * Email consent mode.
	 *
	 * @var string
	 */
	public $enable_email_consent;

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

		// Actions added below.
		add_action( 'wp_head', array( $this, 'cc_init' ) );
		add_action( 'wp_footer', array( $this, 'addEvents' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'ordered' ) );
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

		// Filters
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
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_sms_consent_checkbox' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_email_consent_checkbox' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_sms_consent_to_order_or_customer' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_email_consent_to_order_or_customer' ), 10, 2 );
		add_action( 'woocommerce_created_customer', array( $this, 'save_sms_consent_when_account_is_created' ), 10, 3 );
		add_action( 'woocommerce_created_customer', array( $this, 'save_email_consent_when_account_is_created' ), 10, 3 );
		add_action( 'woocommerce_edit_account_form', array( $this, 'add_sms_consent_checkbox_to_account_page' ) );
		add_action( 'woocommerce_edit_account_form', array( $this, 'add_email_consent_checkbox_to_account_page' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_sms_consent_from_account_page' ), 12, 1 );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_email_consent_from_account_page' ), 12, 1 );
		add_action( 'woocommerce_register_form', array( $this, 'add_sms_consent_to_registration_form' ) );
		add_action( 'woocommerce_register_form', array( $this, 'add_email_consent_to_registration_form' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'save_sms_consent_from_registration_form' ), 10, 1 );
		add_action( 'woocommerce_created_customer', array( $this, 'save_email_consent_from_registration_form' ), 10, 1 );
		add_action( 'woocommerce_created_customer', array( $this, 'update_consent_from_previous_orders' ), 20, 3 );
		add_action( 'woocommerce_created_customer', array( $this, 'update_consent_from_previous_orders_email' ), 20, 3 );
		add_action( 'admin_menu', array( $this, 'add_convert_cart_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_codemirror_assets' ) );
		add_action( 'admin_head', array( $this, 'add_menu_icon_styles' ) );
		add_filter( 'parent_file', array( $this, 'highlight_menu_item' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu_item' ) );

		if (defined('WP_CLI') && WP_CLI) {
			WP_CLI::add_command('cc-consent', function($args, $assoc_args) {
				$user_id = $assoc_args['user'] ?? get_current_user_id();
				
				// Check SMS consent
				$sms_consent = get_user_meta($user_id, 'sms_consent', true);
				WP_CLI::line(sprintf('SMS Consent for user %d: %s', $user_id, $sms_consent ?: 'not set'));
				
				// Check Email consent
				$email_consent = get_user_meta($user_id, 'email_consent', true);
				WP_CLI::line(sprintf('Email Consent for user %d: %s', $user_id, $email_consent ?: 'not set'));
			}, array(
				'shortdesc' => 'Check consent status for a user',
				'synopsis' => array(
					array(
						'type'        => 'assoc',
						'name'        => 'user',
						'description' => 'User ID to check',
						'optional'    => true,
					),
				),
			));
		}

		// Add this line with the other action hooks
		add_action('admin_init', array($this, 'register_settings'));
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
		$this->settings = get_option( $this->plugin_id . $this->id . '_settings', null );
		if ( is_array( $this->settings ) ) {
			foreach ( $this->settings as $key => $value ) {
				$this->$key = $value;
			}
		}
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
		$consent_mode = $this->get_option('enable_sms_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$html = get_option('cc_sms_consent_checkout_html');
		if (empty($html)) {
			$html = $this->get_default_sms_consent_html();
		}
		
		echo $html;
	}

    /**
     * Saves SMS consent to order or customer.
     *
     * @param int $order_id The order ID.
     * @return void
     */
	public function add_email_consent_checkbox() {
		$consent_mode = $this->get_option('enable_email_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$html = get_option('cc_email_consent_checkout_html');
		if (empty($html)) {
			$html = $this->get_default_email_consent_html();
		}
		
		echo $html;
	}


	/**
	 * Saves SMS consent to order or customer.
	 *
	 * @param int $order_id The order ID.
	 * @return void
	 */
	public function save_sms_consent_to_order_or_customer( $order, $data ) {
		$consent_mode = $this->get_option('enable_sms_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['sms_consent']) ? 'yes' : 'no';
		$order->update_meta_data('sms_consent', $consent);
		
		// If user is logged in, also save to user meta
		$user_id = $order->get_customer_id();
		if ($user_id > 0) {
			update_user_meta($user_id, 'sms_consent', $consent);
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
		$consent_mode = $this->get_option('enable_email_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['email_consent']) ? 'yes' : 'no';
		$order->update_meta_data('email_consent', $consent);
		
		// If user is logged in, also save to user meta
		$user_id = $order->get_customer_id();
		if ($user_id > 0) {
			update_user_meta($user_id, 'email_consent', $consent);
		}
	}

	/**
	 * Saves SMS consent when account is created.
	 *
	 * @param int $customer_id The customer ID.
	 * @return void
	 */
	public function save_sms_consent_when_account_is_created( $customer_id, $new_customer_data, $password_generated ) {
		$consent_mode = $this->get_option('enable_sms_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['sms_consent']) ? 'yes' : 'no';
		update_user_meta($customer_id, 'sms_consent', $consent);
	}

    /**
     * Saves email consent when account is created.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function save_email_consent_when_account_is_created( $customer_id, $new_customer_data, $password_generated ) {
		$consent_mode = $this->get_option('enable_email_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['email_consent']) ? 'yes' : 'no';
		update_user_meta($customer_id, 'email_consent', $consent);
	}

	/**
	 * Adds SMS consent checkbox to account page.
	 *
	 * @return void
	 */
	public function add_sms_consent_checkbox_to_account_page() {
		$consent_mode = $this->get_option('enable_sms_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$user_id = get_current_user_id();
		$current_consent = get_user_meta($user_id, 'sms_consent', true);
		$checked = ($current_consent === 'yes') ? 'checked' : '';
		
		$html = get_option('cc_sms_consent_account_html');
		if (empty($html)) {
			$html = $this->get_default_sms_consent_html();
		}
		
		echo $html;
	}


		/**
     * Saves SMS consent when account is created.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function add_email_consent_checkbox_to_account_page() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_email_consent'] ) && ( 'live' === $options['enable_email_consent'] ) ) {
			$user_id     = get_current_user_id();
			$email_consent = get_user_meta( $user_id, 'email_consent', true );

			$default_html = '
<p class="form-row form-row-wide">
	<label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label>
	<input type="checkbox" name="email_consent" id="email_consent" ' . checked( $email_consent, 'yes', false ) . ' />
</p>';

			$account_html = get_option( 'cc_email_consent_account_html', $default_html );

			$account_html = str_replace( 'id="email_consent"', 'id="email_consent" ' . checked( $email_consent, 'yes', false ), $account_html );

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
		$consent_mode = $this->get_option('enable_sms_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['sms_consent']) ? 'yes' : 'no';
		update_user_meta($user_id, 'sms_consent', $consent);
	}

    /**
     * Saves email consent from account page.
     *
     * @param int $user_id The user ID.
     * @return void
     */
	public function save_email_consent_from_account_page( $user_id ) {
		$consent_mode = $this->get_option('enable_email_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['email_consent']) ? 'yes' : 'no';
		update_user_meta($user_id, 'email_consent', $consent);
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
     * Adds SMS consent checkbox to registration form.
     *
     * @return void
     */
	public function add_sms_consent_to_registration_form() {
		$consent_mode = $this->get_option('enable_sms_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$html = get_option('cc_sms_consent_registration_html');
		if (empty($html)) {
			$html = $this->get_default_sms_consent_html();
		}
		
		echo $html;
	}

    /**
     * Saves SMS consent from registration form.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function save_sms_consent_from_registration_form( $customer_id ) {
		$consent_mode = $this->get_option('enable_sms_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['sms_consent']) ? 'yes' : 'no';
		update_user_meta($customer_id, 'sms_consent', $consent);
	}

    /**
     * Saves email consent from registration form.
     *
     * @param int $customer_id The customer ID.
     * @return void
     */
	public function save_email_consent_from_registration_form( $customer_id ) {
		$consent_mode = $this->get_option('enable_email_consent', 'disabled');
		if ($consent_mode !== 'live') {
			return;
		}
		
		$consent = isset($_POST['email_consent']) ? 'yes' : 'no';
		update_user_meta($customer_id, 'email_consent', $consent);
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
     * Add Convert Cart menu and submenus.
     */
	public function add_convert_cart_menu() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		$sms_enabled = isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' || $options['enable_sms_consent'] === 'draft' );
		$email_enabled = isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' );
		
		// Only show the menu if either SMS or Email consent is enabled
		if ( !$sms_enabled && !$email_enabled ) {
			return;
		}
		
		// Add main menu
		$parent_slug = 'convert-cart';
			add_menu_page(
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
				__( 'Convert Cart', 'woocommerce_cc_analytics' ),
			'manage_woocommerce',
			$parent_slug,
			null, // No callback for the main menu
			'', // Icon is added via CSS
			59
		);
		
		// Add SMS Consent submenu if enabled
		if ( $sms_enabled ) {
			add_submenu_page(
				$parent_slug,
				__( 'SMS Consent', 'woocommerce_cc_analytics' ),
				__( 'SMS Consent', 'woocommerce_cc_analytics' ),
				'manage_woocommerce',
				'convert-cart-sms-consent',
				array( $this, 'render_sms_consent_page' )
			);
		}
		
		// Add Email Consent submenu if enabled
		if ( $email_enabled ) {
			add_submenu_page(
				$parent_slug,
				__( 'Email Consent', 'woocommerce_cc_analytics' ),
				__( 'Email Consent', 'woocommerce_cc_analytics' ),
				'manage_woocommerce',
				'convert-cart-email-consent',
				array( $this, 'render_email_consent_page' )
			);
		}
		
		// Add Settings submenu at the bottom
		add_submenu_page(
			$parent_slug,
			__( 'Convert Cart Settings', 'woocommerce_cc_analytics' ),
			__( 'Domain Settings', 'woocommerce_cc_analytics' ),
			'manage_woocommerce',
			'admin.php?page=wc-settings&tab=integration&section=' . $this->id,
			null
		);
		
		// Remove the default submenu that WordPress adds automatically
		remove_submenu_page($parent_slug, $parent_slug);
	}

	/**
	 * Add custom menu icon styles
	 */
	public function add_menu_icon_styles() {
		// Get the correct path to the icon
		$icon_url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/images/icon.svg';
		?>
		<style>
			#adminmenu .toplevel_page_convert-cart .wp-menu-image {
				background-image: url('<?php echo esc_url($icon_url); ?>') !important;
				background-repeat: no-repeat;
				background-position: center center;
				background-size: 20px auto;
			}
			#adminmenu .toplevel_page_convert-cart .wp-menu-image:before {
				content: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Highlight the correct parent menu item
	 */
	public function highlight_menu_item($parent_file) {
		global $plugin_page, $submenu_file;
		
		// Check if we're on the WC settings page with our integration section
		if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' && 
			isset($_GET['tab']) && $_GET['tab'] === 'integration' && 
			isset($_GET['section']) && $_GET['section'] === $this->id) {
			
			$parent_file = 'convert-cart';
		}
		
		return $parent_file;
	}

	/**
	 * Highlight the correct submenu item
	 */
	public function highlight_submenu_item($submenu_file) {
		// Check if we're on the WC settings page with our integration section
		if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' && 
			isset($_GET['tab']) && $_GET['tab'] === 'integration' && 
			isset($_GET['section']) && $_GET['section'] === $this->id) {
			
			$submenu_file = 'admin.php?page=wc-settings&tab=integration&section=' . $this->id;
		}
		
		return $submenu_file;
	}

	/**
	 * Render dashboard page
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woocommerce_cc_analytics' ) );
		}
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Convert Cart Dashboard', 'woocommerce_cc_analytics' ); ?></h1>
			<div class="card">
				<h2><?php esc_html_e( 'Welcome to Convert Cart', 'woocommerce_cc_analytics' ); ?></h2>
				<p><?php esc_html_e( 'Use the menu on the left to navigate to different Convert Cart settings.', 'woocommerce_cc_analytics' ); ?></p>
				
				<?php if ( empty( $this->cc_client_id ) ) : ?>
					<div class="notice notice-warning inline">
						<p>
							<?php 
							printf(
								/* translators: %s: settings page URL */
								esc_html__( 'Please configure your Domain ID in the %s to fully activate Convert Cart.', 'woocommerce_cc_analytics' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=integration&section=' . $this->id ) ) . '">' . esc_html__( 'Domain Settings', 'woocommerce_cc_analytics' ) . '</a>'
							); 
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render SMS consent settings page
	 */
	public function render_sms_consent_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woocommerce_cc_analytics' ) );
		}

		// Handle restore all defaults
		if ( isset( $_POST['restore_all'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cc_sms_consent_settings' ) ) {
			$default_html = $this->get_default_sms_consent_html();
			update_option( 'cc_sms_consent_checkout_html', $default_html );
			update_option( 'cc_sms_consent_registration_html', $default_html );
			update_option( 'cc_sms_consent_account_html', $default_html );
			
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All SMS Consent HTML templates have been restored to defaults.', 'woocommerce_cc_analytics' ) . '</p></div>';
			
			// Refresh the values
			$checkout_html = $default_html;
			$registration_html = $default_html;
			$account_html = $default_html;
		} 
		// Handle form submission
		else if ( isset( $_POST['submit'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cc_sms_consent_settings' ) ) {
			if ( isset( $_POST['cc_sms_consent_checkout_html'] ) ) {
				// Use custom sanitization that preserves form elements
				$checkout_html = $this->sanitize_consent_html( wp_unslash( $_POST['cc_sms_consent_checkout_html'] ), true );
				update_option( 'cc_sms_consent_checkout_html', $checkout_html );
			}
			if ( isset( $_POST['cc_sms_consent_registration_html'] ) ) {
				$registration_html = $this->sanitize_consent_html( wp_unslash( $_POST['cc_sms_consent_registration_html'] ), true );
				update_option( 'cc_sms_consent_registration_html', $registration_html );
			}
			if ( isset( $_POST['cc_sms_consent_account_html'] ) ) {
				$account_html = $this->sanitize_consent_html( wp_unslash( $_POST['cc_sms_consent_account_html'] ), true );
				update_option( 'cc_sms_consent_account_html', $account_html );
			}
			
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SMS Consent settings saved successfully.', 'woocommerce_cc_analytics' ) . '</p></div>';
		}
		// Load existing values
		else {
			$consent_mode = $this->get_option( 'enable_sms_consent', 'disabled' );
			$checkout_html = get_option( 'cc_sms_consent_checkout_html' );
			$registration_html = get_option( 'cc_sms_consent_registration_html' );
			$account_html = get_option( 'cc_sms_consent_account_html' );
			
			// Set default values if empty
			if ( empty( $checkout_html ) ) {
				$checkout_html = $this->get_default_sms_consent_html();
				update_option( 'cc_sms_consent_checkout_html', $checkout_html );
			}
			if ( empty( $registration_html ) ) {
				$registration_html = $this->get_default_sms_consent_html();
				update_option( 'cc_sms_consent_registration_html', $registration_html );
			}
			if ( empty( $account_html ) ) {
				$account_html = $this->get_default_sms_consent_html();
				update_option( 'cc_sms_consent_account_html', $account_html );
			}
		}

		// Use inline HTML instead of including a file that might not exist
			?>
			<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( isset($consent_mode) && $consent_mode === 'disabled' ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'SMS Consent collection is currently disabled. Enable it in Convert Cart Analytics settings to customize the consent forms.', 'woocommerce_cc_analytics' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" id="convert-cart-form">
				<?php wp_nonce_field( 'cc_sms_consent_settings' ); ?>
				
				<h2><?php esc_html_e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<div class="code-editor-wrapper">
					<textarea id="cc_sms_consent_checkout_html" name="cc_sms_consent_checkout_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $checkout_html ); ?></textarea>
			</div>

				<h2><?php esc_html_e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<div class="code-editor-wrapper">
					<textarea id="cc_sms_consent_registration_html" name="cc_sms_consent_registration_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $registration_html ); ?></textarea>
				</div>

				<h2><?php esc_html_e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<div class="code-editor-wrapper">
					<textarea id="cc_sms_consent_account_html" name="cc_sms_consent_account_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $account_html ); ?></textarea>
				</div>

				<div class="submit-buttons" style="display: flex; gap: 10px; margin-top: 20px;">
					<?php submit_button( __( 'Save SMS Consent Settings', 'woocommerce_cc_analytics' ), 'primary', 'submit', false ); ?>
					<input type="submit" name="restore_all" id="restore_all_button" value="<?php esc_attr_e('Restore All to Default', 'woocommerce_cc_analytics'); ?>" class="button button-secondary">
				</div>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Add confirmation to the Restore All button
			$('#restore_all_button').on('click', function(e) {
				if (!confirm('<?php echo esc_js(__('Are you sure you want to restore all HTML templates to default? This cannot be undone.', 'woocommerce_cc_analytics')); ?>')) {
					e.preventDefault();
				}
			});
			
			// Bypass validation for the Restore All button
			$('#convert-cart-form').on('submit', function(e) {
				if (e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === 'restore_all') {
					// Don't validate when clicking Restore All
					return true;
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Email consent settings page
	 */
	public function render_email_consent_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woocommerce_cc_analytics' ) );
		}

		// Handle restore all defaults
		if ( isset( $_POST['restore_all'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cc_email_consent_settings' ) ) {
			$default_html = $this->get_default_email_consent_html();
			update_option( 'cc_email_consent_checkout_html', $default_html );
			update_option( 'cc_email_consent_registration_html', $default_html );
			update_option( 'cc_email_consent_account_html', $default_html );
			
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All Email Consent HTML templates have been restored to defaults.', 'woocommerce_cc_analytics' ) . '</p></div>';
			
			// Refresh the values
			$checkout_html = $default_html;
			$registration_html = $default_html;
			$account_html = $default_html;
		} 
		// Handle form submission
		else if ( isset( $_POST['submit'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cc_email_consent_settings' ) ) {
			if ( isset( $_POST['cc_email_consent_checkout_html'] ) ) {
				$checkout_html = $this->sanitize_consent_html( wp_unslash( $_POST['cc_email_consent_checkout_html'] ), false );
				update_option( 'cc_email_consent_checkout_html', $checkout_html );
			}
			if ( isset( $_POST['cc_email_consent_registration_html'] ) ) {
				$registration_html = $this->sanitize_consent_html( wp_unslash( $_POST['cc_email_consent_registration_html'] ), false );
				update_option( 'cc_email_consent_registration_html', $registration_html );
			}
			if ( isset( $_POST['cc_email_consent_account_html'] ) ) {
				$account_html = $this->sanitize_consent_html( wp_unslash( $_POST['cc_email_consent_account_html'] ), false );
				update_option( 'cc_email_consent_account_html', $account_html );
			}
			
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email Consent settings saved successfully.', 'woocommerce_cc_analytics' ) . '</p></div>';
		}
		// Load existing values
		else {
			$consent_mode = $this->get_option( 'enable_email_consent', 'disabled' );
			$checkout_html = get_option( 'cc_email_consent_checkout_html' );
			$registration_html = get_option( 'cc_email_consent_registration_html' );
			$account_html = get_option( 'cc_email_consent_account_html' );

			// Set default values if empty
			if ( empty( $checkout_html ) ) {
				$checkout_html = $this->get_default_email_consent_html();
				update_option( 'cc_email_consent_checkout_html', $checkout_html );
			}
			if ( empty( $registration_html ) ) {
				$registration_html = $this->get_default_email_consent_html();
				update_option( 'cc_email_consent_registration_html', $registration_html );
			}
			if ( empty( $account_html ) ) {
				$account_html = $this->get_default_email_consent_html();
				update_option( 'cc_email_consent_account_html', $account_html );
			}
		}

		// Use inline HTML instead of including a file that might not exist
			?>
			<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( isset($consent_mode) && $consent_mode === 'disabled' ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Email Consent collection is currently disabled. Enable it in Convert Cart Analytics settings to customize the consent forms.', 'woocommerce_cc_analytics' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" id="convert-cart-form">
				<?php wp_nonce_field( 'cc_email_consent_settings' ); ?>
				
				<h2><?php esc_html_e( 'Checkout Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<div class="code-editor-wrapper">
					<textarea id="cc_email_consent_checkout_html" name="cc_email_consent_checkout_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $checkout_html ); ?></textarea>
			</div>

				<h2><?php esc_html_e( 'Registration Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<div class="code-editor-wrapper">
					<textarea id="cc_email_consent_registration_html" name="cc_email_consent_registration_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $registration_html ); ?></textarea>
				</div>

				<h2><?php esc_html_e( 'My Account Page HTML', 'woocommerce_cc_analytics' ); ?></h2>
				<div class="code-editor-wrapper">
					<textarea id="cc_email_consent_account_html" name="cc_email_consent_account_html" rows="5" cols="50" class="large-text code consent-html-editor"><?php echo esc_textarea( $account_html ); ?></textarea>
				</div>

				<div class="submit-buttons" style="display: flex; gap: 10px; margin-top: 20px;">
					<?php submit_button( __( 'Save Email Consent Settings', 'woocommerce_cc_analytics' ), 'primary', 'submit', false ); ?>
					<input type="submit" name="restore_all" id="restore_all_button" value="<?php esc_attr_e('Restore All to Default', 'woocommerce_cc_analytics'); ?>" class="button button-secondary">
				</div>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Add confirmation to the Restore All button
			$('#restore_all_button').on('click', function(e) {
				if (!confirm('<?php echo esc_js(__('Are you sure you want to restore all HTML templates to default? This cannot be undone.', 'woocommerce_cc_analytics')); ?>')) {
					e.preventDefault();
				}
			});
			
			// Bypass validation for the Restore All button
			$('#convert-cart-form').on('submit', function(e) {
				if (e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === 'restore_all') {
					// Don't validate when clicking Restore All
					return true;
				}
			});
		});
		</script>
			<?php
		}

	/**
	 * Sanitize consent HTML while preserving form elements
	 * 
	 * @param string $html The HTML to sanitize
	 * @param bool $is_sms Whether this is SMS consent HTML (true) or Email consent HTML (false)
	 * @return string Sanitized HTML
	 */
	public function sanitize_consent_html($html, $is_sms = true) {
		if (empty($html)) {
			return $is_sms ? $this->get_default_sms_consent_html() : $this->get_default_email_consent_html();
		}
		
		// Allow specific HTML tags including input elements with their attributes
		$allowed_html = array(
			'div' => array(
				'class' => true,
				'id' => true,
				'style' => true,
			),
			'span' => array(
				'class' => true,
				'id' => true,
				'style' => true,
			),
			'label' => array(
				'for' => true,
				'class' => true,
				'style' => true,
			),
			'input' => array(
				'type' => true,
				'name' => true,
				'id' => true,
				'class' => true,
				'value' => true,
				'checked' => true,
				'style' => true,
			),
			'p' => array(
				'class' => true,
				'style' => true,
			),
			'br' => array(),
			'strong' => array(),
			'em' => array(),
			'a' => array(
				'href' => true,
				'target' => true,
				'rel' => true,
				'class' => true,
			),
		);
		
		// Use wp_kses with our allowed HTML tags
		$sanitized_html = wp_kses($html, $allowed_html);
		
		// Check if the required checkbox exists
		$checkbox_name = $is_sms ? 'sms_consent' : 'email_consent';
		if (strpos($sanitized_html, 'name="' . $checkbox_name . '"') === false || 
			strpos($sanitized_html, 'type="checkbox"') === false) {
			// If checkbox is missing, return the default HTML
			return $is_sms ? $this->get_default_sms_consent_html() : $this->get_default_email_consent_html();
		}
		
		return $sanitized_html;
	}

	/**
	 * Get default SMS consent HTML
	 * 
	 * @return string Default SMS consent HTML
	 */
	public function get_default_sms_consent_html() {
		return '<div class="sms-consent-checkbox form-row">
			<label for="sms_consent">
				<input type="checkbox" name="sms_consent" id="sms_consent" />
				<span>' . esc_html__( 'I consent to receive SMS communications.', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
	}

	/**
	 * Get default Email consent HTML
	 * 
	 * @return string Default Email consent HTML
	 */
	public function get_default_email_consent_html() {
		return '<div class="email-consent-checkbox form-row">
			<label for="email_consent">
				<input type="checkbox" name="email_consent" id="email_consent" />
				<span>' . esc_html__( 'I consent to receive email communications.', 'woocommerce_cc_analytics' ) . '</span>
			</label>
		</div>';
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
	 * Enqueues CodeMirror assets for the Convert Cart settings pages.
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_codemirror_assets($hook_suffix) {
		// Only load on our consent pages
		if (strpos($hook_suffix, 'convert-cart-sms-consent') === false && 
			strpos($hook_suffix, 'convert-cart-email-consent') === false) {
			return;
		}

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
		if (false === $settings) {
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

		// Get default HTML templates for reset functionality
		$default_sms_html = $this->get_default_sms_consent_html();
		$default_email_html = $this->get_default_email_consent_html();

		wp_add_inline_script('code-editor', '
			jQuery(function($) {
				var editorSettings = ' . wp_json_encode($settings) . ';
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

				// Default HTML templates for reset functionality
				var defaultTemplates = {
					sms_checkout: ' . json_encode($default_sms_html) . ',
					sms_registration: ' . json_encode($default_sms_html) . ',
					sms_account: ' . json_encode($default_sms_html) . ',
					email_checkout: ' . json_encode($default_email_html) . ',
					email_registration: ' . json_encode($default_email_html) . ',
					email_account: ' . json_encode($default_email_html) . '
				};

				editorSettings.codemirror.extraKeys = {
					"Tab": function(cm) {
						if (cm.somethingSelected()) {
							cm.indentSelection("add");
						} else {
							cm.replaceSelection(cm.getOption("indentWithTabs") ? "\t" :
								Array(cm.getOption("indentUnit") + 1).join(" "), "end", "+input");
						}
					},
					"Shift-Tab": "indentLess"
				};

				$(".consent-html-editor").each(function(i, textarea) {
					var $textarea = $(textarea);
					var editorId = $textarea.attr("id");
					var editorType = "";
					
					// Determine editor type for reset functionality
					if (editorId.indexOf("sms_consent") !== -1) {
						if (editorId.indexOf("checkout") !== -1) editorType = "sms_checkout";
						else if (editorId.indexOf("registration") !== -1) editorType = "sms_registration";
						else if (editorId.indexOf("account") !== -1) editorType = "sms_account";
					} else if (editorId.indexOf("email_consent") !== -1) {
						if (editorId.indexOf("checkout") !== -1) editorType = "email_checkout";
						else if (editorId.indexOf("registration") !== -1) editorType = "email_registration";
						else if (editorId.indexOf("account") !== -1) editorType = "email_account";
					}

					// Prevent default tab behavior
					$textarea.on("keydown", function(e) {
						if (e.keyCode === 9) { // tab key
							e.preventDefault();
						}
					});

					var editor = wp.codeEditor.initialize($textarea, editorSettings);

					// Set tab handling message
					var message = $("<p>", {
						text: "Use Tab key for indentation. Shift+Tab to decrease indent.",
						class: "description",
						css: { "margin-top": "5px", "font-style": "italic" }
					});
					$textarea.closest(".code-editor-wrapper").prepend(message);

					// Format the initial content
					var initialContent = editor.codemirror.getValue();
					if (window.html_beautify && initialContent.trim()) {
						var formattedContent = window.html_beautify(initialContent, beautifyOptions);
						editor.codemirror.setValue(formattedContent);
					}

					editor.codemirror.on("change", function() {
						$textarea.val(editor.codemirror.getValue());
					});

					// Create button container for better alignment
					var buttonContainer = $("<div>", {
						class: "button-container",
						css: { 
							"margin-top": "5px",
							"display": "flex",
							"gap": "10px"
						}
					});

					// Add format button
					var formatButton = $("<button>", {
						text: "Format HTML",
						class: "button button-secondary",
						click: function(e) {
							e.preventDefault();
							var content = editor.codemirror.getValue();
							if (window.html_beautify && content.trim()) {
								var formattedContent = window.html_beautify(content, beautifyOptions);
								editor.codemirror.setValue(formattedContent);
							}
						}
					});
					
					// Add reset button
					var resetButton = $("<button>", {
						text: "Reset to Default",
						class: "button button-secondary",
						css: { "margin-left": "10px" },
						click: function(e) {
							e.preventDefault();
							if (confirm("Are you sure you want to reset this HTML to the default template? This cannot be undone.")) {
								if (defaultTemplates[editorType]) {
									var defaultContent = defaultTemplates[editorType];
									if (window.html_beautify) {
										defaultContent = window.html_beautify(defaultContent, beautifyOptions);
									}
									editor.codemirror.setValue(defaultContent);
								}
							}
						}
					});
					
					// Add buttons to container
					buttonContainer.append(formatButton);
					buttonContainer.append(resetButton);
					
					// Add button container to editor wrapper
					$textarea.closest(".code-editor-wrapper").append(buttonContainer);
				});

				// Form validation
				$("#convert-cart-form").on("submit", function(e) {
					// Skip validation if the Restore All button was clicked
					if (e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === "restore_all") {
						return true;
					}
					
					var isEmailForm = $(this).find("[name^=cc_email_consent]").length > 0;
					var checkoutHtml = isEmailForm ? 
						$("#cc_email_consent_checkout_html").val() : 
						$("#cc_sms_consent_checkout_html").val();
					var registrationHtml = isEmailForm ? 
						$("#cc_email_consent_registration_html").val() : 
						$("#cc_sms_consent_registration_html").val();
					var accountHtml = isEmailForm ? 
						$("#cc_email_consent_account_html").val() : 
						$("#cc_sms_consent_account_html").val();
					var inputName = isEmailForm ? "email_consent" : "sms_consent";

					// Function to validate HTML structure
					function isValidHTML(...htmlArgs) {
						return htmlArgs.every(html => {
							let doc = document.createElement("div");
							doc.innerHTML = html.trim();
							return doc.innerHTML !== ""; // Check if it was parsed correctly
						});
					}

					function hasConsentInputBoxWithId(...htmlArgs) {
						return htmlArgs.every(html => {
							let doc = document.createElement("div");
							doc.innerHTML = html.trim();
							const inputTag = doc.querySelector("input[name=\\"" + inputName + "\\"]");
							return inputTag && inputTag.id === inputName && inputTag.type === "checkbox";
						});
					}

					try {
						if (!isValidHTML(checkoutHtml, registrationHtml, accountHtml)) {
							throw new Error("Invalid HTML detected. Please fix the HTML syntax.");
						}

						if (!hasConsentInputBoxWithId(checkoutHtml, registrationHtml, accountHtml)) {
							throw new Error("The \\"" + inputName + "\\" checkbox must be present in all HTML snippets.");
						}
					} catch (error) {
						alert(error.message);
						e.preventDefault(); // Stop form submission
					}
				});
			});
		');
	}

	/**
	 * Register settings for the consent forms
	 */
	public function register_settings() {
		// Register SMS consent settings
		register_setting('cc_consent_settings', 'cc_sms_consent_checkout_html', array(
			'sanitize_callback' => function($input) {
				return $this->sanitize_consent_html($input, true);
			},
		));
		register_setting('cc_consent_settings', 'cc_sms_consent_registration_html', array(
			'sanitize_callback' => function($input) {
				return $this->sanitize_consent_html($input, true);
			},
		));
		register_setting('cc_consent_settings', 'cc_sms_consent_account_html', array(
			'sanitize_callback' => function($input) {
				return $this->sanitize_consent_html($input, true);
			},
		));
		
		// Register Email consent settings
		register_setting('cc_consent_settings', 'cc_email_consent_checkout_html', array(
			'sanitize_callback' => function($input) {
				return $this->sanitize_consent_html($input, false);
			},
		));
		register_setting('cc_consent_settings', 'cc_email_consent_registration_html', array(
			'sanitize_callback' => function($input) {
				return $this->sanitize_consent_html($input, false);
			},
		));
		register_setting('cc_consent_settings', 'cc_email_consent_account_html', array(
			'sanitize_callback' => function($input) {
				return $this->sanitize_consent_html($input, false);
			},
		));
    }
}
