<?php
/**
 * Convert Cart WooCommerce Integration.
 *
 * @package  WC_CC_Analytics
 * @category Integration
 * @author   Aamir
 */

/**
 * ConvertCart Analytics Class
 */
class WC_CC_Analytics extends WC_Integration {
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

		// Actions.
		add_action( 'wp_head', array( $this, 'cc_init' ) );
		add_action( 'wp_footer', array( $this, 'addEvents' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'ordered' ) );
	}

	/**
	 * Initialize integration settings form fields.
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
		);
	}
	/**
	 * Function to include convertcart initial javascript
	 */
	public function cc_init() {
		?>
			<!-- ConvertCart -->
			<script data-cfasync="false" src="//d241ujsiy3yht0.cloudfront.net/<?php echo esc_attr( $this->cc_client_id ); ?>.js">
			</script>
			<!-- ConvertCart -->
		<?php
	}

	/**
	 * Function to track orderCompleted event
	 *
	 * @param type string $data .
	 */
	public function ordered( $data ) {
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
			$promos                 = $order->get_used_coupons();

			if ( is_array( $promos ) ) {
				$event_info['coupon_code'] = isset( $promos[0] ) ? $promos[0] : null;
			}

			$line_items  = $order->get_items();
			$order_items = array();

			foreach ( $line_items as $item ) {
				$order_item = array();
				$product    = $order->get_product_from_item( $item );
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
	}

	/**
	 * Function to track various events
	 */
	public function addEvents() {
		if ( is_front_page() && ! is_shop() ) {
			$event_info['ccEvent'] = $this->getEventType( 'contentPageViewed' );
			$event_info['type']    = 'Blog Home';
		} elseif ( is_shop() ) {
			$event_info['ccEvent'] = $this->getEventType( 'homepageViewed' );
		}

		if ( is_product_category() ) {
			$event_info = $this->getCategoryViewedProps();
		}

		if ( is_product() ) {
			$event_info = $this->getProductViewedProps();
		}

		if ( is_cart() || ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) ) {
			if ( is_cart() ) {
				$event_info['ccEvent'] = $this->getEventType( 'cartViewed' );
			} elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
				$event_info['ccEvent'] = $this->getEventType( 'checkoutViewed' );
			}
			$cart                   = WC()->cart;
			$event_info['total']    = $cart->total;
			$event_info['currency'] = get_woocommerce_currency();
			$event_info['items']    = $this->getCartItems( $cart->get_cart() );
		}

		if ( is_single() && ! is_product() ) {
			$event_info = $this->getContentPageProps();
		}

		if ( is_page() ) {
			if ( ( ( ! is_product_category() && ! is_product() ) && ( ! is_checkout() && ! is_cart() ) ) ) {
				$event_info = $this->getContentPageProps();
			}
		}

		if ( isset( $event_info ) ) {
			$script = $this->displayEventScript( $event_info );
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
			$event_info['title'] = $cat_obj->name;
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
			$thumb_url           = wp_get_attachment_image_src( $thumb_id );
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
				$thumb_url          = wp_get_attachment_image_src( $thumb_id );
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
		$meta_data['pv']       = is_object( $woocommerce ) ? $woocommerce->version : null;
		$meta_data['wv']       = $wp_version;
		$meta_data['pgv']      = CC_PLUGIN_VERSION;
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
}
