<?php
/**
 * Event Tracker Class
 *
 * @package ConvertCart\Analytics
 */

namespace ConvertCart\Analytics;

class CC_Event_Tracker {
	private $client_id;
	private $debug_mode;

	public function __construct( $client_id, $debug_mode ) {
		$this->client_id  = $client_id;
		$this->debug_mode = $debug_mode;
	}

	public function init_tracking() {
		add_action( 'wp_head', array( $this, 'inject_tracking_script' ) );
		add_action( 'wp_footer', array( $this, 'add_events' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'track_order_completed' ) );
	}

	public function inject_tracking_script() {
		if ( ! $this->client_id ) {
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
			})(window, document, 'script', '//cdn.convertcart.com/" . $this->client_id . "', 'ccart');
		</script>";
	}

	public function track_order_completed( $data ) {
		try {
			if ( is_wc_endpoint_url( 'order-received' ) ) {
				$event_info['ccEvent'] = $this->get_event_type( 'orderCompleted' );
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
				$script              = $this->display_event_script( $event_info );
			}
		} catch ( Exception $e ) {
			error_log( 'ConvertCart Error: ' . $e->getMessage() );
		}
	}

	public function add_events() {
		try {
			if ( is_front_page() && ! is_shop() ) {
				$event_info['ccEvent'] = $this->get_event_type( 'homepageViewed' );
			} elseif ( is_shop() ) {
				$event_info['ccEvent'] = $this->get_event_type( 'shopPageViewed' );
			} elseif ( is_product_category() ) {
				$event_info = $this->get_category_viewed_props();
			} elseif ( is_product() ) {
				$event_info = $this->get_product_viewed_props();
			} elseif ( is_search() ) {
				$event_info['ccEvent'] = $this->get_event_type( 'productsSearched' );
				$event_info['query']   = get_search_query();
			} elseif ( is_cart() || ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) ) {
				if ( is_cart() ) {
					$event_info['ccEvent'] = $this->get_event_type( 'cartViewed' );
				} elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
					$event_info['ccEvent'] = $this->get_event_type( 'checkoutViewed' );
				}
				$cart                   = WC()->cart;
				$event_info['total']    = $cart->total;
				$event_info['currency'] = get_woocommerce_currency();
				$event_info['items']    = $this->get_cart_items( $cart->get_cart() );
			} elseif ( is_single() || is_page() ) {
				$event_info = $this->get_content_page_props();
			}

			if ( isset( $event_info ) ) {
				$script = $this->display_event_script( $event_info );
			}
		} catch ( Exception $e ) {
			error_log( 'ConvertCart Error: ' . $e->getMessage() );
		}
	}

	public function get_category_viewed_props() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->get_event_type( 'categoryViewed' );
		global $wp_query;
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

	public function get_product_viewed_props() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->get_event_type( 'productViewed' );
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

	public function get_content_page_props() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->get_event_type( 'contentPageViewed' );
		$event_info['type']    = 'Page';
		$event_info['title']   = get_the_title();
		$event_info['url']     = get_permalink();
		return $event_info;
	}

	public function get_cart_items( $items ) {
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

	public function calculate_final_price( $regular_price, $sale_price ) {
		if ( $sale_price < $regular_price ) {
			return $sale_price;
		} else {
			return $regular_price;
		}
	}

	public function display_event_script( $event_info ) {
		$event_info['metaData'] = $this->get_meta_info();
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

	public function get_meta_info() {
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

		if ( 'yes' === $this->debug_mode ) {
			$meta_data['pv'] = ( function_exists( 'WC' ) && is_object( WC() ) ) ? WC()->version : null;
			$meta_data['wv'] = $wp_version;
		}
		$meta_data['pgv'] = defined( 'CC_PLUGIN_VERSION' ) ? CC_PLUGIN_VERSION : null;

		return $meta_data;
	}

	public function get_event_type( $event ) {
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
}
