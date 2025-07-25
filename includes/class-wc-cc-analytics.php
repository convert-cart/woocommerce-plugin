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
	 * Settings properties to avoid dynamic property deprecation.
	 */
	public $debug_mode;
	public $enable_sms_consent;
	public $enable_email_consent;
	public $flycart_discount_user_ids;

	/**
	 * Extracted component instances
	 */
	private $event_tracker;
	private $sms_consent_manager;
	private $email_consent_manager;
	private $discount_manager;
	private $admin_ui;
	private $rest_api;

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

		$this->id                 = 'cc_analytics';
		$this->method_title       = __( 'CC Analytics Settings', 'woocommerce_cc_analytics' );
		$this->method_description = __( 'Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->cc_client_id = $this->get_option( 'cc_client_id' );
		$this->flycart_discount_user_ids = $this->get_option( 'flycart_discount_user_ids', '' );
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( ! isset( $this->cc_client_id ) || '' === $this->cc_client_id ) {
			return;
		}

		// Initialize extracted components
		$this->event_tracker = new CC_Event_Tracker( $this->cc_client_id, $this->get_option( 'debug_mode' ) );
		$this->sms_consent_manager = new CC_SMS_Consent_Manager( $this->settings );
		$this->email_consent_manager = new CC_Email_Consent_Manager( $this->settings );
		$this->discount_manager = new CC_Discount_Manager( $this->flycart_discount_user_ids );
		$this->admin_ui = new CC_Admin_UI( $this->settings );
		$this->rest_api = new CC_Rest_API();

		// Initialize all components
		$this->event_tracker->init_tracking();
		$this->discount_manager->init_cron();
		$this->admin_ui->init_menu();
		$this->rest_api->init_endpoints();

		// Register cron on activation/deactivation - using proper namespaced class references
		register_activation_hook( WC_CC_Analytics::get_plugin_file(), array( $this->discount_manager, 'register_cron' ) );
		register_deactivation_hook( WC_CC_Analytics::get_plugin_file(), array( $this->discount_manager, 'clear_cron' ) );

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
		add_action( 'woocommerce_admin_product_category_webhook_handler', array( $this->rest_api, 'send_category_related_notification' ), 10, 1 );


		add_action( 'woocommerce_review_order_before_submit', array( $this->sms_consent_manager, 'add_checkout_checkbox' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this->email_consent_manager, 'add_checkout_checkbox' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this->sms_consent_manager, 'save_consent_to_order_or_customer' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order', array( $this->email_consent_manager, 'save_consent_to_order_or_customer' ), 10, 2 );
		add_action( 'woocommerce_update_product', array( $this->discount_manager, 'sync_discounts_for_product' ), 20, 1 );
		add_action( 'woocommerce_created_customer', array( $this->sms_consent_manager, 'save_consent_when_account_is_created' ), 10, 3 );
		add_action( 'woocommerce_created_customer', array( $this->email_consent_manager, 'save_consent_when_account_is_created' ), 10, 3 );
		add_action( 'woocommerce_edit_account_form', array( $this->sms_consent_manager, 'add_account_checkbox' ) );
		add_action( 'woocommerce_edit_account_form', array( $this->email_consent_manager, 'add_account_checkbox' ) );
		add_action( 'woocommerce_save_account_details', array( $this->sms_consent_manager, 'save_consent_from_account_page' ), 12, 1 );
		add_action( 'woocommerce_save_account_details', array( $this->email_consent_manager, 'save_consent_from_account_page' ), 12, 1 );
		add_action( 'woocommerce_register_form', array( $this->sms_consent_manager, 'add_registration_checkbox' ) );
		add_action( 'woocommerce_register_form', array( $this->email_consent_manager, 'add_registration_checkbox' ) );
		add_action( 'woocommerce_created_customer', array( $this->sms_consent_manager, 'save_consent_from_registration_form' ), 10, 1 );
		add_action( 'woocommerce_created_customer', array( $this->email_consent_manager, 'save_consent_from_registration_form' ), 10, 1 );
		add_action( 'woocommerce_created_customer', array( $this->sms_consent_manager, 'update_consent_from_previous_orders' ), 20, 3 );
		add_action( 'woocommerce_created_customer', array( $this->email_consent_manager, 'update_consent_from_previous_orders' ), 20, 3 );
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

		// Add flycart_discount_user_id field if flycart is enabled 
		if ( is_plugin_active( 'woo-discount-rules/woo-discount-rules.php' ) ) {
			$this->form_fields['flycart_discount_user_ids'] = array(
				'title'       => __( 'Flycart Discount User IDs', 'woocommerce_cc_analytics' ),
				'type'        => 'text',
				'description' => __( 'Comma-separated user IDs for Flycart discount sync. Leave empty to disable.', 'woocommerce_cc_analytics' ),
				'desc_tip'    => true,
				'default'     => '',
			);
		}
	}

	/**
	 * Initialize integration settings.
	 */
	public function init_settings() {
		$this->settings = get_option( $this->plugin_id . $this->id . '_settings', null );
		if ( is_array( $this->settings ) ) {
			foreach ( $this->settings as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
		// Also load flycart_discount_user_ids if set as option (for backward compatibility)
		if ( empty( $this->flycart_discount_user_ids  ) ) {
			$this->flycart_discount_user_ids = get_option( 'flycart_discount_user_ids', '' );
		}
	}

	/**
	 * Save admin options and handle Flycart cron logic
	 */
	public function process_admin_options() {
		$prev_user_ids = $this->get_option( 'flycart_discount_user_ids', '' );
		parent::process_admin_options();
		$new_user_ids = $this->get_option( 'flycart_discount_user_ids', '' );

		// If value becomes empty, clear the cron job
		if ( empty( $new_user_ids ) ) {
			self::flycart_clear_cron();
		}

		// If previously empty and now set, run job immediately and schedule cron
		if ( empty( $prev_user_ids ) && ! empty( $new_user_ids ) || $prev_user_ids !== $new_user_ids ) {
			$this->flycart_user_discount_cron();
			self::flycart_register_cron();
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
		// Show info about cron job
		if ( ! empty( $this->flycart_discount_user_ids )  && is_plugin_active('woo-discount-rules/woo-discount-rules.php') ) {
			echo '<p><strong>Cron job is enabled for user IDs:</strong> ' . esc_html( $this->flycart_discount_user_ids ) . '</p>';
		}
	}

	/**
	 * Register cron event on plugin activation
	 */
	public static function flycart_register_cron() {
		if ( ! wp_next_scheduled( 'flycart_user_discount_cron_hook' ) ) {
			wp_schedule_event( time(), 'one_hours', 'flycart_user_discount_cron_hook' );
			echo "Flycart cron registered.\n";
		} 
	}
	/**
	 * Clear cron event on plugin deactivation
	 */
	public static function flycart_clear_cron() {
		wp_clear_scheduled_hook( 'flycart_user_discount_cron_hook' );
		echo "Flycart cron cleared.\n";
	}

    /**
     * Handles the woocommerce_update_product action.
     *
     * @param WC_Product $product The product object.
     */
    public function handle_woocommerce_update_product( $post_id ) {
        // Skip during autosave or AJAX
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;

        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        // You may want to check for permissions here too
        if ( method_exists( $this, 'flycart_user_discount_for_product_update_create' ) && is_plugin_active('woo-discount-rules/woo-discount-rules.php') ) {
            $this->flycart_user_discount_for_product_update_create( $post_id );
        }
    }

	/**
	 * Helper to get plugin file for activation/deactivation hooks
	 */
	public static function get_plugin_file() {
		// Try to find the main plugin file
		if ( defined( 'WC_CC_ANALYTICS_FILE' ) ) {
			return WC_CC_ANALYTICS_FILE;
		}
		// Fallback: try to guess based on this file's location
		return dirname( __FILE__, 2 ) . '/cc-analytics.php';
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
					$product    = $item->get_product();
					if ( ! is_object( $product ) ) {
						continue;
					}
					$order_item['name']     = $product->get_title();
					$order_item['price']    = $product->get_price();
					$order_item['currency'] = get_woocommerce_currency();
					$order_item['quantity'] = isset( $item['qty'] ) ? $item['qty'] : null;
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
			$meta_data['pv'] = ( function_exists( 'WC' ) && is_object( WC() ) ) ? WC()->version : null;
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
	 * @param string $param1 Description of parameter
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

		$info               = array();
		$info['wp_version'] = $wp_version;
		$info['wc_version'] = ( function_exists( 'WC' ) && is_object( WC() ) ) ? WC()->version : null;
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
	 * @param int $order_id The order ID.
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
		wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
	}

	/**
	 * Renders the Convert Cart settings page.
	 *
	 * @return void
	 */
	public function render_convert_cart_settings_page() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		if ( isset( $options['enable_sms_consent'] ) && ( $options['enable_sms_consent'] === 'live' || $options['enable_sms_consent'] === 'draft' ) ) {
			if ( isset( $_POST['save_convert_cart_html'] ) ) {
				// PHP Validation to ensure the sms_consent checkbox is present
				$cc_sms_consent_checkout_html     = stripslashes( $_POST['cc_sms_consent_checkout_html'] );
				$cc_sms_consent_registration_html = stripslashes( $_POST['cc_sms_consent_registration_html'] );
				$cc_sms_consent_account_html      = stripslashes( $_POST['cc_sms_consent_account_html'] );
				if (
					strpos( $cc_sms_consent_checkout_html, 'name="sms_consent"' ) === false ||
					strpos( $cc_sms_consent_registration_html, 'name="sms_consent"' ) === false ||
					strpos( $cc_sms_consent_account_html, 'name="sms_consent"' ) === false
				) {
					echo '<div class="error"><p>Error: The "sms_consent" checkbox must be present in all snippets.</p></div>';
				} else {
					// Save custom HTML snippets to options if valid
					update_option( 'cc_sms_consent_checkout_html', $cc_sms_consent_checkout_html );
					update_option( 'cc_sms_consent_registration_html', $cc_sms_consent_registration_html );
					update_option( 'cc_sms_consent_account_html', $cc_sms_consent_account_html );

					echo '<div class="updated"><p>HTML Snippets saved successfully!</p></div>';
				}
			}

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
				<form method="POST" id="convert-cart-form">
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
				</form>
			</div>

			<script>
				jQuery(document).ready(function ($) {
					// Initialize CodeMirror for each textarea
					var editor_settings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
					editor_settings.codemirror = _.extend(
						{},
						editor_settings.codemirror,
						{
							mode: 'htmlmixed',
							indentUnit: 2,
							tabSize: 2,
							lineNumbers: true,
							theme: 'default',
							lint: {
								"indentation": "tabs"
							}
						}
					);
					wp.codeEditor.initialize($('#cc_sms_consent_checkout_html'), editor_settings);
					wp.codeEditor.initialize($('#cc_sms_consent_registration_html'), editor_settings);
					wp.codeEditor.initialize($('#cc_sms_consent_account_html'), editor_settings);

					// JavaScript validation to check for sms_consent checkbox
					$('#convert-cart-form').on('submit', function (e) {
						var checkoutHtml = $('#cc_sms_consent_checkout_html').val();
						var registrationHtml = $('#cc_sms_consent_registration_html').val();
						var accountHtml = $('#cc_sms_consent_account_html').val();

						// Function to validate HTML structure
						function isValidHTML(...htmlArgs) {
							return htmlArgs.every(html => {
								let doc = document.createElement('div');
								doc.innerHTML = html.trim();
								return doc.innerHTML === html.trim(); // Check if it was parsed correctly
							});
						}

						function hasSmsConsentInputBoxWithId(...htmlArgs) {
							return htmlArgs.every(html => {
								let doc = document.createElement('div');
								doc.innerHTML = html.trim();
								const inputTag = doc.querySelector('input[name="sms_consent"]');
								return inputTag && inputTag.id === 'sms_consent' && inputTag.type === 'checkbox';
							});
	}

						try {
							if (!isValidHTML(checkoutHtml, registrationHtml, accountHtml)) {
								throw new Error('Invalid HTML detected. Please fix the HTML syntax.');
							}

							if (!hasSmsConsentInputBoxWithId(checkoutHtml, registrationHtml, accountHtml)) {
								throw new Error('The "sms_consent" checkbox must be present in all HTML snippets.');
							}
						} catch (error) {
							alert(error.message);
							e.preventDefault(); // Stop form submission
			}
					});
				});
			</script>
			<?php
		}
		if ( isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' ) ) {
			$options = get_option( 'woocommerce_cc_analytics_settings' );
			if ( isset( $options['enable_email_consent'] ) && ( $options['enable_email_consent'] === 'live' || $options['enable_email_consent'] === 'draft' ) ) {
				if ( isset( $_POST['save_convert_cart_email_html'] ) ) {
					// PHP Validation to ensure the email_consent checkbox is present
					$cc_email_consent_checkout_html = stripslashes( $_POST['cc_email_consent_checkout_html'] );
					$cc_email_consent_registration_html = stripslashes( $_POST['cc_email_consent_registration_html'] );
					$cc_email_consent_account_html = stripslashes( $_POST['cc_email_consent_account_html'] );
					
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
					<form method="POST" id="convert-cart-email-form">
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
					</form>
				</div>
				<script>
					jQuery(document).ready(function ($) {
						// Initialize CodeMirror for each textarea
						var editor_settings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
						editor_settings.codemirror = _.extend(
							{},
							editor_settings.codemirror,
							{
								mode: 'htmlmixed',
								indentUnit: 2,
								tabSize: 2,
								lineNumbers: true,
								theme: 'default',
								lint: {
									"indentation": "tabs"
								}
							}
						);
						wp.codeEditor.initialize($('#cc_email_consent_checkout_html'), editor_settings);
						wp.codeEditor.initialize($('#cc_email_consent_registration_html'), editor_settings);
						wp.codeEditor.initialize($('#cc_email_consent_account_html'), editor_settings);

						// JavaScript validation to check for email_consent checkbox
						$('#convert-cart-email-form').on('submit', function (e) {
							var checkoutHtml = $('#cc_email_consent_checkout_html').val();
							var registrationHtml = $('#cc_email_consent_registration_html').val();
							var accountHtml = $('#cc_email_consent_account_html').val();

							console.log(checkoutHtml, registrationHtml, accountHtml);

							// Function to validate HTML structure
							function isValidHTML(...htmlArgs) {
								return htmlArgs.every(html => {
									let doc = document.createElement('div');
									doc.innerHTML = html.trim();
									return doc.innerHTML === html.trim(); // Check if it was parsed correctly
								});
							}

							function hasEmailConsentInputBoxWithId(...htmlArgs) {
								return htmlArgs.every(html => {
									let doc = document.createElement('div');
									doc.innerHTML = html.trim();
									const inputTag = doc.querySelector('input[name="email_consent"]');
									return inputTag && inputTag.id === 'email_consent' && inputTag.type === 'checkbox';
								});
							}

							try {
								if (!isValidHTML(checkoutHtml, registrationHtml, accountHtml)) {
									throw new Error('Invalid HTML detected. Please fix the HTML syntax.');
								}

								if (!hasEmailConsentInputBoxWithId(checkoutHtml, registrationHtml, accountHtml)) {
									throw new Error('The "email_consent" checkbox must be present in all HTML snippets.');
								}
							} catch (error) {
								alert(error.message);
								e.preventDefault(); // Stop form submission
							}
						});
					});
				</script>
				<?php
			}
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

		$info               = array();
		$info['wp_version'] = $wp_version;
		$info['wc_plugin_version'] = ( function_exists( 'WC' ) && is_object( WC() ) ) ? WC()->version : null;
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
