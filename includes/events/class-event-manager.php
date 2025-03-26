<?php
/**
 * Event Manager for Convert Cart Analytics.
 *
 * This file contains the Event_Manager class which handles tracking events.
 *
 * @package  ConvertCart\Analytics\Events
 * @category Events
 */

namespace ConvertCart\Analytics\Events;

use ConvertCart\Analytics\Core\Integration;

class Event_Manager {

	/**
	 * Integration instance.
	 *
	 * @var Integration
	 */
	private $integration;

	/**
	 * Constructor.
	 *
	 * @param Integration $integration Integration instance.
	 */
	public function __construct( $integration ) {
		$this->integration = $integration;
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		add_action( 'wp_footer', array( $this, 'add_events' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'ordered' ) );
	}

	/**
	 * Track order completed event.
	 *
	 * @param int $data Order ID.
	 * @return array|void Event info or void.
	 */
	public function ordered( $data ) {
		try {
			if ( is_wc_endpoint_url( 'order-received' ) ) {
				$event_info = array();
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
					$product    = wc_get_product( $item->get_product_id() );
					if ( ! is_object( $product ) ) {
						continue;
					}
					$order_item['name']     = $product->get_title();
					$order_item['price']    = $product->get_price();
					$order_item['currency'] = get_woocommerce_currency();
					$order_item['quantity'] = $item->get_quantity();
					$order_item['url']      = get_permalink( $product->get_id() );

					// Get image ID
					$thumb_id = $product->get_image_id() ? $product->get_image_id() : get_post_thumbnail_id( $product->get_id() );

					// Get product image URL
					$order_item['image'] = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : null;
					$order_items[]       = $order_item;
				}
				$event_info['items'] = $order_items;
				$this->display_event_script( $event_info );
			}
		} catch ( \Exception $e ) {
			// Use WC_Logger instead of error_log for better debugging in production.
			wc_get_logger()->error( 'ConvertCart Error: ' . $e->getMessage(), array( 'source' => 'convertcart-analytics' ) );
		}
	}

	/**
	 * Add events to the page.
	 */
	public function add_events() {
		try {
			global $wp_query;
			$event_info = array();

			if ( is_product_category() ) {
				$event_info = $this->get_category_viewed_props();
			} elseif ( is_product() ) {
				$event_info = $this->get_product_viewed_props();
			} elseif ( is_cart() ) {
				$event_info['ccEvent'] = $this->get_event_type( 'cartViewed' );
				$event_info['items']   = $this->get_cart_items();
			} elseif ( is_checkout() ) {
				$event_info['ccEvent'] = $this->get_event_type( 'checkoutStarted' );
				$event_info['items']   = $this->get_cart_items();
			} elseif ( is_front_page() || is_home() || is_page() ) {
				$event_info = $this->get_content_page_props();
			}

			if ( ! empty( $event_info ) ) {
				$this->display_event_script( $event_info );
			}
		} catch ( \Exception $e ) {
			// Use WC_Logger instead of error_log for better debugging in production.
			wc_get_logger()->error( 'ConvertCart Error: ' . $e->getMessage(), array( 'source' => 'convertcart-analytics' ) );
		}
	}

	/**
	 * Get category viewed properties.
	 *
	 * @return array Category viewed properties.
	 */
	public function get_category_viewed_props() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->get_event_type( 'categoryViewed' );
		$category              = get_queried_object();
		if ( is_object( $category ) ) {
			$event_info['categoryId']   = $category->term_id;
			$event_info['categoryName'] = $category->name;
			$event_info['categoryUrl']  = get_term_link( $category->term_id, 'product_cat' );
		}
		return $event_info;
	}

	/**
	 * Get product viewed properties.
	 *
	 * @return array Product viewed properties.
	 */
	public function get_product_viewed_props() {
		global $product;
		$event_info            = array();
		$event_info['ccEvent'] = $this->get_event_type( 'productViewed' );
		if ( is_object( $product ) ) {
			$event_info['productId']    = $product->get_id();
			$event_info['productName']  = $product->get_title();
			$event_info['productPrice'] = $product->get_price();
			$event_info['productUrl']   = get_permalink( $product->get_id() );
			
			// Get image ID
			$thumb_id = $product->get_image_id() ? $product->get_image_id() : get_post_thumbnail_id( $product->get_id() );
			
			// Get product image URL
			$event_info['productImg'] = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : null;
		}
		return $event_info;
	}

	/**
	 * Get content page properties.
	 *
	 * @return array Content page properties.
	 */
	public function get_content_page_props() {
		$event_info            = array();
		$event_info['ccEvent'] = $this->get_event_type( 'contentViewed' );
		$event_info['title']   = wp_get_document_title();
		$event_info['url']     = home_url( add_query_arg( array() ) );

		return $event_info;
	}

	/**
	 * Get cart items.
	 *
	 * @return array Cart items.
	 */
	public function get_cart_items() {
		$cart_items = array();
		if ( ! WC()->cart ) {
			return $cart_items;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			if ( ! is_object( $product ) ) {
				continue;
			}

			$item = array(
				'name'     => $product->get_title(),
				'price'    => $product->get_price(),
				'currency' => get_woocommerce_currency(),
				'quantity' => $cart_item['quantity'],
				'url'      => get_permalink( $product->get_id() ),
			);

			// Get image ID
			$thumb_id = $product->get_image_id() ? $product->get_image_id() : get_post_thumbnail_id( $product->get_id() );

			// Get product image URL
			$item['image'] = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : null;

			$cart_items[] = $item;
		}

		return $cart_items;
	}

	/**
	 * Display event script.
	 *
	 * @param array $event_info Event information.
	 * @return void
	 */
	public function display_event_script( $event_info ) {
		if ( empty( $event_info ) ) {
			return;
		}

		$event_json = wp_json_encode( $event_info );
		?>
		<!-- ConvertCart -->
		<script>
			window.ccLayer = window.ccLayer || [];
			ccLayer.push(<?php echo wp_kses_post( $event_json ); ?>);
		</script>
		<!-- ConvertCart -->
		<?php
	}

	/**
	 * Get event type.
	 *
	 * @param string $event_name Event name.
	 * @return string Event type.
	 */
	public function get_event_type( $event_name ) {
		return $event_name;
	}
}
