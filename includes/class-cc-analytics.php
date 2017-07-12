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
		$this->method_description = __( 'Contact Convert Cart To Get Client ID', 'woocommerce_cc_analytics' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->cc_client_id  = $this->get_option( 'cc_client_id' );
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( ! isset( $this->cc_client_id ) or '' === $this->cc_client_id ) {
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
				'title'             => __( 'Client ID', 'woocommerce_cc_analytics' ),
				'type'              => 'text',
				'description'       => __( 'Contact Convert Cart To Get Client ID', 'woocommerce_cc_analytics' ),
				'desc_tip'          => true,
				'default'           => '',
			),
		);
	}
	/**
	 * Function to include convert cart initial javascript code
	 */
	public function cc_init() {
		?>
			<!-- ConvertCart -->
			<script>                    
			(function(c,o,n,v,e,r,t,s){s=c.fetch?'f':'',
			c.ccartObj=e,c[e]=c[e]||function(){(c[e].q=c[e].q||[]).push(arguments)},c[e].t=Date.now(),
			r=o.createElement(n);r.async=1;r.src=v+s+'.js';t=o.getElementsByTagName(n)[0];t.parentNode
			.insertBefore(r,t)})(window, document,'script','//d241ujsiy3yht0.cloudfront.net/<?php echo esc_attr( $this->cc_client_id ); ?>','ccart')
			</script>
			<!-- ConvertCart -->    
		<?php
	}

	/**
	 * Function to track various events
	 *
	 * @param type string $data .
	 */
	public function ordered( $data ) {
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			$event_info['ccEvent'] = $this->getEventType( 'orderCompleted' );
			$order = wc_get_order( $data );
			$event_info['orderId'] = (string) $order->id;
			$event_info['total'] = $order->get_total();
			$event_info['currency'] = get_woocommerce_currency();
			$event_info['status'] = $order->post->post_status;
			$event_info['payment_method'] = $order->payment_method;
			$event_info['shipping_method'] = $order->shipping_method;
			$promos = $order->get_used_coupons();
			if ( is_array( $promos ) ) {
				$event_info['coupon_code'] = $promos[0];
			}

			$line_items = $order->get_items();
			$order_items = array();

			foreach ( $line_items as $item ) {
				$order_item = array();
				$product = $order->get_product_from_item( $item );
				if ( ! is_object( $product ) ) {
					continue;
				}
				$order_item['name'] = $product->get_title();
				$order_item['price'] = $product->get_price();
				$order_item['currency'] = get_woocommerce_currency();
				$order_item['quantity'] = $item['qty'];
				$order_item['url'] = get_permalink( $product->post->ID );
				if ( $product->image_id ) {
					$thumb_id = $product->image_id;
				} else {
					$thumb_id = get_post_thumbnail_id( $product->post->ID );
				}
				$thumb_url = wp_get_attachment_image_src( $thumb_id );
				if ( ! empty( $thumb_url[0] ) ) {
					$order_item['image'] = $thumb_url[0];
				}
				$order_items[] = $order_item;
			}
			$event_info['items'] = $order_items;
			$script = $this->displayEventScript( $event_info );
		}
	}

	/**
	 * Function to track various events
	 */
	public function addEvents() {
		if ( is_front_page() and ! is_shop() ) {
			$event_info['ccEvent'] = $this->getEventType( 'contentPageViewed' );
			$event_info['type'] = 'Blog Home';
		} elseif ( is_shop() ) {
			$event_info['ccEvent'] = $this->getEventType( 'homepageViewed' );
		}

		if ( is_product_category() ) {
			$event_info['ccEvent'] = $this->getEventType( 'categoryViewed' );
			global $wp_query;
			// get the query object.
			$cat_obj = $wp_query->get_queried_object();
			if ( is_object( $cat_obj ) ) {
				$event_info['title'] = $cat_obj->name;
				$event_info['url'] = get_category_link( $cat_obj->term_id );
				$event_info['id'] = $cat_obj->term_id;
				$event_info['count'] = $cat_obj->count;
			}
		}

		if ( is_product() ) {
			global $product;
			$event_info['ccEvent'] = $this->getEventType( 'productViewed' );
			$event_info['id'] = $product->post->ID;
			$event_info['name'] = $product->post->post_title;
			$event_info['is_in_stock'] = $product->is_in_stock();
			$event_info['url'] = $product->get_permalink();
			$event_info['price'] = $product->get_price();
			$event_info['regular_price'] = $product->get_regular_price();
			$event_info['currency'] = get_woocommerce_currency();
			$event_info['short_description'] = $product->post->post_excerpt;
			$event_info['type'] = $product->product_type;
			$thumb_id = get_post_thumbnail_id( $product_id );
			$thumb_url = wp_get_attachment_image_src( $thumb_id );
			$event_info['image'] = $thumb_url[0];

			$product_cats = get_the_terms( $product->post->ID, 'product_cat' );
			if ( is_array( $product_cats ) ) {
				$i = 0;
				foreach ( $product_cats as $cat ) {
					$cats[ $i ]['name'] = $cat->name;
					$cats[ $i ]['slug'] = $cat->slug;
					$i++;
				}
			}
			$event_info['categories'] = $cats;
		}

		if ( is_cart() ) {
			$event_info['ccEvent'] = $this->getEventType( 'cartViewed' );
			$cart = WC()->cart;
			$event_info['total'] = $cart->total;
			$event_info['currency'] = get_woocommerce_currency();
			$items = $cart->get_cart();
			$cart_items = array();
			foreach ( $items as $item => $values ) {
				$cart_item['name'] = $values['data']->post->post_title;
				$cart_item['quantity'] = $values['quantity'];
				$cart_item['price'] = $values['data']->price;
				if ( $values['data']->post->ID ) {
					$cart_item['url'] = get_permalink( $values['data']->post->ID );
					if ( is_object( $values['data'] ) and $values['data']->image_id ) {
						$thumb_id = $values['data']->image_id;
					} else {
						$thumb_id = get_post_thumbnail_id( $values['data']->post->ID );
					}
					$thumb_url = wp_get_attachment_image_src( $thumb_id );
					$cart_item['image'] = $thumb_url[0];
				}
				$cart_items[] = $cart_item;
			}
			$event_info['items'] = $cart_items;
		}

		if ( is_checkout() and ! is_wc_endpoint_url( 'order-received' ) ) {
			$event_info['ccEvent'] = $this->getEventType( 'checkoutViewed' );
			$cart = WC()->cart;
			$event_info['total'] = $cart->total;
			$event_info['currency'] = get_woocommerce_currency();
			$items = $cart->get_cart();
			$cart_items = array();
			foreach ( $items as $item => $values ) {
				$cart_item['name'] = $values['data']->post->post_title;
				$cart_item['quantity'] = $values['quantity'];
				$cart_item['price'] = $values['data']->price;
				if ( $values['data']->post->ID ) {
					$cart_item['url'] = get_permalink( $values['data']->post->ID );
					if ( is_object( $values['data'] ) and $values['data']->image_id ) {
						$thumb_id = $values['data']->image_id;
					} else {
						$thumb_id = get_post_thumbnail_id( $values['data']->post->ID );
					}
					$thumb_url = wp_get_attachment_image_src( $thumb_id );
					$cart_item['image'] = $thumb_url[0];
				}

				$cart_items[] = $cart_item;
			}
			$event_info['items'] = $cart_items;
		}

		if ( is_single() and ! is_product() ) {
			$event_info['ccEvent'] = $this->getEventType( 'contentPageViewed' );
			$event_info['type'] = 'Post';
			$event_info['title'] = get_the_title();
			$event_info['url'] = get_permalink();
		}

		if ( is_page() ) {
			if ( ( ( ! is_product_category() and ! is_product() ) and ( ! is_checkout() and ! is_cart() ) ) ) {
				$event_info['ccEvent'] = $this->getEventType( 'contentPageViewed' );
				$event_info['type'] = 'Page';
				$event_info['title'] = get_the_title();
				$event_info['url'] = get_permalink();
			}
		}

		if ( isset( $event_info ) ) {
			$script = $this->displayEventScript( $event_info );
		}
	}

	/**
	 * Function To Calculate Final Price Of Product
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
	 * Function To Display CC Tracking Script
	 *
	 * @param type string $event_info .
	 */
	public function displayEventScript( $event_info ) {
		$event_info['metaData'] = $this->getMetaInfo();
		$event_json = wp_json_encode( $event_info );
		if ( isset( $event_json ) and '' !== $event_json ) { ?>
			<!-- ConvertCart -->
			<script type='text/javascript'>
				ccart('send', 'evv1', <?php echo $event_json; ?>)
			</script>
			<!-- ConvertCart -->
		<?php }
	}

	/**
	 * Function To Add Meta Information To CC Tracking Script
	 */
	public function getMetaInfo() {
		global $wp_version;
		global $woocommerce;
		global $current_user;

		if ( is_object( $current_user ) ) {
			if ( isset( $current_user->user_email ) ) {
				$meta_data['customer_status'] = 'logged_in';
				$meta_data['customer_email'] = $current_user->user_email;
			} else {
				$meta_data['customer_status'] = 'guest';
			}
		}

		$meta_data['date'] = gmdate( 'Y-m-d H:i:s' );
		$meta_data['currency'] = get_woocommerce_currency();
		$meta_data['platform'] = 'Wordpress WooCommerce';
		$meta_data['platform_version'] = $woocommerce->version;
		$meta_data['wp_version'] = $wp_version;
		$meta_data['plugin_version'] = CC_PLUGIN_VERSION;
		return $meta_data;
	}

	/**
	 * Function Declaring All CC Event Names
	 *
	 * @param type string $event .
	 */
	public function getEventType( $event ) {
		$event_map = array(
							'homepageViewed'             => 'homepageViewed',
							'contentPageViewed'          => 'contentPageViewed',
							'categoryViewed'             => 'categoryViewed',
							'productViewed'              => 'productViewed',
							'cartViewed'                 => 'cartViewed',
							'checkoutViewed'             => 'checkoutViewed',
							'orderCompleted'             => 'orderCompleted',
						);
		if ( isset( $event_map[ $event ] ) ) {
			return $event_map[ $event ];
		} else {
			return 'default';
		}
	}
}
